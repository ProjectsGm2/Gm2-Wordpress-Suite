<?php
namespace AE\CSS;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

class AE_CSS_CLI extends \WP_CLI_Command {
    /**
     * Show queue length and pending job types.
     */
    public function status() {
        $queue = \get_option( 'ae_css_queue', [] );
        if ( ! \is_array( $queue ) ) {
            $queue = [];
        }
        $types = [];
        foreach ( $queue as $job ) {
            $type = $job['type'] ?? 'unknown';
            $types[ $type ] = ( $types[ $type ] ?? 0 ) + 1;
        }
        \WP_CLI::line( sprintf( __( 'Queue length: %d', 'gm2-wordpress-suite' ), count( $queue ) ) );
        if ( empty( $types ) ) {
            \WP_CLI::line( __( 'No pending jobs.', 'gm2-wordpress-suite' ) );
        } else {
            \WP_CLI::line( __( 'Pending job types:', 'gm2-wordpress-suite' ) );
            foreach ( $types as $type => $count ) {
                \WP_CLI::line( sprintf( ' - %s: %d', $type, $count ) );
            }
        }

        $status = \get_option( 'ae_css_job_status', [] );
        if ( \is_array( $status ) && ! empty( $status ) ) {
            \WP_CLI::line( __( 'Job status:', 'gm2-wordpress-suite' ) );
            foreach ( $status as $job => $data ) {
                $msg = $data['status'] ?? '';
                if ( ! empty( $data['message'] ) ) {
                    $msg .= ' - ' . $data['message'];
                }
                \WP_CLI::line( sprintf( ' * %s: %s', $job, $msg ) );
            }
        }
    }

    /**
     * Enqueue snapshot & critical jobs for a URL.
     *
     * ## OPTIONS
     *
     * --url=<url>
     */
    public function generate( $args, $assoc_args ) {
        $url = $assoc_args['url'] ?? '';
        $url = \is_string( $url ) ? \esc_url_raw( $url ) : '';
        if ( $url === '' || ! \filter_var( $url, FILTER_VALIDATE_URL ) ) {
            \WP_CLI::error( __( 'Please provide a valid --url.', 'gm2-wordpress-suite' ) );
        }

        if ( ! \class_exists( __NAMESPACE__ . '\\AE_CSS_Queue' ) || ! \method_exists( AE_CSS_Queue::class, 'get_instance' ) ) {
            \WP_CLI::error( __( 'AE_CSS_Queue class is required.', 'gm2-wordpress-suite' ) );
        }
        if ( ! \class_exists( __NAMESPACE__ . '\\AE_CSS_Optimizer' ) || ! \method_exists( AE_CSS_Optimizer::class, 'get_instance' ) ) {
            \WP_CLI::error( __( 'AE_CSS_Optimizer class is required.', 'gm2-wordpress-suite' ) );
        }

        try {
            $queue = AE_CSS_Queue::get_instance();
            $queue->enqueue( 'snapshot', $url );
            AE_CSS_Optimizer::get_instance()->mark_url_for_critical_generation( $url );
        } catch ( \Throwable $e ) {
            \WP_CLI::error( $e->getMessage() );
        }

        \WP_CLI::success( sprintf( __( 'Enqueued snapshot and critical jobs for %s.', 'gm2-wordpress-suite' ), $url ) );
    }

    /**
     * Enqueue a purge job for the current theme.
     *
     * ## OPTIONS
     *
     * --theme  Purge current theme.
     */
    public function purge( $args, $assoc_args ) {
        if ( empty( $assoc_args['theme'] ) ) {
            \WP_CLI::error( __( 'Usage: wp ae-css purge --theme', 'gm2-wordpress-suite' ) );
        }
        if ( ! \function_exists( 'get_stylesheet_directory' ) ) {
            \WP_CLI::error( __( 'Function get_stylesheet_directory() is required.', 'gm2-wordpress-suite' ) );
        }
        $theme_dir = \get_stylesheet_directory();
        if ( ! \is_string( $theme_dir ) || $theme_dir === '' || ! \is_dir( $theme_dir ) ) {
            \WP_CLI::error( __( 'Unable to determine theme directory.', 'gm2-wordpress-suite' ) );
        }
        if ( ! \class_exists( __NAMESPACE__ . '\\AE_CSS_Queue' ) || ! \method_exists( AE_CSS_Queue::class, 'get_instance' ) ) {
            \WP_CLI::error( __( 'AE_CSS_Queue class is required.', 'gm2-wordpress-suite' ) );
        }
        try {
            AE_CSS_Queue::get_instance()->enqueue( 'purge', $theme_dir );
        } catch ( \Throwable $e ) {
            \WP_CLI::error( $e->getMessage() );
        }
        \WP_CLI::success( __( 'Enqueued purge job for current theme.', 'gm2-wordpress-suite' ) );
    }

    /**
     * Regenerate snapshots for queued URLs.
     */
    public function refresh_snapshots() {
        $queue = \get_option( 'ae_css_queue', [] );
        if ( ! \is_array( $queue ) ) {
            $queue = [];
        }
        $urls      = [];
        $remaining = [];
        foreach ( $queue as $job ) {
            if ( ( $job['type'] ?? '' ) === 'snapshot' && \is_string( $job['payload'] ) ) {
                $urls[] = $job['payload'];
            } else {
                $remaining[] = $job;
            }
        }
        \update_option( 'ae_css_queue', $remaining, false );

        if ( empty( $urls ) ) {
            \WP_CLI::success( __( 'No snapshot jobs found.', 'gm2-wordpress-suite' ) );
            return;
        }

        $css_paths = \glob( trailingslashit( \get_stylesheet_directory() ) . 'css/*.css' ) ?: [];
        $queue_obj = AE_CSS_Queue::get_instance();
        foreach ( $urls as $url ) {
            $payload = [
                'css'      => $css_paths,
                'html'     => [ $url ],
                'safelist' => [],
            ];
            $queue_obj->enqueue( 'snapshot', $payload );
        }
        \WP_CLI::success( sprintf( __( 'Refreshed %d snapshot(s).', 'gm2-wordpress-suite' ), count( $urls ) ) );
    }
}

\WP_CLI::add_command( 'ae-css', __NAMESPACE__ . '\\AE_CSS_CLI' );

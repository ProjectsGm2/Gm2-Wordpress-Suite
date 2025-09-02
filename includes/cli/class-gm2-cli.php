<?php
namespace Gm2;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

class Gm2_CLI extends \WP_CLI_Command {
    /**
     * Manage the plugin sitemap.
     *
     * ## SUBCOMMANDS
     *
     * generate  Generate the XML sitemap
     */
    public function sitemap( $args, $assoc_args ) {
        $sub = $args[0] ?? '';
        if ( $sub !== 'generate' ) {
            \WP_CLI::error( __( 'Usage: wp gm2 sitemap generate', 'gm2-wordpress-suite' ) );
        }

        $result = \gm2_generate_sitemap();
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }
        \WP_CLI::success( __( 'Sitemap generated.', 'gm2-wordpress-suite' ) );
    }

    /**
     * Clear stored AI data and logs.
     *
     * ## SUBCOMMANDS
     *
     * clear  Remove cached AI research and logs
     */
    public function ai( $args, $assoc_args ) {
        $sub = $args[0] ?? '';
        if ( $sub !== 'clear' ) {
            \WP_CLI::error( __( 'Usage: wp gm2 ai clear', 'gm2-wordpress-suite' ) );
        }

        if ( ! function_exists( '\gm2_ai_clear' ) ) {
            \WP_CLI::error( __( 'gm2_ai_clear() function not found.', 'gm2-wordpress-suite' ) );
        }
        $result = \gm2_ai_clear();
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }
        \WP_CLI::success( __( 'AI data cleared.', 'gm2-wordpress-suite' ) );
    }

    /**
     * Manage abandoned carts data.
     *
     * ## SUBCOMMANDS
     *
     * migrate  Move recovered carts into wc_ac_recovered table
     * process  Run the abandonment processor immediately
     */
    public function ac( $args, $assoc_args ) {
        $sub = $args[0] ?? '';
        if ( $sub === 'migrate' ) {
            $logger = function_exists('wc_get_logger') ? wc_get_logger() : null;
            $ac    = new Gm2_Abandoned_Carts($logger);
            $count = $ac->migrate_recovered_carts();
            \WP_CLI::success( sprintf( __( '%d carts migrated.', 'gm2-wordpress-suite' ), $count ) );
            return;
        }

        if ( $sub === 'process' ) {
            Gm2_Abandoned_Carts::cron_mark_abandoned();
            \WP_CLI::success( __( 'Abandoned carts processed.', 'gm2-wordpress-suite' ) );
            return;
        }

        if ( $sub === 'retry-failed' ) {
            $messaging = new Gm2_Abandoned_Carts_Messaging();
            $count = $messaging->reprocess_failed_messages();
            \WP_CLI::success( sprintf( __( '%d failed messages reprocessed.', 'gm2-wordpress-suite' ), $count ) );
            return;
        }

        \WP_CLI::error( __( 'Usage: wp gm2 ac <migrate|process|retry-failed>', 'gm2-wordpress-suite' ) );
    }

    /**
     * Scaffold theme assets such as Twig/Blade templates or theme.json.
     *
     * ## SUBCOMMANDS
     *
     * twig <slug>       Create a Twig template under templates/.
     * blade <slug>      Create a Blade template under resources/views/.
     * theme-json        Create a basic theme.json if one does not exist.
     */
    public function scaffold( $args, $assoc_args ) {
        $type = $args[0] ?? '';
        $slug = $args[1] ?? 'example';
        $theme_dir = get_stylesheet_directory();

        switch ( $type ) {
            case 'twig':
                $path = trailingslashit( $theme_dir ) . 'templates/' . $slug . '.twig';
                if ( ! file_exists( dirname( $path ) ) ) {
                    wp_mkdir_p( dirname( $path ) );
                }
                file_put_contents( $path, "{{ gm2_field('example') }}\n" );
                \WP_CLI::success( sprintf( __( 'Twig template created at %s', 'gm2-wordpress-suite' ), $path ) );
                break;
            case 'blade':
                $path = trailingslashit( $theme_dir ) . 'resources/views/' . $slug . '.blade.php';
                if ( ! file_exists( dirname( $path ) ) ) {
                    wp_mkdir_p( dirname( $path ) );
                }
                file_put_contents( $path, "{{ gm2_field('example') }}\n" );
                \WP_CLI::success( sprintf( __( 'Blade template created at %s', 'gm2-wordpress-suite' ), $path ) );
                break;
            case 'theme-json':
            case 'theme':
                $path = trailingslashit( $theme_dir ) . 'theme.json';
                if ( ! file_exists( $path ) ) {
                    $contents = json_encode( [ '$schema' => 'https://schemas.wp.org/wp/6.4/theme.json' ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
                    file_put_contents( $path, $contents );
                    \WP_CLI::success( sprintf( __( 'theme.json created at %s', 'gm2-wordpress-suite' ), $path ) );
                } else {
                    \WP_CLI::success( sprintf( __( 'theme.json already exists at %s', 'gm2-wordpress-suite' ), $path ) );
                }
                $label = ucwords( str_replace( '-', ' ', $slug ) );
                $sample = [
                    'customTemplates' => [
                        [ 'name' => 'single-' . $slug, 'title' => sprintf( __( 'Single %s', 'gm2-wordpress-suite' ), $label ) ],
                        [ 'name' => 'archive-' . $slug, 'title' => sprintf( __( 'Archive %s', 'gm2-wordpress-suite' ), $label ) ],
                    ],
                    'patterns' => [ 'gm2/' . $slug ],
                ];
                \WP_CLI::line( __( 'Hint: add the following to theme.json to reference custom templates and patterns:', 'gm2-wordpress-suite' ) );
                \WP_CLI::line( json_encode( $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
                break;
            default:
                \WP_CLI::error( __( 'Usage: wp gm2 scaffold <twig|blade|theme-json> <slug>', 'gm2-wordpress-suite' ) );
        }
    }
}

\WP_CLI::add_command( 'gm2', __NAMESPACE__ . '\\Gm2_CLI' );

<?php
namespace AE_SEO;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

class AE_SEO_Critical_CLI extends \WP_CLI_Command {
    /**
     * Build Critical CSS for important pages.
     */
    public function build() {
        $urls = [];
        $urls[] = [
            'context' => 'home',
            'url'     => home_url( '/' ),
        ];

        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        foreach ( $post_types as $type ) {
            if ( ! empty( $type->has_archive ) && ( $link = get_post_type_archive_link( $type->name ) ) ) {
                $urls[] = [
                    'context' => 'archive-' . $type->name,
                    'url'     => $link,
                ];
            }
        }

        $query = new \WP_Query( [
            'post_type'           => array_keys( get_post_types( [ 'public' => true ] ) ),
            'posts_per_page'      => 20,
            'post_status'         => 'publish',
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ] );
        foreach ( $query->posts as $post ) {
            $urls[] = [
                'context' => 'single-' . $post->post_type,
                'url'     => get_permalink( $post ),
            ];
        }
        wp_reset_postdata();

        set_transient( 'ae_seo_ro_critical_job', $urls, 12 * HOUR_IN_SECONDS );

        if ( ! function_exists( 'shell_exec' ) ) {
            \WP_CLI::error( __( 'shell_exec is not available.', 'gm2-wordpress-suite' ) );
        }
        $critical = shell_exec( 'which critical' );
        if ( empty( trim( $critical ) ) ) {
            \WP_CLI::error( __( 'critical CLI not found. Install with `npm install -g critical`.', 'gm2-wordpress-suite' ) );
        }

        $css_map = get_option( 'ae_seo_ro_critical_css_map', [] );
        foreach ( $urls as $data ) {
            $url     = $data['url'];
            $context = $data['context'];
            \WP_CLI::log( sprintf( __( 'Processing %s', 'gm2-wordpress-suite' ), $url ) );
            $cmd = 'critical ' . escapeshellarg( $url ) . ' --inline=false --minify';
            $css = shell_exec( $cmd );
            if ( ! isset( $css_map[ $context ] ) ) {
                $css_map[ $context ] = [];
            }
            $css_map[ $context ][ md5( $url ) ] = $css;
        }
        update_option( 'ae_seo_ro_critical_css_map', $css_map );
        \WP_CLI::success( __( 'Critical CSS build complete.', 'gm2-wordpress-suite' ) );
    }
}

\WP_CLI::add_command( 'ae-seo critical', 'AE_SEO\\AE_SEO_Critical_CLI' );

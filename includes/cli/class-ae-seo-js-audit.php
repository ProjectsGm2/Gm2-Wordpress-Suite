<?php
namespace AE_SEO;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

/**
 * Audit JavaScript usage across recent content.
 */
class AE_SEO_JS_Audit extends \WP_CLI_Command {
    /**
     * Audit scripts on recent posts, pages and products.
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : Number of posts to inspect. Default 50.
     *
     * ## EXAMPLES
     *
     *     wp ae-seo js:audit --limit=10
     *
     * @when after_wp_load
     */
    public function __invoke( $args, $assoc_args ) {
        $limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 50;
        if ( $limit <= 0 ) {
            $limit = 50;
        }

        $query = new \WP_Query([
            'post_type'           => [ 'post', 'page', 'product' ],
            'post_status'         => 'publish',
            'posts_per_page'      => $limit,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ]);

        $items = [];

        foreach ( $query->posts as $post ) {
            $url  = get_permalink( $post );
            $resp = wp_remote_get( $url );
            if ( is_wp_error( $resp ) ) {
                \WP_CLI::warning( sprintf( 'Request failed for %s: %s', $url, $resp->get_error_message() ) );
                continue;
            }
            $html = wp_remote_retrieve_body( $resp );

            $total    = 0;
            $dequeued = [];
            $jquery   = 'N';
            $esm      = 'N';

            if ( is_string( $html ) && $html !== '' ) {
                $dom = new \DOMDocument();
                libxml_use_internal_errors( true );
                $dom->loadHTML( $html );
                libxml_clear_errors();
                $scripts = $dom->getElementsByTagName( 'script' );
                $total   = $scripts->length;
                foreach ( $scripts as $script ) {
                    $src = $script->getAttribute( 'src' );
                    if ( $src && stripos( $src, 'jquery' ) !== false ) {
                        $jquery = 'Y';
                    }
                    if ( strtolower( $script->getAttribute( 'type' ) ) === 'module' ) {
                        $esm = 'Y';
                    }
                    $content = $script->textContent;
                    if ( preg_match_all( '/dequeue\s+([\w-]+)/i', $content, $m ) ) {
                        $dequeued = array_merge( $dequeued, $m[1] );
                    }
                }

                if ( preg_match_all( '/aejs[^<]*dequeue\s+([\w-]+)/i', $html, $m2 ) ) {
                    $dequeued = array_merge( $dequeued, $m2[1] );
                }

                if ( $jquery === 'N' && preg_match( '/jquery/i', $html ) ) {
                    $jquery = 'Y';
                }
                if ( $esm === 'N' && preg_match( '/<script[^>]*type\s*=\s*"module"/i', $html ) ) {
                    $esm = 'Y';
                }
            }

            $items[] = [
                'url'      => $url,
                'total'    => $total,
                'dequeued' => implode( ',', array_unique( $dequeued ) ),
                'jquery'   => $jquery,
                'esm'      => $esm,
            ];
        }
        wp_reset_postdata();

        \WP_CLI\Utils\format_items( 'table', $items, [ 'url', 'total', 'dequeued', 'jquery', 'esm' ] );
    }
}

\WP_CLI::add_command( 'ae-seo js:audit', __NAMESPACE__ . '\\AE_SEO_JS_Audit' );

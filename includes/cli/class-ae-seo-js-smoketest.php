<?php
namespace AE_SEO;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

/**
 * Smoke test the JS optimizer on key pages.
 */
class AE_SEO_JS_Smoketest extends \WP_CLI_Command {
    /**
     * Run smoke test requests and log metrics.
     *
     * ## EXAMPLES
     *
     *     wp ae-seo js:smoketest
     *
     * @when after_wp_load
     */
    public function __invoke( $args, $assoc_args ) {
        $urls = [];
        $urls['home'] = home_url( '/' );

        $post = get_posts( [ 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1 ] );
        if ( ! empty( $post ) ) {
            $urls['post'] = get_permalink( $post[0] );
        }

        if ( post_type_exists( 'product' ) ) {
            $product = get_posts( [ 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 1 ] );
            if ( ! empty( $product ) ) {
                $urls['product'] = get_permalink( $product[0] );
            }
        }

        $contact = get_page_by_path( 'contact' );
        if ( ! $contact ) {
            $contact = get_page_by_title( 'Contact' );
        }
        if ( $contact ) {
            $urls['contact'] = get_permalink( $contact );
        }

        foreach ( $urls as $label => $url ) {
            $resp = wp_remote_get( $url );
            if ( is_wp_error( $resp ) ) {
                \WP_CLI::warning( sprintf( 'Request failed for %s: %s', $url, $resp->get_error_message() ) );
                continue;
            }

            $body    = wp_remote_retrieve_body( $resp );
            $headers = wp_remote_retrieve_headers( $resp );
            $server  = $headers['server-timing'] ?? '';

            $metrics = [ 'dequeued' => 0, 'lazy' => 0, 'polyfills' => 0, 'jquery' => 0 ];
            if ( is_string( $server ) ) {
                foreach ( explode( ',', $server ) as $part ) {
                    if ( preg_match( '/ae-dequeued=(\d+)/', $part, $m ) ) {
                        $metrics['dequeued'] = (int) $m[1];
                    }
                    if ( preg_match( '/ae-lazy=(\d+)/', $part, $m ) ) {
                        $metrics['lazy'] = (int) $m[1];
                    }
                    if ( preg_match( '/ae-polyfills=(\d+)/', $part, $m ) ) {
                        $metrics['polyfills'] = (int) $m[1];
                    }
                    if ( preg_match( '/ae-jquery=(\d+)/', $part, $m ) ) {
                        $metrics['jquery'] = (int) $m[1];
                    }
                }
            }

            $enqueued = 0;
            $jquery   = $metrics['jquery'] > 0 ? 'Y' : 'N';
            if ( is_string( $body ) && $body !== '' ) {
                $dom = new \DOMDocument();
                libxml_use_internal_errors( true );
                $dom->loadHTML( $body );
                libxml_clear_errors();
                $scripts  = $dom->getElementsByTagName( 'script' );
                $enqueued = $scripts->length;
                if ( $jquery === 'N' && stripos( $body, 'jquery' ) !== false ) {
                    $jquery = 'Y';
                }
            }

            $total = $enqueued + $metrics['dequeued'];
            $line  = sprintf(
                '%s registered=%d enqueued=%d dequeued=%d lazy=%d jquery=%s polyfills=%d',
                $url,
                $total,
                $enqueued,
                $metrics['dequeued'],
                $metrics['lazy'],
                $jquery,
                $metrics['polyfills']
            );

            self::log( $line );
            \WP_CLI::log( $line );
        }
    }

    private static function log( string $message ): void {
        $dir = WP_CONTENT_DIR . '/ae-seo/logs';
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        $file = $dir . '/js-optimizer.log';
        $time = gmdate( 'Y-m-d H:i:s' );
        file_put_contents( $file, '[' . $time . '] ' . $message . PHP_EOL, FILE_APPEND );
    }
}

\WP_CLI::add_command( 'ae-seo js:smoketest', __NAMESPACE__ . '\\AE_SEO_JS_Smoketest' );

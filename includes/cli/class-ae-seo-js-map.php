<?php
namespace AE_SEO;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

/**
 * Rebuild the cached JavaScript map.
 */
class AE_SEO_JS_Map extends \WP_CLI_Command {
    /**
     * Rebuild the JavaScript dependency map.
     *
     * ## EXAMPLES
     *
     *     wp ae-seo js:map
     *
     * @when after_wp_load
     */
    public function __invoke( $args, $assoc_args ) {
        delete_transient( 'aejs_map' );
        \Gm2\AE_SEO_JS_Detector::build_map();
        \WP_CLI::success( 'JavaScript map rebuilt.' );
    }
}

\WP_CLI::add_command( 'ae-seo js:map', __NAMESPACE__ . '\\AE_SEO_JS_Map' );

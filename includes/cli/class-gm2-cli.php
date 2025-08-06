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
            \WP_CLI::error( 'Usage: wp gm2 sitemap generate' );
        }

        $result = \gm2_generate_sitemap();
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }
        \WP_CLI::success( 'Sitemap generated.' );
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
            \WP_CLI::error( 'Usage: wp gm2 ai clear' );
        }

        if ( ! function_exists( '\gm2_ai_clear' ) ) {
            \WP_CLI::error( 'gm2_ai_clear() function not found.' );
        }
        $result = \gm2_ai_clear();
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }
        \WP_CLI::success( 'AI data cleared.' );
    }

    /**
     * Manage abandoned carts data.
     *
     * ## SUBCOMMANDS
     *
     * migrate  Move recovered carts into wc_ac_recovered table
     */
    public function ac( $args, $assoc_args ) {
        $sub = $args[0] ?? '';
        if ( $sub !== 'migrate' ) {
            \WP_CLI::error( 'Usage: wp gm2 ac migrate' );
        }

        $ac = new Gm2_Abandoned_Carts();
        $count = $ac->migrate_recovered_carts();
        \WP_CLI::success( sprintf( '%d carts migrated.', $count ) );
    }
}

\WP_CLI::add_command( 'gm2', __NAMESPACE__ . '\\Gm2_CLI' );

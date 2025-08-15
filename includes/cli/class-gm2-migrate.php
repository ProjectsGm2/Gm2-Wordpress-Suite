<?php
namespace Gm2;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

/**
 * WP-CLI commands for running database migrations.
 */
class Gm2_Migrate_CLI extends \WP_CLI_Command {
    /**
     * Install or upgrade custom tables.
     */
    public function install( $args, $assoc_args ) {
        \gm2_custom_tables_maybe_install();
        \WP_CLI::success( 'Custom tables are up to date.' );
    }

    /**
     * Backfill custom tables with existing core data.
     */
    public function backfill( $args, $assoc_args ) {
        $count = \gm2_custom_tables_backfill();
        \WP_CLI::success( sprintf( '%d rows backfilled.', $count ) );
    }
}

\WP_CLI::add_command( 'gm2 migrate', __NAMESPACE__ . '\\Gm2_Migrate_CLI' );

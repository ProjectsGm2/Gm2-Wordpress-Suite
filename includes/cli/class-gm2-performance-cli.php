<?php
namespace Gm2;

use Gm2\Performance\MetaIndexManager;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

class Gm2_Performance_CLI extends \WP_CLI_Command {
    /**
     * List registered composite meta indexes.
     *
     * ## OPTIONS
     *
     * [--key=<meta_key>]
     * : Limit output to a single meta key.
     */
    public function list_( $args, $assoc_args ) {
        $manager = $this->get_manager();
        $key     = $assoc_args['key'] ?? null;
        $rows    = $manager->describe();

        if ( $key !== null ) {
            if ( ! isset( $rows[ $key ] ) ) {
                \WP_CLI::error( sprintf( __( 'Meta key "%s" is not registered for indexing.', 'gm2-wordpress-suite' ), $key ) );
            }
            $rows = [ $rows[ $key ] ];
        }

        foreach ( $rows as $row ) {
            $status = $row['exists'] ? __( 'exists', 'gm2-wordpress-suite' ) : __( 'missing', 'gm2-wordpress-suite' );
            \WP_CLI::line( sprintf( '%s: %s (%s %s)', $row['meta_key'], $status, __( 'index', 'gm2-wordpress-suite' ), $row['index_name'] ) );
            \WP_CLI::line( sprintf( '  %s', $row['expression'] ) );
        }

        \WP_CLI::line( sprintf(
            __( 'Filters: %1$s (meta keys), %2$s (index definitions).', 'gm2-wordpress-suite' ),
            MetaIndexManager::FILTER_META_KEYS,
            MetaIndexManager::FILTER_DEFINITIONS
        ) );
    }

    /**
     * Create a composite meta index.
     *
     * ## OPTIONS
     *
     * --key=<meta_key>
     * : Meta key to index.
     *
     * [--yes]
     * : Answer yes to the confirmation message.
     */
    public function create( $args, $assoc_args ) {
        $manager = $this->get_manager();
        $key     = $assoc_args['key'] ?? '';

        if ( $key === '' ) {
            \WP_CLI::error( __( 'Use --key=<meta_key> to select an index.', 'gm2-wordpress-suite' ) );
        }

        $definition = $manager->getDefinition( $key );
        if ( $definition === null ) {
            \WP_CLI::error( sprintf( __( 'Meta key "%s" is not registered for indexing.', 'gm2-wordpress-suite' ), $key ) );
        }

        \WP_CLI::confirm( sprintf( __( 'Create index %1$s on %2$s?', 'gm2-wordpress-suite' ), $definition['name'], $definition['table'] ), $assoc_args );

        $result = $manager->create( $key );
        switch ( $result['status'] ?? '' ) {
            case 'created':
                \WP_CLI::success( sprintf( __( 'Created index %1$s for meta key %2$s.', 'gm2-wordpress-suite' ), $definition['name'], $key ) );
                return;
            case 'exists':
                \WP_CLI::warning( sprintf( __( 'Index %1$s already exists for meta key %2$s.', 'gm2-wordpress-suite' ), $definition['name'], $key ) );
                return;
            case 'error':
                \WP_CLI::error( sprintf( __( 'Failed to create index: %s', 'gm2-wordpress-suite' ), $result['message'] ?? __( 'Unknown error.', 'gm2-wordpress-suite' ) ) );
                return;
        }

        \WP_CLI::error( __( 'Meta index operation failed.', 'gm2-wordpress-suite' ) );
    }

    /**
     * Drop a composite meta index.
     *
     * ## OPTIONS
     *
     * --key=<meta_key>
     * : Meta key whose index should be dropped.
     *
     * [--yes]
     * : Answer yes to the confirmation message.
     */
    public function drop( $args, $assoc_args ) {
        $manager = $this->get_manager();
        $key     = $assoc_args['key'] ?? '';

        if ( $key === '' ) {
            \WP_CLI::error( __( 'Use --key=<meta_key> to select an index.', 'gm2-wordpress-suite' ) );
        }

        $definition = $manager->getDefinition( $key );
        if ( $definition === null ) {
            \WP_CLI::error( sprintf( __( 'Meta key "%s" is not registered for indexing.', 'gm2-wordpress-suite' ), $key ) );
        }

        \WP_CLI::confirm( sprintf( __( 'Drop index %1$s on %2$s?', 'gm2-wordpress-suite' ), $definition['name'], $definition['table'] ), $assoc_args );

        $result = $manager->drop( $key );
        switch ( $result['status'] ?? '' ) {
            case 'dropped':
                \WP_CLI::success( sprintf( __( 'Dropped index %1$s for meta key %2$s.', 'gm2-wordpress-suite' ), $definition['name'], $key ) );
                return;
            case 'missing':
                \WP_CLI::warning( sprintf( __( 'Index %1$s was not present on %2$s.', 'gm2-wordpress-suite' ), $definition['name'], $definition['table'] ) );
                return;
            case 'error':
                \WP_CLI::error( sprintf( __( 'Failed to drop index: %s', 'gm2-wordpress-suite' ), $result['message'] ?? __( 'Unknown error.', 'gm2-wordpress-suite' ) ) );
                return;
        }

        \WP_CLI::error( __( 'Meta index operation failed.', 'gm2-wordpress-suite' ) );
    }

    private function get_manager(): MetaIndexManager {
        return new MetaIndexManager();
    }
}

\WP_CLI::add_command( 'gm2 perf indexes', __NAMESPACE__ . '\\Gm2_Performance_CLI' );

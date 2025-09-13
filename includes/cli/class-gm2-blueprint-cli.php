<?php
namespace Gm2;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

/**
 * Export or import model blueprints.
 */
class Gm2_Blueprint_CLI extends \WP_CLI_Command {
    /**
     * Export the current model configuration to a file.
     *
     * ## OPTIONS
     *
     * <file>
     * : Destination file path.
     */
    public function export( $args ) {
        $file = $args[0] ?? '';
        if ( ! $file ) {
            \WP_CLI::error( __( 'Missing file argument.', 'gm2-wordpress-suite' ) );
        }
        $data = \gm2_model_export( 'json' );
        if ( is_wp_error( $data ) ) {
            \WP_CLI::error( $data->get_error_message() );
        }
        file_put_contents( $file, $data );
        \WP_CLI::success( sprintf( __( 'Blueprint exported to %s', 'gm2-wordpress-suite' ), $file ) );
    }

    /**
     * Import one or more blueprint files.
     *
     * ## OPTIONS
     *
     * <files>...
     * : One or more blueprint file paths.
     */
    public function import( $args ) {
        if ( empty( $args ) ) {
            \WP_CLI::error( __( 'Missing file argument.', 'gm2-wordpress-suite' ) );
        }
        $merged = [
            'post_types'   => [],
            'taxonomies'   => [],
            'field_groups' => [],
        ];
        foreach ( $args as $file ) {
            if ( ! file_exists( $file ) ) {
                \WP_CLI::error( sprintf( __( 'File not found: %s', 'gm2-wordpress-suite' ), $file ) );
            }
            $contents = file_get_contents( $file );
            $data     = json_decode( $contents, true );
            if ( ! is_array( $data ) ) {
                \WP_CLI::error( sprintf( __( 'Invalid JSON in %s', 'gm2-wordpress-suite' ), $file ) );
            }
            $merged['post_types']   = array_merge( $merged['post_types'], $data['post_types'] ?? [] );
            $merged['taxonomies']   = array_merge( $merged['taxonomies'], $data['taxonomies'] ?? [] );
            $merged['field_groups'] = array_merge( $merged['field_groups'], $data['field_groups'] ?? [] );
        }
        $result = \gm2_model_import( $merged, 'array' );
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }
        \WP_CLI::success( __( 'Blueprint(s) imported.', 'gm2-wordpress-suite' ) );
    }
}

\WP_CLI::add_command( 'gm2 blueprint', __NAMESPACE__ . '\\Gm2_Blueprint_CLI' );

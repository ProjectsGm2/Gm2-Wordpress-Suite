<?php
namespace Gm2;

use Gm2\Presets\BlueprintIO;

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
     *
     * [--format=<format>]
     * : Output format: json or yaml. Defaults to json.
     *
     * [--field-groups]
     * : Export only field group definitions.
     */
    public function export( $args, $assoc_args ) {
        $file = $args[0] ?? '';
        if ( ! $file ) {
            \WP_CLI::error( __( 'Missing file argument.', 'gm2-wordpress-suite' ) );
        }
        $format = strtolower( $assoc_args['format'] ?? 'json' );
        $fields_only = ! empty( $assoc_args['field-groups'] );

        if ( $fields_only ) {
            $data = \gm2_field_groups_export( $format );
        } else {
            $data = \gm2_model_export( $format );
        }

        if ( is_wp_error( $data ) ) {
            \WP_CLI::error( $data->get_error_message() );
        }

        file_put_contents( $file, $data );
        $message = $fields_only
            ? sprintf( __( 'Field groups exported to %s', 'gm2-wordpress-suite' ), $file )
            : sprintf( __( 'Blueprint exported to %s', 'gm2-wordpress-suite' ), $file );
        \WP_CLI::success( $message );
    }

    /**
     * Import one or more blueprint files.
     *
     * ## OPTIONS
     *
     * <files>...
     * : One or more blueprint file paths.
     *
     * [--format=<format>]
     * : File format: json or yaml. Defaults to json.
     *
     * [--field-groups]
     * : Import field group definitions instead of full blueprints.
     *
     * [--replace]
     * : Replace existing field groups instead of merging (only used with --field-groups).
     */
    public function import( $args, $assoc_args ) {
        if ( empty( $args ) ) {
            \WP_CLI::error( __( 'Missing file argument.', 'gm2-wordpress-suite' ) );
        }
        $format = strtolower( $assoc_args['format'] ?? 'json' );
        $fields_only = ! empty( $assoc_args['field-groups'] );
        $replace = ! empty( $assoc_args['replace'] );

        if ( $fields_only ) {
            $first = true;
            foreach ( $args as $file ) {
                if ( ! file_exists( $file ) ) {
                    \WP_CLI::error( sprintf( __( 'File not found: %s', 'gm2-wordpress-suite' ), $file ) );
                }
                $contents = file_get_contents( $file );
                $merge    = $replace ? ! $first : true;
                $result   = \gm2_field_groups_import( $contents, $format, $merge );
                if ( is_wp_error( $result ) ) {
                    \WP_CLI::error( $result->get_error_message() );
                }
                $first = false;
            }
            \WP_CLI::success( __( 'Field groups imported.', 'gm2-wordpress-suite' ) );
            return;
        }

        $merged = [
            'post_types'      => [],
            'taxonomies'      => [],
            'field_groups'    => [],
            'schema_mappings' => [],
        ];

        foreach ( $args as $file ) {
            if ( ! file_exists( $file ) ) {
                \WP_CLI::error( sprintf( __( 'File not found: %s', 'gm2-wordpress-suite' ), $file ) );
            }
            $contents = file_get_contents( $file );
            $data     = BlueprintIO::decode( $contents, $format );
            if ( is_wp_error( $data ) ) {
                \WP_CLI::error( $data->get_error_message() );
            }

            $merged['post_types']   = array_merge( $merged['post_types'], $data['post_types'] ?? [] );
            $merged['taxonomies']   = array_merge( $merged['taxonomies'], $data['taxonomies'] ?? [] );
            $groups                 = $data['field_groups'] ?? ( $data['fields']['groups'] ?? [] );
            if ( is_array( $groups ) ) {
                $merged['field_groups'] = array_merge( $merged['field_groups'], $groups );
            }
            $maps = $data['schema_mappings'] ?? ( $data['seo']['mappings'] ?? [] );
            if ( is_array( $maps ) ) {
                $merged['schema_mappings'] = array_merge( $merged['schema_mappings'], $maps );
            }
        }

        $result = \gm2_model_import( $merged, 'array' );
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }
        \WP_CLI::success( __( 'Blueprint(s) imported.', 'gm2-wordpress-suite' ) );
    }
}

\WP_CLI::add_command( 'gm2 blueprint', __NAMESPACE__ . '\\Gm2_Blueprint_CLI' );

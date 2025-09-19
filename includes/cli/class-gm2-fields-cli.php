<?php
namespace Gm2;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

require_once GM2_PLUGIN_DIR . 'includes/gm2-model-export.php';

/**
 * Manage field groups via WP-CLI.
 */
class Gm2_Fields_CLI extends \WP_CLI_Command {
    /**
     * Export field group definitions to a file.
     *
     * ## OPTIONS
     *
     * <file>
     * : Destination file path.
     *
     * [--format=<format>]
     * : Output format: json or yaml. Defaults to json.
     *
     * [--slug=<slug>]
     * : Limit the export to specific group slugs. Repeat for multiple slugs.
     *
     * [--slugs=<list>]
     * : Comma separated list of slugs to export.
     */
    public function export( $args, $assoc_args ) {
        $file = $args[0] ?? '';
        if ( '' === $file ) {
            \WP_CLI::error( __( 'Missing file argument.', 'gm2-wordpress-suite' ) );
        }

        $format = $assoc_args['format'] ?? 'json';

        $slugs = [];
        if ( isset( $assoc_args['slug'] ) ) {
            $slugs = array_merge( $slugs, (array) $assoc_args['slug'] );
        }
        if ( isset( $assoc_args['slugs'] ) ) {
            if ( is_array( $assoc_args['slugs'] ) ) {
                $slugs = array_merge( $slugs, $assoc_args['slugs'] );
            } else {
                $slugs = array_merge( $slugs, explode( ',', (string) $assoc_args['slugs'] ) );
            }
        }

        $slugs = array_values( array_unique( array_filter( array_map( static function ( $slug ) {
            $slug = is_string( $slug ) ? trim( $slug ) : '';
            return $slug !== '' ? $slug : null;
        }, $slugs ) ) ) );

        $data = \gm2_field_groups_export( $format, $slugs ? $slugs : null );
        if ( is_wp_error( $data ) ) {
            \WP_CLI::error( $data->get_error_message() );
        }

        file_put_contents( $file, $data );

        if ( $slugs ) {
            \WP_CLI::success( sprintf( __( 'Field groups %s exported to %s', 'gm2-wordpress-suite' ), implode( ', ', $slugs ), $file ) );
        } else {
            \WP_CLI::success( sprintf( __( 'Field groups exported to %s', 'gm2-wordpress-suite' ), $file ) );
        }
    }

    /**
     * Import field group definitions from a file.
     *
     * ## OPTIONS
     *
     * <file>
     * : Source file path.
     *
     * [--format=<format>]
     * : Input format: json or yaml. Defaults to json or guessed from the extension.
     *
     * [--replace]
     * : Replace existing field groups instead of merging.
     */
    public function import( $args, $assoc_args ) {
        $file = $args[0] ?? '';
        if ( '' === $file || ! file_exists( $file ) ) {
            \WP_CLI::error( __( 'File not found.', 'gm2-wordpress-suite' ) );
        }

        $format = $assoc_args['format'] ?? '';
        if ( ! $format ) {
            $format = preg_match( '/\.ya?ml$/i', $file ) ? 'yaml' : 'json';
        }

        $replace  = ! empty( $assoc_args['replace'] );
        $contents = file_get_contents( $file );
        $result   = \gm2_field_groups_import( $contents, $format, ! $replace );

        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }

        \WP_CLI::success( sprintf( __( 'Field groups imported from %s', 'gm2-wordpress-suite' ), $file ) );
    }
}

\WP_CLI::add_command( 'gm2 fields', __NAMESPACE__ . '\\Gm2_Fields_CLI' );

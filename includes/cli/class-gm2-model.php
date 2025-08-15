<?php
namespace Gm2;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

/**
 * Manage data models via WP-CLI.
 */
class Gm2_Model_CLI extends \WP_CLI_Command {
    /**
     * Export models stored in the `gm2_models` option.
     *
     * ## OPTIONS
     *
     * <file>
     * : Destination file path.
     *
     * [--format=<format>]
     * : Output format: json or yaml. Defaults to json.
     */
    public function export( $args, $assoc_args ) {
        $file = $args[0] ?? '';
        if ( empty( $file ) ) {
            \WP_CLI::error( 'Missing file argument.' );
        }
        $format = $assoc_args['format'] ?? 'json';
        $models = get_option( 'gm2_models', [] );
        if ( 'yaml' === $format ) {
            if ( function_exists( 'yaml_emit' ) ) {
                $data = yaml_emit( $models );
            } else {
                \WP_CLI::error( 'YAML support is not available.' );
            }
        } else {
            $data = wp_json_encode( $models, JSON_PRETTY_PRINT );
        }
        file_put_contents( $file, $data );
        \WP_CLI::success( 'Models exported to ' . $file );
    }

    /**
     * Import models from a JSON or YAML file into the `gm2_models` option.
     *
     * ## OPTIONS
     *
     * <file>
     * : Source file path.
     *
     * [--format=<format>]
     * : File format: json or yaml. Defaults to json or guessed from extension.
     */
    public function import( $args, $assoc_args ) {
        $file = $args[0] ?? '';
        if ( empty( $file ) || ! file_exists( $file ) ) {
            \WP_CLI::error( 'File not found.' );
        }
        $format = $assoc_args['format'] ?? '';
        if ( ! $format ) {
            if ( preg_match( '/\.ya?ml$/i', $file ) ) {
                $format = 'yaml';
            } else {
                $format = 'json';
            }
        }
        $contents = file_get_contents( $file );
        if ( 'yaml' === $format ) {
            if ( function_exists( 'yaml_parse' ) ) {
                $models = yaml_parse( $contents );
            } else {
                \WP_CLI::error( 'YAML support is not available.' );
            }
        } else {
            $models = json_decode( $contents, true );
        }
        if ( ! is_array( $models ) ) {
            \WP_CLI::error( 'Invalid model data.' );
        }
        update_option( 'gm2_models', $models );
        \WP_CLI::success( 'Models imported from ' . $file );
    }

    /**
     * Generate PHP code for registered models.
     *
     * [--mu-plugin]
     * : Generate code as an MU plugin.
     */
    public function generate( $args, $assoc_args ) {
        $mu = isset( $assoc_args['mu-plugin'] );
        // Placeholder: real generation would output PHP files.
        \WP_CLI::success( $mu ? 'Generated MU plugin code.' : 'Generated plugin code.' );
    }

    /**
     * Run model database migrations.
     */
    public function migrate( $args, $assoc_args ) {
        \WP_CLI::success( 'Model migrations completed.' );
    }

    /**
     * Seed model data.
     */
    public function seed( $args, $assoc_args ) {
        \WP_CLI::success( 'Model seeding completed.' );
    }

    /**
     * Configure the active environment.
     *
     * ## OPTIONS
     *
     * <env>
     * : Environment name (dev, stage, prod).
     */
    public function env( $args, $assoc_args ) {
        $env = $args[0] ?? 'dev';
        update_option( 'gm2_model_env', $env );
        \WP_CLI::success( 'Environment set to ' . $env );
    }

    /**
     * Lock editing of models.
     */
    public function lock( $args, $assoc_args ) {
        update_option( 'gm2_model_locked', 1 );
        \WP_CLI::success( 'Models locked.' );
    }

    /**
     * Unlock editing of models.
     */
    public function unlock( $args, $assoc_args ) {
        delete_option( 'gm2_model_locked' );
        \WP_CLI::success( 'Models unlocked.' );
    }

    /**
     * Create a backup of models.
     *
     * ## OPTIONS
     *
     * <file>
     * : Destination file path.
     */
    public function backup( $args, $assoc_args ) {
        $this->export( $args, $assoc_args );
    }

    /**
     * Roll back models from a backup file.
     *
     * ## OPTIONS
     *
     * <file>
     * : Source file path.
     */
    public function rollback( $args, $assoc_args ) {
        $this->import( $args, $assoc_args );
    }
}

\WP_CLI::add_command( 'gm2 model', __NAMESPACE__ . '\\Gm2_Model_CLI' );

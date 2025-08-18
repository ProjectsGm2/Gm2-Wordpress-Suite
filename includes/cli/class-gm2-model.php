<?php
namespace Gm2;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

require_once GM2_PLUGIN_DIR . 'includes/class-gm2-model-migrator.php';

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
     * Create a custom post type.
     *
     * ## OPTIONS
     *
     * <slug>
     * : CPT slug.
     *
     * [--args=<json>]
     * : JSON encoded arguments for register_post_type().
     */
    public function cpt_create( $args, $assoc_args ) {
        $slug = $args[0] ?? '';
        if ( ! $slug ) {
            \WP_CLI::error( 'Missing CPT slug.' );
        }

        $models = get_option( 'gm2_models', [] );
        foreach ( $models as $model ) {
            $pt = $model['slug'] ?? ( $model['post_type'] ?? '' );
            if ( $pt === $slug ) {
                \WP_CLI::error( 'CPT already exists.' );
            }
        }

        $args_json = $assoc_args['args'] ?? '{}';
        $pt_args   = json_decode( $args_json, true );
        if ( ! is_array( $pt_args ) ) {
            \WP_CLI::error( 'Invalid args JSON.' );
        }

        $model = [
            'slug'       => $slug,
            'post_type'  => $slug,
            'args'       => $pt_args,
            'taxonomies' => [],
            'fields'     => [],
            'version'    => (int) ( $assoc_args['version'] ?? 1 ),
        ];

        $models[] = $model;
        update_option( 'gm2_models', $models );
        $this->run_migrations_for_model( $slug, $model );
        \WP_CLI::success( 'CPT created.' );
    }

    /**
     * Update a custom post type.
     *
     * ## OPTIONS
     *
     * <slug>
     * : CPT slug.
     *
     * [--args=<json>]
     * : JSON encoded arguments to merge.
     *
     * [--version=<version>]
     * : Target schema version.
     */
    public function cpt_update( $args, $assoc_args ) {
        $slug = $args[0] ?? '';
        if ( ! $slug ) {
            \WP_CLI::error( 'Missing CPT slug.' );
        }

        $models = get_option( 'gm2_models', [] );
        foreach ( $models as &$model ) {
            $pt = $model['slug'] ?? ( $model['post_type'] ?? '' );
            if ( $pt === $slug ) {
                if ( isset( $assoc_args['args'] ) ) {
                    $new_args = json_decode( $assoc_args['args'], true );
                    if ( ! is_array( $new_args ) ) {
                        \WP_CLI::error( 'Invalid args JSON.' );
                    }
                    $model['args'] = array_merge( $model['args'] ?? [], $new_args );
                }
                if ( isset( $assoc_args['version'] ) ) {
                    $model['version'] = (int) $assoc_args['version'];
                }
                update_option( 'gm2_models', $models );
                $this->run_migrations_for_model( $slug, $model );
                \WP_CLI::success( 'CPT updated.' );
                return;
            }
        }
        \WP_CLI::error( 'CPT not found.' );
    }

    /**
     * Delete a custom post type.
     *
     * ## OPTIONS
     *
     * <slug>
     * : CPT slug.
     */
    public function cpt_delete( $args, $assoc_args ) {
        $slug = $args[0] ?? '';
        if ( ! $slug ) {
            \WP_CLI::error( 'Missing CPT slug.' );
        }

        $models = get_option( 'gm2_models', [] );
        foreach ( $models as $index => $model ) {
            $pt = $model['slug'] ?? ( $model['post_type'] ?? '' );
            if ( $pt === $slug ) {
                unset( $models[ $index ] );
                update_option( 'gm2_models', array_values( $models ) );
                $versions = get_option( 'gm2_model_versions', [] );
                unset( $versions[ $slug ] );
                update_option( 'gm2_model_versions', $versions );
                \WP_CLI::success( 'CPT deleted.' );
                return;
            }
        }
        \WP_CLI::error( 'CPT not found.' );
    }

    /**
     * Create a taxonomy for a CPT.
     *
     * ## OPTIONS
     *
     * <cpt>
     * : CPT slug.
     *
     * <taxonomy>
     * : Taxonomy slug.
     *
     * [--args=<json>]
     * : JSON encoded arguments for register_taxonomy().
     */
    public function taxonomy_create( $args, $assoc_args ) {
        $cpt = $args[0] ?? '';
        $slug = $args[1] ?? '';
        if ( ! $cpt || ! $slug ) {
            \WP_CLI::error( 'Usage: wp gm2 model taxonomy create <cpt> <slug> [--args]' );
        }

        $models = get_option( 'gm2_models', [] );
        foreach ( $models as &$model ) {
            $pt = $model['slug'] ?? ( $model['post_type'] ?? '' );
            if ( $pt === $cpt ) {
                $taxes = $model['taxonomies'] ?? [];
                foreach ( $taxes as $tax ) {
                    $tax_slug = $tax['taxonomy'] ?? ( $tax['slug'] ?? '' );
                    if ( $tax_slug === $slug ) {
                        \WP_CLI::error( 'Taxonomy already exists.' );
                    }
                }
                $tax_args = isset( $assoc_args['args'] ) ? json_decode( $assoc_args['args'], true ) : [];
                if ( isset( $assoc_args['args'] ) && ! is_array( $tax_args ) ) {
                    \WP_CLI::error( 'Invalid args JSON.' );
                }
                $model['taxonomies'][] = [
                    'slug'        => $slug,
                    'taxonomy'    => $slug,
                    'object_type' => $cpt,
                    'args'        => $tax_args,
                ];
                update_option( 'gm2_models', $models );
                $this->run_migrations_for_model( $cpt, $model );
                \WP_CLI::success( 'Taxonomy created.' );
                return;
            }
        }
        \WP_CLI::error( 'CPT not found.' );
    }

    /**
     * Update a taxonomy for a CPT.
     */
    public function taxonomy_update( $args, $assoc_args ) {
        $cpt = $args[0] ?? '';
        $slug = $args[1] ?? '';
        if ( ! $cpt || ! $slug ) {
            \WP_CLI::error( 'Usage: wp gm2 model taxonomy update <cpt> <slug> [--args]' );
        }

        $models = get_option( 'gm2_models', [] );
        foreach ( $models as &$model ) {
            $pt = $model['slug'] ?? ( $model['post_type'] ?? '' );
            if ( $pt === $cpt ) {
                foreach ( $model['taxonomies'] ?? [] as &$tax ) {
                    $tax_slug = $tax['taxonomy'] ?? ( $tax['slug'] ?? '' );
                    if ( $tax_slug === $slug ) {
                        if ( isset( $assoc_args['args'] ) ) {
                            $new_args = json_decode( $assoc_args['args'], true );
                            if ( ! is_array( $new_args ) ) {
                                \WP_CLI::error( 'Invalid args JSON.' );
                            }
                            $tax['args'] = array_merge( $tax['args'] ?? [], $new_args );
                        }
                        update_option( 'gm2_models', $models );
                        $this->run_migrations_for_model( $cpt, $model );
                        \WP_CLI::success( 'Taxonomy updated.' );
                        return;
                    }
                }
                \WP_CLI::error( 'Taxonomy not found.' );
            }
        }
        \WP_CLI::error( 'CPT not found.' );
    }

    /**
     * Delete a taxonomy for a CPT.
     */
    public function taxonomy_delete( $args, $assoc_args ) {
        $cpt = $args[0] ?? '';
        $slug = $args[1] ?? '';
        if ( ! $cpt || ! $slug ) {
            \WP_CLI::error( 'Usage: wp gm2 model taxonomy delete <cpt> <slug>' );
        }

        $models = get_option( 'gm2_models', [] );
        foreach ( $models as &$model ) {
            $pt = $model['slug'] ?? ( $model['post_type'] ?? '' );
            if ( $pt === $cpt ) {
                foreach ( $model['taxonomies'] ?? [] as $i => $tax ) {
                    $tax_slug = $tax['taxonomy'] ?? ( $tax['slug'] ?? '' );
                    if ( $tax_slug === $slug ) {
                        unset( $model['taxonomies'][ $i ] );
                        $model['taxonomies'] = array_values( $model['taxonomies'] );
                        update_option( 'gm2_models', $models );
                        $this->run_migrations_for_model( $cpt, $model );
                        \WP_CLI::success( 'Taxonomy deleted.' );
                        return;
                    }
                }
                \WP_CLI::error( 'Taxonomy not found.' );
            }
        }
        \WP_CLI::error( 'CPT not found.' );
    }

    /**
     * Create a field for a CPT.
     *
     * ## OPTIONS
     *
     * <cpt>
     * : CPT slug.
     *
     * <key>
     * : Field key.
     *
     * [--args=<json>]
     * : JSON encoded arguments for register_post_meta().
     */
    public function field_create( $args, $assoc_args ) {
        $cpt = $args[0] ?? '';
        $key = $args[1] ?? '';
        if ( ! $cpt || ! $key ) {
            \WP_CLI::error( 'Usage: wp gm2 model field create <cpt> <key> [--args]' );
        }

        $models = get_option( 'gm2_models', [] );
        foreach ( $models as &$model ) {
            $pt = $model['slug'] ?? ( $model['post_type'] ?? '' );
            if ( $pt === $cpt ) {
                foreach ( $model['fields'] ?? [] as $field ) {
                    $f_key = $field['key'] ?? ( $field['name'] ?? '' );
                    if ( $f_key === $key ) {
                        \WP_CLI::error( 'Field already exists.' );
                    }
                }
                $field_args = isset( $assoc_args['args'] ) ? json_decode( $assoc_args['args'], true ) : [];
                if ( isset( $assoc_args['args'] ) && ! is_array( $field_args ) ) {
                    \WP_CLI::error( 'Invalid args JSON.' );
                }
                $model['fields'][] = [
                    'key'  => $key,
                    'name' => $key,
                    'args' => $field_args,
                ];
                update_option( 'gm2_models', $models );
                $this->run_migrations_for_model( $cpt, $model );
                \WP_CLI::success( 'Field created.' );
                return;
            }
        }
        \WP_CLI::error( 'CPT not found.' );
    }

    /**
     * Update a field for a CPT.
     */
    public function field_update( $args, $assoc_args ) {
        $cpt = $args[0] ?? '';
        $key = $args[1] ?? '';
        if ( ! $cpt || ! $key ) {
            \WP_CLI::error( 'Usage: wp gm2 model field update <cpt> <key> [--args]' );
        }

        $models = get_option( 'gm2_models', [] );
        foreach ( $models as &$model ) {
            $pt = $model['slug'] ?? ( $model['post_type'] ?? '' );
            if ( $pt === $cpt ) {
                foreach ( $model['fields'] ?? [] as &$field ) {
                    $f_key = $field['key'] ?? ( $field['name'] ?? '' );
                    if ( $f_key === $key ) {
                        if ( isset( $assoc_args['args'] ) ) {
                            $new_args = json_decode( $assoc_args['args'], true );
                            if ( ! is_array( $new_args ) ) {
                                \WP_CLI::error( 'Invalid args JSON.' );
                            }
                            $field['args'] = array_merge( $field['args'] ?? [], $new_args );
                        }
                        update_option( 'gm2_models', $models );
                        $this->run_migrations_for_model( $cpt, $model );
                        \WP_CLI::success( 'Field updated.' );
                        return;
                    }
                }
                \WP_CLI::error( 'Field not found.' );
            }
        }
        \WP_CLI::error( 'CPT not found.' );
    }

    /**
     * Delete a field for a CPT.
     */
    public function field_delete( $args, $assoc_args ) {
        $cpt = $args[0] ?? '';
        $key = $args[1] ?? '';
        if ( ! $cpt || ! $key ) {
            \WP_CLI::error( 'Usage: wp gm2 model field delete <cpt> <key>' );
        }

        $models = get_option( 'gm2_models', [] );
        foreach ( $models as &$model ) {
            $pt = $model['slug'] ?? ( $model['post_type'] ?? '' );
            if ( $pt === $cpt ) {
                foreach ( $model['fields'] ?? [] as $i => $field ) {
                    $f_key = $field['key'] ?? ( $field['name'] ?? '' );
                    if ( $f_key === $key ) {
                        unset( $model['fields'][ $i ] );
                        $model['fields'] = array_values( $model['fields'] );
                        update_option( 'gm2_models', $models );
                        $this->run_migrations_for_model( $cpt, $model );
                        \WP_CLI::success( 'Field deleted.' );
                        return;
                    }
                }
                \WP_CLI::error( 'Field not found.' );
            }
        }
        \WP_CLI::error( 'CPT not found.' );
    }

    /**
     * Run migrations for a specific model.
     *
     * @param string $slug  CPT slug.
     * @param array  $model Model data.
     * @return void
     */
    private function run_migrations_for_model( $slug, $model ) {
        $versions = get_option( 'gm2_model_versions', [] );
        $target   = (int) ( $model['version'] ?? 1 );
        $current  = (int) ( $versions[ $slug ] ?? 0 );
        if ( $target > $current ) {
            gm2_run_model_migrations( $slug, $current, $target );
            $versions[ $slug ] = $target;
            update_option( 'gm2_model_versions', $versions );
        }
    }

    /**
     * Generate PHP code for registered models.
     *
     * [--mu-plugin]
     * : Generate code as an MU plugin.
     */
    public function generate( $args, $assoc_args ) {
        $mu         = isset( $assoc_args['mu-plugin'] );
        $php_file   = $assoc_args['php'] ?? '';
        $json_file  = $assoc_args['json'] ?? '';
        $models     = get_option( 'gm2_models', [] );

        if ( empty( $models ) ) {
            \WP_CLI::warning( 'No models found in the gm2_models option.' );
            return;
        }

        if ( ! $php_file ) {
            $php_file = $mu ? WPMU_PLUGIN_DIR . '/gm2-models.php' : WP_PLUGIN_DIR . '/gm2-models.php';
        }

        $php_code  = "<?php\n";
        $php_code .= "/**\n * Auto-generated by wp gm2 model generate.\n */\n";
        $php_code .= "add_action( 'init', function() {\n";

        foreach ( $models as $model ) {
            $pt = $model['post_type'] ?? ( $model['slug'] ?? '' );
            if ( $pt ) {
                $args = var_export( $model['args'] ?? [], true );
                $php_code .= "    register_post_type( '{$pt}', {$args} );\n";
            }

            if ( ! empty( $model['taxonomies'] ) && is_array( $model['taxonomies'] ) ) {
                foreach ( $model['taxonomies'] as $tax ) {
                    $tax_slug   = $tax['taxonomy'] ?? ( $tax['slug'] ?? '' );
                    if ( ! $tax_slug ) {
                        continue;
                    }
                    $object_type = $tax['object_type'] ?? $pt;
                    $tax_args    = var_export( $tax['args'] ?? [], true );
                    $php_code   .= "    register_taxonomy( '{$tax_slug}', '{$object_type}', {$tax_args} );\n";
                }
            }

            if ( ! empty( $model['fields'] ) && is_array( $model['fields'] ) ) {
                foreach ( $model['fields'] as $field ) {
                    $field_key = $field['key'] ?? ( $field['name'] ?? '' );
                    if ( ! $field_key ) {
                        continue;
                    }
                    $meta_args = var_export( $field['args'] ?? [ 'type' => 'string', 'single' => true, 'show_in_rest' => true ], true );
                    $php_code .= "    register_post_meta( '{$pt}', '{$field_key}', {$meta_args} );\n";
                }
            }
        }

        $php_code .= "});\n";

        if ( ! wp_mkdir_p( dirname( $php_file ) ) ) {
            \WP_CLI::error( 'Failed to create directory: ' . dirname( $php_file ) );
        }
        file_put_contents( $php_file, $php_code );

        $json_code = wp_json_encode( $models, JSON_PRETTY_PRINT );
        if ( $json_file ) {
            if ( ! wp_mkdir_p( dirname( $json_file ) ) ) {
                \WP_CLI::error( 'Failed to create directory: ' . dirname( $json_file ) );
            }
            file_put_contents( $json_file, $json_code );
        }

        if ( function_exists( 'gm2_render_open_in_code' ) ) {
            \WP_CLI::line( gm2_render_open_in_code( $php_code, $json_code ) );
        }

        \WP_CLI::success( $mu ? 'Generated MU plugin code.' : 'Generated plugin code.' );
        \WP_CLI::line( 'PHP file: ' . $php_file );
        if ( $json_file ) {
            \WP_CLI::line( 'JSON file: ' . $json_file );
        }
    }

    /**
     * Run model database migrations.
     */
    public function migrate( $args, $assoc_args ) {
        $models   = get_option( 'gm2_models', [] );
        $versions = get_option( 'gm2_model_versions', [] );
        $migrated = 0;

        foreach ( $models as $model ) {
            $slug    = $model['slug'] ?? ( $model['post_type'] ?? '' );
            $target  = (int) ( $model['version'] ?? 1 );
            $current = (int) ( $versions[ $slug ] ?? 0 );

            if ( $slug && $target > $current ) {
                gm2_run_model_migrations( $slug, $current, $target );
                $versions[ $slug ] = $target;
                $migrated++;
            }
        }

        update_option( 'gm2_model_versions', $versions );

        if ( $migrated ) {
            \WP_CLI::success( sprintf( '%d model migrations completed.', $migrated ) );
        } else {
            \WP_CLI::success( 'Models are up to date.' );
        }
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

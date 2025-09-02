<?php
namespace Gm2;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

require_once GM2_PLUGIN_DIR . 'includes/class-gm2-model-migrator.php';
require_once GM2_PLUGIN_DIR . 'includes/gm2-model-export.php';

/**
 * Manage data models via WP-CLI.
 */
class Gm2_Model_CLI extends \WP_CLI_Command {
    /**
     * Export model configuration to a file.
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
            \WP_CLI::error( __( 'Missing file argument.', 'gm2-wordpress-suite' ) );
        }
        $format = $assoc_args['format'] ?? 'json';
        $data   = \gm2_model_export( $format );
        if ( is_wp_error( $data ) ) {
            \WP_CLI::error( $data->get_error_message() );
        }
        file_put_contents( $file, $data );
        \WP_CLI::success( sprintf( __( 'Models exported to %s', 'gm2-wordpress-suite' ), $file ) );
    }

    /**
     * Import model configuration from a file.
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
            \WP_CLI::error( __( 'File not found.', 'gm2-wordpress-suite' ) );
        }
        $format = $assoc_args['format'] ?? '';
        if ( ! $format ) {
            $format = preg_match( '/\.ya?ml$/i', $file ) ? 'yaml' : 'json';
        }
        $contents = file_get_contents( $file );
        $result   = \gm2_model_import( $contents, $format );
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }
        \WP_CLI::success( sprintf( __( 'Models imported from %s', 'gm2-wordpress-suite' ), $file ) );
    }

    /**
     * Generate a plugin or mu-plugin zip containing the registered models.
     *
     * ## OPTIONS
     *
     * <file>
     * : Destination zip file path.
     *
     * [--mu]
     * : Generate as a must-use plugin.
     */
    public function generate_plugin( $args, $assoc_args ) {
        $file = $args[0] ?? '';
        if ( empty( $file ) ) {
            \WP_CLI::error( __( 'Missing file argument.', 'gm2-wordpress-suite' ) );
        }
        $mu   = ! empty( $assoc_args['mu'] );
        $data = \gm2_model_export( 'array' );
        $zip  = \gm2_model_generate_plugin( $data, $file, $mu );
        if ( is_wp_error( $zip ) ) {
            \WP_CLI::error( $zip->get_error_message() );
        }
        \WP_CLI::success( sprintf( __( 'Plugin generated at %s', 'gm2-wordpress-suite' ), $file ) );
    }

    /**
     * Create a CPT, taxonomy or field group.
     *
     * ## OPTIONS
     *
     * <type>
     * : Model type to create. Accepts `cpt`, `taxonomy` or `field`.
     *
     * [<args>...]
     * : Additional arguments passed to the underlying command.
     */
    public function create( $args, $assoc_args ) {
        $type = $args[0] ?? '';
        if ( ! $type ) {
            \WP_CLI::error( __( 'Usage: wp gm2 model create <cpt|taxonomy|field> ...', 'gm2-wordpress-suite' ) );
        }

        array_shift( $args );
        switch ( $type ) {
            case 'cpt':
            case 'post-type':
                $this->cpt_create( $args, $assoc_args );
                break;
            case 'taxonomy':
                $this->taxonomy_create( $args, $assoc_args );
                break;
            case 'field':
            case 'field-group':
                $this->field_create( $args, $assoc_args );
                break;
            default:
                \WP_CLI::error( __( 'Unknown type. Use cpt, taxonomy or field.', 'gm2-wordpress-suite' ) );
        }
    }

    /**
     * Update an existing CPT, taxonomy or field group.
     *
     * ## OPTIONS
     *
     * <type>
     * : Model type to update. Accepts `cpt`, `taxonomy` or `field`.
     *
     * [<args>...]
     * : Additional arguments passed to the underlying command.
     */
    public function update( $args, $assoc_args ) {
        $type = $args[0] ?? '';
        if ( ! $type ) {
            \WP_CLI::error( __( 'Usage: wp gm2 model update <cpt|taxonomy|field> ...', 'gm2-wordpress-suite' ) );
        }

        array_shift( $args );
        switch ( $type ) {
            case 'cpt':
            case 'post-type':
                $this->cpt_update( $args, $assoc_args );
                break;
            case 'taxonomy':
                $this->taxonomy_update( $args, $assoc_args );
                break;
            case 'field':
            case 'field-group':
                $this->field_update( $args, $assoc_args );
                break;
            default:
                \WP_CLI::error( __( 'Unknown type. Use cpt, taxonomy or field.', 'gm2-wordpress-suite' ) );
        }
    }

    /**
     * Modify an existing CPT, taxonomy or field group.
     *
     * This is an alias of the `update` subcommand.
     */
    public function modify( $args, $assoc_args ) {
        $this->update( $args, $assoc_args );
    }

    /**
     * Delete a CPT, taxonomy or field group.
     *
     * ## OPTIONS
     *
     * <type>
     * : Model type to delete. Accepts `cpt`, `taxonomy` or `field`.
     *
     * [<args>...]
     * : Additional arguments passed to the underlying command.
     */
    public function delete( $args, $assoc_args ) {
        $type = $args[0] ?? '';
        if ( ! $type ) {
            \WP_CLI::error( __( 'Usage: wp gm2 model delete <cpt|taxonomy|field> ...', 'gm2-wordpress-suite' ) );
        }

        array_shift( $args );
        switch ( $type ) {
            case 'cpt':
            case 'post-type':
                $this->cpt_delete( $args, $assoc_args );
                break;
            case 'taxonomy':
                $this->taxonomy_delete( $args, $assoc_args );
                break;
            case 'field':
            case 'field-group':
                $this->field_delete( $args, $assoc_args );
                break;
            default:
                \WP_CLI::error( __( 'Unknown type. Use cpt, taxonomy or field.', 'gm2-wordpress-suite' ) );
        }
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
            \WP_CLI::error( __( 'Missing CPT slug.', 'gm2-wordpress-suite' ) );
        }

        $models = get_option( 'gm2_models', [] );
        foreach ( $models as $model ) {
            $pt = $model['slug'] ?? ( $model['post_type'] ?? '' );
            if ( $pt === $slug ) {
                \WP_CLI::error( __( 'CPT already exists.', 'gm2-wordpress-suite' ) );
            }
        }

        $args_json = $assoc_args['args'] ?? '{}';
        $pt_args   = json_decode( $args_json, true );
        if ( ! is_array( $pt_args ) ) {
            \WP_CLI::error( __( 'Invalid args JSON.', 'gm2-wordpress-suite' ) );
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
        \WP_CLI::success( __( 'CPT created.', 'gm2-wordpress-suite' ) );
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
            \WP_CLI::error( __( 'Missing CPT slug.', 'gm2-wordpress-suite' ) );
        }

        $models = get_option( 'gm2_models', [] );
        foreach ( $models as &$model ) {
            $pt = $model['slug'] ?? ( $model['post_type'] ?? '' );
            if ( $pt === $slug ) {
                if ( isset( $assoc_args['args'] ) ) {
                    $new_args = json_decode( $assoc_args['args'], true );
                    if ( ! is_array( $new_args ) ) {
                        \WP_CLI::error( __( 'Invalid args JSON.', 'gm2-wordpress-suite' ) );
                    }
                    $model['args'] = array_merge( $model['args'] ?? [], $new_args );
                }
                if ( isset( $assoc_args['version'] ) ) {
                    $model['version'] = (int) $assoc_args['version'];
                }
                update_option( 'gm2_models', $models );
                $this->run_migrations_for_model( $slug, $model );
                \WP_CLI::success( __( 'CPT updated.', 'gm2-wordpress-suite' ) );
                return;
            }
        }
        \WP_CLI::error( __( 'CPT not found.', 'gm2-wordpress-suite' ) );
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
            \WP_CLI::error( __( 'Missing CPT slug.', 'gm2-wordpress-suite' ) );
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
                \WP_CLI::success( __( 'CPT deleted.', 'gm2-wordpress-suite' ) );
                return;
            }
        }
        \WP_CLI::error( __( 'CPT not found.', 'gm2-wordpress-suite' ) );
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
            \WP_CLI::error( __( 'Usage: wp gm2 model taxonomy create <cpt> <slug> [--args]', 'gm2-wordpress-suite' ) );
        }

        $models = get_option( 'gm2_models', [] );
        foreach ( $models as &$model ) {
            $pt = $model['slug'] ?? ( $model['post_type'] ?? '' );
            if ( $pt === $cpt ) {
                $taxes = $model['taxonomies'] ?? [];
                foreach ( $taxes as $tax ) {
                    $tax_slug = $tax['taxonomy'] ?? ( $tax['slug'] ?? '' );
                    if ( $tax_slug === $slug ) {
                        \WP_CLI::error( __( 'Taxonomy already exists.', 'gm2-wordpress-suite' ) );
                    }
                }
                $tax_args = isset( $assoc_args['args'] ) ? json_decode( $assoc_args['args'], true ) : [];
                if ( isset( $assoc_args['args'] ) && ! is_array( $tax_args ) ) {
                    \WP_CLI::error( __( 'Invalid args JSON.', 'gm2-wordpress-suite' ) );
                }
                $model['taxonomies'][] = [
                    'slug'        => $slug,
                    'taxonomy'    => $slug,
                    'object_type' => $cpt,
                    'args'        => $tax_args,
                ];
                update_option( 'gm2_models', $models );
                $this->run_migrations_for_model( $cpt, $model );
                \WP_CLI::success( __( 'Taxonomy created.', 'gm2-wordpress-suite' ) );
                return;
            }
        }
        \WP_CLI::error( __( 'CPT not found.', 'gm2-wordpress-suite' ) );
    }

    /**
     * Update a taxonomy for a CPT.
     */
    public function taxonomy_update( $args, $assoc_args ) {
        $cpt = $args[0] ?? '';
        $slug = $args[1] ?? '';
        if ( ! $cpt || ! $slug ) {
            \WP_CLI::error( __( 'Usage: wp gm2 model taxonomy update <cpt> <slug> [--args]', 'gm2-wordpress-suite' ) );
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
                                \WP_CLI::error( __( 'Invalid args JSON.', 'gm2-wordpress-suite' ) );
                            }
                            $tax['args'] = array_merge( $tax['args'] ?? [], $new_args );
                        }
                        update_option( 'gm2_models', $models );
                        $this->run_migrations_for_model( $cpt, $model );
                        \WP_CLI::success( __( 'Taxonomy updated.', 'gm2-wordpress-suite' ) );
                        return;
                    }
                }
                \WP_CLI::error( __( 'Taxonomy not found.', 'gm2-wordpress-suite' ) );
            }
        }
        \WP_CLI::error( __( 'CPT not found.', 'gm2-wordpress-suite' ) );
    }

    /**
     * Delete a taxonomy for a CPT.
     */
    public function taxonomy_delete( $args, $assoc_args ) {
        $cpt = $args[0] ?? '';
        $slug = $args[1] ?? '';
        if ( ! $cpt || ! $slug ) {
            \WP_CLI::error( __( 'Usage: wp gm2 model taxonomy delete <cpt> <slug>', 'gm2-wordpress-suite' ) );
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
                        \WP_CLI::success( __( 'Taxonomy deleted.', 'gm2-wordpress-suite' ) );
                        return;
                    }
                }
                \WP_CLI::error( __( 'Taxonomy not found.', 'gm2-wordpress-suite' ) );
            }
        }
        \WP_CLI::error( __( 'CPT not found.', 'gm2-wordpress-suite' ) );
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
            \WP_CLI::error( __( 'Usage: wp gm2 model field create <cpt> <key> [--args]', 'gm2-wordpress-suite' ) );
        }

        $models = get_option( 'gm2_models', [] );
        foreach ( $models as &$model ) {
            $pt = $model['slug'] ?? ( $model['post_type'] ?? '' );
            if ( $pt === $cpt ) {
                foreach ( $model['fields'] ?? [] as $field ) {
                    $f_key = $field['key'] ?? ( $field['name'] ?? '' );
                    if ( $f_key === $key ) {
                        \WP_CLI::error( __( 'Field already exists.', 'gm2-wordpress-suite' ) );
                    }
                }
                $field_args = isset( $assoc_args['args'] ) ? json_decode( $assoc_args['args'], true ) : [];
                if ( isset( $assoc_args['args'] ) && ! is_array( $field_args ) ) {
                    \WP_CLI::error( __( 'Invalid args JSON.', 'gm2-wordpress-suite' ) );
                }
                $model['fields'][] = [
                    'key'  => $key,
                    'name' => $key,
                    'args' => $field_args,
                ];
                update_option( 'gm2_models', $models );
                $this->run_migrations_for_model( $cpt, $model );
                \WP_CLI::success( __( 'Field created.', 'gm2-wordpress-suite' ) );
                return;
            }
        }
        \WP_CLI::error( __( 'CPT not found.', 'gm2-wordpress-suite' ) );
    }

    /**
     * Update a field for a CPT.
     */
    public function field_update( $args, $assoc_args ) {
        $cpt = $args[0] ?? '';
        $key = $args[1] ?? '';
        if ( ! $cpt || ! $key ) {
            \WP_CLI::error( __( 'Usage: wp gm2 model field update <cpt> <key> [--args]', 'gm2-wordpress-suite' ) );
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
                                    \WP_CLI::error( __( 'Invalid args JSON.', 'gm2-wordpress-suite' ) );
                                }
                            $field['args'] = array_merge( $field['args'] ?? [], $new_args );
                        }
                        update_option( 'gm2_models', $models );
                        $this->run_migrations_for_model( $cpt, $model );
                        \WP_CLI::success( __( 'Field updated.', 'gm2-wordpress-suite' ) );
                        return;
                    }
                }
                \WP_CLI::error( __( 'Field not found.', 'gm2-wordpress-suite' ) );
            }
        }
        \WP_CLI::error( __( 'CPT not found.', 'gm2-wordpress-suite' ) );
    }

    /**
     * Delete a field for a CPT.
     */
    public function field_delete( $args, $assoc_args ) {
        $cpt = $args[0] ?? '';
        $key = $args[1] ?? '';
        if ( ! $cpt || ! $key ) {
            \WP_CLI::error( __( 'Usage: wp gm2 model field delete <cpt> <key>', 'gm2-wordpress-suite' ) );
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
                        \WP_CLI::success( __( 'Field deleted.', 'gm2-wordpress-suite' ) );
                        return;
                    }
                }
                \WP_CLI::error( __( 'Field not found.', 'gm2-wordpress-suite' ) );
            }
        }
        \WP_CLI::error( __( 'CPT not found.', 'gm2-wordpress-suite' ) );
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
        \WP_CLI::warning( __( 'No models found in the gm2_models option.', 'gm2-wordpress-suite' ) );
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
            \WP_CLI::error( sprintf( __( 'Failed to create directory: %s', 'gm2-wordpress-suite' ), dirname( $php_file ) ) );
        }
        file_put_contents( $php_file, $php_code );

        $json_code = wp_json_encode( $models, JSON_PRETTY_PRINT );
        if ( $json_file ) {
            if ( ! wp_mkdir_p( dirname( $json_file ) ) ) {
                \WP_CLI::error( sprintf( __( 'Failed to create directory: %s', 'gm2-wordpress-suite' ), dirname( $json_file ) ) );
            }
            file_put_contents( $json_file, $json_code );
        }

        if ( function_exists( 'gm2_render_open_in_code' ) ) {
            \WP_CLI::line( gm2_render_open_in_code( $php_code, $json_code ) );
        }

        \WP_CLI::success( $mu ? __( 'Generated MU plugin code.', 'gm2-wordpress-suite' ) : __( 'Generated plugin code.', 'gm2-wordpress-suite' ) );
        \WP_CLI::line( sprintf( __( 'PHP file: %s', 'gm2-wordpress-suite' ), $php_file ) );
        if ( $json_file ) {
            \WP_CLI::line( sprintf( __( 'JSON file: %s', 'gm2-wordpress-suite' ), $json_file ) );
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
            \WP_CLI::success( sprintf( __( '%d model migrations completed.', 'gm2-wordpress-suite' ), $migrated ) );
        } else {
            \WP_CLI::success( __( 'Models are up to date.', 'gm2-wordpress-suite' ) );
        }
    }

    /**
     * Seed model data.
     *
     * ## OPTIONS
     *
     * [--qty=<number>]
     * : Number of items to create for posts and terms. Defaults to 1.
     *
     * [--cpt=<slug>]
     * : Restrict seeding to a specific custom post type.
     *
     * [--taxonomy=<slug>]
     * : Restrict seeding to a specific taxonomy.
     *
     * [--media=<number>]
     * : Number of attachment posts to create.
     */
    public function seed( $args, $assoc_args ) {
        $qty       = max( 0, (int) ( $assoc_args['qty'] ?? 1 ) );
        $cpt_filter = $assoc_args['cpt'] ?? '';
        $tax_filter = $assoc_args['taxonomy'] ?? '';
        $media_qty  = max( 0, (int) ( $assoc_args['media'] ?? 0 ) );

        $models     = get_option( 'gm2_models', [] );
        $post_count = 0;
        $term_count = 0;
        $media_count = 0;

        foreach ( $models as $model ) {
            $cpt = $model['slug'] ?? ( $model['post_type'] ?? '' );
            if ( ! $cpt ) {
                continue;
            }
            if ( $cpt_filter && $cpt !== $cpt_filter ) {
                continue;
            }

            for ( $i = 1; $i <= $qty; $i++ ) {
                wp_insert_post(
                    [
                        'post_type'   => $cpt,
                        'post_title'  => sprintf( '%s Seed %d', ucfirst( $cpt ), $i ),
                        'post_status' => 'publish',
                    ]
                );
                $post_count++;
            }

            foreach ( $model['taxonomies'] ?? [] as $tax ) {
                $tax_slug = $tax['taxonomy'] ?? ( $tax['slug'] ?? '' );
                if ( ! $tax_slug ) {
                    continue;
                }
                if ( $tax_filter && $tax_slug !== $tax_filter ) {
                    continue;
                }
                for ( $i = 1; $i <= $qty; $i++ ) {
                    wp_insert_term( sprintf( '%s Term %d', ucfirst( $tax_slug ), $i ), $tax_slug );
                    $term_count++;
                }
            }
        }

        for ( $i = 1; $i <= $media_qty; $i++ ) {
            wp_insert_attachment(
                [
                    'post_title'    => sprintf( 'Seed Attachment %d', $i ),
                    'post_status'   => 'inherit',
                    'post_mime_type'=> 'image/jpeg',
                ],
                '',
                0
            );
            $media_count++;
        }

        \WP_CLI::success( sprintf( __( 'Seeded %d posts, %d terms and %d media items.', 'gm2-wordpress-suite' ), $post_count, $term_count, $media_count ) );
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
        \WP_CLI::success( sprintf( __( 'Environment set to %s', 'gm2-wordpress-suite' ), $env ) );
    }

    /**
     * Lock editing of models.
     */
    public function lock( $args, $assoc_args ) {
        update_option( 'gm2_model_locked', 1 );
        \WP_CLI::success( __( 'Models locked.', 'gm2-wordpress-suite' ) );
    }

    /**
     * Unlock editing of models.
     */
    public function unlock( $args, $assoc_args ) {
        delete_option( 'gm2_model_locked' );
        \WP_CLI::success( __( 'Models unlocked.', 'gm2-wordpress-suite' ) );
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

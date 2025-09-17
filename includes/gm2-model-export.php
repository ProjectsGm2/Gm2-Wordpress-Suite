<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gm2_model_export')) {
    /**
     * Export model configuration including post types, taxonomies and field groups.
     *
     * @param string $format Output format: json, yaml or array.
     * @return string|array|WP_Error
     */
    function gm2_model_export(string $format = 'json') {
        $config = get_option('gm2_custom_posts_config', []);
        if (!is_array($config)) {
            $config = [];
        }
        $field_groups = get_option('gm2_field_groups', []);
        if (!is_array($field_groups)) {
            $field_groups = [];
        }
        $schema_maps = get_option('gm2_cp_schema_map', []);
        if (!is_array($schema_maps)) {
            $schema_maps = [];
        }
        $data = [
            'post_types'      => $config['post_types'] ?? [],
            'taxonomies'      => $config['taxonomies'] ?? [],
            'field_groups'    => $field_groups,
            'schema_mappings' => $schema_maps,
            'fields'          => [
                'groups' => $field_groups,
            ],
            'seo'             => [
                'mappings' => $schema_maps,
            ],
        ];
        switch ($format) {
            case 'array':
                return $data;
            case 'yaml':
                if (function_exists('yaml_emit')) {
                    return yaml_emit($data);
                }
                return new \WP_Error('yaml_unavailable', __('YAML support is not available.', 'gm2-wordpress-suite'));
            case 'json':
            default:
                $encode = function_exists('wp_json_encode') ? 'wp_json_encode' : 'json_encode';
                return $encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }
}

if (!function_exists('gm2_validate_blueprint')) {
    /**
     * Validate blueprint data against the bundled schema.
     *
     * @param array $data Blueprint data.
     * @return true|WP_Error
     */
    function gm2_validate_blueprint(array $data) {
        $schema_file = GM2_PLUGIN_DIR . 'presets/schema.json';
        if (!file_exists($schema_file)) {
            $schema_file = GM2_PLUGIN_DIR . 'assets/blueprints/schema.json';
        }
        if (!file_exists($schema_file)) {
            return new \WP_Error('schema_missing', __('Blueprint schema not found.', 'gm2-wordpress-suite'));
        }
        $schema = json_decode(file_get_contents($schema_file), true);
        if (!is_array($schema)) {
            return new \WP_Error('schema_invalid', __('Invalid blueprint schema.', 'gm2-wordpress-suite'));
        }
        $result = rest_validate_value_from_schema($data, $schema, 'blueprint');
        if (is_wp_error($result)) {
            return $result;
        }
        return true;
    }
}

if (!function_exists('gm2_model_import')) {
    /**
     * Import model configuration.
     *
     * @param string|array $input  Model data.
     * @param string       $format Input format: json, yaml or array.
     * @return true|WP_Error
     */
    function gm2_model_import($input, string $format = 'json') {
        if ('array' === $format) {
            $data = $input;
        } elseif ('yaml' === $format) {
            if (!function_exists('yaml_parse')) {
                return new \WP_Error('yaml_unavailable', __('YAML support is not available.', 'gm2-wordpress-suite'));
            }
            $data = yaml_parse($input);
        } else {
            $data = json_decode($input, true);
        }

        if (!is_array($data)) {
            return new \WP_Error('invalid_data', __('Invalid model data.', 'gm2-wordpress-suite'));
        }

        $valid = gm2_validate_blueprint($data);
        if (is_wp_error($valid)) {
            return $valid;
        }

        $config = [
            'post_types' => $data['post_types'] ?? [],
            'taxonomies' => $data['taxonomies'] ?? [],
        ];
        update_option('gm2_custom_posts_config', $config);
        $field_groups = $data['field_groups'] ?? [];
        if (!$field_groups && isset($data['fields']['groups'])) {
            $field_groups = $data['fields']['groups'];
        }
        $schema_mappings = $data['schema_mappings'] ?? [];
        if (!$schema_mappings && isset($data['seo']['mappings'])) {
            $schema_mappings = $data['seo']['mappings'];
        }
        update_option('gm2_field_groups', is_array($field_groups) ? $field_groups : []);
        update_option('gm2_cp_schema_map', is_array($schema_mappings) ? $schema_mappings : []);
        return true;
    }
}

if (!function_exists('gm2_model_generate_plugin')) {
    /**
     * Generate a plugin or MU plugin zip containing model registrations.
     *
     * @param array  $data Model data array.
     * @param string $dest Destination zip path.
     * @param bool   $mu   Whether to generate as mu-plugin.
     * @return string|WP_Error Path to generated zip or error.
     */
    function gm2_model_generate_plugin(array $data, string $dest, bool $mu = false) {
        $slug = 'gm2-models';
        $plugin_name = 'Gm2 Models';
        $code  = "<?php\n";
        $code .= "/*\nPlugin Name: {$plugin_name}\n*/\n";
        $code .= "if (!defined('ABSPATH')) { exit; }\n";
        $code .= "add_action('init', function() {\n";
        foreach ($data['post_types'] as $pt_slug => $args) {
            $code .= "    register_post_type('" . $pt_slug . "', " . var_export($args, true) . ");\n";
        }
        foreach ($data['taxonomies'] as $tax_slug => $tax_args) {
            $objects = $tax_args['object_type'] ?? [];
            unset($tax_args['object_type']);
            $code .= "    register_taxonomy('" . $tax_slug . "', " . var_export($objects, true) . ', ' . var_export($tax_args, true) . ");\n";
        }
        $code .= "    \$groups = " . var_export($data['field_groups'] ?? [], true) . ";\n";
        $code .= "    \$existing = get_option('gm2_field_groups', []);\n";
        $code .= "    update_option('gm2_field_groups', array_merge(\$existing, \$groups));\n";
        $code .= "    \$maps = " . var_export($data['schema_mappings'] ?? [], true) . ";\n";
        $code .= "    \$existing_maps = get_option('gm2_cp_schema_map', []);\n";
        $code .= "    update_option('gm2_cp_schema_map', array_merge(\$existing_maps, \$maps));\n";
        $code .= "});\n";

        $tmp_dir = sys_get_temp_dir() . '/gm2_model_' . uniqid();
        if (!mkdir($tmp_dir) && !is_dir($tmp_dir)) {
            return new \WP_Error('mkdir_failed', __('Could not create temporary directory.', 'gm2-wordpress-suite'));
        }
        $file_path = $tmp_dir . '/' . $slug . '.php';
        file_put_contents($file_path, $code);

        $zip = new \ZipArchive();
        if (true !== $zip->open($dest, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            return new \WP_Error('zip_failed', __('Could not create zip archive.', 'gm2-wordpress-suite'));
        }
        if ($mu) {
            $zip->addFile($file_path, $slug . '.php');
        } else {
            $zip->addFile($file_path, $slug . '/' . $slug . '.php');
        }
        $zip->close();
        return $dest;
    }
}

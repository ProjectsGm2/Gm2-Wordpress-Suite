<?php

use Gm2\Presets\BlueprintIO;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gm2_model_export')) {
    /**
     * Export the full blueprint definition.
     *
     * @param string $format Output format: json, yaml or array.
     * @return string|array|WP_Error
     */
    function gm2_model_export(string $format = 'json') {
        $blueprint = BlueprintIO::exportBlueprint();

        if ($format === 'array') {
            return $blueprint;
        }

        return BlueprintIO::encode($blueprint, $format);
    }
}

if (!function_exists('gm2_field_groups_export')) {
    /**
     * Export only field group definitions.
     *
     * @param string               $format Output format: json, yaml or array.
     * @param array<int, string>|null $slugs Optional list of group slugs to include.
     * @return string|array|WP_Error
     */
    function gm2_field_groups_export(string $format = 'json', ?array $slugs = null) {
        $blueprint = BlueprintIO::exportFieldGroups($slugs);

        if ($format === 'array') {
            return $blueprint;
        }

        return BlueprintIO::encode($blueprint, $format);
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

        $prepared = BlueprintIO::prepareForSchema($data);
        $result = rest_validate_value_from_schema($prepared, $schema, 'blueprint');
        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }
}

if (!function_exists('gm2_model_import')) {
    /**
     * Import a full blueprint definition.
     *
     * @param string|array $input  Model data.
     * @param string       $format Input format: json, yaml or array.
     * @return true|WP_Error
     */
    function gm2_model_import($input, string $format = 'json') {
        $data = BlueprintIO::decode($input, $format);
        if (is_wp_error($data)) {
            return $data;
        }

        $valid = gm2_validate_blueprint($data);
        if (is_wp_error($valid)) {
            return $valid;
        }

        return BlueprintIO::importBlueprint($data);
    }
}

if (!function_exists('gm2_field_groups_import')) {
    /**
     * Import only field group definitions.
     *
     * @param string|array $input Field group data.
     * @param string       $format Input format.
     * @param bool         $merge  Whether to merge with existing groups.
     * @return true|WP_Error
     */
    function gm2_field_groups_import($input, string $format = 'json', bool $merge = true) {
        $data = BlueprintIO::decode($input, $format);
        if (is_wp_error($data)) {
            return $data;
        }

        return BlueprintIO::importFieldGroups($data, $merge);
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
        foreach ($data['post_types'] ?? [] as $pt_slug => $args) {
            $code .= "    register_post_type('" . $pt_slug . "', " . var_export($args, true) . ");\n";
        }
        foreach ($data['taxonomies'] ?? [] as $tax_slug => $tax_args) {
            $objects = $tax_args['object_type'] ?? [];
            unset($tax_args['object_type']);
            $code .= "    register_taxonomy('" . $tax_slug . "', " . var_export($objects, true) . ', ' . var_export($tax_args, true) . ");\n";
        }
        $groups = BlueprintIO::prepareFieldGroupsForStorage($data);
        $code .= "    \$groups = " . var_export($groups, true) . ";\n";
        $code .= "    \$existing = get_option('gm2_field_groups', []);\n";
        $code .= "    update_option('gm2_field_groups', array_merge(\$existing, \$groups));\n";
        $maps = $data['schema_mappings'] ?? ($data['seo']['mappings'] ?? []);
        if (!is_array($maps)) {
            $maps = [];
        }
        $code .= "    \$maps = " . var_export($maps, true) . ";\n";
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

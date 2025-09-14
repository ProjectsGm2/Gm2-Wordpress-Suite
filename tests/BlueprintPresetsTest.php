<?php
class BlueprintPresetsTest extends WP_UnitTestCase {
    public function test_presets_validate_and_import() {
        $files = glob(GM2_PLUGIN_DIR . 'assets/blueprints/presets/*.json');
        $this->assertNotEmpty($files, 'No preset blueprints found');
        foreach ($files as $file) {
            delete_option('gm2_custom_posts_config');
            delete_option('gm2_field_groups');
            delete_option('gm2_cp_schema_map');
            $json = file_get_contents($file);
            $data = json_decode($json, true);
            $this->assertIsArray($data, basename($file) . ' decoded');
            $this->assertTrue(true === gm2_validate_blueprint($data), basename($file) . ' validation');
            $result = gm2_model_import($json);
            $this->assertTrue(true === $result, basename($file) . ' import');
            $config = get_option('gm2_custom_posts_config');
            $this->assertIsArray($config);
            $this->assertNotEmpty($config['post_types']);
        }
    }
}

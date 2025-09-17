<?php

use Gm2\Presets\PresetManager;
use org\bovigo\vfs\vfsStream;

class PresetManagerTest extends WP_UnitTestCase {
    public function test_presets_load_and_validate(): void {
        $manager = new PresetManager(GM2_PLUGIN_DIR . 'presets', GM2_PLUGIN_DIR . 'presets/schema.json');
        $all = $manager->all();

        $this->assertNotEmpty($all, 'Expected bundled presets to be available.');
        $this->assertArrayHasKey('directory', $all, 'Directory preset missing.');

        $directory = $manager->get('directory');
        $this->assertIsArray($directory, 'Directory preset should return an array.');
        $this->assertArrayHasKey('default_terms', $directory);
        $this->assertArrayHasKey('elementor_query_ids', $directory);
        $this->assertNotEmpty($directory['elementor_query_ids']);
        $this->assertArrayHasKey('seo_mappings', $directory);
        $this->assertNotEmpty($directory['seo_mappings']);
        $elementorKeys = [];
        foreach ($directory['elementor_query_ids'] as $entry) {
            if (is_array($entry) && isset($entry['key'])) {
                $elementorKeys[] = $entry['key'];
            }
        }
        $this->assertContains('nearby', $elementorKeys, 'Expected Elementor query list to include the "nearby" definition.');

        $seoKeys = [];
        foreach ($directory['seo_mappings'] as $entry) {
            if (is_array($entry) && isset($entry['key'])) {
                $seoKeys[] = $entry['key'];
            }
        }
        $this->assertContains('listing', $seoKeys, 'Expected SEO mappings to include the "listing" schema map.');
        $this->assertArrayHasKey('templates', $directory);

        $this->assertTrue(true === $manager->validate($directory, 'directory'));
    }

    public function test_filters_expose_manager_data(): void {
        $manager = new PresetManager(GM2_PLUGIN_DIR . 'presets', GM2_PLUGIN_DIR . 'presets/schema.json');
        $manager->registerHooks();

        $all = apply_filters('gm2/presets/all', []);
        $this->assertArrayHasKey('directory', $all);

        $queries = apply_filters('gm2/presets/elementor/queries', []);
        $this->assertArrayHasKey('directory', $queries);

        $queryIds = apply_filters('gm2/presets/elementor/query_ids', []);
        $this->assertArrayHasKey('gm2_directory_nearby', $queryIds);
        $this->assertSame('directory', $queryIds['gm2_directory_nearby']['preset']);

        $seoMappings = apply_filters('gm2/presets/seo/mappings', []);
        $this->assertArrayHasKey('directory', $seoMappings);
        $this->assertArrayHasKey('listing', $seoMappings['directory']);
    }

    public function test_invalid_blueprint_reports_error(): void {
        $root = vfsStream::setup('presets');
        $dir  = vfsStream::newDirectory('broken')->at($root);
        vfsStream::newFile('blueprint.json')->at($dir)->setContent('{"invalid": true}');

        $manager = new PresetManager($root->url(), GM2_PLUGIN_DIR . 'presets/schema.json');
        $errors  = $manager->getErrors();

        $this->assertArrayHasKey('broken', $errors);
        $this->assertInstanceOf(WP_Error::class, $errors['broken']);
    }
}

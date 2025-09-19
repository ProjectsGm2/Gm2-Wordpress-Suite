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
        $this->assertContains('by_category', $elementorKeys, 'Expected Elementor query list to include the "by_category" definition.');

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
        $this->assertArrayHasKey('gm2_directory_by_category', $queryIds);
        $this->assertSame('directory', $queryIds['gm2_directory_by_category']['preset']);

        $seoMappings = apply_filters('gm2/presets/seo/mappings', []);
        $this->assertArrayHasKey('directory', $seoMappings);
        $this->assertArrayHasKey('listing', $seoMappings['directory']);

        $relationships = apply_filters('gm2/presets/relationships', []);
        $this->assertArrayHasKey('directory', $relationships);
        $this->assertIsArray($relationships['directory']);
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

    public function test_restore_defaults_clears_existing_definitions(): void {
        update_option('gm2_custom_posts_config', [
            'post_types' => [ 'example' => [] ],
            'taxonomies' => [ 'genre' => [] ],
            'relationships' => [
                'example_to_genre' => [
                    'type' => 'example_to_genre',
                    'from' => 'example',
                    'to'   => 'genre',
                ],
            ],
        ]);
        update_option('gm2_field_groups', [ 'group' => [ 'title' => 'Group', 'fields' => [] ] ]);
        update_option('gm2_cp_schema_map', [ 'map' => [ 'type' => 'Thing' ] ]);
        update_option('gm2_model_blueprint_meta', [
            'relationships' => [ [ 'from' => 'example', 'to' => 'genre', 'type' => 'taxonomy' ] ],
            'templates'     => [ 'example' => [] ],
            'elementor'     => [
                'queries'   => [ 'sample' => [ 'id' => 'sample' ] ],
                'templates' => [ 'sample' => [] ],
            ],
        ]);

        $manager = new PresetManager(GM2_PLUGIN_DIR . 'presets', GM2_PLUGIN_DIR . 'presets/schema.json');
        $result  = $manager->restoreDefaults();

        $this->assertTrue($result === true);

        $config = get_option('gm2_custom_posts_config');
        $this->assertIsArray($config);
        $this->assertSame([], $config['post_types'] ?? []);
        $this->assertSame([], $config['taxonomies'] ?? []);
        $this->assertSame([], $config['relationships'] ?? []);

        $this->assertSame([], get_option('gm2_field_groups'));
        $this->assertSame([], get_option('gm2_cp_schema_map'));

        $meta = get_option('gm2_model_blueprint_meta');
        $this->assertIsArray($meta);
        $this->assertSame([], $meta['relationships'] ?? []);
        $this->assertSame([], $meta['templates'] ?? []);
        $elementor = $meta['elementor'] ?? [];
        $this->assertIsArray($elementor);
        $this->assertSame([], $elementor['queries'] ?? []);
        $this->assertSame([], $elementor['templates'] ?? []);

        delete_option('gm2_custom_posts_config');
        delete_option('gm2_field_groups');
        delete_option('gm2_cp_schema_map');
        delete_option('gm2_model_blueprint_meta');
    }
}

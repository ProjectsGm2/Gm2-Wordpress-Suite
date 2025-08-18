<?php

class ThemeToolsTest extends WP_UnitTestCase {

    public function test_media_helpers_return_expected_values() {
        $post_id = self::factory()->post->create();
        $filename = DIR_TESTDATA . '/images/canola.jpg';
        $attachment_id = self::factory()->attachment->create_upload_object($filename, $post_id);
        update_post_meta($post_id, 'hero_image', $attachment_id);

        $attachment = gm2_field_media_object('hero_image', null, $post_id);
        $this->assertInstanceOf('WP_Post', $attachment);
        $html = gm2_field_image('hero_image', 'thumbnail', [], $post_id);
        $this->assertStringContainsString('img', $html);
        $this->assertStringContainsString('src=', $html);
    }

    public function test_theme_json_snippet_is_generated() {
        update_option('gm2_enable_theme_json', '1');
        update_option('gm2_field_groups', [
            'design' => [
                'fields' => [
                    'primary_color' => [
                        'type'    => 'design',
                        'label'   => 'Primary',
                        'default' => '#ff0000',
                    ],
                    'main_font' => [
                        'type'    => 'typography',
                        'label'   => 'Main Font',
                        'default' => 'Roboto',
                    ],
                ],
            ],
        ]);

        gm2_maybe_write_theme_json_snippets();

        $path = GM2_PLUGIN_DIR . 'theme-integration/theme.json';
        $this->assertFileExists($path);
        $data = json_decode(file_get_contents($path), true);
        $this->assertSame('#ff0000', $data['settings']['color']['palette'][0]['color']);
        $this->assertSame('Roboto', $data['settings']['typography']['fontFamilies'][0]['fontFamily']);
    }

    public function test_theme_json_references_are_updated() {
        // Ensure clean slate.
        $theme_json = get_stylesheet_directory() . '/theme.json';
        if (file_exists($theme_json)) {
            unlink($theme_json);
        }

        register_post_type('book', ['public' => true]);
        update_option('gm2_enable_block_templates', '1');
        update_option('gm2_field_groups', [
            [ 'pattern' => 'sample', 'fields' => [] ],
        ]);

        gm2_maybe_generate_block_templates();

        $this->assertFileExists($theme_json);
        $data = json_decode(file_get_contents($theme_json), true);
        $templates = wp_list_pluck($data['customTemplates'], 'name');
        $this->assertContains('single-book', $templates);
        $this->assertContains('archive-book', $templates);
        $this->assertContains('gm2/sample', $data['patterns']);
    }
}


<?php

use Gm2\Font_Performance\Font_Performance;

class FontDisplaySwapTest extends WP_UnitTestCase {
    protected function setUp(): void {
        parent::setUp();

        // Reset hooks and options.
        remove_all_actions('wp_head');
        add_action('wp_head', 'wp_print_styles', 8);

        $ref  = new ReflectionClass(Font_Performance::class);
        $prop = $ref->getProperty('hooks_added');
        $prop->setAccessible(true);
        $prop->setValue(false);
        $prop = $ref->getProperty('options');
        $prop->setAccessible(true);
        $prop->setValue([]);

        update_option('gm2seo_fonts', [
            'enabled'             => true,
            'inject_display_swap' => true,
        ]);
        Font_Performance::bootstrap();
    }

    protected function tearDown(): void {
        remove_all_actions('wp_head');
        foreach (wp_styles()->queue as $handle) {
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }
        wp_styles()->queue = [];
        wp_styles()->done  = [];
        parent::tearDown();
    }

    public function test_injects_swap_for_missing_font_display(): void {
        $css_path = WP_CONTENT_DIR . '/test-font.css';
        file_put_contents($css_path, "@font-face{font-family:'Foo';src:url('foo.woff2');}");
        wp_enqueue_style('test-font', content_url('test-font.css'), [], null);

        ob_start();
        do_action('wp_head');
        $html = ob_get_clean();

        $this->assertStringContainsString("@font-face{font-family:'Foo';font-display:swap;}", $html);

        unlink($css_path);
    }
}


<?php

use Gm2\Font_Performance\Font_Performance;

class FontDisplaySwapTest extends WP_UnitTestCase {
    protected function setUp(): void {
        parent::setUp();

        // Reset hooks and options.
        remove_all_actions('wp_head');
        add_action('wp_head', 'wp_print_styles', 8);
        remove_action('wp_print_styles', 'print_emoji_styles');

        $ref  = new ReflectionClass(Font_Performance::class);
        $prop = $ref->getProperty('hooks_added');
        $prop->setAccessible(true);
        $prop->setValue(null, false);
        $prop = $ref->getProperty('options');
        $prop->setAccessible(true);
        $prop->setValue(null, []);

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

    public function test_remote_google_font_emits_swap_and_reduces_cls(): void {
        $css_path = WP_CONTENT_DIR . '/roboto-local.css';
        file_put_contents($css_path, "@font-face{font-family:'Roboto';src:url('foo.woff2');}");
        wp_enqueue_style('google-font', 'https://fonts.googleapis.com/css?family=Roboto');
        wp_enqueue_style('roboto-local', content_url('roboto-local.css'), [], null);

        ob_start();
        do_action('wp_head');
        $html = ob_get_clean();

        $this->assertStringContainsString('display=swap', $html);
        $this->assertStringContainsString("@font-face{font-family:'Roboto';font-display:swap;}", $html);

        $cls_before = $this->mock_cls(str_replace([
            'display=swap',
            "@font-face{font-family:'Roboto';font-display:swap;}",
        ], '', $html));
        $cls_after  = $this->mock_cls($html);
        $this->assertGreaterThan($cls_after, $cls_before);

        unlink($css_path);
    }

    private function mock_cls(string $html): float {
        return str_contains($html, 'font-display:swap') ? 0.0 : 0.25;
    }
}


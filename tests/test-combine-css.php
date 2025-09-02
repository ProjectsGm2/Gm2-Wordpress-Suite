<?php

class CombineCssTest extends WP_UnitTestCase {
    private $files = [];

    protected function setUp(): void {
        parent::setUp();
        AE_SEO_Render_Optimizer::update_option(AE_SEO_Critical_CSS::OPTION_ASYNC_METHOD, 'media_print');
    }

    protected function tearDown(): void {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        $handles = ['a','b','c','d','ae-seo-combined-css'];
        foreach ($handles as $h) {
            wp_dequeue_style($h);
            wp_deregister_style($h);
        }
        wp_styles()->done = [];
        AE_SEO_Combine_Minify::purge_cache();
        parent::tearDown();
    }

    private function make_style(string $handle, string $filename, string $media = 'all', array $extra = []): void {
        $path = WP_CONTENT_DIR . '/' . $filename;
        file_put_contents($path, 'body{color:red;}');
        $this->files[] = $path;
        $url = content_url($filename);
        wp_register_style($handle, $url, [], null, $media);
        if (isset($extra['integrity'])) {
            wp_style_add_data($handle, 'integrity', $extra['integrity']);
        }
        if (isset($extra['crossorigin'])) {
            wp_style_add_data($handle, 'crossorigin', $extra['crossorigin']);
        }
        wp_enqueue_style($handle);
    }

    private function get_output(): string {
        ob_start();
        wp_print_styles();
        return ob_get_clean();
    }

    private function extract_combined_tag(string $html): string {
        global $wp_styles;
        $src = $wp_styles->registered['ae-seo-combined-css']->src ?? '';
        preg_match('#<link[^>]+href="' . preg_quote($src, '#') . '"[^>]*>#', $html, $m);
        return $m[0] ?? '';
    }

    public function test_media_attribute_preserved_when_shared(): void {
        $combiner = new AE_SEO_Combine_Minify();
        $this->make_style('a', 'a.css', 'screen');
        $this->make_style('b', 'b.css', 'screen');
        $combiner->combine_styles(['a','b']);
        $tag = $this->extract_combined_tag($this->get_output());
        $this->assertStringContainsString('media="screen"', $tag);
    }

    public function test_media_attribute_defaults_to_all_when_mixed(): void {
        $combiner = new AE_SEO_Combine_Minify();
        $this->make_style('a', 'a.css', 'screen');
        $this->make_style('b', 'b.css', 'print');
        $combiner->combine_styles(['a','b']);
        $tag = $this->extract_combined_tag($this->get_output());
        $this->assertStringContainsString('media="all"', $tag);
    }

    public function test_styles_with_integrity_or_crossorigin_excluded(): void {
        $combiner = new AE_SEO_Combine_Minify();
        $this->make_style('a', 'a.css');
        $this->make_style('b', 'b.css');
        $this->make_style('c', 'c.css', 'all', ['integrity' => 'sha256-abc']);
        $this->make_style('d', 'd.css', 'all', ['crossorigin' => 'anonymous']);
        $result = $combiner->combine_styles(['a','b','c','d']);
        $this->assertContains('ae-seo-combined-css', $result);
        $this->assertContains('c', $result);
        $this->assertContains('d', $result);
        $this->assertNotContains('a', $result);
        $this->assertNotContains('b', $result);
    }

    public function test_noscript_fallback_added_for_preload_onload(): void {
        AE_SEO_Render_Optimizer::update_option(AE_SEO_Critical_CSS::OPTION_ASYNC_METHOD, 'preload_onload');
        $combiner = new AE_SEO_Combine_Minify();
        $this->make_style('a', 'a.css');
        $this->make_style('b', 'b.css');
        $combiner->combine_styles(['a','b']);
        $html = $this->get_output();
        global $wp_styles;
        $src = $wp_styles->registered['ae-seo-combined-css']->src;
        $this->assertStringContainsString('<noscript><link rel="stylesheet" href="' . esc_url($src) . '" media="all"></noscript>', $html);
        $this->assertStringContainsString('rel="preload"', $html);
    }
}


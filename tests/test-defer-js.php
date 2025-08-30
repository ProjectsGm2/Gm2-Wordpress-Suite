<?php

require_once __DIR__ . '/../includes/render-optimizer/class-ae-seo-defer-js.php';

class DeferJsTest extends WP_UnitTestCase {
    protected function setUp(): void {
        parent::setUp();
        new AE_SEO_Defer_JS();
    }

    protected function tearDown(): void {
        wp_dequeue_script('gm2-foo');
        wp_deregister_script('gm2-foo');
        wp_dequeue_script('gm2-bar');
        wp_deregister_script('gm2-bar');
        wp_dequeue_script('gm2-baz');
        wp_deregister_script('gm2-baz');
        wp_scripts()->done = [];

        delete_option('gm2_defer_js_enabled');
        delete_option('gm2_defer_js_allowlist');
        delete_option('gm2_defer_js_denylist');
        delete_option('gm2_defer_js_overrides');
        delete_option('gm2_script_attributes');
        parent::tearDown();
    }

    private function get_output(string $handle): string {
        ob_start();
        wp_print_scripts($handle);
        return ob_get_clean();
    }

    private function extract_tag(string $html, string $handle): string {
        preg_match("/\<script[^>]*id='" . preg_quote($handle, '/') . "-js'[^>]*>\<\/script>/", $html, $m);
        return $m[0] ?? '';
    }

    public function test_allowlist_restricts_defer() {
        update_option('gm2_defer_js_allowlist', 'gm2-foo');
        wp_register_script('gm2-foo', 'https://example.com/foo.js', [], null);
        wp_register_script('gm2-bar', 'https://example.com/bar.js', [], null);
        wp_enqueue_script('gm2-foo');
        wp_enqueue_script('gm2-bar');
        $html   = $this->get_output('gm2-bar');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $barTag = $this->extract_tag($html, 'gm2-bar');
        $this->assertStringContainsString('defer', $fooTag);
        $this->assertStringNotContainsString('defer', $barTag);
    }

    public function test_override_async_replaces_defer() {
        update_option('gm2_defer_js_overrides', [ 'gm2-foo' => 'async' ]);
        wp_register_script('gm2-foo', 'https://example.com/foo.js', [], null);
        wp_enqueue_script('gm2-foo');
        $html   = $this->get_output('gm2-foo');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringContainsString('async', $fooTag);
        $this->assertStringNotContainsString('defer', $fooTag);
    }

    public function test_dependency_with_blocking_attr_removes_defer() {
        update_option('gm2_script_attributes', [ 'gm2-bar' => 'blocking' ]);
        wp_register_script('gm2-bar', 'https://example.com/bar.js', [], null);
        wp_register_script('gm2-foo', 'https://example.com/foo.js', ['gm2-bar'], null);
        wp_enqueue_script('gm2-foo');
        $html   = $this->get_output('gm2-foo');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringNotContainsString('defer', $fooTag);
    }
}


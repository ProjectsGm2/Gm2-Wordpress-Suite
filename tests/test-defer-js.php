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
        delete_option('ae_seo_ro_defer_allow_domains');
        delete_option('ae_seo_ro_defer_deny_domains');
        delete_option('ae_seo_ro_defer_respect_in_footer');
        delete_option('ae_seo_ro_defer_preserve_jquery');
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

    public function test_domain_allowlist_adds_async_defer() {
        update_option('ae_seo_ro_defer_allow_domains', 'cdn.example.com');
        wp_register_script('gm2-foo', 'https://cdn.example.com/foo.js', [], null);
        wp_enqueue_script('gm2-foo');
        $html   = $this->get_output('gm2-foo');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringContainsString('async', $fooTag);
        $this->assertStringContainsString('defer', $fooTag);
    }

    public function test_domain_denylist_leaves_tag() {
        update_option('ae_seo_ro_defer_deny_domains', 'cdn.example.com');
        wp_register_script('gm2-foo', 'https://cdn.example.com/foo.js', [], null);
        wp_enqueue_script('gm2-foo');
        $html   = $this->get_output('gm2-foo');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringNotContainsString('async', $fooTag);
        $this->assertStringNotContainsString('defer', $fooTag);
    }

    public function test_analytics_domain_gets_async_defer() {
        wp_register_script('gm2-foo', 'https://www.googletagmanager.com/foo.js', [], null);
        wp_enqueue_script('gm2-foo');
        $html   = $this->get_output('gm2-foo');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringContainsString('async', $fooTag);
        $this->assertStringContainsString('defer', $fooTag);
    }

    public function test_analytics_domain_respects_denylist() {
        update_option('ae_seo_ro_defer_deny_domains', 'www.googletagmanager.com');
        wp_register_script('gm2-foo', 'https://www.googletagmanager.com/foo.js', [], null);
        wp_enqueue_script('gm2-foo');
        $html   = $this->get_output('gm2-foo');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringNotContainsString('async', $fooTag);
        $this->assertStringNotContainsString('defer', $fooTag);
    }

    public function test_footer_group_without_allowlist_remains_unchanged_when_respected() {
        update_option('ae_seo_ro_defer_respect_in_footer', '1');
        wp_register_script('gm2-foo', 'https://example.com/foo.js', [], null, true);
        wp_enqueue_script('gm2-foo');
        $html   = $this->get_output('gm2-foo');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringNotContainsString('defer', $fooTag);
    }

    public function test_footer_respect_disables_defer() {
        wp_register_script('gm2-foo', 'https://example.com/foo.js', [], null, true);
        wp_enqueue_script('gm2-foo');
        $html   = $this->get_output('gm2-foo');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringContainsString('defer', $fooTag);
        update_option('ae_seo_ro_defer_respect_in_footer', '1');
        $html   = $this->get_output('gm2-foo');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringNotContainsString('defer', $fooTag);
    }

    public function test_inline_reference_marks_handle_blocking() {
        update_option('ae_seo_ro_defer_respect_in_footer', '1');
        wp_register_script('gm2-foo', 'https://example.com/foo.js', [], null);
        wp_register_script('gm2-bar', 'https://example.com/bar.js', [], null, true);
        wp_add_inline_script('gm2-bar', "console.log('gm2-foo');");
        wp_enqueue_script('gm2-foo');
        wp_enqueue_script('gm2-bar');
        $html   = $this->get_output('gm2-bar');
        $fooTag = $this->extract_tag($html, 'gm2-foo');
        $this->assertStringNotContainsString('defer', $fooTag);
    }

    public function test_head_inline_script_preserves_jquery() {
        wp_deregister_script('jquery');
        wp_register_script('jquery', 'https://example.com/jquery.js', [], null);
        wp_enqueue_script('jquery');
        $cb = function() { echo '<script>jQuery(function(){ console.log("hi"); });</script>'; };
        add_action('wp_head', $cb, 1);
        ob_start();
        do_action('wp_head');
        $html = ob_get_clean();
        remove_action('wp_head', $cb, 1);
        wp_dequeue_script('jquery');
        wp_deregister_script('jquery');
        $tag = $this->extract_tag($html, 'jquery');
        $this->assertStringNotContainsString('defer', $tag);
    }

    public function test_head_inline_script_with_dollar_preserves_jquery() {
        wp_deregister_script('jquery');
        wp_register_script('jquery', 'https://example.com/jquery.js', [], null);
        wp_enqueue_script('jquery');
        $cb = function() { echo '<script>$(function(){ console.log("hi"); });</script>'; };
        add_action('wp_head', $cb, 1);
        ob_start();
        do_action('wp_head');
        $html = ob_get_clean();
        remove_action('wp_head', $cb, 1);
        wp_dequeue_script('jquery');
        wp_deregister_script('jquery');
        $tag = $this->extract_tag($html, 'jquery');
        $this->assertStringNotContainsString('defer', $tag);
    }

    public function test_detection_can_be_disabled() {
        update_option('ae_seo_ro_defer_preserve_jquery', '0');
        wp_deregister_script('jquery');
        wp_register_script('jquery', 'https://example.com/jquery.js', [], null);
        wp_enqueue_script('jquery');
        $cb = function() { echo '<script>jQuery(function(){ console.log("hi"); });</script>'; };
        add_action('wp_head', $cb, 1);
        ob_start();
        do_action('wp_head');
        $html = ob_get_clean();
        remove_action('wp_head', $cb, 1);
        wp_dequeue_script('jquery');
        wp_deregister_script('jquery');
        $tag = $this->extract_tag($html, 'jquery');
        $this->assertStringContainsString('defer', $tag);
    }
}


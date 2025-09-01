<?php

require_once __DIR__ . '/../includes/render-optimizer/class-ae-seo-diff-serving.php';
require_once __DIR__ . '/../includes/render-optimizer/class-ae-seo-defer-js.php';

class DiffServingTest extends WP_UnitTestCase {
    protected function tearDown(): void {
        wp_dequeue_script('ae-seo-optimizer-modern');
        wp_deregister_script('ae-seo-optimizer-modern');
        wp_dequeue_script('ae-seo-optimizer-legacy');
        wp_deregister_script('ae-seo-optimizer-legacy');
        wp_scripts()->done = [];

        delete_option('ae_seo_ro_enable_diff_serving');
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

    public function test_both_tags_emitted_when_enabled() {
        update_option('ae_seo_ro_enable_diff_serving', '1');
        new AE_SEO_Diff_Serving();
        do_action('wp_enqueue_scripts');

        $html      = $this->get_output('ae-seo-optimizer-legacy');
        $modernTag = $this->extract_tag($html, 'ae-seo-optimizer-modern');
        $legacyTag = $this->extract_tag($html, 'ae-seo-optimizer-legacy');

        $this->assertNotEmpty($modernTag);
        $this->assertNotEmpty($legacyTag);
        $this->assertStringContainsString('type="module"', $modernTag);
        $this->assertStringContainsString('nomodule', $legacyTag);
    }

    public function test_only_legacy_bundle_when_disabled() {
        update_option('ae_seo_ro_enable_diff_serving', '0');
        new AE_SEO_Diff_Serving();
        do_action('wp_enqueue_scripts');

        $html      = $this->get_output('ae-seo-optimizer-legacy');
        $modernTag = $this->extract_tag($html, 'ae-seo-optimizer-modern');
        $legacyTag = $this->extract_tag($html, 'ae-seo-optimizer-legacy');

        $this->assertEmpty($modernTag);
        $this->assertNotEmpty($legacyTag);
        $this->assertStringNotContainsString('nomodule', $legacyTag);
    }

    public function test_module_nomodule_never_deferred() {
        update_option('ae_seo_ro_enable_diff_serving', '1');
        new AE_SEO_Diff_Serving();
        new AE_SEO_Defer_JS();
        do_action('wp_enqueue_scripts');

        $html      = $this->get_output('ae-seo-optimizer-legacy');
        $modernTag = $this->extract_tag($html, 'ae-seo-optimizer-modern');
        $legacyTag = $this->extract_tag($html, 'ae-seo-optimizer-legacy');

        $this->assertStringNotContainsString('defer', $modernTag);
        $this->assertStringNotContainsString('defer', $legacyTag);
    }
}


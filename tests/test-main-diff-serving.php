<?php
require_once __DIR__ . '/../includes/class-ae-seo-diff-serving.php';

class MainDiffServingTest extends WP_UnitTestCase {
    protected function tearDown(): void {
        wp_dequeue_script('ae-main-modern');
        wp_deregister_script('ae-main-modern');
        wp_dequeue_script('ae-main-legacy');
        wp_deregister_script('ae-main-legacy');
        wp_dequeue_script('ae-polyfills');
        wp_deregister_script('ae-polyfills');
        wp_scripts()->done = [];
        unset($_COOKIE['ae_js_polyfills']);
        delete_option('ae_js_nomodule_legacy');
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

    public function test_module_and_nomodule_when_enabled() {
        update_option('ae_js_nomodule_legacy', '1');
        new AE_SEO_Main_Diff_Serving();
        do_action('wp_enqueue_scripts');

        $html      = $this->get_output('ae-main-legacy');
        $modernTag = $this->extract_tag($html, 'ae-main-modern');
        $legacyTag = $this->extract_tag($html, 'ae-main-legacy');

        $this->assertNotEmpty($modernTag);
        $this->assertNotEmpty($legacyTag);
        $this->assertStringContainsString('type="module"', $modernTag);
        $this->assertStringContainsString('nomodule', $legacyTag);
    }

    public function test_legacy_skipped_when_disabled() {
        update_option('ae_js_nomodule_legacy', '0');
        new AE_SEO_Main_Diff_Serving();
        do_action('wp_enqueue_scripts');

        $html      = $this->get_output('ae-main-modern');
        $modernTag = $this->extract_tag($html, 'ae-main-modern');
        $legacyTag = $this->extract_tag($html, 'ae-main-legacy');

        $this->assertNotEmpty($modernTag);
        $this->assertEmpty($legacyTag);
    }

    public function test_polyfills_enqueued_when_cookie_set() {
        $_COOKIE['ae_js_polyfills'] = '1';
        new AE_SEO_Main_Diff_Serving();
        do_action('wp_enqueue_scripts');
        $html = $this->get_output('ae-polyfills');
        $tag  = $this->extract_tag($html, 'ae-polyfills');
        $this->assertNotEmpty($tag);
    }

    public function test_polyfills_skipped_when_cookie_zero() {
        $_COOKIE['ae_js_polyfills'] = '0';
        new AE_SEO_Main_Diff_Serving();
        do_action('wp_enqueue_scripts');
        $this->assertFalse(wp_script_is('ae-polyfills', 'enqueued'));
    }
}

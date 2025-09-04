<?php
require_once __DIR__ . '/../includes/class-ae-seo-js-manager.php';

class ThirdPartyFilterTest extends WP_UnitTestCase {
    protected function tearDown(): void {
        wp_dequeue_script('gm2-foo');
        wp_deregister_script('gm2-foo');
        delete_option('gm2_third_party_disabled');
        parent::tearDown();
    }

    public function test_disabled_handle_is_dequeued() {
        update_option('gm2_third_party_disabled', ['gm2-foo']);
        add_filter('gm2_third_party_allowed', ['\\Gm2\\AE_SEO_JS_Manager', 'filter_disabled'], 10, 2);
        wp_register_script('gm2-foo', 'https://example.com/foo.js', [], null);
        wp_enqueue_script('gm2-foo');
        \Gm2\AE_SEO_JS_Manager::audit_third_party();
        $this->assertFalse(wp_script_is('gm2-foo', 'enqueued'));
    }
}

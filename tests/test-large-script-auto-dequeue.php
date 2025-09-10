<?php
require_once __DIR__ . '/../includes/class-ae-seo-js-manager.php';

class LargeScriptAutoDequeueTest extends WP_UnitTestCase {
    protected function tearDown(): void {
        wp_dequeue_script('gm2-large');
        wp_deregister_script('gm2-large');
        delete_option('ae_js_size_threshold');
        delete_option('ae_js_auto_dequeue_large');
        remove_all_filters('pre_http_request');
        parent::tearDown();
    }

    public function test_large_script_auto_dequeued() {
        set_current_screen('front');
        update_option('ae_js_size_threshold', 100); // bytes
        update_option('ae_js_auto_dequeue_large', '1');
        wp_register_script('gm2-large', 'https://example.com/large.js', [], null);
        wp_enqueue_script('gm2-large');
        add_filter('pre_http_request', function($pre, $r, $url) {
            return [
                'headers'  => [ 'content-length' => 200 ],
                'response' => [ 'code' => 200, 'message' => 'OK' ],
                'body'     => '',
            ];
        }, 10, 3);
        \Gm2\AE_SEO_JS_Manager::audit_third_party();
        $this->assertFalse(wp_script_is('gm2-large', 'enqueued'));
    }
}

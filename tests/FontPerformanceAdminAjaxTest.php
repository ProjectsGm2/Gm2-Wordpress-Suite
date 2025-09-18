<?php

use Gm2\Font_Performance\Admin\Font_Performance_Admin;

class FontPerformanceAdminAjaxTest extends WP_Ajax_UnitTestCase {
    public static function set_up_before_class(): void {
        parent::set_up_before_class();

        // Ensure AJAX callbacks are registered for tests.
        if (!class_exists(Font_Performance_Admin::class) && defined('GM2_PLUGIN_DIR')) {
            require_once GM2_PLUGIN_DIR . 'modules/font-performance/admin/class-font-performance-admin.php';
        }
        Font_Performance_Admin::init();
    }

    public function test_detect_variants_denied_without_manage_options(): void {
        $this->_setRole('subscriber');
        $_POST['nonce'] = wp_create_nonce('gm2_font_variants');

        $status = null;
        $callback = static function ($status_header, $code, $description) use (&$status) {
            $status = $code;
            return $status_header;
        };
        add_filter('status_header', $callback, 10, 3);

        try {
            $this->_handleAjax('gm2_detect_font_variants');
        } catch (WPAjaxDieContinueException $e) {
            // Expected due to wp_die in wp_send_json_* functions.
        }

        remove_filter('status_header', $callback, 10);

        $response = json_decode($this->_last_response, true);
        $this->assertSame(403, $status);
        $this->assertFalse($response['success']);
        $this->assertSame('Insufficient permissions.', $response['data']['message']);
    }

    public function test_detect_variants_allowed_for_manage_options(): void {
        $this->_setRole('administrator');
        $_POST['nonce'] = wp_create_nonce('gm2_font_variants');

        try {
            $this->_handleAjax('gm2_detect_font_variants');
        } catch (WPAjaxDieContinueException $e) {
            // Expected due to wp_die in wp_send_json_* functions.
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']);
    }

    public function test_font_size_diff_denied_without_manage_options(): void {
        $this->_setRole('subscriber');
        $_POST['nonce']    = wp_create_nonce('gm2_font_variants');
        $_POST['variants'] = ['400 normal'];

        $status = null;
        $callback = static function ($status_header, $code, $description) use (&$status) {
            $status = $code;
            return $status_header;
        };
        add_filter('status_header', $callback, 10, 3);

        try {
            $this->_handleAjax('gm2_font_size_diff');
        } catch (WPAjaxDieContinueException $e) {
            // Expected due to wp_die in wp_send_json_* functions.
        }

        remove_filter('status_header', $callback, 10);

        $response = json_decode($this->_last_response, true);
        $this->assertSame(403, $status);
        $this->assertFalse($response['success']);
        $this->assertSame('Insufficient permissions.', $response['data']['message']);
    }

    public function test_font_size_diff_allowed_for_manage_options(): void {
        $this->_setRole('administrator');
        $_POST['nonce']    = wp_create_nonce('gm2_font_variants');
        $_POST['variants'] = ['400 normal'];

        try {
            $this->_handleAjax('gm2_font_size_diff');
        } catch (WPAjaxDieContinueException $e) {
            // Expected due to wp_die in wp_send_json_* functions.
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('total', $response['data']);
        $this->assertArrayHasKey('selected', $response['data']);
        $this->assertArrayHasKey('reduction', $response['data']);
    }
}

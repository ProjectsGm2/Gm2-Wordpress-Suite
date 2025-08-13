<?php
namespace Gm2 {
    function check_ajax_referer($action, $query_arg = false) {
        if (!isset($_POST[$query_arg]) || $_POST[$query_arg] !== 'good') {
            throw new \Exception('bad_nonce');
        }
    }
    function wp_send_json_success($data = null) { return ['success'=>true,'data'=>$data]; }
}
namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/../');
    }
    if (!defined('GM2_PLUGIN_DIR')) {
        define('GM2_PLUGIN_DIR', dirname(__DIR__) . '/');
    }
    if (!defined('GM2_PLUGIN_URL')) {
        define('GM2_PLUGIN_URL', '');
    }
    if (!defined('GM2_VERSION')) {
        define('GM2_VERSION', '1.0');
    }
    require_once dirname(__DIR__) . '/includes/Gm2_Analytics.php';
    class AnalyticsNonceTest extends \PHPUnit\Framework\TestCase {
        public function test_rejects_invalid_nonce() {
            $_POST = ['url' => 'https://example.com'];
            $analytics = new \Gm2\Gm2_Analytics();
            $this->expectException(\Exception::class);
            $analytics->ajax_track();
        }
    }
}

<?php
namespace Gm2 {
    function wp_doing_ajax() { return false; }
    function home_url($path = '') { return 'https://example.com' . $path; }
    function esc_url_raw($url) { return $url; }
    function sanitize_text_field($str) { return $str; }
    function wp_unslash($str) { return $str; }
    function wp_is_mobile() { return false; }
    function current_time($type) { return '2024-01-01 00:00:00'; }
    function wp_privacy_anonymize_ip($ip) {
        $parts = explode('.', $ip);
        if (4 === count($parts)) {
            $parts[3] = '0';
            return implode('.', $parts);
        }
        return $ip;
    }
    function check_ajax_referer($action, $query_arg = false) {}
    function wp_send_json_success($data = null) {}
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
    if (!defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 24 * 60 * 60);
    }
    if (!defined('YEAR_IN_SECONDS')) {
        define('YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS);
    }
    if (!defined('COOKIEPATH')) {
        define('COOKIEPATH', '');
    }
    if (!defined('COOKIE_DOMAIN')) {
        define('COOKIE_DOMAIN', '');
    }

    require_once dirname(__DIR__) . '/includes/Gm2_Analytics.php';
}
namespace {
    class WPDBStub {
        public string $prefix = '';
        public array $prepared_args = [];
        public function prepare($query, ...$args) {
            $this->prepared_args = $args;
            return '';
        }
        public function query($query) {}
    }

    class AnalyticsIpAnonymizationTest extends \PHPUnit\Framework\TestCase {
        private WPDBStub $wpdbStub;

        protected function setUp(): void {
            global $wpdb;
            $this->wpdbStub = new WPDBStub();
            $wpdb = $this->wpdbStub;
            $_COOKIE = [];
        }

        public function test_maybe_log_request_anonymizes_ip() {
            $_COOKIE[\Gm2\Gm2_Analytics::COOKIE_NAME] = 'uid';
            $_COOKIE[\Gm2\Gm2_Analytics::SESSION_COOKIE] = 'sid';
            $_SERVER['REQUEST_URI'] = '/test';
            $_SERVER['REMOTE_ADDR'] = '123.123.123.123';
            $_SERVER['HTTP_USER_AGENT'] = 'UA';

            $analytics = new \Gm2\Gm2_Analytics();
            $analytics->maybe_log_request();

            $this->assertSame('123.123.123.0', $this->wpdbStub->prepared_args[7]);
        }

        public function test_log_event_anonymizes_ip() {
            $_COOKIE[\Gm2\Gm2_Analytics::COOKIE_NAME] = 'uid';
            $_COOKIE[\Gm2\Gm2_Analytics::SESSION_COOKIE] = 'sid';
            $_SERVER['REMOTE_ADDR'] = '123.123.123.123';
            $_SERVER['HTTP_USER_AGENT'] = 'UA';

            $analytics = new \Gm2\Gm2_Analytics();
            $_POST = ['url' => 'https://example.com', 'referrer' => '', 'nonce' => 'ok'];
            $analytics->ajax_track();

            $this->assertSame('123.123.123.0', $this->wpdbStub->prepared_args[7]);
        }
    }
}

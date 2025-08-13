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
    function current_user_can($cap) { global $gm2_current_user_can; return $gm2_current_user_can ?? false; }
    function apply_filters($hook, $value) { return $value; }
    function error_log($message) { global $gm2_error_logged; $gm2_error_logged = $message; }
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
        public array $insert_data = [];
        public array $insert_format = [];
        public $insert_result = 1;
        public string $last_error = '';

        public function insert($table, $data, $format) {
            $this->insert_data  = $data;
            $this->insert_format = $format;
            return $this->insert_result;
        }

        public function prepare($query, ...$args) {}
        public function query($query) {}
    }

    class AnalyticsTest extends \PHPUnit\Framework\TestCase {
        private WPDBStub $wpdbStub;

        protected function setUp(): void {
            global $wpdb, $gm2_current_user_can;
            $this->wpdbStub = new WPDBStub();
            $wpdb = $this->wpdbStub;
            $_COOKIE = [];
            unset($_SERVER['HTTP_DNT']);
            $gm2_current_user_can = false;
        }

        public function test_maybe_log_request_anonymizes_ip() {
            $_COOKIE[\Gm2\Gm2_Analytics::COOKIE_NAME] = 'uid';
            $_COOKIE[\Gm2\Gm2_Analytics::SESSION_COOKIE] = 'sid';
            $_SERVER['REQUEST_URI'] = '/test';
            $_SERVER['REMOTE_ADDR'] = '123.123.123.123';
            $_SERVER['HTTP_USER_AGENT'] = 'UA';

            $analytics = new \Gm2\Gm2_Analytics();
            $analytics->maybe_log_request();

            $this->assertSame('123.123.123.0', $this->wpdbStub->insert_data['ip']);
        }

        public function test_log_event_anonymizes_ip() {
            $_COOKIE[\Gm2\Gm2_Analytics::COOKIE_NAME] = 'uid';
            $_COOKIE[\Gm2\Gm2_Analytics::SESSION_COOKIE] = 'sid';
            $_SERVER['REMOTE_ADDR'] = '123.123.123.123';
            $_SERVER['HTTP_USER_AGENT'] = 'UA';

            $analytics = new \Gm2\Gm2_Analytics();
            $_POST = ['url' => 'https://example.com', 'referrer' => '', 'nonce' => 'ok'];
            $analytics->ajax_track();

            $this->assertSame('123.123.123.0', $this->wpdbStub->insert_data['ip']);
        }

        public function test_maybe_log_request_skips_for_admin() {
            global $gm2_current_user_can;
            $gm2_current_user_can = true;
            $_COOKIE[\Gm2\Gm2_Analytics::COOKIE_NAME] = 'uid';
            $_COOKIE[\Gm2\Gm2_Analytics::SESSION_COOKIE] = 'sid';
            $_SERVER['REQUEST_URI'] = '/test';
            $_SERVER['REMOTE_ADDR'] = '123.123.123.123';
            $_SERVER['HTTP_USER_AGENT'] = 'UA';

            $analytics = new \Gm2\Gm2_Analytics();
            $analytics->maybe_log_request();

            $this->assertEmpty($this->wpdbStub->insert_data);
        }

        public function test_maybe_log_request_skips_for_bots() {
            $_COOKIE[\Gm2\Gm2_Analytics::COOKIE_NAME] = 'uid';
            $_COOKIE[\Gm2\Gm2_Analytics::SESSION_COOKIE] = 'sid';
            $_SERVER['REQUEST_URI'] = '/test';
            $_SERVER['REMOTE_ADDR'] = '123.123.123.123';
            $_SERVER['HTTP_USER_AGENT'] = 'Googlebot';

            $analytics = new \Gm2\Gm2_Analytics();
            $analytics->maybe_log_request();

            $this->assertEmpty($this->wpdbStub->insert_data);
        }

        public function test_maybe_log_request_skips_for_dnt_header() {
            $_COOKIE[\Gm2\Gm2_Analytics::COOKIE_NAME] = 'uid';
            $_COOKIE[\Gm2\Gm2_Analytics::SESSION_COOKIE] = 'sid';
            $_SERVER['REQUEST_URI'] = '/test';
            $_SERVER['REMOTE_ADDR'] = '123.123.123.123';
            $_SERVER['HTTP_USER_AGENT'] = 'UA';
            $_SERVER['HTTP_DNT'] = '1';

            $analytics = new \Gm2\Gm2_Analytics();
            $analytics->maybe_log_request();

            $this->assertEmpty($this->wpdbStub->insert_data);
        }

        public function test_insert_failure_logs_error() {
            global $gm2_error_logged;
            $_COOKIE[\Gm2\Gm2_Analytics::COOKIE_NAME] = 'uid';
            $_COOKIE[\Gm2\Gm2_Analytics::SESSION_COOKIE] = 'sid';
            $_SERVER['REQUEST_URI'] = '/test';
            $_SERVER['REMOTE_ADDR'] = '123.123.123.123';
            $_SERVER['HTTP_USER_AGENT'] = 'UA';

            $this->wpdbStub->insert_result = false;
            $this->wpdbStub->last_error    = 'insert failed';

            $analytics = new \Gm2\Gm2_Analytics();
            $analytics->maybe_log_request();

            $this->assertSame('insert failed', $gm2_error_logged);
        }
    }
}

<?php
namespace Gm2 {
    // Use WordPress's wp_verify_nonce if available; otherwise, a simple stub.
    if (!function_exists('wp_verify_nonce')) {
        function wp_verify_nonce($nonce, $action = -1) {
            return $nonce === 'good';
        }
    }
}

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/../');
    }
    require_once dirname(__DIR__) . '/admin/Gm2_Analytics_Admin.php';

    use Gm2\Gm2_Analytics_Admin;

    class ActivityLogNonceTest extends \PHPUnit\Framework\TestCase {
        private $orig_get;

        protected function setUp(): void {
            $this->orig_get = $_GET;
        }

        protected function tearDown(): void {
            $_GET = $this->orig_get;
        }

        public function test_invalid_nonce_results_in_no_output() {
            $_GET['log_user'] = '1';
            $_GET['gm2_activity_log_nonce'] = 'bad';
            $admin = new Gm2_Analytics_Admin();
            $ref = new \ReflectionClass(Gm2_Analytics_Admin::class);
            $method = $ref->getMethod('render_activity_log');
            $method->setAccessible(true);
            ob_start();
            $method->invoke($admin, ['start' => '2024-01-01', 'end' => '2024-01-02']);
            $output = ob_get_clean();
            $this->assertSame('', $output);
        }
    }
}

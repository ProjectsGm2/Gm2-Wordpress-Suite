<?php
namespace Gm2 {
    if (!function_exists(__NAMESPACE__ . '\\current_user_can')) {
        function current_user_can( $cap ) { return true; }
    }
    if (!function_exists(__NAMESPACE__ . '\\check_ajax_referer')) {
        function check_ajax_referer( $action, $query_arg = false ) { return true; }
    }
    if (!function_exists(__NAMESPACE__ . '\\wp_send_json_success')) {
        function wp_send_json_success( $data = null ) { $GLOBALS['gm2_ajax_success'] = true; return ['success'=>true]; }
    }
    if (!function_exists(__NAMESPACE__ . '\\wp_send_json_error')) {
        function wp_send_json_error( $data = null ) { $GLOBALS['gm2_ajax_success'] = false; return ['success'=>false]; }
    }
}
namespace {
    use Tests\Phpunit\BrainMonkeyTestCase;

    if ( ! defined( 'ABSPATH' ) ) {
        define( 'ABSPATH', __DIR__ . '/../' );
    }
    if ( ! defined( 'GM2_PLUGIN_DIR' ) ) {
        define( 'GM2_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
    }
    if ( ! defined( 'GM2_PLUGIN_URL' ) ) {
        define( 'GM2_PLUGIN_URL', '' );
    }
    if ( ! defined( 'GM2_VERSION' ) ) {
        define( 'GM2_VERSION', '1.0' );
    }

    require_once dirname( __DIR__ ) . '/admin/Gm2_Abandoned_Carts_Admin.php';

    class AbandonedCartProcessTest extends BrainMonkeyTestCase {
        public function test_ajax_process_triggers_cron() {
            $_POST['nonce'] = 'n';
            $mock = \Mockery::mock('alias:Gm2\\Gm2_Abandoned_Carts');
            $mock->shouldReceive('cron_mark_abandoned')->once();
            $admin = new \Gm2\Gm2_Abandoned_Carts_Admin();
            $admin->ajax_process();
            $this->assertTrue( $GLOBALS['gm2_ajax_success'] );
        }
    }
}

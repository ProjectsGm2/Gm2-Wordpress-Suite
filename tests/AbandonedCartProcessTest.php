<?php
namespace Gm2 {
    function current_user_can( $cap ) { return true; }
    function check_ajax_referer( $action, $query_arg = false ) { return true; }
    function wp_send_json_success( $data = null ) { $GLOBALS['gm2_ajax_success'] = true; return ['success'=>true]; }
    function wp_send_json_error( $data = null ) { $GLOBALS['gm2_ajax_success'] = false; return ['success'=>false]; }
    class Gm2_Abandoned_Carts {
        public static $called = false;
        public static function cron_mark_abandoned() { self::$called = true; }
    }
}
namespace {
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

    class AbandonedCartProcessTest extends \PHPUnit\Framework\TestCase {
        public function test_ajax_process_triggers_cron() {
            $_POST['nonce'] = 'n';
            $admin = new \Gm2\Gm2_Abandoned_Carts_Admin();
            $admin->ajax_process();
            $this->assertTrue( \Gm2\Gm2_Abandoned_Carts::$called );
            $this->assertTrue( $GLOBALS['gm2_ajax_success'] );
        }
    }
}

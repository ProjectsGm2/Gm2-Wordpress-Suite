<?php
namespace Gm2;

function wp_json_encode( $data ) { return json_encode( $data ); }

class Gm2_Cache_Headers_Apache {
    public static $removed = false;
    public static function maybe_apply() { return $GLOBALS['apache_result']; }
    public static function remove_rules() { self::$removed = true; }
}

class Gm2_Cache_Headers_Nginx {
    public static function maybe_apply() { return $GLOBALS['nginx_result']; }
    public static function get_file_path() { return $GLOBALS['nginx_result']['file'] ?? '/tmp/nginx.conf'; }
}

function file_exists( $file ) { return $GLOBALS['gm2_file_exists'] ?? false; }
function unlink( $file ) { return $GLOBALS['gm2_unlink'] ?? true; }
?>
<?php
namespace {
use PHPUnit\Framework\TestCase;

define( 'WP_CLI', true );

if ( ! class_exists( 'WP_CLI' ) ) {
    class WP_CLI {
        public static $lines = [];
        public static $confirmations = [];
        public static function error( $msg, $code = 1 ) { throw new \Exception( $msg, $code ); }
        public static function success( $msg ) { self::$lines[] = $msg; }
        public static function warning( $msg ) { self::$lines[] = $msg; }
        public static function line( $msg ) { self::$lines[] = $msg; }
        public static function confirm( $msg, $assoc_args = [] ) { self::$confirmations[] = $msg; }
        public static function add_command( $name, $callable ) {}
    }
}
if ( ! class_exists( 'WP_CLI_Command' ) ) {
    class WP_CLI_Command {}
}

class WP_Error {
    protected $code;
    protected $msg;
    public function __construct( $code = 0, $msg = '' ) { $this->code = $code; $this->msg = $msg; }
    public function get_error_message() { return $this->msg; }
    public function get_error_code() { return $this->code; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

$GLOBALS['gm2_ai_response'] = [];
function gm2_ai_send_prompt( $prompt ) { return $GLOBALS['gm2_ai_response']; }

class SeoPerfCliTest extends TestCase {
    protected function setUp(): void {
        WP_CLI::$lines = [];
        if ( property_exists( WP_CLI::class, 'confirmations' ) ) {
            WP_CLI::$confirmations = [];
        }
        $GLOBALS['apache_result'] = [ 'status' => 'written' ];
        $GLOBALS['nginx_result']  = [ 'status' => 'written', 'file' => '/tmp/nginx.conf' ];
        $GLOBALS['gm2_ai_response'] = [ 'ok' => true ];
        $GLOBALS['gm2_file_exists'] = false;
        $GLOBALS['gm2_unlink'] = true;
        \Gm2\Gm2_Cache_Headers_Apache::$removed = false;
        if ( ! class_exists( '\\Gm2\\Gm2_SEO_Perf_CLI' ) ) {
            require __DIR__ . '/../../includes/cli/class-gm2-seo-perf-cli.php';
        }
    }

    public function test_audit_success() {
        $cli = new \Gm2\Gm2_SEO_Perf_CLI();
        $cli->audit( [], [] );
        $this->assertNotEmpty( WP_CLI::$lines );
    }

    public function test_audit_failure() {
        $GLOBALS['gm2_ai_response'] = new WP_Error( 5, 'bad' );
        $cli = new \Gm2\Gm2_SEO_Perf_CLI();
        $this->expectException( \Exception::class );
        $this->expectExceptionCode( 5 );
        $cli->audit( [], [] );
    }

    public function test_apply_htaccess_success() {
        $cli = new \Gm2\Gm2_SEO_Perf_CLI();
        $cli->apply_htaccess( [], [] );
        $this->assertContains( 'Cache headers written to .htaccess.', WP_CLI::$lines );
    }

    public function test_apply_htaccess_not_writable() {
        $GLOBALS['apache_result'] = [ 'status' => 'not_writable' ];
        $cli = new \Gm2\Gm2_SEO_Perf_CLI();
        $this->expectException( \Exception::class );
        $this->expectExceptionCode( 3 );
        $cli->apply_htaccess( [], [] );
    }

    public function test_generate_nginx_success() {
        $cli = new \Gm2\Gm2_SEO_Perf_CLI();
        $cli->generate_nginx( [], [] );
        $this->assertContains( '/tmp/nginx.conf', WP_CLI::$lines );
    }

    public function test_generate_nginx_not_writable() {
        $GLOBALS['nginx_result'] = [ 'status' => 'not_writable', 'file' => '/tmp/nginx.conf' ];
        $cli = new \Gm2\Gm2_SEO_Perf_CLI();
        $this->expectException( \Exception::class );
        $this->expectExceptionCode( 3 );
        $cli->generate_nginx( [], [] );
    }

    public function test_clear_markers_success() {
        $cli = new \Gm2\Gm2_SEO_Perf_CLI();
        $cli->clear_markers( [], [] );
        $this->assertTrue( \Gm2\Gm2_Cache_Headers_Apache::$removed );
    }

    public function test_clear_markers_unlink_failure() {
        $GLOBALS['gm2_file_exists'] = true;
        $GLOBALS['gm2_unlink'] = false;
        $cli = new \Gm2\Gm2_SEO_Perf_CLI();
        $this->expectException( \Exception::class );
        $this->expectExceptionCode( 1 );
        $cli->clear_markers( [], [] );
    }
}

class SeoPerfCliMultisiteTest extends TestCase {
    /**
     * @runInSeparateProcess
     */
    public function test_commands_run_under_multisite() {
        define( 'MULTISITE', true );
        WP_CLI::$lines = [];
        $GLOBALS['apache_result'] = [ 'status' => 'written' ];
        $GLOBALS['nginx_result']  = [ 'status' => 'written', 'file' => '/tmp/nginx.conf' ];
        $GLOBALS['gm2_ai_response'] = [ 'ok' => true ];
        $GLOBALS['gm2_file_exists'] = false;
        $GLOBALS['gm2_unlink'] = true;
        \Gm2\Gm2_Cache_Headers_Apache::$removed = false;
        require __DIR__ . '/../../includes/cli/class-gm2-seo-perf-cli.php';
        $cli = new \Gm2\Gm2_SEO_Perf_CLI();
        $cli->audit( [], [] );
        $cli->apply_htaccess( [], [] );
        $cli->generate_nginx( [], [] );
        $cli->clear_markers( [], [] );
        $this->assertTrue( true );
    }
}
}

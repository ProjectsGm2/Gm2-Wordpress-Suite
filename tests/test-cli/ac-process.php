<?php
// Minimal stub to verify the abandoned carts CLI processor.

define( 'WP_CLI', true );

define( 'GM2_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );

class WP_CLI {
    public static function error( $msg ) { throw new Exception( $msg ); }
    public static function success( $msg ) { echo $msg, "\n"; }
    public static function add_command( $name, $callable ) {}
}
class WP_CLI_Command {}

namespace Gm2 {
    class Gm2_Abandoned_Carts {
        public static $called = false;
        public static function cron_mark_abandoned() { self::$called = true; }
        public function migrate_recovered_carts() { return 0; }
    }
}

require dirname( __DIR__, 2 ) . '/includes/cli/class-gm2-cli.php';

$cli = new \Gm2\Gm2_CLI();
$cli->ac( [ 'process' ], [] );
if ( ! \Gm2\Gm2_Abandoned_Carts::$called ) {
    throw new Exception( 'cron_mark_abandoned not called' );
}

echo "abandoned carts CLI process test completed\n";

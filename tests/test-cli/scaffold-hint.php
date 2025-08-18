<?php
// Minimal stubs to test scaffold hint output.

define('WP_CLI', true);
define('GM2_PLUGIN_DIR', dirname(__DIR__, 2) . '/');

if (!class_exists('WP_CLI')) {
    class WP_CLI {
        public static function error($msg) { throw new Exception($msg); }
        public static function success($msg) { echo $msg, "\n"; }
        public static function warning($msg) { echo $msg, "\n"; }
        public static function line($msg) { echo $msg, "\n"; }
        public static function add_command($name, $callable) {}
    }
}
if (!class_exists('WP_CLI_Command')) {
    class WP_CLI_Command {}
}

function trailingslashit($p) { return rtrim($p, '/\\') . '/'; }
function get_stylesheet_directory() { return sys_get_temp_dir() . '/gm2-cli-scaffold'; }
function wp_mkdir_p($dir) { if (!is_dir($dir)) { mkdir($dir, 0777, true); } }

require dirname(__DIR__, 2) . '/includes/cli/class-gm2-cli.php';

$dir = get_stylesheet_directory();
wp_mkdir_p($dir);
@unlink(trailingslashit($dir) . 'theme.json');

$cli = new \Gm2\Gm2_CLI();
ob_start();
$cli->scaffold(['theme-json', 'book'], []);
$output = ob_get_clean();
if (strpos($output, 'customTemplates') === false || strpos($output, 'gm2/book') === false) {
    throw new Exception('CLI scaffold hint missing.');
}

echo "CLI scaffold hint test completed\n";

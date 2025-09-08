<?php
declare(strict_types=1);

namespace {
// Minimal environment to exercise AE CSS CLI commands.

define('WP_CLI', true);
define('ABSPATH', __DIR__);
define('GM2_PLUGIN_DIR', dirname(__DIR__, 2) . '/');

define('MINUTE_IN_SECONDS', 60);

// Collect notices/warnings as exceptions.
set_error_handler(function ($errno, $errstr) {
    if (in_array($errno, [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE], true)) {
        throw new \Exception($errstr);
    }
    return false;
});

class WP_CLI {
    public static $commands = [];
    public static function add_command($name, $callable) { self::$commands[$name] = $callable; }
    public static function line($msg) { echo $msg, "\n"; }
    public static function success($msg) { echo $msg, "\n"; }
    public static function warning($msg) { echo $msg, "\n"; }
    public static function error($msg) { throw new \Exception($msg); }
    public static function runcommand($command) {
        $parts = preg_split('/\s+/', trim($command));
        $cmd = array_shift($parts);
        $sub = str_replace('-', '_', array_shift($parts) ?? '');
        $args = [];
        $assoc = [];
        foreach ($parts as $part) {
            if (strpos($part, '--') === 0) {
                $arg = substr($part, 2);
                if (strpos($arg, '=') !== false) {
                    [$k, $v] = explode('=', $arg, 2);
                    $assoc[$k] = $v;
                } else {
                    $assoc[$arg] = true;
                }
            } else {
                $args[] = $part;
            }
        }
        $class = self::$commands[$cmd] ?? null;
        if (!$class) {
            throw new \Exception("Command {$cmd} not registered");
        }
        $instance = is_string($class) ? new $class() : $class;
        if (!method_exists($instance, $sub)) {
            throw new \Exception("Subcommand {$sub} not found");
        }
        $instance->$sub($args, $assoc);
    }
}
class WP_CLI_Command {}

// Option storage.
$GLOBALS['gm2_options'] = [];
function get_option($name, $default = []) { return $GLOBALS['gm2_options'][$name] ?? $default; }
function update_option($name, $value, $autoload = false) { $GLOBALS['gm2_options'][$name] = $value; return true; }
function delete_option($name) { unset($GLOBALS['gm2_options'][$name]); return true; }

function __($text, $domain = null) { return $text; }
function esc_url_raw($url) { return $url; }
function get_stylesheet_directory() { return sys_get_temp_dir(); }
function trailingslashit($str) { return rtrim($str, '/').'/'; }
function wp_next_scheduled($hook) { return false; }
function wp_schedule_event($timestamp, $recurrence, $hook) { return true; }
function wp_schedule_single_event($timestamp, $hook) { return true; }
}

namespace AE\CSS {
    class AE_CSS_Optimizer {
        private static $instance; public static function get_instance() { return self::$instance ?? (self::$instance = new self()); }
        public function mark_url_for_critical_generation($url) { return true; }
        public static function purgecss_analyze($css, $html, $safelist) { return true; }
        public function cron_run_purgecss($payload) { return true; }
        public function process_critical_job($payload) { return true; }
    }
}

namespace {
require GM2_PLUGIN_DIR . 'includes/class-ae-css-queue.php';
require GM2_PLUGIN_DIR . 'includes/cli/class-ae-css-cli.php';

// Execute commands; any notice will throw an exception via the error handler.
\WP_CLI::runcommand('ae-css status');
\WP_CLI::runcommand('ae-css generate --url=https://example.com');
\WP_CLI::runcommand('ae-css purge --theme');
\WP_CLI::runcommand('ae-css refresh-snapshots');

echo "ae-css CLI tests completed\n";
}

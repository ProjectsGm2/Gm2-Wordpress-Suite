<?php
namespace Gm2 {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
}

namespace {
    if (!function_exists('dbDelta')) {
        function dbDelta($sql) {
            global $wpdb;
            if (preg_match('/CREATE TABLE\s+(\w+)/', $sql, $m)) {
                $wpdb->tables[] = $m[1];
            }
        }
    }
    if (!class_exists('WP_UnitTestCase')) {
        abstract class WP_UnitTestCase extends \PHPUnit\Framework\TestCase {}
    }
    if (!defined('ABSPATH')) {
        define('ABSPATH', sys_get_temp_dir() . '/');
    }
    require_once dirname(__DIR__) . '/includes/Gm2_Abandoned_Carts.php';

    use Gm2\Gm2_Abandoned_Carts;

    class ActivityTableInstallTest extends WP_UnitTestCase {
    private $orig_wpdb;
    private $upgrade_file;
    private $created_upgrade = false;

    protected function setUp(): void {
        parent::setUp();
        $this->orig_wpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = new FakeMaybeInstallDB();
        $root = defined('ABSPATH') ? ABSPATH : dirname(__DIR__) . '/';
        if (!defined('ABSPATH')) {
            define('ABSPATH', $root);
        }
        $path = $root . 'wp-admin/includes';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
            $this->created_upgrade = true;
        }
        $this->upgrade_file = $path . '/upgrade.php';
        if (!file_exists($this->upgrade_file)) {
            file_put_contents($this->upgrade_file, "<?php\n");
            $this->created_upgrade = true;
        }
    }

    protected function tearDown(): void {
        if ($this->created_upgrade && file_exists($this->upgrade_file)) {
            unlink($this->upgrade_file);
            @rmdir(dirname($this->upgrade_file));
            @rmdir(dirname(dirname($this->upgrade_file)));
        }
        $GLOBALS['wpdb'] = $this->orig_wpdb;
        parent::tearDown();
    }

    public function test_activity_table_created_when_missing() {
        $ac = new Gm2_Abandoned_Carts();
        $ref = new \ReflectionClass(Gm2_Abandoned_Carts::class);
        $method = $ref->getMethod('maybe_install');
        $method->setAccessible(true);
        $method->invoke($ac);
        $activity_table = $GLOBALS['wpdb']->prefix . 'wc_ac_cart_activity';
        $this->assertContains($activity_table, $GLOBALS['wpdb']->tables);
    }
    }

    class FakeMaybeInstallDB {
    public $prefix = 'wp_';
    public $tables;
    public function __construct() {
        $this->tables = [
            $this->prefix . 'wc_ac_carts',
            $this->prefix . 'wc_ac_email_queue',
            $this->prefix . 'wc_ac_recovered'
        ];
    }
    public function prepare($query, $arg) {
        return str_replace('%s', "'" . $arg . "'", $query);
    }
    public function get_var($query) {
        if (preg_match("/SHOW TABLES LIKE '([^']+)'/", $query, $m)) {
            return in_array($m[1], $this->tables, true) ? $m[1] : null;
        }
        return null;
    }
    public function get_charset_collate() {
        return '';
    }
        public function query($sql) {
            // not needed for this test
        }
    }
}

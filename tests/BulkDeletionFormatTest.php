<?php
namespace Gm2;

use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
if (!class_exists('WP_List_Table')) {
    class WP_List_Table {
        public function __construct($args = []) {}
    }
}
require_once __DIR__ . '/../admin/class-gm2-ac-table.php';

class BulkDeletionFormatTest extends TestCase {
    private $orig_wpdb;
    protected function setUp(): void {
        parent::setUp();
        $this->orig_wpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = new FakeDB();
        $_REQUEST['id'] = [1,2,3];
    }
    protected function tearDown(): void {
        $GLOBALS['wpdb'] = $this->orig_wpdb;
        parent::tearDown();
    }
    public function test_bulk_delete_uses_prepared_formats() {
        $table = new class extends GM2_AC_Table {
            public function current_action() { return 'delete'; }
        };
        $table->process_bulk_action();
        $this->assertSame('DELETE FROM wp_wc_ac_carts WHERE id IN (%d,%d,%d)', $GLOBALS['wpdb']->last_prepare_query);
        $this->assertSame([1,2,3], $GLOBALS['wpdb']->last_prepare_args);
    }
}

class FakeDB {
    public $prefix = 'wp_';
    public $last_prepare_query;
    public $last_prepare_args;
    public function prepare($query, ...$args) {
        $this->last_prepare_query = $query;
        $this->last_prepare_args = $args;
        return $query;
    }
    public function query($sql) {
    }
}

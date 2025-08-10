<?php
namespace {
use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
if (!class_exists('WP_List_Table')) {
    class WP_List_Table {
        public $items = [];
        public function __construct($args = []) {}
        protected function get_items_per_page($opt, $default) { return $default; }
        protected function get_pagenum() { return 1; }
        protected function set_pagination_args($args) {}
    }
}
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) { return $value; }
}
if (!function_exists('get_option')) {
    function get_option($name, $default = false) { return $default; }
}
if (!function_exists('absint')) {
    function absint($value) { return (int) abs($value); }
}
if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($data) { return $data; }
}
if (!function_exists('wc_get_product')) {
    function wc_get_product($id) { return null; }
}

class GroupedDB {
    public $prefix = 'wp_';
    public $data = [];
    public function __construct() {
        $this->data[$this->prefix.'wc_ac_carts'] = [
            ['id'=>1,'ip_address'=>'1.1.1.1','cart_contents'=>'[]','created_at'=>'2024-01-01 00:00:00','revisit_count'=>1,'browsing_time'=>30,'email'=>'','location'=>'','device'=>'','browser'=>'','entry_url'=>'','exit_url'=>'','cart_total'=>0,'abandoned_at'=>null],
            ['id'=>2,'ip_address'=>'1.1.1.1','cart_contents'=>'[]','created_at'=>'2024-01-02 00:00:00','revisit_count'=>2,'browsing_time'=>40,'email'=>'','location'=>'','device'=>'','browser'=>'','entry_url'=>'','exit_url'=>'','cart_total'=>0,'abandoned_at'=>null],
            ['id'=>3,'ip_address'=>'2.2.2.2','cart_contents'=>'[]','created_at'=>'2024-01-03 00:00:00','revisit_count'=>1,'browsing_time'=>20,'email'=>'','location'=>'','device'=>'','browser'=>'','entry_url'=>'','exit_url'=>'','cart_total'=>0,'abandoned_at'=>null],
        ];
    }
    public function prepare($query, ...$args) { return $query; }
    public function esc_like($text) { return $text; }
    public function get_var($query) {
        return 2; // distinct IPs
    }
    public function get_results($query) {
        $table = $this->data[$this->prefix.'wc_ac_carts'];
        $grouped = [];
        foreach ($table as $row) {
            $ip = $row['ip_address'];
            if (!isset($grouped[$ip])) {
                $grouped[$ip] = $row;
                $grouped[$ip]['total_revisit_count'] = $row['revisit_count'];
                $grouped[$ip]['total_browsing_time'] = $row['browsing_time'];
            } else {
                if ($row['created_at'] > $grouped[$ip]['created_at']) {
                    $grouped[$ip] = $row + ['total_revisit_count'=>$grouped[$ip]['total_revisit_count'] + $row['revisit_count'], 'total_browsing_time'=>$grouped[$ip]['total_browsing_time'] + $row['browsing_time']];
                } else {
                    $grouped[$ip]['total_revisit_count'] += $row['revisit_count'];
                    $grouped[$ip]['total_browsing_time'] += $row['browsing_time'];
                }
            }
        }
        // order by created_at DESC
        usort($grouped, function($a,$b){ return strcmp($b['created_at'],$a['created_at']); });
        return array_map(fn($r)=>(object)$r, $grouped);
    }
}

require_once __DIR__.'/../admin/class-gm2-ac-table.php';

final class GroupedQueryTest extends TestCase {
    public function test_prepare_items_groups_by_ip() {
        $db = new GroupedDB();
        $GLOBALS['wpdb'] = $db;
        $table = new \Gm2\GM2_AC_Table(['table'=>'wc_ac_carts']);
        $table->prepare_items();
        $this->assertCount(2, $table->items);
        $ips = array_map(fn($i)=>$i['ip_address'], $table->items);
        $this->assertSame(['2.2.2.2','1.1.1.1'], $ips);
        $revisits = array_column($table->items, 'revisit_count');
        $this->assertSame([1,3], $revisits);
    }
}
}

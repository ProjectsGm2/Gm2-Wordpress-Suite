<?php
namespace Gm2 {
    // ensure constants
    if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
        define( 'MINUTE_IN_SECONDS', 60 );
    }
}
namespace {
use Tests\Phpunit\BrainMonkeyTestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
if (!function_exists('get_transient')) {
    $GLOBALS['transients'] = [];
    function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
    function set_transient($key,$value,$expire){ $GLOBALS['transients'][$key]=$value; return true; }
    function delete_transient($key){ unset($GLOBALS['transients'][$key]); return true; }
}
if (!function_exists('add_action')) {
    function add_action($hook, $cb) {}
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
require_once dirname(__DIR__) . '/admin/Gm2_Abandoned_Carts_Admin.php';
class SummaryDB {
    public $prefix = 'wp_';
    public $carts;
    public $recovered;
    public function __construct(){
        $this->carts = [
            ['abandoned_at'=>null,'recovered_order_id'=>null,'cart_total'=>10],
            ['abandoned_at'=>'2024-01-01 00:00:00','recovered_order_id'=>null,'cart_total'=>20],
        ];
        $this->recovered = [
            ['cart_total'=>15],
            ['cart_total'=>25],
        ];
    }
    public function get_row($sql, $output = ARRAY_A){
        if (strpos($sql,'wc_ac_carts') !== false){
            $total = count($this->carts);
            $pending = 0; $abandoned = 0; $potential = 0;
            foreach($this->carts as $row){
                $potential += $row['cart_total'];
                if ($row['abandoned_at'] === null && empty($row['recovered_order_id'])) { $pending++; }
                if ($row['abandoned_at'] !== null) { $abandoned++; }
            }
            return ['total'=>$total,'pending'=>$pending,'abandoned'=>$abandoned,'potential_revenue'=>$potential];
        }
        if (strpos($sql,'wc_ac_recovered') !== false){
            $recovered = count($this->recovered);
            $rev = 0;
            foreach($this->recovered as $r){ $rev += $r['cart_total']; }
            return ['recovered'=>$recovered,'recovered_revenue'=>$rev];
        }
        return [];
    }
}
final class AbandonedCartSummaryTest extends BrainMonkeyTestCase {
    public function test_summary_calculations(){
        $db = new SummaryDB();
        $GLOBALS['wpdb'] = $db;
        $admin = new \Gm2\Gm2_Abandoned_Carts_Admin();
        $data = $admin->refresh_summary();
        $this->assertSame(4, $data['total']);
        $this->assertSame(1, $data['pending']);
        $this->assertSame(1, $data['abandoned']);
        $this->assertSame(2, $data['recovered']);
        $this->assertSame(30.0, $data['potential_revenue']);
        $this->assertSame(40.0, $data['recovered_revenue']);
    }
}
}

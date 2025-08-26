<?php
namespace Gm2 {
    function check_ajax_referer($action, $query_arg = false, $die = true) { return true; }
    function wp_send_json_success($data = null) { $GLOBALS['gm2_ajax_data'] = ['success'=>true,'data'=>$data]; return $GLOBALS['gm2_ajax_data']; }
    function wp_send_json_error($data = null) { $GLOBALS['gm2_ajax_data'] = ['success'=>false,'data'=>$data]; return $GLOBALS['gm2_ajax_data']; }
    function current_user_can($cap = '') { return true; }
    function sanitize_text_field($str) { return $str; }
    function wp_unslash($v) { return $v; }
    function absint($v) { return abs((int)$v); }
    function mysql2date($f, $d) { return $d; }
    function get_option($n) { return 'Y-m-d H:i:s'; }
    function add_action($hook, $callback, $priority = 10, $args = 1) {}
}

namespace {
    if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/../'); }
    require_once __DIR__ . '/../includes/Gm2_Abandoned_Carts.php';
    use PHPUnit\Framework\TestCase;

    class ActivityLogPagingTest extends TestCase {
        private $orig_wpdb;
        protected function setUp(): void {
            $this->orig_wpdb = $GLOBALS['wpdb'] ?? null;
            $GLOBALS['wpdb'] = new FakeDB();
            $GLOBALS['gm2_ajax_data'] = null;
        }
        protected function tearDown(): void {
            $GLOBALS['wpdb'] = $this->orig_wpdb;
        }
        public function test_returns_paged_activity_and_visits() {
            global $wpdb;
            $cart_id = 1;
            for($i=1;$i<=30;$i++){
                $wpdb->insert($wpdb->prefix.'wc_ac_cart_activity',[
                    'cart_id'=>$cart_id,
                    'action'=>'add',
                    'sku'=>'SKU'.$i,
                    'quantity'=>1,
                    'changed_at'=>'2024-01-01 00:00:'.sprintf('%02d',$i)
                ]);
                $wpdb->insert($wpdb->prefix.'wc_ac_visit_log',[
                    'cart_id'=>$cart_id,
                    'ip_address'=>'127.0.0.'.$i,
                    'entry_url'=>'/p'.$i,
                    'exit_url'=>'/p'.$i.'b',
                    'visit_start'=>'2024-01-01 01:00:'.sprintf('%02d',$i),
                    'visit_end'=>'2024-01-01 01:10:'.sprintf('%02d',$i)
                ]);
            }
            $_POST = ['nonce'=>'n','cart_id'=>$cart_id,'page'=>2,'per_page'=>20];
            \Gm2\Gm2_Abandoned_Carts::gm2_ac_get_activity();
            $result = $GLOBALS['gm2_ajax_data'];
            $this->assertTrue($result['success']);
            $this->assertCount(10, $result['data']['activity']);
            $this->assertSame('SKU10', $result['data']['activity'][0]['sku']);
            $this->assertSame('SKU1', end($result['data']['activity'])['sku']);
            $this->assertCount(10, $result['data']['visits']);
            $this->assertSame('/p10', $result['data']['visits'][0]['entry_url']);
            $this->assertSame('127.0.0.10', $result['data']['visits'][0]['ip_address']);
            $this->assertTrue($result['data']['visits'][0]['is_revisit']);
            $last_visit = end($result['data']['visits']);
            $this->assertSame('/p1', $last_visit['entry_url']);
            $this->assertFalse($last_visit['is_revisit']);
        }
    }

    class FakeDB {
        public $prefix = 'wp_';
        public $data = [];
        private $last_sql;
        private $last_args;
        public function __construct(){
            $this->data[$this->prefix.'wc_ac_cart_activity']=[];
            $this->data[$this->prefix.'wc_ac_visit_log']=[];
        }
        public function insert($table,$row,$format=null){ $this->data[$table][]=$row; }
        public function prepare($sql,...$args){ $this->last_sql=$sql; $this->last_args=$args; return $sql; }
        public function get_results($sql){
            if(strpos($this->last_sql,'wc_ac_cart_activity')!==false){
                $cart_id=$this->last_args[0]; $limit=$this->last_args[1]; $offset=$this->last_args[2];
                $rows=array_filter($this->data[$this->prefix.'wc_ac_cart_activity'], fn($r)=>$r['cart_id']==$cart_id);
                usort($rows, fn($a,$b)=>strcmp($b['changed_at'],$a['changed_at']));
                $slice=array_slice($rows,$offset,$limit);
                return array_map(fn($r)=>(object)$r,$slice);
            }
            if(strpos($this->last_sql,'wc_ac_visit_log')!==false){
                $cart_id=$this->last_args[0]; $limit=$this->last_args[1]; $offset=$this->last_args[2];
                $rows=array_filter($this->data[$this->prefix.'wc_ac_visit_log'], fn($r)=>$r['cart_id']==$cart_id);
                usort($rows, fn($a,$b)=>strcmp($b['visit_start'],$a['visit_start']));
                $slice=array_slice($rows,$offset,$limit);
                return array_map(fn($r)=>(object)$r,$slice);
            }
            return [];
        }
        public function get_var($sql){
            if(strpos($this->last_sql,'MIN(visit_start)')!==false){
                $cart_id=$this->last_args[0];
                $rows=array_filter($this->data[$this->prefix.'wc_ac_visit_log'], fn($r)=>$r['cart_id']==$cart_id);
                usort($rows, fn($a,$b)=>strcmp($a['visit_start'],$b['visit_start']));
                return $rows? $rows[0]['visit_start']: null;
            }
            return null;
        }
    }
}

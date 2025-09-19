<?php
namespace Gm2 {
    if (!function_exists(__NAMESPACE__ . '\\current_time')) {
        function current_time($type) { return '2024-01-01 00:00:00'; }
    }
    if (!function_exists(__NAMESPACE__ . '\\current_user_can')) {
        function current_user_can($cap = '') { return false; }
    }
    if (!function_exists(__NAMESPACE__ . '\\wp_json_encode')) {
        function wp_json_encode($data) { return json_encode($data); }
    }
    if (!function_exists(__NAMESPACE__ . '\\sanitize_text_field')) {
        function sanitize_text_field($str) { return $str; }
    }
    if (!function_exists(__NAMESPACE__ . '\\wp_unslash')) {
        function wp_unslash($v) { return $v; }
    }
    if (!function_exists(__NAMESPACE__ . '\\esc_url_raw')) {
        function esc_url_raw($url) { return $url; }
    }
    if (!function_exists(__NAMESPACE__ . '\\home_url')) {
        function home_url($path = '') { return 'https://example.com' . $path; }
    }
    if (!function_exists(__NAMESPACE__ . '\\get_current_user_id')) {
        function get_current_user_id() { return 1; }
    }
    if (!function_exists(__NAMESPACE__ . '\\is_admin')) {
        function is_admin() { return false; }
    }
    if (!function_exists(__NAMESPACE__ . '\\apply_filters')) {
        function apply_filters($tag, $value) { return $value; }
    }
    if (!function_exists(__NAMESPACE__ . '\\get_option')) {
        function get_option($name, $default = false) { return $default; }
    }
}

namespace {
use Tests\Phpunit\BrainMonkeyTestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
if (!defined('GM2_PLUGIN_DIR')) {
    define('GM2_PLUGIN_DIR', __DIR__ . '/../');
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!defined('COOKIEPATH')) {
    define('COOKIEPATH', '/');
}
if (!defined('COOKIE_DOMAIN')) {
    define('COOKIE_DOMAIN', '');
}

if (!function_exists('wc_get_user_ip')) {
    function wc_get_user_ip() { return '1.2.3.4'; }
}
if (!class_exists('WC_Geolocation')) {
    class WC_Geolocation {
        public static function geolocate_ip($ip, $arg1 = false, $arg2 = false) { return ['country' => 'US', 'state' => 'CA']; }
        public static function get_ip_address() { return '1.2.3.4'; }
    }
}

if (!class_exists('WC_Session')) {
    class WC_Session {
        private $cid; private $data = [];
        public function __construct($id) { $this->cid = $id; }
        public function get_customer_id() { return $this->cid; }
        public function get($key) { return $this->data[$key] ?? null; }
        public function set($key, $val) { if ($val === null) { unset($this->data[$key]); } else { $this->data[$key] = $val; } }
    }
}
if (!class_exists('WC_Cart')) {
    class WC_Cart {
        private $items; public function __construct($items) { $this->items = $items; }
        public function get_cart() { return $this->items; }
        public function set_items($items) { $this->items = $items; }
        public function is_empty() { return empty($this->items); }
        public function get_cart_contents_total() {
            $total = 0; foreach ($this->items as $item) { $prod = $item['data']; $total += $prod->get_price() * $item['quantity']; } return $total;
        }
    }
}
if (!function_exists('WC')) {
    function WC() { global $wc_obj; return $wc_obj; }
}
if (!class_exists('FakeProduct')) {
    class FakeProduct {
        private $id; public function __construct($id) { $this->id = $id; }
        public function get_name() { return 'Product ' . $this->id; }
        public function get_price() { return 5; }
        public function get_sku() { return 'SKU' . $this->id; }
    }
}
if (!function_exists('wc_get_product')) {
    function wc_get_product($id) { return new FakeProduct($id); }
}

class CaptureCartFakeDB {
    public $prefix = 'wp_';
    public $data = [];
    public $insert_id = 0;
    private $last_table; private $last_token;
    public function __construct() {
        $this->data[$this->prefix.'wc_ac_carts'] = [];
        $this->data[$this->prefix.'wc_ac_cart_activity'] = [];
    }
    public function prepare($query, $token) {
        $this->last_token = $token;
        if (preg_match('/FROM\s+(\w+)/', $query, $m)) { $this->last_table = $m[1]; }
        return $query;
    }
    public function get_row($query, $output = OBJECT) {
        foreach ($this->data[$this->last_table] as $row) {
            if ($row['cart_token'] === $this->last_token) { return $output === ARRAY_A ? $row : (object)$row; }
        }
        return null;
    }
    public function insert($table, $data, $format = null) {
        $data['id'] = count($this->data[$table]) + 1;
        $this->data[$table][] = $data;
        $this->insert_id = $data['id'];
    }
    public function update($table, $data, $where, $format = null, $where_format = null) {
        foreach ($this->data[$table] as &$row) {
            $match = true; foreach ($where as $k=>$v) { if ($row[$k] !== $v) { $match=false; break; } }
            if ($match) { foreach ($data as $k=>$v) { $row[$k] = $v; } }
        }
    }
}

require_once __DIR__.'/../includes/Gm2_Abandoned_Carts.php';

final class CaptureCartActivityTest extends BrainMonkeyTestCase {
    private $ac; private $db; private $cart;
    protected function setUp(): void {
        parent::setUp();
        $this->db = new CaptureCartFakeDB();
        $GLOBALS['wpdb'] = $this->db;
        $this->cart = new WC_Cart([
            [ 'product_id'=>1, 'quantity'=>1, 'data'=>new FakeProduct(1) ],
        ]);
        $session = new WC_Session('tok');
        global $wc_obj; $wc_obj = (object)['cart'=>$this->cart, 'session'=>$session];
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla';
        $this->ac = new \Gm2\Gm2_Abandoned_Carts();
    }
    public function test_capture_cart_logs_activity() {
        $this->ac->capture_cart();
        $activity = $this->db->data[$this->db->prefix.'wc_ac_cart_activity'];
        $this->assertCount(1, $activity);
        $this->assertSame('add', $activity[0]['action']);
        $this->assertSame(1, $activity[0]['product_id']);
        $this->assertSame(1, $activity[0]['quantity']);

        // quantity change
        $this->cart->set_items([[ 'product_id'=>1, 'quantity'=>2, 'data'=>new FakeProduct(1) ]]);
        $this->ac->capture_cart();
        $activity = $this->db->data[$this->db->prefix.'wc_ac_cart_activity'];
        $this->assertCount(2, $activity);
        $this->assertSame('quantity', $activity[1]['action']);
        $this->assertSame(2, $activity[1]['quantity']);

        // remove old product and add new
        $this->cart->set_items([[ 'product_id'=>2, 'quantity'=>1, 'data'=>new FakeProduct(2) ]]);
        $this->ac->capture_cart();
        $activity = $this->db->data[$this->db->prefix.'wc_ac_cart_activity'];
        $this->assertCount(4, $activity);
        $this->assertSame('remove', $activity[2]['action']);
        $this->assertSame(1, $activity[2]['product_id']);
        $this->assertSame(0, $activity[2]['quantity']);
        $this->assertSame('add', $activity[3]['action']);
        $this->assertSame(2, $activity[3]['product_id']);
    }
}
}

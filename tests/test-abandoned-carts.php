<?php
namespace Gm2 {
    function check_ajax_referer($action, $query_arg = false) { return true; }
    function wp_send_json_success($data = null) { return ['success'=>true,'data'=>$data]; }
    function wp_send_json_error($data = null) { return ['success'=>false,'data'=>$data]; }
    function current_time($type) { return gmdate('Y-m-d H:i:s'); }
    function current_user_can($cap = '') { return $GLOBALS['gm2_is_admin'] ?? false; }
    function esc_url_raw($url) { return $url; }
    function sanitize_text_field($str) { return $str; }
    function wp_unslash($value) { return $value; }
    function sanitize_email($email) { return $email; }
    function home_url($path = '') { return 'https://example.com' . $path; }
    function admin_url($path = '') { return 'https://example.com' . $path; }
    function wp_create_nonce($action = '') { return 'nonce'; }
    function wp_json_encode($data) { return json_encode($data); }
    function get_current_user_id() { return 1; }
    function is_admin() { return false; }
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
    function wp_schedule_event() {}
    function wp_next_scheduled() { return false; }
    function wp_clear_scheduled_hook() {}
    function apply_filters($tag, $value) { return $value; }
    function get_option($option, $default = false) { return $default; }
    function is_ssl() { return false; }
}

namespace {
define('ABSPATH', __DIR__ . '/../');
define('GM2_PLUGIN_DIR', dirname(__DIR__) . '/');
define('GM2_PLUGIN_URL', '');
define('GM2_VERSION', '1.0');
define('HOUR_IN_SECONDS', 3600);
define('COOKIEPATH', '/');
define('COOKIE_DOMAIN', '');
require_once dirname(__DIR__) . '/includes/Gm2_Abandoned_Carts.php';
if (!class_exists('WC_Session')) {
    class WC_Session {
        private $cid;
        private $data = [];
        public function __construct($id) { $this->cid = $id; }
        public function get_customer_id() { return $this->cid; }
        public function get($key) { return $this->data[$key] ?? null; }
        public function set($key, $value) {
            if ($value === null) {
                unset($this->data[$key]);
            } else {
                $this->data[$key] = $value;
            }
        }
    }
}
if (!function_exists('WC')) {
    function WC() {
        global $wc_session_obj;
        if (!$wc_session_obj) {
            $wc_session_obj = (object) ['session' => new WC_Session('default')];
        }
        return $wc_session_obj;
    }
}

if (!function_exists('wc_get_order')) {
    function wc_get_order($order_id) {
        return new FakeOrder();
    }
}

class FakeOrder {
    public function get_billing_email() { return 'user@example.com'; }
    public function get_billing_country() { return 'US'; }
    public function get_billing_state() { return 'CA'; }
}

class FakeProduct {
    public function get_name() { return 'Test Product'; }
    public function get_price() { return 10; }
    public function get_sku() { return 'SKU'; }
}

class FakeCart {
    public function is_empty() { return false; }
    public function get_cart() {
        return [
            [
                'product_id' => 1,
                'quantity'   => 1,
                'data'       => new FakeProduct(),
            ],
        ];
    }
    public function get_cart_contents_total() { return 10; }
}

if (!class_exists('WP_UnitTestCase')) {
    abstract class WP_UnitTestCase extends \PHPUnit\Framework\TestCase {}
}

class AbandonedCartsTest extends WP_UnitTestCase {
    private $orig_wpdb;
    private $token = 'tok123';

    public function setUp(): void {
        parent::setUp();
        $this->orig_wpdb = $GLOBALS['wpdb'];
        $GLOBALS['wpdb'] = new AbandonedCartFakeDB($this->token);
        global $wc_session_obj;
        $wc_session_obj = (object) ['session' => new WC_Session($this->token)];
    }

    public function tearDown(): void {
        $GLOBALS['wpdb'] = $this->orig_wpdb;
        parent::tearDown();
    }

    public function test_active_abandoned_revisit_flow() {
        $_POST = [ 'nonce' => 'n', 'url' => 'https://example.com/page1' ];
        $_REQUEST = $_POST;
        \Gm2\Gm2_Abandoned_Carts::gm2_ac_mark_active();

        $table = $GLOBALS['wpdb']->prefix . 'wc_ac_carts';
        $row = $GLOBALS['wpdb']->data[$table][0];
        $this->assertNotEmpty($row['session_start']);
        $this->assertNull($row['abandoned_at']);
        $this->assertSame(0, $row['revisit_count']);
        // exit_url should remain unchanged on active ping
        $this->assertSame('https://initial.com', $row['exit_url']);

        // Abandon the cart and ensure exit_url reflects the last visited page
        $_POST = [ 'nonce' => 'n', 'url' => 'https://example.com/page1' ];
        $_REQUEST = $_POST;
        \Gm2\Gm2_Abandoned_Carts::gm2_ac_mark_abandoned();

        $row = $GLOBALS['wpdb']->data[$table][0];
        $this->assertNotNull($row['abandoned_at']);
        $this->assertNull($row['session_start']);
        $this->assertSame('https://example.com/page1', $row['exit_url']);

        // Reactivate the cart; exit_url should not change until abandonment
        $_POST = [ 'nonce' => 'n', 'url' => 'https://example.com/page2' ];
        $_REQUEST = $_POST;
        \Gm2\Gm2_Abandoned_Carts::gm2_ac_mark_active();

        $row = $GLOBALS['wpdb']->data[$table][0];
        $this->assertNull($row['abandoned_at']);
        $this->assertNotEmpty($row['session_start']);
        $this->assertSame(1, $row['revisit_count']);
        $this->assertSame('https://example.com/page1', $row['exit_url']);

        // Abandon without explicit URL; session value should persist
        $_POST = [ 'nonce' => 'n' ];
        $_REQUEST = $_POST;
        \Gm2\Gm2_Abandoned_Carts::gm2_ac_mark_abandoned();

        $row = $GLOBALS['wpdb']->data[$table][0];
        $this->assertSame('https://example.com/page2', $row['exit_url']);
        $this->assertNull(WC()->session->get('gm2_ac_last_seen_url'));
    }

    public function test_external_link_sets_exit_url() {
        $_POST = [ 'nonce' => 'n', 'url' => 'https://example.com/page1' ];
        $_REQUEST = $_POST;
        \Gm2\Gm2_Abandoned_Carts::gm2_ac_mark_active();

        $table = $GLOBALS['wpdb']->prefix . 'wc_ac_carts';

        $_POST = [ 'nonce' => 'n', 'url' => 'https://external.com/off' ];
        $_REQUEST = $_POST;
        \Gm2\Gm2_Abandoned_Carts::gm2_ac_mark_abandoned();

        $row = $GLOBALS['wpdb']->data[$table][0];
        $this->assertSame('https://external.com/off', $row['exit_url']);
    }

    public function test_exit_url_populated_only_on_abandonment_for_new_cart() {
        $table = $GLOBALS['wpdb']->prefix . 'wc_ac_carts';
        // Start with an empty cart table to simulate a brand new cart.
        $GLOBALS['wpdb']->data[$table] = [];

        $_POST = [ 'nonce' => 'n', 'url' => 'https://example.com/start' ];
        $_REQUEST = $_POST;
        \Gm2\Gm2_Abandoned_Carts::gm2_ac_mark_active();

        $this->assertCount(1, $GLOBALS['wpdb']->data[$table]);
        $row = $GLOBALS['wpdb']->data[$table][0];
        // exit_url should be empty until the cart is abandoned
        $this->assertSame('', $row['exit_url']);

        // Abandon the cart and ensure exit_url is now recorded
        $_POST = [ 'nonce' => 'n', 'url' => 'https://example.com/start' ];
        $_REQUEST = $_POST;
        \Gm2\Gm2_Abandoned_Carts::gm2_ac_mark_abandoned();

        $row = $GLOBALS['wpdb']->data[$table][0];
        $this->assertSame('https://example.com/start', $row['exit_url']);
    }

    public function test_shutdown_caches_last_url() {
        $table = $GLOBALS['wpdb']->prefix . 'wc_ac_carts';
        $ac    = new \Gm2\Gm2_Abandoned_Carts();
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/final';
        $ac->store_last_seen_url();
        $row = $GLOBALS['wpdb']->data[$table][0];
        // database should remain unchanged
        $this->assertSame('https://initial.com', $row['exit_url']);
        // session should hold the last seen url
        $this->assertSame('http://example.com/final', WC()->session->get('gm2_ac_last_seen_url'));
    }

    public function test_entry_url_captured_without_js() {
        $ac = new \Gm2\Gm2_Abandoned_Carts();
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/landing';
        $ac->maybe_set_entry_url();
        $table = $GLOBALS['wpdb']->prefix . 'wc_ac_carts';
        $row = $GLOBALS['wpdb']->data[$table][0];
        $this->assertSame('http://example.com/landing', $row['entry_url']);
        $this->assertSame('http://example.com/landing', WC()->session->get('gm2_entry_url'));
    }

    public function test_mark_cart_recovered_moves_row() {
        $ac = new \Gm2\Gm2_Abandoned_Carts();
        $ac->mark_cart_recovered(123);
        $cart_table = $GLOBALS['wpdb']->prefix . 'wc_ac_carts';
        $rec_table = $GLOBALS['wpdb']->prefix . 'wc_ac_recovered';
        $this->assertCount(0, $GLOBALS['wpdb']->data[$cart_table]);
        $this->assertCount(1, $GLOBALS['wpdb']->data[$rec_table]);
        $row = $GLOBALS['wpdb']->data[$rec_table][0];
        $this->assertSame(123, $row['recovered_order_id']);
    }

    public function test_mark_cart_recovered_preserves_contact_info() {
        $cart_table = $GLOBALS['wpdb']->prefix . 'wc_ac_carts';
        $GLOBALS['wpdb']->data[$cart_table][0]['email'] = '';
        $GLOBALS['wpdb']->data[$cart_table][0]['location'] = '';
        $ac = new \Gm2\Gm2_Abandoned_Carts();
        $ac->mark_cart_recovered(321);
        $rec_table = $GLOBALS['wpdb']->prefix . 'wc_ac_recovered';
        $row = $GLOBALS['wpdb']->data[$rec_table][0];
        $this->assertSame('user@example.com', $row['email']);
        $this->assertSame('US-CA', $row['location']);
    }

    public function test_admin_cart_not_recorded() {
        $GLOBALS['gm2_is_admin'] = true;
        $table = $GLOBALS['wpdb']->prefix . 'wc_ac_carts';

        $before = $GLOBALS['wpdb']->data[$table][0];
        $_POST = [ 'nonce' => 'n', 'url' => 'https://example.com/page1' ];
        $_REQUEST = $_POST;
        \Gm2\Gm2_Abandoned_Carts::gm2_ac_mark_active();
        \Gm2\Gm2_Abandoned_Carts::gm2_ac_mark_abandoned();
        $this->assertSame($before, $GLOBALS['wpdb']->data[$table][0]);

        $GLOBALS['wpdb']->data[$table] = [];
        global $wc_session_obj;
        $wc_session_obj->cart = new FakeCart();
        $ac = new \Gm2\Gm2_Abandoned_Carts();
        $ac->capture_cart();
        $this->assertCount(0, $GLOBALS['wpdb']->data[$table]);
        unset($GLOBALS['gm2_is_admin']);
    }

    public function test_mark_active_refreshes_session_start_for_pending_cart() {
        $table = $GLOBALS['wpdb']->prefix . 'wc_ac_carts';
        $old_time = gmdate('Y-m-d H:i:s', time() - 10 * 60);
        $GLOBALS['wpdb']->data[$table][0]['session_start'] = $old_time;
        $GLOBALS['wpdb']->data[$table][0]['abandoned_at'] = null;

        $threshold = time() - 5 * 60;
        $status_before = strtotime($old_time) <= $threshold ? 'Pending Abandonment' : 'Active';
        $this->assertSame('Pending Abandonment', $status_before);

        $_POST = [ 'nonce' => 'n', 'url' => 'https://example.com/page1' ];
        $_REQUEST = $_POST;
        \Gm2\Gm2_Abandoned_Carts::gm2_ac_mark_active();

        $row = $GLOBALS['wpdb']->data[$table][0];
        $this->assertGreaterThan(strtotime($old_time), strtotime($row['session_start']));
        $this->assertNull($row['abandoned_at']);

        $threshold_after = time() - 5 * 60;
        $status_after = strtotime($row['session_start']) <= $threshold_after ? 'Pending Abandonment' : 'Active';
        $this->assertSame('Active', $status_after);
    }
}

class AbandonedCartFakeDB {
    public $prefix = 'wp_';
    public $data;
    private $last_token;
    private $last_table;

    public function __construct($token) {
        $carts = $this->prefix . 'wc_ac_carts';
        $this->data[$carts] = [
            [
                'id' => 1,
                'cart_token' => $token,
                'abandoned_at' => null,
                'session_start' => null,
                'revisit_count' => 0,
                'browsing_time' => 0,
                'recovered_order_id' => null,
                'exit_url' => 'https://initial.com',
                'email' => '',
                'phone' => '',
                'location' => '',
            ],
        ];
        $recovered = $this->prefix . 'wc_ac_recovered';
        $this->data[$recovered] = [];
    }

    public function prepare($query, $token) {
        $this->last_token = $token;
        if (preg_match('/FROM\s+(\w+)/', $query, $m)) {
            $this->last_table = $m[1];
        }
        return $query;
    }

    public function get_row($query, $output = OBJECT) {
        foreach ($this->data[$this->last_table] as $row) {
            if ($row['cart_token'] === $this->last_token) {
                return $output === ARRAY_A ? $row : (object) $row;
            }
        }
        return null;
    }

    public function insert($table, $data) {
        $this->data[$table][] = $data;
    }

    public function delete($table, $where) {
        foreach ($this->data[$table] as $i => $row) {
            $match = true;
            foreach ($where as $k => $v) {
                if ($row[$k] !== $v) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                array_splice($this->data[$table], $i, 1);
            }
        }
    }

    public function update($table, $data, $where) {
        foreach ($this->data[$table] as &$row) {
            if ($row['id'] === $where['id']) {
                foreach ($data as $k => $v) {
                    $row[$k] = $v;
                }
            }
        }
    }
}
}

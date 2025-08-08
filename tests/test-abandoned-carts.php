<?php
namespace Gm2 {
    function check_ajax_referer($action, $query_arg = false) { return true; }
    function wp_send_json_success($data = null) { return ['success'=>true,'data'=>$data]; }
    function wp_send_json_error($data = null) { return ['success'=>false,'data'=>$data]; }
    function current_time($type) { return gmdate('Y-m-d H:i:s'); }
}

namespace {
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

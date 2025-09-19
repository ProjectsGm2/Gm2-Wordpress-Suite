<?php

namespace Gm2 {
    if (!function_exists(__NAMESPACE__ . '\\add_action')) {
        function add_action($hook, $callback) {}
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

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!class_exists('WC_Cart')) {
    class WC_Cart {
        private $items;
        public function __construct($items = []) {
            $this->items = $items;
        }
        public function is_empty() {
            return empty($this->items);
        }
        public function get_cart() {
            return $this->items;
        }
    }
}

if (!function_exists('WC')) {
    function WC() {
        global $wc_obj;
        return $wc_obj;
    }
}

require_once __DIR__ . '/../includes/Gm2_Abandoned_Carts.php';

final class AbandonedCartSessionTest extends BrainMonkeyTestCase {
    public function test_capture_cart_handles_missing_session() {
        global $wc_obj;
        $product = new class {
            public function get_name() { return 'Test'; }
            public function get_price() { return 1.0; }
            public function get_sku() { return 'TST'; }
        };
        $wc_obj = (object) [
            'cart' => new WC_Cart([
                [
                    'product_id' => 1,
                    'quantity' => 1,
                    'data' => $product,
                ],
            ]),
            'session' => null,
        ];
        $ac = new \Gm2\Gm2_Abandoned_Carts();
        $ac->capture_cart();
        $this->assertTrue(true);
    }
}
}

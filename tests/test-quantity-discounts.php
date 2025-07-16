<?php
use Gm2\Gm2_Quantity_Discount_Manager;
use Gm2\Gm2_Quantity_Discounts_Public;

if (!class_exists('WooCommerce')) {
    class WooCommerce {}
}
if (!class_exists('WC_Cart')) {
    class WC_Cart {
        public $cart_contents = [];
        public function get_cart() { return $this->cart_contents; }
    }
}
if (!class_exists('WC_Product')) {
    class WC_Product {
        private $price;
        public function __construct($price) { $this->price = $price; }
        public function get_price($ctx = '') { return $this->price; }
        public function set_price($p) { $this->price = $p; }
    }
}
if (!function_exists('wc_get_product')) {
    function wc_get_product($id) { return new WC_Product(100); }
}
if (!function_exists('is_admin')) {
    function is_admin() { return false; }
}

class QuantityDiscountsTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        delete_option('gm2_quantity_discount_groups');
    }

    public function test_add_and_get_group() {
        $m = new Gm2_Quantity_Discount_Manager();
        $id = $m->add_group([
            'name' => 'Test',
            'products' => [1],
            'rules' => [ [ 'min' => 3, 'type' => 'percent', 'amount' => 10 ] ]
        ]);
        $this->assertNotFalse($id);
        $group = $m->get_group($id);
        $this->assertSame('Test', $group['name']);
        $this->assertSame([1], $group['products']);
        $groups = $m->get_groups();
        $this->assertCount(1, $groups);
    }

    public function test_adjust_price_applies_discount() {
        $m = new Gm2_Quantity_Discount_Manager();
        $m->add_group([
            'name' => 'Test',
            'products' => [1],
            'rules' => [ [ 'min' => 2, 'type' => 'percent', 'amount' => 50 ] ]
        ]);
        $cart = new WC_Cart();
        $product = new WC_Product(100);
        $cart->cart_contents['item'] = [
            'product_id' => 1,
            'quantity' => 2,
            'data' => $product
        ];
        $qd = new Gm2_Quantity_Discounts_Public();
        $qd->run();
        $qd->adjust_prices($cart);
        $this->assertSame(50.0, $cart->cart_contents['item']['data']->get_price());
    }
}

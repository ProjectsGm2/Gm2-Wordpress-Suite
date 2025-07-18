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
        public function calculate_totals() {
            do_action('woocommerce_before_calculate_totals', $this);
        }
        public function get_cart_total() {
            $total = 0;
            foreach ($this->cart_contents as $item) {
                $total += $item['data']->get_price() * $item['quantity'];
            }
            return $total;
        }
    }
}
if (!class_exists('WC_Product')) {
    class WC_Product {
        private $price;
        private $id;
        public function __construct($price, $id = 0) { $this->price = $price; $this->id = $id; }
        public function get_price($ctx = '') { return $this->price; }
        public function set_price($p) { $this->price = $p; }
        public function get_id() { return $this->id; }
    }
}
if (!function_exists('wc_get_product')) {
    function wc_get_product($id) { return new WC_Product(100, $id); }
}
if (!function_exists('wc_get_price_to_display')) {
    function wc_get_price_to_display($product, $args = []) {
        return $args['price'] ?? $product->get_price();
    }
}
if (!function_exists('is_admin')) {
    function is_admin() { return false; }
}
if (!class_exists('Elementor\\Widget_Base')) {
    eval('namespace Elementor; class Widget_Base {}');
}
if (!class_exists('Elementor\\Icons_Manager')) {
    eval('namespace Elementor; class Icons_Manager { public static function render_icon($icon, $args = []) { echo "<i></i>"; } }');
}
if (!function_exists('wc_price')) {
    function wc_price($price, $args = []) { return (string)$price; }
}
if (!function_exists('get_woocommerce_currency_symbol')) {
    function get_woocommerce_currency_symbol() { return '$'; }
}
if (!function_exists('WC')) {
    function WC() {
        static $instance = null;
        if (!$instance) {
            $instance = new class {
                public $cart;
                public function __construct() { $this->cart = new WC_Cart(); }
            };
        }
        return $instance;
    }
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

    public function test_multiple_groups_use_highest_percentage() {
        require_once GM2_PLUGIN_DIR . 'includes/widgets/class-gm2-qd-widget.php';

        $m = new Gm2_Quantity_Discount_Manager();
        $m->add_group([
            'name'     => 'Low',
            'products' => [1],
            'rules'    => [
                [ 'min' => 1, 'type' => 'percent', 'amount' => 10 ],
                [ 'min' => 5, 'type' => 'percent', 'amount' => 15 ],
            ],
        ]);
        $m->add_group([
            'name'     => 'High',
            'products' => [1],
            'rules'    => [
                [ 'min' => 1, 'type' => 'percent', 'amount' => 20 ],
                [ 'min' => 5, 'type' => 'percent', 'amount' => 25 ],
            ],
        ]);

        // Verify that the discount table only lists rules from the group with
        // the highest percentage.
        $widget = new \Gm2\GM2_QD_Widget();
        $method = new ReflectionMethod($widget, 'get_rules');
        $method->setAccessible(true);
        $rules  = $method->invoke($widget, 1);
        $this->assertCount(2, $rules);
        $this->assertSame([20, 25], array_column($rules, 'amount'));

        // Confirm the cart discount also uses the rule from that group.
        $cart    = new WC_Cart();
        $product = new WC_Product(100);
        $cart->cart_contents['item'] = [
            'product_id' => 1,
            'quantity'   => 5,
            'data'       => $product,
        ];
        $qd = new Gm2_Quantity_Discounts_Public();
        $qd->run();
        $qd->adjust_prices($cart);

        $this->assertSame(75.0, $cart->cart_contents['item']['data']->get_price());
    }

    public function test_calculate_discounted_price_matches_adjust_prices() {
        require_once GM2_PLUGIN_DIR . 'includes/widgets/class-gm2-qd-widget.php';
        $widget = new \Gm2\GM2_QD_Widget();

        $rule = [ 'min' => 2, 'type' => 'percent', 'amount' => 10 ];

        $calc_product = new WC_Product(100);
        $method = new ReflectionMethod($widget, 'calculate_discounted_price');
        $method->setAccessible(true);
        $calc = $method->invoke($widget, $calc_product, $rule);

        $m = new Gm2_Quantity_Discount_Manager();
        $m->add_group([
            'name'     => 'Test',
            'products' => [1],
            'rules'    => [ $rule ],
        ]);

        $cart_product = new WC_Product(100);
        $cart = new WC_Cart();
        $cart->cart_contents['item'] = [
            'product_id' => 1,
            'quantity'   => 2,
            'data'       => $cart_product,
        ];
        $qd = new Gm2_Quantity_Discounts_Public();
        $qd->run();
        $qd->adjust_prices($cart);
        $expected = $cart->cart_contents['item']['data']->get_price();

        $this->assertSame($expected, $calc);
    }

    public function test_widget_renders_in_edit_mode() {
        require_once GM2_PLUGIN_DIR . 'includes/widgets/class-gm2-qd-widget.php';

        if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
            eval('namespace Elementor; class Plugin { public static $instance; public $editor; public function __construct(){ self::$instance = $this; $this->editor = new class { public function is_edit_mode(){ return true; } }; } }');
        } else {
            \Elementor\Plugin::$instance = new class { public $editor; public function __construct(){ $this->editor = new class { public function is_edit_mode(){ return true; } }; } };
        }

        if ( ! class_exists( 'WC_Product_With_ID' ) ) {
            class WC_Product_With_ID extends WC_Product { public function get_id() { return 1; } }
        }
        global $product;
        $product = new WC_Product_With_ID(100);

        $m = new Gm2_Quantity_Discount_Manager();
        $m->add_group([
            'name'     => 'Test',
            'products' => [1],
            'rules'    => [ [ 'min' => 1, 'type' => 'percent', 'amount' => 10, 'label' => 'L' ] ],
        ]);

        ob_start();
        $widget = new \Gm2\GM2_QD_Widget();
        $widget->render();
        $out = ob_get_clean();
        $this->assertStringContainsString('gm2-qd-options', $out);
    }

    public function test_widget_renders_with_preview_id() {
        require_once GM2_PLUGIN_DIR . 'includes/widgets/class-gm2-qd-widget.php';

        if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
            eval('namespace Elementor; class Plugin { public static $instance; public $editor; public function __construct(){ self::$instance = $this; $this->editor = new class { public function is_edit_mode(){ return true; } }; } }');
        } else {
            \Elementor\Plugin::$instance = new class { public $editor; public function __construct(){ $this->editor = new class { public function is_edit_mode(){ return true; } }; } };
        }

        global $product, $post;
        $product = null;

        $template_id = self::factory()->post->create();
        $product_id  = self::factory()->post->create();
        $post        = get_post( $template_id );
        update_post_meta( $template_id, '_elementor_preview_id', $product_id );

        $m = new Gm2_Quantity_Discount_Manager();
        $m->add_group([
            'name'     => 'Test',
            'products' => [ $product_id ],
            'rules'    => [ [ 'min' => 1, 'type' => 'percent', 'amount' => 10, 'label' => 'L' ] ],
        ]);

        ob_start();
        $widget = new \Gm2\GM2_QD_Widget();
        $widget->render();
        $out = ob_get_clean();
        $this->assertStringContainsString('gm2-qd-options', $out);
    }

    public function test_widget_outputs_br_in_label() {
        require_once GM2_PLUGIN_DIR . 'includes/widgets/class-gm2-qd-widget.php';

        if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
            eval('namespace Elementor; class Plugin { public static $instance; public $editor; public function __construct(){ self::$instance = $this; $this->editor = new class { public function is_edit_mode(){ return true; } }; } }');
        } else {
            \Elementor\Plugin::$instance = new class { public $editor; public function __construct(){ $this->editor = new class { public function is_edit_mode(){ return true; } }; } };
        }

        if ( ! class_exists( 'WC_Product_With_ID' ) ) {
            class WC_Product_With_ID extends WC_Product { public function get_id() { return 1; } }
        }
        global $product;
        $product = new WC_Product_With_ID(100);

        $m = new Gm2_Quantity_Discount_Manager();
        $m->add_group([
            'name'     => 'Test',
            'products' => [1],
            'rules'    => [ [ 'min' => 1, 'type' => 'percent', 'amount' => 10, 'label' => 'Line1<br>Line2' ] ],
        ]);

        ob_start();
        $widget = new \Gm2\GM2_QD_Widget();
        $widget->render();
        $out = ob_get_clean();
        $this->assertStringContainsString('Line1<br>Line2', $out);
    }

    public function test_ajax_recalculate_updates_fragments() {
        $m = new Gm2_Quantity_Discount_Manager();
        $m->add_group([
            'name'     => 'Test',
            'products' => [1],
            'rules'    => [ [ 'min' => 2, 'type' => 'percent', 'amount' => 50 ] ],
        ]);

        $cart = WC()->cart;
        $product = new WC_Product(100);
        $cart->cart_contents['item'] = [
            'product_id' => 1,
            'quantity'   => 2,
            'data'       => $product,
        ];

        $qd = new Gm2_Quantity_Discounts_Public();
        $qd->run();

        do_action('woocommerce_ajax_added_to_cart', 1);

        add_filter('woocommerce_add_to_cart_fragments', function($fragments) {
            $fragments['price'] = WC()->cart->cart_contents['item']['data']->get_price();
            return $fragments;
        });

        $fragments = apply_filters('woocommerce_add_to_cart_fragments', []);
        $this->assertSame(50.0, $fragments['price']);

        remove_all_filters('woocommerce_add_to_cart_fragments');
    }

    public function test_filter_cart_item_price_returns_discounted_value() {
        $cart_item = [
            'gm2_qd_discounted_price' => 42.5,
        ];
        $qd = new Gm2_Quantity_Discounts_Public();
        $qd->run();
        $result = apply_filters('woocommerce_cart_item_price', '100', $cart_item, 'item');
        $this->assertSame('42.5', $result);
    }
}


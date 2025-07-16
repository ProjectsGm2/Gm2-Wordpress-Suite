<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Quantity_Discounts_Public {
    public function run() {
        add_action('woocommerce_before_calculate_totals', [ $this, 'adjust_prices' ], 20);
    }

    public function adjust_prices($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        if (!class_exists('WooCommerce') || !is_a($cart, 'WC_Cart')) {
            return;
        }
        $groups = get_option('gm2_quantity_discount_groups', []);
        if (empty($groups)) {
            return;
        }
        foreach ($cart->get_cart() as $key => $item) {
            $product_id = $item['product_id'];
            $group      = null;
            foreach ($groups as $g) {
                if (!empty($g['products']) && in_array($product_id, $g['products'], true)) {
                    $group = $g;
                    break;
                }
            }
            if (!$group || empty($group['rules'])) {
                continue;
            }
            $product = $item['data'];
            if (!isset($cart->cart_contents[$key]['gm2_qd_original_price'])) {
                $cart->cart_contents[$key]['gm2_qd_original_price'] = (float) $product->get_price('edit');
            } else {
                $product->set_price($cart->cart_contents[$key]['gm2_qd_original_price']);
            }
            $base  = $cart->cart_contents[$key]['gm2_qd_original_price'];
            $qty   = $item['quantity'];
            $rules = $group['rules'];
            usort($rules, function($a, $b) {
                return $b['min'] <=> $a['min'];
            });
            $applied = null;
            foreach ($rules as $rule) {
                if ($qty >= intval($rule['min'])) {
                    $applied = $rule;
                    break;
                }
            }
            if ($applied) {
                $new_price = $base;
                if ($applied['type'] === 'percent') {
                    $new_price = $base * (1 - ($applied['amount'] / 100));
                } else {
                    $new_price = $base - $applied['amount'];
                }
                if ($new_price < 0) {
                    $new_price = 0;
                }
                $product->set_price($new_price);
                $cart->cart_contents[$key]['data'] = $product;
            }
        }
    }
}

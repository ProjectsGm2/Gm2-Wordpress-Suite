<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Quantity_Discounts_Public {
    public function run() {
        add_action('woocommerce_before_calculate_totals', [ $this, 'adjust_prices' ], 20);
        add_filter('woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 4);
        add_action('woocommerce_add_to_cart', [ $this, 'recalculate_after_add' ], 5, 6);
        add_action('woocommerce_ajax_added_to_cart', [ $this, 'recalculate_after_add' ], 5);
        add_filter('woocommerce_cart_item_price', [ $this, 'filter_cart_item_price' ], 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', [ $this, 'add_order_item_meta' ], 10, 4);
        add_filter('woocommerce_display_item_meta', [ $this, 'display_item_meta' ], 10, 3);
        add_filter('woocommerce_email_order_item_meta', [ $this, 'display_item_meta' ], 10, 3);
    }

    private function format_rule_desc($rule) {
        if ($rule['type'] === 'percent') {
            return sprintf('%d+ units: %s%% off', $rule['min'], $rule['amount']);
        }
        return sprintf('%d+ units: %s discount', $rule['min'], wc_price($rule['amount']));
    }

    private function get_applicable_rule($product_id, $qty) {
        $groups = get_option('gm2_quantity_discount_groups', []);
        if (empty($groups)) {
            return null;
        }

        $group = null;
        foreach ($groups as $g) {
            if (!empty($g['products']) && in_array($product_id, $g['products'], true)) {
                $group = $g;
                break;
            }
        }
        if (!$group || empty($group['rules'])) {
            return null;
        }

        $rules = $group['rules'];
        usort($rules, function($a, $b) {
            return $b['min'] <=> $a['min'];
        });
        foreach ($rules as $rule) {
            if ($qty >= intval($rule['min'])) {
                return $rule;
            }
        }
        return null;
    }

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id, $quantity) {
        $rule = $this->get_applicable_rule($product_id, $quantity);
        if ($rule) {
            $cart_item_data['gm2_qd_rule']      = $rule;
            $cart_item_data['gm2_qd_rule_desc'] = $this->format_rule_desc($rule);
            $product = wc_get_product($product_id);
            if ($product) {
                $cart_item_data['gm2_qd_original_price'] = (float) $product->get_price('edit');
            }
        }
        return $cart_item_data;
    }

    public function recalculate_after_add() {
        if (function_exists('WC') && WC()->cart) {
            WC()->cart->calculate_totals();
        }
    }

    public function filter_cart_item_price($price_html, $cart_item, $cart_item_key) {
        if (isset($cart_item['gm2_qd_discounted_price'])) {
            return wc_price($cart_item['gm2_qd_discounted_price']);
        }
        return $price_html;
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
                $cart->cart_contents[$key]['gm2_qd_rule']            = $applied;
                $cart->cart_contents[$key]['gm2_qd_rule_desc']       = $this->format_rule_desc($applied);
                $cart->cart_contents[$key]['gm2_qd_discounted_price'] = $new_price;
            } else {
                unset($cart->cart_contents[$key]['gm2_qd_rule'], $cart->cart_contents[$key]['gm2_qd_rule_desc'], $cart->cart_contents[$key]['gm2_qd_discounted_price']);
            }
        }
    }

    public function add_order_item_meta($item, $cart_item_key, $values, $order) {
        $desc = '';
        if (isset($values['gm2_qd_rule_desc'])) {
            $desc = $values['gm2_qd_rule_desc'];
        } elseif (isset($values['gm2_qd_rule'])) {
            $desc = $this->format_rule_desc($values['gm2_qd_rule']);
        }

        if ($desc !== '') {
            $item->add_meta_data('_gm2_qd_rule_desc', $desc, true);
        }
        $item->add_meta_data('_gm2_purchased_qty', $item->get_quantity(), true);
        if (isset($values['gm2_qd_discounted_price'])) {
            $item->add_meta_data('_gm2_discounted_price', $values['gm2_qd_discounted_price'], true);
        }
    }

    public function display_item_meta($html, $item, $args) {
        $rule  = $item->get_meta('_gm2_qd_rule_desc', true);
        if ($rule !== '') {
            $show = (!empty($args['sent_to_admin']) && $args['sent_to_admin']) || current_user_can('manage_woocommerce');
            if ($show) {
                $html .= '<br><small>' . sprintf(__('Quantity Discount Rule: %s', 'gm2-wordpress-suite'), esc_html($rule)) . '</small>';
            }
        }

        $qty   = $item->get_meta('_gm2_purchased_qty', true);
        $price = $item->get_meta('_gm2_discounted_price', true);
        if ($qty !== '') {
            $html .= '<br><small>' . sprintf(__('Purchased Quantity: %s', 'gm2-wordpress-suite'), esc_html($qty)) . '</small>';
        }
        if ($price !== '') {
            $html .= '<br><small>' . sprintf(__('Discounted Price: %s', 'gm2-wordpress-suite'), wp_kses_post(wc_price($price))) . '</small>';
        }
        return $html;
    }
}

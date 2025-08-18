<?php
namespace Gm2;
if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Cart_Settings_Admin {
    public function register_hooks() {
        add_action('admin_menu', [ $this, 'add_admin_menu' ]);
    }

    public function add_admin_menu() {
        add_menu_page(
            esc_html__( 'Gm2 Cart', 'gm2-wordpress-suite' ),
            esc_html__( 'Gm2 Cart', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-cart',
            '__return_null',
            'dashicons-cart'
        );

        add_submenu_page(
            'gm2-cart',
            esc_html__( 'Cart Settings', 'gm2-wordpress-suite' ),
            esc_html__( 'Cart Settings', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-cart-settings',
            [ $this, 'render_page' ]
        );
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        $notice             = '';
        $enable_phone_login = get_option('gm2_enable_phone_login', '0');
        $currency           = get_option('gm2_currency', 'USD');
        $stock_min          = (int) get_option('gm2_stock_min', 0);
        $stock_max          = (int) get_option('gm2_stock_max', 0);
        $sku_pattern        = get_option('gm2_sku_pattern', '^[A-Z0-9-]+$');
        if (isset($_POST['gm2_cart_settings_nonce']) && wp_verify_nonce($_POST['gm2_cart_settings_nonce'], 'gm2_cart_settings_save')) {
            $popup_id = isset($_POST['gm2_cart_popup_id']) ? absint($_POST['gm2_cart_popup_id']) : 0;
            update_option('gm2_cart_popup_id', $popup_id);
            $enable_phone_login = isset($_POST['gm2_enable_phone_login']) ? '1' : '0';
            update_option('gm2_enable_phone_login', $enable_phone_login);
            $currency    = isset($_POST['gm2_currency']) ? sanitize_text_field($_POST['gm2_currency']) : $currency;
            update_option('gm2_currency', $currency);
            $stock_min   = isset($_POST['gm2_stock_min']) ? (int) $_POST['gm2_stock_min'] : 0;
            $stock_max   = isset($_POST['gm2_stock_max']) ? (int) $_POST['gm2_stock_max'] : 0;
            update_option('gm2_stock_min', $stock_min);
            update_option('gm2_stock_max', $stock_max);
            $sku_pattern = isset($_POST['gm2_sku_pattern']) ? sanitize_text_field($_POST['gm2_sku_pattern']) : $sku_pattern;
            update_option('gm2_sku_pattern', $sku_pattern);
            $notice = '<div class="updated notice"><p>' . esc_html__( 'Settings saved.', 'gm2-wordpress-suite' ) . '</p></div>';
        }

        $selected = (int) get_option('gm2_cart_popup_id', 0);

        $args = [
            'post_type'      => 'elementor_library',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'   => '_elementor_template_type',
                    'value' => 'popup',
                ],
            ],
            'orderby' => 'title',
            'order'   => 'ASC',
        ];

        $query = new \WP_Query($args);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Cart Settings', 'gm2-wordpress-suite' ) . '</h1>';
        echo $notice;
        echo '<form method="post">';
        wp_nonce_field('gm2_cart_settings_save', 'gm2_cart_settings_nonce');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="gm2_cart_popup_id">' . esc_html__( 'Cart Popup', 'gm2-wordpress-suite' ) . '</label></th><td>';
        echo '<select name="gm2_cart_popup_id" id="gm2_cart_popup_id">';
        echo '<option value="0">' . esc_html__( 'None', 'gm2-wordpress-suite' ) . '</option>';
        foreach ($query->posts as $p) {
            $id    = $p->ID;
            $title = get_the_title($id);
            $sel   = selected($selected, $id, false);
            echo '<option value="' . esc_attr($id) . '"' . $sel . '>' . esc_html($title) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';
        echo '<tr><th scope="row"><label for="gm2_enable_phone_login">' . esc_html__( 'Enable phone number', 'gm2-wordpress-suite' ) . '</label></th><td>';
        echo '<input type="checkbox" name="gm2_enable_phone_login" id="gm2_enable_phone_login" value="1"' . checked($enable_phone_login, '1', false) . ' />';
        echo '</td></tr>';
        echo '<tr><th scope="row"><label for="gm2_currency">' . esc_html__( 'Currency', 'gm2-wordpress-suite' ) . '</label></th><td>';
        $currencies = ['USD','EUR','GBP','JPY'];
        echo '<select name="gm2_currency" id="gm2_currency">';
        foreach ($currencies as $c) {
            $sel = selected($currency, $c, false);
            echo '<option value="' . esc_attr($c) . '"' . $sel . '>' . esc_html($c) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';
        echo '<tr><th scope="row"><label for="gm2_stock_min">' . esc_html__( 'Min Stock', 'gm2-wordpress-suite' ) . '</label></th><td>';
        echo '<input type="number" name="gm2_stock_min" id="gm2_stock_min" value="' . esc_attr($stock_min) . '" />';
        echo '</td></tr>';
        echo '<tr><th scope="row"><label for="gm2_stock_max">' . esc_html__( 'Max Stock', 'gm2-wordpress-suite' ) . '</label></th><td>';
        echo '<input type="number" name="gm2_stock_max" id="gm2_stock_max" value="' . esc_attr($stock_max) . '" />';
        echo '</td></tr>';
        echo '<tr><th scope="row"><label for="gm2_sku_pattern">' . esc_html__( 'SKU Pattern', 'gm2-wordpress-suite' ) . '</label></th><td>';
        echo '<input type="text" name="gm2_sku_pattern" id="gm2_sku_pattern" value="' . esc_attr($sku_pattern) . '" />';
        echo '</td></tr>';
        echo '</tbody></table>';
        submit_button();
        echo '</form>';
        echo '</div>';

        wp_reset_postdata();
    }
}

<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Abandoned_Carts_Public {
    public function run() {
        if (function_exists('current_user_can') && current_user_can('manage_options')) {
            return;
        }
        add_action('wp_enqueue_scripts', [ $this, 'enqueue_scripts' ]);
        add_action('wp_ajax_gm2_ac_contact_capture', [ $this, 'handle_contact_capture' ]);
        add_action('wp_ajax_nopriv_gm2_ac_contact_capture', [ $this, 'handle_contact_capture' ]);
        add_action('wp_ajax_gm2_ac_mark_active', [ Gm2_Abandoned_Carts::class, 'gm2_ac_mark_active' ]);
        add_action('wp_ajax_nopriv_gm2_ac_mark_active', [ Gm2_Abandoned_Carts::class, 'gm2_ac_mark_active' ]);
        add_action('wp_ajax_gm2_ac_mark_abandoned', [ Gm2_Abandoned_Carts::class, 'gm2_ac_mark_abandoned' ]);
        add_action('wp_ajax_nopriv_gm2_ac_mark_abandoned', [ Gm2_Abandoned_Carts::class, 'gm2_ac_mark_abandoned' ]);
    }

    public function enqueue_scripts() {
        if (function_exists('current_user_can') && current_user_can('manage_options')) {
            return;
        }
        wp_enqueue_script(
            'gm2-ac-email-capture',
            GM2_PLUGIN_URL . 'public/js/gm2-ac-email-capture.js',
            [ 'jquery' ],
            GM2_VERSION,
            true
        );
        wp_localize_script(
            'gm2-ac-email-capture',
            'gm2AcEmailCapture',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('gm2_ac_contact_capture'),
            ]
        );

        $popup_id = (int) get_option('gm2_cart_popup_id', 0);
        if ($popup_id > 0) {
            wp_enqueue_script(
                'gm2-ac-popup',
                GM2_PLUGIN_URL . 'public/js/gm2-ac-popup.js',
                [ 'jquery' ],
                GM2_VERSION,
                true
            );
            wp_localize_script(
                'gm2-ac-popup',
                'gm2AcPopup',
                [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('gm2_ac_contact_capture'),
                    'popup_id' => $popup_id,
                ]
            );
        }

        wp_enqueue_script(
            'gm2-ac-activity',
            GM2_PLUGIN_URL . 'public/js/gm2-ac-activity.js',
            [],
            GM2_VERSION,
            true
        );
        wp_localize_script(
            'gm2-ac-activity',
            'gm2AcActivity',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('gm2_ac_activity'),
            ]
        );
    }

    public function handle_contact_capture() {
        if (function_exists('current_user_can') && current_user_can('manage_options')) {
            wp_send_json_success();
        }
        check_ajax_referer('gm2_ac_contact_capture', 'nonce');

        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        if (empty($email) && empty($phone)) {
            wp_send_json_error('empty_contact');
        }

        $token            = '';
        $session_entry_url = '';
        if (class_exists('WC_Session') && WC()->session) {
            $token            = WC()->session->get_customer_id();
            $session_entry_url = WC()->session->get('gm2_entry_url');
        }

        if (empty($token)) {
            wp_send_json_error('no_cart');
        }

        $stored_entry = '';
        if (!empty($session_entry_url)) {
            $stored_entry = esc_url_raw($session_entry_url);
            WC()->session->set('gm2_entry_url', null);
        } elseif (isset($_COOKIE['gm2_entry_url'])) {
            $stored_entry = esc_url_raw(wp_unslash($_COOKIE['gm2_entry_url']));
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
        $current_url = $stored_entry ?: home_url($request_uri);

        $agent   = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $browser = Gm2_Abandoned_Carts::get_browser($agent);

        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        $row   = $wpdb->get_row($wpdb->prepare("SELECT id, entry_url FROM $table WHERE cart_token = %s", $token));
        if ($row) {
            $wpdb->update(
                $table,
                [
                    'email'      => $email,
                    'phone'      => $phone,
                    'browser'    => $browser,
                    'user_agent' => $agent,
                ],
                [ 'id' => $row->id ]
            );
            if (empty($row->entry_url) && !empty($stored_entry)) {
                $wpdb->update(
                    $table,
                    [ 'entry_url' => $stored_entry ],
                    [ 'id' => $row->id ]
                );
            }
        } else {
            $cart = class_exists('WC_Cart') ? WC()->cart : null;
            if (!$cart || $cart->is_empty()) {
                wp_send_json_error('no_cart');
            }
            $cart_items = [];
            foreach ($cart->get_cart() as $item) {
                $prod_id = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                $qty     = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                $product = isset($item['data']) && is_object($item['data']) ? $item['data'] : wc_get_product($prod_id);
                $name    = $product ? $product->get_name() : 'Product #' . $prod_id;
                $price   = $product ? (float) $product->get_price() : 0;
                $cart_items[] = [
                    'id'    => $prod_id,
                    'name'  => $name,
                    'qty'   => $qty,
                    'price' => $price,
                ];
            }
            $contents = wp_json_encode($cart_items);
            $ip_info  = Gm2_Abandoned_Carts::get_ip_and_location();
            $ip       = $ip_info['ip'];
            $location = $ip_info['location'];
            $device = 'Desktop';
            if (file_exists(GM2_PLUGIN_DIR . 'includes/MobileDetect.php')) {
                require_once GM2_PLUGIN_DIR . 'includes/MobileDetect.php';
                if (class_exists('Detection\\MobileDetect')) {
                    $detect = new \Detection\MobileDetect();
                } elseif (class_exists('Mobile_Detect')) {
                    $detect = new \Mobile_Detect();
                } else {
                    $detect = null;
                }
                if ($detect) {
                    $detect->setUserAgent($agent);
                    if ($detect->isTablet()) {
                        $device = 'Tablet';
                    } elseif ($detect->isMobile()) {
                        $device = 'Mobile';
                    }
                }
            }
            $total = (float) $cart->get_cart_contents_total();
            $wpdb->insert($table, [
                'cart_token'    => $token,
                'user_id'       => get_current_user_id(),
                'cart_contents' => $contents,
                'created_at'    => current_time('mysql'),
                'ip_address'    => $ip,
                'user_agent'    => $agent,
                'browser'       => $browser,
                'location'      => $location,
                'device'        => $device,
                'entry_url'     => $current_url,
                'cart_total'    => $total,
                'email'         => $email,
                'phone'         => $phone,
            ]);
        }

        wp_send_json_success();
    }
}

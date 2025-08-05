<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Abandoned_Carts {
    public function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $carts  = $wpdb->prefix . 'wc_ac_carts';
        $queue  = $wpdb->prefix . 'wc_ac_email_queue';
        $sql = "CREATE TABLE $carts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cart_token varchar(100) NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            cart_contents longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            abandoned_at datetime DEFAULT NULL,
            recovered_order_id bigint(20) unsigned DEFAULT NULL,
            email varchar(200) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            browser varchar(50) DEFAULT NULL,
            location varchar(100) DEFAULT NULL,
            device varchar(20) DEFAULT NULL,
            entry_url text DEFAULT NULL,
            exit_url text DEFAULT NULL,
            cart_total decimal(10,2) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY cart_token (cart_token)
        ) $charset_collate;";
        $sql .= "CREATE TABLE $queue (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cart_id bigint(20) unsigned NOT NULL,
            send_at datetime NOT NULL,
            sent tinyint(1) NOT NULL DEFAULT 0,
            message_type varchar(20) NOT NULL,
            PRIMARY KEY  (id),
            KEY cart_id (cart_id)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function run() {
        $this->maybe_install();

        add_action('woocommerce_add_to_cart', [$this, 'capture_cart'], 10, 6);
        add_action('woocommerce_update_cart_action_cart_updated', [$this, 'capture_cart']);
        add_action('woocommerce_cart_loaded_from_session', [$this, 'capture_cart']);
        add_action('woocommerce_thankyou', [$this, 'mark_cart_recovered']);
    }

    public function capture_cart() {
        if (!class_exists('WC_Cart')) {
            return;
        }
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }
        if ($cart->is_empty()) {
            return;
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
        $contents   = wp_json_encode($cart_items);
        $token      = WC()->session->get_customer_id();
        $ip         = $_SERVER['REMOTE_ADDR'] ?? '';
        $agent      = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $browser    = self::get_browser($agent);
        $location   = '';
        if (class_exists('WC_Geolocation') && !empty($ip)) {
            $geo = \WC_Geolocation::geolocate_ip($ip, false, false);
            if (!empty($geo['country'])) {
                $location = $geo['country'];
                if (!empty($geo['state'])) {
                    $location .= '-' . $geo['state'];
                }
            }
        }
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
        $current_url = home_url($_SERVER['REQUEST_URI'] ?? '/');
        $total      = (float) $cart->get_cart_contents_total();
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, entry_url FROM $table WHERE cart_token = %s", $token));
        if ($row) {
            $wpdb->update(
                $table,
                [
                    'cart_contents' => $contents,
                    'created_at'    => current_time('mysql'),
                    'ip_address'    => $ip,
                    'user_agent'    => $agent,
                    'browser'       => $browser,
                    'location'      => $location,
                    'device'        => $device,
                    'cart_total'    => $total
                ],
                ['id' => $row->id]
            );
            if (empty($row->entry_url)) {
                $wpdb->update(
                    $table,
                    ['entry_url' => $current_url],
                    ['id' => $row->id]
                );
            }
        } else {
            $wpdb->insert($table, [
                'cart_token'   => $token,
                'user_id'      => get_current_user_id(),
                'cart_contents'=> $contents,
                'created_at'   => current_time('mysql'),
                'ip_address'   => $ip,
                'user_agent'   => $agent,
                'browser'      => $browser,
                'location'     => $location,
                'device'       => $device,
                'entry_url'    => $current_url,
                'cart_total'   => $total,
            ]);
        }
    }

    public static function gm2_ac_mark_active() {
        check_ajax_referer('gm2_ac_activity', 'nonce');

        $url   = esc_url_raw($_POST['url'] ?? '');
        $token = '';
        if (class_exists('WC_Session') && WC()->session) {
            $token = WC()->session->get_customer_id();
        }
        if (empty($token)) {
            wp_send_json_error('no_cart');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        $wpdb->update(
            $table,
            [
                'exit_url'     => $url,
                'abandoned_at' => null,
            ],
            [ 'cart_token' => $token ]
        );

        wp_send_json_success();
    }

    public static function gm2_ac_mark_abandoned() {
        check_ajax_referer('gm2_ac_activity', 'nonce');

        $url   = esc_url_raw($_POST['url'] ?? '');
        $token = '';
        if (class_exists('WC_Session') && WC()->session) {
            $token = WC()->session->get_customer_id();
        }
        if (empty($token)) {
            wp_send_json_error('no_cart');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        $wpdb->update(
            $table,
            [
                'exit_url'     => $url,
                'abandoned_at' => current_time('mysql'),
            ],
            [ 'cart_token' => $token ]
        );

        wp_send_json_success();
    }

    public function mark_cart_recovered($order_id) {
        if (!$order_id) {
            return;
        }
        $token = WC()->session->get_customer_id();
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        $row = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE cart_token = %s", $token));
        if ($row) {
            $wpdb->update($table, [ 'recovered_order_id' => $order_id ], ['id' => $row->id]);
        }
    }

    private function maybe_install() {
        global $wpdb;
        $carts_table = $wpdb->prefix . 'wc_ac_carts';
        $queue_table = $wpdb->prefix . 'wc_ac_email_queue';
        $carts_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $carts_table));
        $queue_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $queue_table));
        if ($carts_exists !== $carts_table || $queue_exists !== $queue_table) {
            $this->install();
        } else {
            $has_browser = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $carts_table LIKE %s", 'browser'));
            if (!$has_browser) {
                $wpdb->query("ALTER TABLE $carts_table ADD browser varchar(50) DEFAULT NULL AFTER user_agent");
            }
        }
    }

    public static function get_browser($agent) {
        $browsers = [
            'Edge' => 'Edge',
            'OPR' => 'Opera',
            'Chrome' => 'Chrome',
            'Safari' => 'Safari',
            'Firefox' => 'Firefox',
            'MSIE' => 'IE',
            'Trident/7.0' => 'IE'
        ];
        foreach ($browsers as $key => $name) {
            if (stripos($agent, $key) !== false) {
                return $name;
            }
        }
        return 'Unknown';
    }

    public static function clear_scheduled_event() {
        // Deprecated: cron scheduling removed.
    }
}

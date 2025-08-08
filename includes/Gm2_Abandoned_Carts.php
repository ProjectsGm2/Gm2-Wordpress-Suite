<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Abandoned_Carts {
    public function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $carts = $wpdb->prefix . 'wc_ac_carts';
        $queue = $wpdb->prefix . 'wc_ac_email_queue';
        $recovered = $wpdb->prefix . 'wc_ac_recovered';

        $carts_sql = "CREATE TABLE $carts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cart_token varchar(100) NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            cart_contents longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            session_start datetime DEFAULT NULL,
            abandoned_at datetime DEFAULT NULL,
            browsing_time bigint(20) unsigned DEFAULT 0,
            revisit_count int DEFAULT 0,
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

        $queue_sql = "CREATE TABLE $queue (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cart_id bigint(20) unsigned NOT NULL,
            send_at datetime NOT NULL,
            sent tinyint(1) NOT NULL DEFAULT 0,
            message_type varchar(20) NOT NULL,
            PRIMARY KEY  (id),
            KEY cart_id (cart_id)
        ) $charset_collate;";

        $recovered_sql = "CREATE TABLE $recovered (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cart_token varchar(100) NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            cart_contents longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            session_start datetime DEFAULT NULL,
            abandoned_at datetime DEFAULT NULL,
            browsing_time bigint(20) unsigned DEFAULT 0,
            revisit_count int DEFAULT 0,
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

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($carts_sql);
        dbDelta($queue_sql);
        dbDelta($recovered_sql);
    }

    public function run() {
        $this->maybe_install();

        add_action('woocommerce_add_to_cart', [$this, 'capture_cart'], 10, 6);
        add_action('woocommerce_update_cart_action_cart_updated', [$this, 'capture_cart']);
        add_action('woocommerce_cart_loaded_from_session', [$this, 'capture_cart']);
        add_action('woocommerce_thankyou', [$this, 'mark_cart_recovered']);
    }

    public function capture_cart() {
        if (function_exists('current_user_can') && current_user_can('manage_options')) {
            return;
        }
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
                'sku'   => $product ? $product->get_sku() : '',
            ];
        }
        $contents   = wp_json_encode($cart_items);

        $wc = WC();
        if (!is_object($wc->session)) {
            return;
        }
        $token      = $wc->session->get_customer_id();
        $agent      = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $browser    = self::get_browser($agent);
        $ip_info    = self::get_ip_and_location();
        $ip         = $ip_info['ip'];
        $location   = $ip_info['location'];
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
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
        $current_url = home_url($request_uri);

        $stored_entry = '';
        if (isset($_COOKIE['gm2_entry_url'])) {
            $stored_entry = esc_url_raw(wp_unslash($_COOKIE['gm2_entry_url']));
        } else {
            $session_entry = $wc->session->get('gm2_entry_url');
            if (!empty($session_entry)) {
                $stored_entry = esc_url_raw($session_entry);
                $wc->session->set('gm2_entry_url', null);
            }
        }
        if (!empty($stored_entry)) {
            $current_url = $stored_entry;
        }
        $total      = (float) $cart->get_cart_contents_total();
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, entry_url, exit_url, abandoned_at, revisit_count, session_start FROM $table WHERE cart_token = %s",
                $token
            )
        );
        if ($row) {
            $update = [
                'cart_contents' => $contents,
                'created_at'    => current_time('mysql'),
                'ip_address'    => $ip,
                'user_agent'    => $agent,
                'browser'       => $browser,
                'location'      => $location,
                'device'        => $device,
                'cart_total'    => $total,
            ];
            if ($row->abandoned_at) {
                $update['abandoned_at']  = null;
                $update['session_start'] = current_time('mysql');
                $update['revisit_count'] = (int) $row->revisit_count + 1;
            } elseif (!$row->session_start) {
                $update['session_start'] = current_time('mysql');
            }
            $wpdb->update($table, $update, ['id' => $row->id]);
            $url_update = [];
            if (empty($row->entry_url)) {
                $url_update['entry_url'] = $current_url;
            }
            if (empty($row->exit_url)) {
                $url_update['exit_url'] = $current_url;
            }
            if (!empty($url_update)) {
                $wpdb->update($table, $url_update, ['id' => $row->id]);
            }
        } else {
            $wpdb->insert($table, [
                'cart_token'    => $token,
                'user_id'       => get_current_user_id(),
                'cart_contents' => $contents,
                'created_at'    => current_time('mysql'),
                'session_start' => current_time('mysql'),
                'ip_address'    => $ip,
                'user_agent'    => $agent,
                'browser'       => $browser,
                'location'      => $location,
                'device'        => $device,
                'entry_url'     => $current_url,
                'exit_url'      => $current_url,
                'cart_total'    => $total,
                'browsing_time' => 0,
                'revisit_count' => 0,
            ]);
        }
    }

    private static function log_no_cart($action) {
        $message = sprintf('Gm2 Abandoned Carts: %s called without cart token.', $action);
        error_log($message);
        if (is_admin()) {
            add_action('admin_notices', function () use ($message) {
                echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
            });
        }
    }

    public static function gm2_ac_mark_active() {
        if (function_exists('current_user_can') && current_user_can('manage_options')) {
            wp_send_json_success();
        }
        check_ajax_referer('gm2_ac_activity', 'nonce');

        $url   = esc_url_raw($_POST['url'] ?? '');
        $token = '';
        if (class_exists('WC_Session') && WC()->session) {
            $token = WC()->session->get_customer_id();
            if (method_exists(WC()->session, 'set')) {
                // store the most recent URL in the session instead of updating the database
                WC()->session->set('gm2_ac_last_url', $url);
                $session_entry = method_exists(WC()->session, 'get') ? WC()->session->get('gm2_entry_url') : '';
                if (empty($session_entry) && !empty($url)) {
                    WC()->session->set('gm2_entry_url', $url);
                    if (isset($_COOKIE['gm2_entry_url'])) {
                        setcookie('gm2_entry_url', '', time() - HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
                        unset($_COOKIE['gm2_entry_url']);
                    }
                }
            }
        }
        if (empty($token)) {
            self::log_no_cart(__FUNCTION__);
            wp_send_json_error('no_cart');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, abandoned_at, revisit_count, session_start FROM $table WHERE cart_token = %s", $token));
        if ($row) {
            $update = [];
            if ($row->abandoned_at) {
                $update['abandoned_at']  = null;
                $update['session_start'] = current_time('mysql');
                $update['revisit_count'] = (int) $row->revisit_count + 1;
            } elseif (!$row->session_start) {
                $update['session_start'] = current_time('mysql');
            }
            if (!empty($update)) {
                $wpdb->update($table, $update, [ 'id' => $row->id ]);
            }
        }

        wp_send_json_success();
    }

    public static function gm2_ac_mark_abandoned() {
        if (function_exists('current_user_can') && current_user_can('manage_options')) {
            wp_send_json_success();
        }
        check_ajax_referer('gm2_ac_activity', 'nonce');

        $token = '';
        $url   = esc_url_raw($_POST['url'] ?? '');
        if (class_exists('WC_Session') && WC()->session) {
            $token = WC()->session->get_customer_id();
            if (method_exists(WC()->session, 'get')) {
                $session_url = WC()->session->get('gm2_ac_last_url');
                WC()->session->set('gm2_ac_last_url', null);
                if (empty($url)) {
                    $url = $session_url;
                }
            }
        }
        if (empty($token)) {
            self::log_no_cart(__FUNCTION__);
            wp_send_json_error('no_cart');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, session_start, browsing_time FROM $table WHERE cart_token = %s", $token));
        if ($row) {
            $update = [
                'exit_url'     => $url,
                'abandoned_at' => current_time('mysql'),
            ];
            if ($row->session_start) {
                $elapsed = time() - strtotime($row->session_start);
                if ($elapsed < 0) {
                    $elapsed = 0;
                }
                $update['browsing_time'] = (int) $row->browsing_time + $elapsed;
            }
            $update['session_start'] = null;
            $wpdb->update($table, $update, [ 'id' => $row->id ]);
        }

        wp_send_json_success();
    }

    public function mark_cart_recovered($order_id) {
        if (!$order_id) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        $token = WC()->session->get_customer_id();
        global $wpdb;
        $carts_table = $wpdb->prefix . 'wc_ac_carts';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $carts_table WHERE cart_token = %s", $token),
            ARRAY_A
        );
        if ($row) {
            if (empty($row['email'])) {
                $row['email'] = $order->get_billing_email();
            }
            if (empty($row['location'])) {
                $country = $order->get_billing_country();
                $state   = $order->get_billing_state();
                $row['location'] = $country;
                if (!empty($state)) {
                    $row['location'] .= '-' . $state;
                }
            }
            $recovered_table = $wpdb->prefix . 'wc_ac_recovered';
            $row['recovered_order_id'] = $order_id;
            $wpdb->insert($recovered_table, $row);
            $wpdb->delete($carts_table, [ 'id' => $row['id'] ]);
        }
    }

    public function migrate_recovered_carts() {
        global $wpdb;
        $carts_table = $wpdb->prefix . 'wc_ac_carts';
        $recovered_table = $wpdb->prefix . 'wc_ac_recovered';
        $rows = $wpdb->get_results("SELECT * FROM $carts_table WHERE recovered_order_id IS NOT NULL");
        $count = 0;
        if ($rows) {
            foreach ($rows as $row) {
                $data = (array) $row;
                $wpdb->insert($recovered_table, $data);
                $wpdb->delete($carts_table, [ 'id' => $row->id ]);
                $count++;
            }
        }
        return $count;
    }

    private function maybe_install() {
        global $wpdb;
        $carts_table = $wpdb->prefix . 'wc_ac_carts';
        $queue_table = $wpdb->prefix . 'wc_ac_email_queue';
        $recovered_table = $wpdb->prefix . 'wc_ac_recovered';
        $carts_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $carts_table));
        $queue_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $queue_table));
        $recovered_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $recovered_table));
        if ($carts_exists !== $carts_table || $queue_exists !== $queue_table || $recovered_exists !== $recovered_table) {
            $this->install();
        } else {
            $has_browser = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $carts_table LIKE %s", 'browser'));
            if (!$has_browser) {
                $wpdb->query("ALTER TABLE $carts_table ADD browser varchar(50) DEFAULT NULL AFTER user_agent");
            }
            $has_session_start = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $carts_table LIKE %s", 'session_start'));
            if (!$has_session_start) {
                $wpdb->query("ALTER TABLE $carts_table ADD session_start datetime DEFAULT NULL AFTER created_at");
            }
            $has_browsing_time = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $carts_table LIKE %s", 'browsing_time'));
            if (!$has_browsing_time) {
                $wpdb->query("ALTER TABLE $carts_table ADD browsing_time bigint(20) unsigned DEFAULT 0 AFTER exit_url");
            }
            $has_revisit_count = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $carts_table LIKE %s", 'revisit_count'));
            if (!$has_revisit_count) {
                $wpdb->query("ALTER TABLE $carts_table ADD revisit_count int DEFAULT 0 AFTER browsing_time");
            }
        }
    }

    public static function get_ip_and_location() {
        $ip = '';
        if (function_exists('wc_get_user_ip')) {
            $ip = wc_get_user_ip();
        } elseif (class_exists('WC_Geolocation')) {
            $ip = \WC_Geolocation::get_ip_address();
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        $ip = sanitize_text_field(wp_unslash($ip));
        $location = '';
        if (class_exists('WC_Geolocation') && !empty($ip)) {
            $geo = \WC_Geolocation::geolocate_ip($ip, false, false);
            if (!empty($geo['country'])) {
                $location = $geo['country'];
                if (!empty($geo['state'])) {
                    $location .= '-' . $geo['state'];
                }
            }
        }
        return [ 'ip' => $ip, 'location' => $location ];
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

    /**
     * Schedule the recurring cron event used to mark carts abandoned.
     */
    public static function schedule_event() {
        if (get_option('gm2_enable_abandoned_carts', '0') !== '1') {
            return;
        }
        $minutes = absint(apply_filters('gm2_ac_mark_abandoned_interval', (int) get_option('gm2_ac_mark_abandoned_interval', 5)));
        if ($minutes < 1) {
            $minutes = 1;
        }
        if (!wp_next_scheduled('gm2_ac_mark_abandoned_cron')) {
            wp_schedule_event(time(), 'gm2_ac_' . $minutes . '_mins', 'gm2_ac_mark_abandoned_cron');
        }
    }

    /**
     * Cron callback to mark carts as abandoned after the configured interval.
     */
    public static function cron_mark_abandoned() {
        if (get_option('gm2_enable_abandoned_carts', '0') !== '1') {
            return;
        }
        $minutes = absint(apply_filters('gm2_ac_mark_abandoned_interval', (int) get_option('gm2_ac_mark_abandoned_interval', 5)));
        if ($minutes < 1) {
            $minutes = 1;
        }
        $threshold = gmdate('Y-m-d H:i:s', time() - $minutes * MINUTE_IN_SECONDS);
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, session_start, browsing_time FROM $table WHERE abandoned_at IS NULL AND session_start IS NOT NULL AND session_start <= %s", $threshold));
        if ($rows) {
            foreach ($rows as $row) {
                $update = [
                    'abandoned_at' => current_time('mysql'),
                    'session_start' => null,
                ];
                if ($row->session_start) {
                    $elapsed = time() - strtotime($row->session_start);
                    if ($elapsed < 0) {
                        $elapsed = 0;
                    }
                    $update['browsing_time'] = (int) $row->browsing_time + $elapsed;
                }
                $wpdb->update($table, $update, ['id' => $row->id]);
            }
        }
    }

    /**
     * Clear the scheduled cron event for marking carts abandoned.
     */
    public static function clear_scheduled_event() {
        if (wp_next_scheduled('gm2_ac_mark_abandoned_cron')) {
            wp_clear_scheduled_hook('gm2_ac_mark_abandoned_cron');
        }
    }
}

add_action('gm2_ac_mark_abandoned_cron', [Gm2_Abandoned_Carts::class, 'cron_mark_abandoned']);

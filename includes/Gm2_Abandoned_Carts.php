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
        add_action('woocommerce_add_to_cart', [$this, 'capture_cart'], 10, 6);
        add_action('woocommerce_update_cart_action_cart_updated', [$this, 'capture_cart']);
        add_action('template_redirect', [$this, 'maybe_mark_cart_abandoned']);
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
        $contents   = maybe_serialize($cart->get_cart());
        $token      = WC()->session->get_customer_id();
        $ip         = $_SERVER['REMOTE_ADDR'] ?? '';
        $agent      = $_SERVER['HTTP_USER_AGENT'] ?? '';
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
                'entry_url'    => $current_url,
                'cart_total'   => $total,
            ]);
        }
    }

    public function maybe_mark_cart_abandoned() {
        // Update the exit URL for the current visitor
        $token = '';
        if (class_exists('WC_Session') && WC()->session) {
            $token = WC()->session->get_customer_id();
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        if (!empty($token)) {
            $wpdb->update(
                $table,
                ['exit_url' => home_url($_SERVER['REQUEST_URI'] ?? '/')],
                ['cart_token' => $token]
            );
        }

        // Mark carts without orders after timeout
        $timeout = absint(get_option('gm2_ac_timeout', 60));
        $threshold = gmdate('Y-m-d H:i:s', time() - $timeout * 60);
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table SET abandoned_at = %s WHERE abandoned_at IS NULL AND cart_contents <> '' AND created_at <= %s",
                current_time('mysql'),
                $threshold
            )
        );
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
}

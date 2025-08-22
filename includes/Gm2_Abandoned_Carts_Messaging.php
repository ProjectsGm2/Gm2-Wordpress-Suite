<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Abandoned_Carts_Messaging {
    const MAX_ATTEMPTS = 3;

    public function run() {
        add_action('gm2_ac_process_queue', [ $this, 'process_queue' ]);
    }

    public function queue_email($cart_id, $send_at) {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_email_queue';
        $wpdb->insert($table, [
            'cart_id'      => $cart_id,
            'send_at'      => gmdate('Y-m-d H:i:s', $send_at),
            'sent'         => 0,
            'attempts'     => 0,
            'message_type' => 'email',
        ]);
        if (!wp_next_scheduled('gm2_ac_process_queue')) {
            wp_schedule_event(time(), 'hourly', 'gm2_ac_process_queue');
        }
    }

    public function process_queue() {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_email_queue';
        $max_attempts = apply_filters('gm2_ac_max_attempts', self::MAX_ATTEMPTS);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE sent = 0 AND attempts < %d AND send_at <= %s", $max_attempts, current_time('mysql')));
        foreach ($rows as $row) {
            try {
                do_action('gm2_ac_send_message', $row);
                $wpdb->update($table, ['sent' => 1], ['id' => $row->id]);
            } catch (\Throwable $e) {
                $wpdb->query($wpdb->prepare("UPDATE $table SET attempts = attempts + 1 WHERE id = %d", $row->id));
            }
        }
    }

    public function reprocess_failed_messages() {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_email_queue';
        $max_attempts = apply_filters('gm2_ac_max_attempts', self::MAX_ATTEMPTS);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE sent = 0 AND attempts >= %d", $max_attempts));
        $count = 0;
        foreach ($rows as $row) {
            try {
                do_action('gm2_ac_send_message', $row);
                $wpdb->update($table, ['sent' => 1], ['id' => $row->id]);
                $count++;
            } catch (\Throwable $e) {
                $wpdb->query($wpdb->prepare("UPDATE $table SET attempts = attempts + 1 WHERE id = %d", $row->id));
            }
        }
        return $count;
    }
}

function gm2_ac_send_default_email($row) {
    global $wpdb;
    $carts_table = $wpdb->prefix . 'wc_ac_carts';
    $cart = $wpdb->get_row($wpdb->prepare("SELECT cart_token, email, cart_contents FROM $carts_table WHERE id = %d", $row->cart_id));
    if (!$cart || empty($cart->email)) {
        return;
    }

    $subject = __('We saved your cart for you', 'gm2-wordpress-suite');
    $subject = apply_filters('gm2_ac_default_email_subject', $subject, $cart, $row);

    $recover_url = wc_get_cart_url();
    $content = sprintf(__('It looks like you left some items in your cart. <a href="%s">Return to your cart</a> to complete your order.', 'gm2-wordpress-suite'), esc_url($recover_url));
    $content = apply_filters('gm2_ac_default_email_body', $content, $cart, $row);

    ob_start();
    do_action('woocommerce_email_header', $subject, null);
    echo wpautop($content);
    do_action('woocommerce_email_footer', null);
    $message = ob_get_clean();

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    wp_mail($cart->email, $subject, $message, $headers);
}

add_action('gm2_ac_send_message', __NAMESPACE__ . '\\gm2_ac_send_default_email');

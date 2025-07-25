<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Abandoned_Carts_Messaging {
    public function run() {
        add_action('gm2_ac_process_queue', [ $this, 'process_queue' ]);
    }

    public function queue_email($cart_id, $send_at) {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_email_queue';
        $wpdb->insert($table, [
            'cart_id'    => $cart_id,
            'send_at'    => gmdate('Y-m-d H:i:s', $send_at),
            'sent'       => 0,
            'message_type' => 'email',
        ]);
        if (!wp_next_scheduled('gm2_ac_process_queue')) {
            wp_schedule_event(time(), 'hourly', 'gm2_ac_process_queue');
        }
    }

    public function process_queue() {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_email_queue';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE sent = 0 AND send_at <= %s", current_time('mysql')));
        foreach ($rows as $row) {
            do_action('gm2_ac_send_message', $row);
            $wpdb->update($table, ['sent' => 1], ['id' => $row->id]);
        }
    }
}

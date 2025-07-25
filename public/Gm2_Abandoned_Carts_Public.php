<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Abandoned_Carts_Public {
    public function run() {
        add_action('wp_enqueue_scripts', [ $this, 'enqueue_scripts' ]);
        add_action('wp_ajax_gm2_ac_email_capture', [ $this, 'handle_email_capture' ]);
        add_action('wp_ajax_nopriv_gm2_ac_email_capture', [ $this, 'handle_email_capture' ]);
    }

    public function enqueue_scripts() {
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
                'nonce'    => wp_create_nonce('gm2_ac_email_capture'),
            ]
        );
    }

    public function handle_email_capture() {
        check_ajax_referer('gm2_ac_email_capture', 'nonce');

        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email)) {
            wp_send_json_error('empty_email');
        }

        $token = '';
        if (class_exists('WC_Session') && WC()->session) {
            $token = WC()->session->get_customer_id();
        }

        if (empty($token)) {
            wp_send_json_error('no_cart');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        $row   = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE cart_token = %s", $token));
        if ($row) {
            $wpdb->update($table, [ 'email' => $email ], [ 'id' => $row->id ]);
        } else {
            $wpdb->insert($table, [
                'cart_token'    => $token,
                'user_id'       => get_current_user_id(),
                'cart_contents' => '',
                'created_at'    => current_time('mysql'),
                'email'         => $email,
            ]);
        }

        wp_send_json_success();
    }
}

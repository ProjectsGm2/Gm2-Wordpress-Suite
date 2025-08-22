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
        /**
         * Filters the inactivity timeout (in milliseconds) before a cart is marked abandoned.
         *
         * Returning `0` disables inactivity tracking entirely.
         *
         * @param int|null $milliseconds Time in milliseconds. Default 5 minutes.
         */
        $inactivity_ms = apply_filters('gm2_ac_inactivity_ms', 5 * MINUTE_IN_SECONDS * 1000);
        wp_localize_script(
            'gm2-ac-activity',
            'gm2AcActivity',
            [
                'ajax_url'      => admin_url('admin-ajax.php'),
                'nonce'         => wp_create_nonce('gm2_ac_activity'),
                'inactivity_ms' => $inactivity_ms,
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
        $client_id        = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : (isset($_COOKIE['gm2_ac_client_id']) ? sanitize_text_field(wp_unslash($_COOKIE['gm2_ac_client_id'])) : '');
        if (class_exists('WC_Session') && WC()->session) {
            $token            = WC()->session->get_customer_id();
            $session_entry_url = WC()->session->get('gm2_entry_url');
        }

        if (empty($token) && empty($client_id)) {
            wp_send_json_error('no_cart');
        }

        $stored_entry = '';
        if (!empty($session_entry_url)) {
            $stored_entry = esc_url_raw($session_entry_url);
            WC()->session->set('gm2_entry_url', null);
        } elseif (isset($_COOKIE['gm2_entry_url'])) {
            $stored_entry = esc_url_raw(wp_unslash($_COOKIE['gm2_entry_url']));
        }

        $host        = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $scheme      = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
        $current_url = $stored_entry ?: esc_url_raw($scheme . $host . $request_uri);

        $agent   = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $browser = Gm2_Abandoned_Carts::get_browser($agent);

        global $wpdb;
        $table = $wpdb->prefix . 'wc_ac_carts';
        if (!empty($client_id)) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, entry_url FROM $table WHERE client_id = %s", $client_id));
            if (!$row && !empty($token)) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT id, entry_url FROM $table WHERE cart_token = %s", $token));
            }
        } else {
            $row   = $wpdb->get_row($wpdb->prepare("SELECT id, entry_url FROM $table WHERE cart_token = %s", $token));
        }
        if ($row) {
            $update = [
                'email'      => $email,
                'phone'      => $phone,
                'browser'    => $browser,
                'user_agent' => $agent,
            ];
            if (!empty($client_id)) {
                $update['client_id'] = $client_id;
            }
            $format = [ '%s', '%s', '%s', '%s' ];
            if (!empty($client_id)) {
                $format[] = '%s';
            }
            $wpdb->update(
                $table,
                $update,
                [ 'id' => $row->id ],
                $format,
                [ '%d' ]
            );
            if (empty($row->entry_url) && !empty($stored_entry)) {
                $wpdb->update(
                    $table,
                    [ 'entry_url' => $stored_entry ],
                    [ 'id' => $row->id ],
                    [ '%s' ],
                    [ '%d' ]
                );
            }
        } else {
            $snapshot   = Gm2_Abandoned_Carts::get_cart_snapshot($agent);
            $cart_items = $snapshot['items'];
            if (empty($cart_items)) {
                wp_send_json_error('no_cart');
            }
            $contents = wp_json_encode($cart_items);
            $total    = $snapshot['total'];
            $device   = $snapshot['device'];
            $ip_info  = Gm2_Abandoned_Carts::get_ip_and_location();
            $ip       = $ip_info['ip'];
            $location = $ip_info['location'];

            $insert = [
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
            ];
            if (!empty($client_id)) {
                $insert['client_id'] = $client_id;
            }
            $format = [
                '%s', // cart_token
                '%d', // user_id
                '%s', // cart_contents
                '%s', // created_at
                '%s', // ip_address
                '%s', // user_agent
                '%s', // browser
                '%s', // location
                '%s', // device
                '%s', // entry_url
                '%f', // cart_total
                '%s', // email
                '%s', // phone
            ];
            if (!empty($client_id)) {
                $format[] = '%s';
            }
            $wpdb->insert($table, $insert, $format);
        }

        wp_send_json_success();
    }
}

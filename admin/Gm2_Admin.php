<?php

namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Admin {

    public function run() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_gm2_add_tariff', [$this, 'ajax_add_tariff']);
        add_action('wp_ajax_nopriv_gm2_add_tariff', [$this, 'ajax_add_tariff']);
        add_action('admin_post_gm2_chatgpt_settings', [$this, 'handle_chatgpt_form']);
        add_action('wp_ajax_gm2_chatgpt_prompt', [$this, 'ajax_chatgpt_prompt']);
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook === 'gm2_page_gm2-tariff') {
            wp_enqueue_script(
                'gm2-tariff',
                GM2_PLUGIN_URL . 'admin/js/gm2-tariff.js',
                ['jquery'],
                GM2_VERSION,
                true
            );
            wp_localize_script(
                'gm2-tariff',
                'gm2Tariff',
                [
                    // Fresh nonce for each page load
                    'nonce'    => wp_create_nonce('gm2_add_tariff'),
                    'ajax_url' => admin_url('admin-ajax.php'),
                ]
            );
        }

        $seo_pages = [
            'gm2_page_gm2-seo',
        ];

        if ($hook === 'gm2_page_gm2-chatgpt') {
            wp_enqueue_script(
                'gm2-chatgpt',
                GM2_PLUGIN_URL . 'admin/js/gm2-chatgpt.js',
                ['jquery'],
                GM2_VERSION,
                true
            );
            wp_localize_script(
                'gm2-chatgpt',
                'gm2ChatGPT',
                [
                    'nonce'    => wp_create_nonce('gm2_chatgpt_nonce'),
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'loading'  => __( 'Loading...', 'gm2-wordpress-suite' ),
                    'error'    => __( 'Error', 'gm2-wordpress-suite' ),
                ]
            );
        }

        if (in_array($hook, $seo_pages, true)) {
            wp_enqueue_style(
                'gm2-seo-style',
                GM2_PLUGIN_URL . 'admin/css/gm2-seo.css',
                [],
                GM2_VERSION
            );
            wp_enqueue_script(
                'gm2-seo',
                GM2_PLUGIN_URL . 'admin/js/gm2-seo.js',
                ['jquery'],
                GM2_VERSION,
                true
            );
            wp_enqueue_script(
                'gm2-keyword-research',
                GM2_PLUGIN_URL . 'admin/js/gm2-keyword-research.js',
                ['jquery'],
                GM2_VERSION,
                true
            );
            wp_localize_script(
                'gm2-keyword-research',
                'gm2KeywordResearch',
                [
                    'nonce'    => wp_create_nonce('gm2_keyword_ideas'),
                    'ajax_url' => admin_url('admin-ajax.php'),
                ]
            );
        }
    }

    public function ajax_add_tariff() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error( __( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        check_ajax_referer('gm2_add_tariff');

        $name = sanitize_text_field($_POST['tariff_name'] ?? '');
        if ($name === '') {
            wp_send_json_error( __( 'Tariff name is required', 'gm2-wordpress-suite' ) );
        }

        $percentage_raw = $_POST['tariff_percentage'] ?? '';

        if (!is_numeric($percentage_raw)) {
            wp_send_json_error( __( 'Tariff percentage must be a number', 'gm2-wordpress-suite' ) );
        }

        $percentage = floatval($percentage_raw);

        if ($percentage < 0 || $percentage > 100) {
            wp_send_json_error( __( 'Tariff percentage must be between 0 and 100', 'gm2-wordpress-suite' ) );
        }
        $status = ($_POST['tariff_status'] ?? '') === 'enabled' ? 'enabled' : 'disabled';

        $manager = new Gm2_Tariff_Manager();
        $id      = $manager->add_tariff([
            'name'       => $name,
            'percentage' => $percentage,
            'status'     => $status,
        ]);

        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=gm2-tariff&action=delete&id=' . $id),
            'gm2_delete_tariff_' . $id
        );
        $edit_url = admin_url('admin.php?page=gm2-add-tariff&id=' . $id);

        wp_send_json_success([
            'id'         => $id,
            'name'       => $name,
            'percentage' => $percentage,
            'status'     => $status,
            'delete_url' => $delete_url,
            'edit_url'   => $edit_url,
        ]);
    }

    public function add_admin_menu() {
        add_menu_page(
            esc_html__( 'Gm2', 'gm2-wordpress-suite' ),
            esc_html__( 'Gm2', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2',
            [$this, 'display_dashboard'],
            'dashicons-admin-generic'
        );

        add_submenu_page(
            'gm2',
            esc_html__( 'Tariff', 'gm2-wordpress-suite' ),
            esc_html__( 'Tariff', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-tariff',
            [$this, 'display_tariff_page']
        );


        // The add tariff form is now part of the Tariff page. The following
        // submenu is kept for editing existing tariffs but hidden from the
        // menu by setting the parent slug to null.
        add_submenu_page(
            null,
            esc_html__( 'Edit Tariff', 'gm2-wordpress-suite' ),
            esc_html__( 'Edit Tariff', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-add-tariff',
            [$this, 'display_add_tariff_page']
        );

        add_submenu_page(
            'gm2',
            esc_html__( 'Google OAuth Setup', 'gm2-wordpress-suite' ),
            esc_html__( 'Google OAuth Setup', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-google-oauth-setup',
            [ $this, 'display_google_oauth_setup_page' ]
        );

        add_submenu_page(
            'gm2',
            esc_html__( 'ChatGPT', 'gm2-wordpress-suite' ),
            esc_html__( 'ChatGPT', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-chatgpt',
            [ $this, 'display_chatgpt_page' ]
        );
    }

    public function display_dashboard() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Gm2 Suite', 'gm2-wordpress-suite' ) . '</h1><p>' . esc_html__( 'Welcome to the admin interface!', 'gm2-wordpress-suite' ) . '</p></div>';
    }

    private function handle_form_submission() {
        if (!empty($_POST['gm2_tariff_nonce']) && wp_verify_nonce($_POST['gm2_tariff_nonce'], 'gm2_save_tariff')) {
            $manager = new Gm2_Tariff_Manager();
            $data    = [
                'name'       => sanitize_text_field($_POST['tariff_name']),
                'percentage' => floatval($_POST['tariff_percentage']),
                'status'     => isset($_POST['tariff_status']) ? 'enabled' : 'disabled',
            ];

            if (!empty($_POST['tariff_id'])) {
                $manager->update_tariff(sanitize_text_field($_POST['tariff_id']), $data);
            } else {
                $manager->add_tariff($data);
            }
            echo '<div class="updated"><p>' . esc_html__('Tariff saved.', 'gm2-wordpress-suite') . '</p></div>';
        }
    }

    public function display_add_tariff_page() {
        $this->handle_form_submission();

        $tariff = false;
        if (!empty($_GET['id'])) {
            $manager = new Gm2_Tariff_Manager();
            $tariff  = $manager->get_tariff(sanitize_text_field($_GET['id']));
        }

        $name       = $tariff ? esc_attr($tariff['name']) : '';
        $percentage = $tariff ? esc_attr($tariff['percentage']) : '';
        $status     = $tariff ? $tariff['status'] : 'enabled';
        $id_field   = $tariff ? '<input type="hidden" name="tariff_id" value="' . esc_attr($tariff['id']) . '" />' : '';

        echo '<div class="wrap"><h1>' . esc_html__( 'Edit Tariff', 'gm2-wordpress-suite' ) . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('gm2_save_tariff', 'gm2_tariff_nonce');
        echo $id_field;
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="tariff_name">' . esc_html__( 'Name', 'gm2-wordpress-suite' ) . '</label></th><td><input name="tariff_name" type="text" id="tariff_name" value="' . $name . '" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row"><label for="tariff_percentage">' . esc_html__( 'Percentage', 'gm2-wordpress-suite' ) . '</label></th><td><input name="tariff_percentage" type="number" step="0.01" id="tariff_percentage" value="' . $percentage . '" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Status', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="tariff_status"' . checked($status, 'enabled', false) . '> ' . esc_html__( 'Enabled', 'gm2-wordpress-suite' ) . '</label></td></tr>';
        echo '</tbody></table>';
        submit_button( esc_html__( 'Save Tariff', 'gm2-wordpress-suite' ) );
        echo '</form></div>';
    }

    public function display_tariff_page() {
        $manager = new Gm2_Tariff_Manager();

        if (!empty($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
            $id = sanitize_text_field($_GET['id']);
            check_admin_referer('gm2_delete_tariff_' . $id);
            $manager->delete_tariff($id);
            echo '<div class="updated"><p>' . esc_html__('Tariff deleted.', 'gm2-wordpress-suite') . '</p></div>';
        }

        $tariffs = $manager->get_tariffs();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Tariffs', 'gm2-wordpress-suite' ) . '</h1>';
        echo '<hr class="wp-header-end">';

        echo '<h2>' . esc_html__( 'Add Tariff', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<div class="notice notice-success hidden" id="gm2-tariff-msg"></div>';
        echo '<form id="gm2-add-tariff-form">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="tariff_name">' . esc_html__( 'Name', 'gm2-wordpress-suite' ) . '</label></th><td><input name="tariff_name" type="text" id="tariff_name" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row"><label for="tariff_percentage">' . esc_html__( 'Percentage', 'gm2-wordpress-suite' ) . '</label></th><td><input name="tariff_percentage" type="number" step="0.01" id="tariff_percentage" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Status', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="tariff_status" id="tariff_status" checked> ' . esc_html__( 'Enabled', 'gm2-wordpress-suite' ) . '</label></td></tr>';
        echo '</tbody></table>';
        submit_button( esc_html__( 'Add Tariff', 'gm2-wordpress-suite' ) );
        echo '</form>';

        echo '<h2>' . esc_html__( 'Existing Tariffs', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<table class="widefat" id="gm2-tariff-table"><thead><tr><th>' . esc_html__( 'Name', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Percentage', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Status', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Actions', 'gm2-wordpress-suite' ) . '</th></tr></thead><tbody>';
        if ($tariffs) {
            foreach ($tariffs as $tariff) {
                $delete_url = wp_nonce_url(admin_url('admin.php?page=gm2-tariff&action=delete&id=' . $tariff['id']), 'gm2_delete_tariff_' . $tariff['id']);
                $edit_url   = admin_url('admin.php?page=gm2-add-tariff&id=' . $tariff['id']);
                echo '<tr>';
                echo '<td>' . esc_html($tariff['name']) . '</td>';
                echo '<td>' . esc_html($tariff['percentage']) . '%</td>';
                echo '<td>' . esc_html(ucfirst($tariff['status'])) . '</td>';
                echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'View', 'gm2-wordpress-suite' ) . '</a> | <a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'gm2-wordpress-suite' ) . '</a> | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure?', 'gm2-wordpress-suite' ) ) . '\');">' . esc_html__( 'Delete', 'gm2-wordpress-suite' ) . '</a></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="4">' . esc_html__( 'No tariffs found.', 'gm2-wordpress-suite' ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function display_google_oauth_setup_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        $notice = '';
        if ( isset( $_POST['gm2_gads_oauth_setup_nonce'] ) && wp_verify_nonce( $_POST['gm2_gads_oauth_setup_nonce'], 'gm2_gads_oauth_setup_save' ) ) {
            $client_id     = isset( $_POST['gm2_gads_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['gm2_gads_client_id'] ) ) : '';
            $client_secret = isset( $_POST['gm2_gads_client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['gm2_gads_client_secret'] ) ) : '';

            update_option( 'gm2_gads_client_id', $client_id );
            update_option( 'gm2_gads_client_secret', $client_secret );

            $notice = '<div class="updated notice"><p>' . esc_html__( 'Settings saved.', 'gm2-wordpress-suite' ) . '</p></div>';
        }

        $client_id     = get_option( 'gm2_gads_client_id', '' );
        $client_secret = get_option( 'gm2_gads_client_secret', '' );
        $redirect      = admin_url( 'admin.php?page=gm2-google-connect' );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Google OAuth Setup', 'gm2-wordpress-suite' ) . '</h1>';
        echo $notice;
        echo '<p>' . esc_html__( 'Follow these steps to create OAuth credentials on the Google Cloud console:', 'gm2-wordpress-suite' ) . '</p>';
        echo '<ol>';
        echo '<li>' . esc_html__( 'Open the Google Cloud console and create a new project.', 'gm2-wordpress-suite' ) . '</li>';
        echo '<li>' . esc_html__( 'Enable the Google Ads API and other required APIs.', 'gm2-wordpress-suite' ) . '</li>';
        echo '<li>' . esc_html__( 'Create OAuth client ID credentials for a Web application.', 'gm2-wordpress-suite' ) . '</li>';
        echo '<li>' . sprintf( esc_html__( 'Set the authorized redirect URI to %s.', 'gm2-wordpress-suite' ), esc_url( $redirect ) ) . '</li>';
        echo '<li>' . esc_html__( 'Copy the client ID and client secret into the fields below.', 'gm2-wordpress-suite' ) . '</li>';
        echo '</ol>';

        echo '<form method="post">';
        wp_nonce_field( 'gm2_gads_oauth_setup_save', 'gm2_gads_oauth_setup_nonce' );
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="gm2_gads_client_id">' . esc_html__( 'Client ID', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input name="gm2_gads_client_id" type="text" id="gm2_gads_client_id" value="' . esc_attr( $client_id ) . '" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row"><label for="gm2_gads_client_secret">' . esc_html__( 'Client Secret', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input name="gm2_gads_client_secret" type="text" id="gm2_gads_client_secret" value="' . esc_attr( $client_secret ) . '" class="regular-text" required></td></tr>';
        echo '</tbody></table>';
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function display_chatgpt_page() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        $key   = get_option('gm2_chatgpt_api_key', '');
        $model = get_option('gm2_chatgpt_model', 'gpt-3.5-turbo');
        $temperature = get_option('gm2_chatgpt_temperature', '1.0');
        $max_tokens  = get_option('gm2_chatgpt_max_tokens', '');
        $endpoint    = get_option('gm2_chatgpt_endpoint', 'https://api.openai.com/v1/chat/completions');
        $notice = '';
        if (!empty($_GET['updated'])) {
            $notice = '<div class="updated notice"><p>' . esc_html__('Settings saved.', 'gm2-wordpress-suite') . '</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'ChatGPT', 'gm2-wordpress-suite' ) . '</h1>';
        echo $notice;
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        wp_nonce_field('gm2_chatgpt_settings');
        echo '<input type="hidden" name="action" value="gm2_chatgpt_settings" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="gm2_chatgpt_api_key">' . esc_html__( 'API Key', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input type="password" id="gm2_chatgpt_api_key" name="gm2_chatgpt_api_key" value="' . esc_attr($key) . '" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row"><label for="gm2_chatgpt_model">' . esc_html__( 'Model', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input type="text" id="gm2_chatgpt_model" name="gm2_chatgpt_model" value="' . esc_attr($model) . '" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row"><label for="gm2_chatgpt_temperature">' . esc_html__( 'Temperature', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input type="number" step="0.1" id="gm2_chatgpt_temperature" name="gm2_chatgpt_temperature" value="' . esc_attr($temperature) . '" class="small-text" /></td></tr>';
        echo '<tr><th scope="row"><label for="gm2_chatgpt_max_tokens">' . esc_html__( 'Max Tokens', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input type="number" id="gm2_chatgpt_max_tokens" name="gm2_chatgpt_max_tokens" value="' . esc_attr($max_tokens) . '" class="small-text" /></td></tr>';
        echo '<tr><th scope="row"><label for="gm2_chatgpt_endpoint">' . esc_html__( 'API Endpoint', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><input type="text" id="gm2_chatgpt_endpoint" name="gm2_chatgpt_endpoint" value="' . esc_attr($endpoint) . '" class="regular-text" /></td></tr>';
        echo '</tbody></table>';
        submit_button();
        echo '</form>';

        echo '<h2>' . esc_html__( 'Test Prompt', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<form id="gm2-chatgpt-form">';
        echo '<p><textarea id="gm2_chatgpt_prompt" rows="3" class="large-text"></textarea></p>';
        echo '<p><button class="button">' . esc_html__( 'Send', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '</form>';
        echo '<pre id="gm2-chatgpt-output"></pre>';
        echo '</div>';
    }

    public function handle_chatgpt_form() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        check_admin_referer('gm2_chatgpt_settings');
        $key = isset($_POST['gm2_chatgpt_api_key']) ? sanitize_text_field($_POST['gm2_chatgpt_api_key']) : '';
        $model = isset($_POST['gm2_chatgpt_model']) ? sanitize_text_field($_POST['gm2_chatgpt_model']) : '';
        $temperature = isset($_POST['gm2_chatgpt_temperature']) ? floatval($_POST['gm2_chatgpt_temperature']) : 1.0;
        $max_tokens  = isset($_POST['gm2_chatgpt_max_tokens']) ? intval($_POST['gm2_chatgpt_max_tokens']) : 0;
        $endpoint    = isset($_POST['gm2_chatgpt_endpoint']) ? esc_url_raw($_POST['gm2_chatgpt_endpoint']) : '';

        update_option('gm2_chatgpt_api_key', $key);
        update_option('gm2_chatgpt_model', $model);
        update_option('gm2_chatgpt_temperature', $temperature);
        update_option('gm2_chatgpt_max_tokens', $max_tokens);
        update_option('gm2_chatgpt_endpoint', $endpoint);

        wp_redirect(admin_url('admin.php?page=gm2-chatgpt&updated=1'));
        exit;
    }

    public function ajax_chatgpt_prompt() {
        check_ajax_referer('gm2_chatgpt_nonce');
        $prompt = sanitize_text_field($_POST['prompt'] ?? '');
        $chat = new Gm2_ChatGPT();
        $resp = $chat->query($prompt);
        if (is_wp_error($resp)) {
            wp_send_json_error($resp->get_error_message());
        }
        wp_send_json_success($resp);
    }
}

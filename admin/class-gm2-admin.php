<?php

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Admin {

    public function run() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Gm2 Suite',
            'Gm2 Suite',
            'manage_options',
            'gm2-suite',
            [$this, 'display_admin_page'],
            'dashicons-admin-generic'
        );

        add_submenu_page(
            'gm2-suite',
            'ChatGPT Settings',
            'ChatGPT Settings',
            'manage_options',
            'gm2-suite-chatgpt',
            [$this, 'display_chatgpt_page']
        );
    }

    public function display_admin_page() {
        echo '<div class="wrap"><h1>Gm2 WordPress Suite</h1><p>Welcome to the admin interface!</p></div>';
    }

    public function display_chatgpt_page() {
        $response = '';
        if (isset($_POST['gm2_chatgpt_submit'])) {
            check_admin_referer('gm2_chatgpt_settings');
            $api_key = sanitize_text_field($_POST['gm2_chatgpt_api_key']);
            update_option('gm2_chatgpt_api_key', $api_key);
            $prompt = self::sanitize_prompt($_POST['gm2_chatgpt_prompt']);
            if (!empty($prompt)) {
                $response = Gm2_ChatGPT::send_prompt($prompt);
            }
        }

        $saved_key = esc_attr(get_option('gm2_chatgpt_api_key', ''));

        echo '<div class="wrap">';
        echo '<h1>ChatGPT Settings</h1>';
        echo '<form method="post">';
        wp_nonce_field('gm2_chatgpt_settings');
        echo '<table class="form-table">';
        echo '<tr><th scope="row"><label for="gm2_chatgpt_api_key">API Key</label></th>';
        echo '<td><input type="text" id="gm2_chatgpt_api_key" name="gm2_chatgpt_api_key" value="' . $saved_key . '" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row"><label for="gm2_chatgpt_prompt">Prompt</label></th>';
        echo '<td><textarea id="gm2_chatgpt_prompt" name="gm2_chatgpt_prompt" rows="5" cols="50"></textarea></td></tr>';
        echo '</table>';
        submit_button('Save & Send', 'primary', 'gm2_chatgpt_submit');
        echo '</form>';
        if (!empty($response)) {
            echo '<h2>Response</h2><pre>' . esc_html($response) . '</pre>';
        }
        echo '</div>';
    }

    public static function sanitize_prompt($prompt) {
        if (function_exists('sanitize_textarea_field')) {
            return sanitize_textarea_field($prompt);
        }

        $prompt = str_replace("\r", '', $prompt);

        return trim($prompt);
    }
}

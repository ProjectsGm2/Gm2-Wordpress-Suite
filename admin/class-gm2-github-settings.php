<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Github_Settings {
    public function run() {
        add_action('admin_menu', [ $this, 'add_menu' ]);
        add_action('admin_init', [ $this, 'register_settings' ]);
    }

    public function add_menu() {
        add_options_page(
            __('GitHub Settings', 'gm2-wordpress-suite'),
            __('GitHub', 'gm2-wordpress-suite'),
            'manage_options',
            'gm2-github-settings',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings() {
        register_setting(
            'gm2_github',
            'gm2_github_token',
            [
                'type'              => 'string',
                'sanitize_callback' => [ $this, 'sanitize_token' ],
                'default'           => '',
                'capability'        => 'manage_options',
            ]
        );

        register_setting(
            'gm2_github',
            'gm2_github_client_id',
            [
                'type'              => 'string',
                'sanitize_callback' => [ $this, 'sanitize_client_id' ],
                'default'           => '',
                'capability'        => 'manage_options',
            ]
        );

        register_setting(
            'gm2_github',
            'gm2_github_client_secret',
            [
                'type'              => 'string',
                'sanitize_callback' => [ $this, 'sanitize_client_secret' ],
                'default'           => '',
                'capability'        => 'manage_options',
            ]
        );

        add_settings_section(
            'gm2_github_section',
            __('GitHub', 'gm2-wordpress-suite'),
            '__return_false',
            'gm2-github-settings'
        );

        add_settings_field(
            'gm2_github_client_id',
            __('OAuth Client ID', 'gm2-wordpress-suite'),
            [ $this, 'client_id_field' ],
            'gm2-github-settings',
            'gm2_github_section'
        );

        add_settings_field(
            'gm2_github_client_secret',
            __('OAuth Client Secret', 'gm2-wordpress-suite'),
            [ $this, 'client_secret_field' ],
            'gm2-github-settings',
            'gm2_github_section'
        );

        add_settings_field(
            'gm2_github_token',
            __('Token', 'gm2-wordpress-suite'),
            [ $this, 'token_field' ],
            'gm2-github-settings',
            'gm2_github_section'
        );
    }

    public function sanitize_token($token) {
        return sanitize_text_field($token);
    }

    public function sanitize_client_id($client_id) {
        return sanitize_text_field($client_id);
    }

    public function sanitize_client_secret($client_secret) {
        return sanitize_text_field($client_secret);
    }

    public function client_id_field() {
        $client_id = get_option('gm2_github_client_id', '');
        printf(
            '<input type="text" name="gm2_github_client_id" value="%s" class="regular-text" autocomplete="off" />',
            esc_attr($client_id)
        );
        echo '<p class="description">' . esc_html__( 'Register a GitHub OAuth application and copy its client ID here. The updater uses this value to launch the device authorization flow.', 'gm2-wordpress-suite' ) . '</p>';
    }

    public function client_secret_field() {
        $client_secret = get_option('gm2_github_client_secret', '');
        printf(
            '<input type="password" name="gm2_github_client_secret" value="%s" class="regular-text" autocomplete="new-password" />',
            esc_attr($client_secret)
        );
        echo '<p class="description">' . esc_html__( 'Copy the client secret from the same GitHub OAuth application. It is required to complete the sign-in process and store the generated access token automatically.', 'gm2-wordpress-suite' ) . '</p>';
    }

    public function token_field() {
        $token = get_option('gm2_github_token', '');
        printf(
            '<input type="password" name="gm2_github_token" value="%s" class="regular-text" />',
            esc_attr($token)
        );
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'GitHub Settings', 'gm2-wordpress-suite' ) . '</h1>';
        $client = new Gm2_Github_Client();
        $user   = $client->validate_token();
        if (is_wp_error($user)) {
            echo '<div class="notice notice-error"><p>' . esc_html($user->get_error_message()) . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>' . sprintf(esc_html__('Connected as %s', 'gm2-wordpress-suite'), esc_html($user['login'])) . '</p></div>';
        }
        echo '<form action="options.php" method="post">';
        settings_fields('gm2_github');
        do_settings_sections('gm2-github-settings');
        submit_button();
        echo '</form>';
        echo '</div>';
    }
}

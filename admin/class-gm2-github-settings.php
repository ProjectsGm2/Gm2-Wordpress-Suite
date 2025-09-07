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

        add_settings_section(
            'gm2_github_section',
            __('GitHub', 'gm2-wordpress-suite'),
            '__return_false',
            'gm2-github-settings'
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

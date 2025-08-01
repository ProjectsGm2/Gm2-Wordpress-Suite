<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_SEO_Wizard {
    public function run() {
        add_action('admin_menu', [$this, 'add_pages']);
        add_action('admin_init', [$this, 'handle_redirect']);
        add_action('admin_post_gm2_save_wizard', [$this, 'handle_post']);
    }

    public function add_pages() {
        add_dashboard_page(
            __('Gm2 Setup Wizard', 'gm2-wordpress-suite'),
            __('Gm2 Setup Wizard', 'gm2-wordpress-suite'),
            'manage_options',
            'gm2-setup-wizard',
            [$this, 'render']
        );
    }

    public function handle_redirect() {
        if (get_option('gm2_setup_complete') !== '1') {
            if (!isset($_GET['page'])) {
                wp_safe_redirect(admin_url('index.php?page=gm2-setup-wizard'));
                exit;
            }
        }
    }

    public function handle_post() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied', 'gm2-wordpress-suite'));
        }
        check_admin_referer('gm2_save_wizard');
        $step = isset($_POST['gm2_wizard_step']) ? sanitize_text_field($_POST['gm2_wizard_step']) : '';
        switch ($step) {
            case 'chatgpt':
                update_option('gm2_chatgpt_api_key', sanitize_text_field($_POST['gm2_chatgpt_api_key'] ?? ''));
                break;
            case 'oauth':
                update_option('gm2_gads_client_id', sanitize_text_field($_POST['gm2_gads_client_id'] ?? ''));
                update_option('gm2_gads_client_secret', sanitize_text_field($_POST['gm2_gads_client_secret'] ?? ''));
                update_option('gm2_gads_developer_token', sanitize_text_field($_POST['gm2_gads_developer_token'] ?? ''));
                break;
            case 'sitemap':
                update_option('gm2_sitemap_path', sanitize_text_field($_POST['gm2_sitemap_path'] ?? ''));
                update_option('gm2_sitemap_max_urls', absint($_POST['gm2_sitemap_max_urls'] ?? 1000));
                break;
            case 'defaults':
                update_option('gm2_enable_seo', isset($_POST['gm2_enable_seo']) ? '1' : '0');
                update_option('gm2_enable_chatgpt', isset($_POST['gm2_enable_chatgpt']) ? '1' : '0');
                update_option('gm2_enable_google_oauth', isset($_POST['gm2_enable_google_oauth']) ? '1' : '0');
                update_option('gm2_setup_complete', '1');
                break;
        }
        $next = isset($_POST['gm2_next_step']) ? sanitize_text_field($_POST['gm2_next_step']) : '';
        wp_safe_redirect(admin_url('index.php?page=gm2-setup-wizard&step=' . $next));
        exit;
    }

    public function render() {
        $step = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : 'chatgpt';
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Gm2 Setup Wizard', 'gm2-wordpress-suite') . '</h1>';
        switch ($step) {
            case 'oauth':
                $this->render_oauth();
                break;
            case 'sitemap':
                $this->render_sitemap();
                break;
            case 'defaults':
                $this->render_defaults();
                break;
            default:
                $this->render_chatgpt();
        }
        echo '</div>';
    }

    private function render_chatgpt() {
        $key = get_option('gm2_chatgpt_api_key', '');
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        wp_nonce_field('gm2_save_wizard');
        echo '<input type="hidden" name="action" value="gm2_save_wizard" />';
        echo '<input type="hidden" name="gm2_wizard_step" value="chatgpt" />';
        echo '<input type="hidden" name="gm2_next_step" value="oauth" />';
        echo '<table class="form-table">';
        echo '<tr><th><label for="gm2_chatgpt_api_key">' . esc_html__('ChatGPT API Key', 'gm2-wordpress-suite') . '</label></th>';
        echo '<td><input type="text" id="gm2_chatgpt_api_key" name="gm2_chatgpt_api_key" value="' . esc_attr($key) . '" class="regular-text" /></td></tr>';
        echo '</table>';
        submit_button(__('Continue', 'gm2-wordpress-suite'));
        echo '</form>';
    }

    private function render_oauth() {
        $id = get_option('gm2_gads_client_id', '');
        $secret = get_option('gm2_gads_client_secret', '');
        $token = get_option('gm2_gads_developer_token', '');
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        wp_nonce_field('gm2_save_wizard');
        echo '<input type="hidden" name="action" value="gm2_save_wizard" />';
        echo '<input type="hidden" name="gm2_wizard_step" value="oauth" />';
        echo '<input type="hidden" name="gm2_next_step" value="sitemap" />';
        echo '<table class="form-table">';
        echo '<tr><th><label for="gm2_gads_client_id">' . esc_html__('Client ID', 'gm2-wordpress-suite') . '</label></th>';
        echo '<td><input type="text" id="gm2_gads_client_id" name="gm2_gads_client_id" value="' . esc_attr($id) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="gm2_gads_client_secret">' . esc_html__('Client Secret', 'gm2-wordpress-suite') . '</label></th>';
        echo '<td><input type="text" id="gm2_gads_client_secret" name="gm2_gads_client_secret" value="' . esc_attr($secret) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="gm2_gads_developer_token">' . esc_html__('Developer Token', 'gm2-wordpress-suite') . '</label></th>';
        echo '<td><input type="text" id="gm2_gads_developer_token" name="gm2_gads_developer_token" value="' . esc_attr($token) . '" class="regular-text" /></td></tr>';
        echo '</table>';
        submit_button(__('Continue', 'gm2-wordpress-suite'));
        echo '</form>';
    }

    private function render_sitemap() {
        $path = get_option('gm2_sitemap_path', ABSPATH . 'sitemap.xml');
        $max = get_option('gm2_sitemap_max_urls', 1000);
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        wp_nonce_field('gm2_save_wizard');
        echo '<input type="hidden" name="action" value="gm2_save_wizard" />';
        echo '<input type="hidden" name="gm2_wizard_step" value="sitemap" />';
        echo '<input type="hidden" name="gm2_next_step" value="defaults" />';
        echo '<table class="form-table">';
        echo '<tr><th><label for="gm2_sitemap_path">' . esc_html__('Sitemap Path', 'gm2-wordpress-suite') . '</label></th>';
        echo '<td><input type="text" id="gm2_sitemap_path" name="gm2_sitemap_path" value="' . esc_attr($path) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="gm2_sitemap_max_urls">' . esc_html__('Max URLs', 'gm2-wordpress-suite') . '</label></th>';
        echo '<td><input type="number" id="gm2_sitemap_max_urls" name="gm2_sitemap_max_urls" value="' . esc_attr($max) . '" class="small-text" /></td></tr>';
        echo '</table>';
        submit_button(__('Continue', 'gm2-wordpress-suite'));
        echo '</form>';
    }

    private function render_defaults() {
        $seo = get_option('gm2_enable_seo', '1') === '1';
        $chatgpt = get_option('gm2_enable_chatgpt', '1') === '1';
        $oauth = get_option('gm2_enable_google_oauth', '1') === '1';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        wp_nonce_field('gm2_save_wizard');
        echo '<input type="hidden" name="action" value="gm2_save_wizard" />';
        echo '<input type="hidden" name="gm2_wizard_step" value="defaults" />';
        echo '<input type="hidden" name="gm2_next_step" value="done" />';
        echo '<table class="form-table">';
        echo '<tr><th scope="row">' . esc_html__('Enable SEO', 'gm2-wordpress-suite') . '</th>';
        echo '<td><label><input type="checkbox" name="gm2_enable_seo" value="1"' . checked($seo, true, false) . '> ' . esc_html__('Yes', 'gm2-wordpress-suite') . '</label></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Enable ChatGPT', 'gm2-wordpress-suite') . '</th>';
        echo '<td><label><input type="checkbox" name="gm2_enable_chatgpt" value="1"' . checked($chatgpt, true, false) . '> ' . esc_html__('Yes', 'gm2-wordpress-suite') . '</label></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Enable Google OAuth', 'gm2-wordpress-suite') . '</th>';
        echo '<td><label><input type="checkbox" name="gm2_enable_google_oauth" value="1"' . checked($oauth, true, false) . '> ' . esc_html__('Yes', 'gm2-wordpress-suite') . '</label></td></tr>';
        echo '</table>';
        submit_button(__('Finish Setup', 'gm2-wordpress-suite'));
        echo '</form>';
    }
}

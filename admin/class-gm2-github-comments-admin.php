<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Github_Comments_Admin {
    public function run() {
        add_action('admin_menu', [ $this, 'add_menu' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_scripts' ]);
        add_action('wp_ajax_gm2_apply_patch', [ $this, 'ajax_apply_patch' ]);
    }

    public function add_menu() {
        add_menu_page(
            __('PR Reviews', 'gm2-wordpress-suite'),
            __('PR Reviews', 'gm2-wordpress-suite'),
            'manage_options',
            'gm2-github-comments',
            [ $this, 'render_page' ],
            'dashicons-editor-code',
            81
        );
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_gm2-github-comments') {
            return;
        }
        wp_enqueue_script(
            'gm2-github-comments',
            GM2_PLUGIN_URL . 'admin/js/gm2-github-comments.js',
            [ 'wp-element' ],
            file_exists(GM2_PLUGIN_DIR . 'admin/js/gm2-github-comments.js') ? filemtime(GM2_PLUGIN_DIR . 'admin/js/gm2-github-comments.js') : GM2_VERSION,
            true
        );
        wp_localize_script(
            'gm2-github-comments',
            'gm2GithubComments',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('gm2_apply_patch'),
                'comments' => $this->get_comments(),
            ]
        );
    }

    private function get_comments() {
        $repo = isset($_GET['repo']) ? sanitize_text_field(wp_unslash($_GET['repo'])) : '';
        $pr   = isset($_GET['pr']) ? absint($_GET['pr']) : 0;
        if ($repo !== '' && $pr > 0) {
            return gm2_get_github_comments($repo, $pr);
        }
        return [];
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__('PR Reviews', 'gm2-wordpress-suite') . '</h1><div id="gm2-github-comments-root"></div></div>';
    }

    public function ajax_apply_patch() {
        check_ajax_referer('gm2_apply_patch', 'nonce');
        $file  = isset($_POST['file']) ? sanitize_text_field(wp_unslash($_POST['file'])) : '';
        $patch = isset($_POST['patch']) ? wp_unslash($_POST['patch']) : '';
        $result = gm2_apply_patch($file, $patch);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success([
            'message'  => __('Patch applied', 'gm2-wordpress-suite'),
            'comments' => $this->get_comments(),
        ]);
    }
}

<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Github_Comments_Admin {
    private $error = '';

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
        $comments = $this->get_comments();
        wp_localize_script(
            'gm2-github-comments',
            'gm2GithubComments',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('gm2_apply_patch'),
                'comments' => $comments,
                'error'    => $this->error,
            ]
        );
    }

    private function get_comments() {
        $repo       = isset($_GET['repo']) ? sanitize_text_field(wp_unslash($_GET['repo'])) : get_option('gm2_last_repo', '');
        $pr         = isset($_GET['pr']) ? absint($_GET['pr']) : 0;
        $this->error = '';
        if ($repo !== '') {
            update_option('gm2_last_repo', $repo);
        }
        if ($repo !== '' && $pr > 0) {
            $comments = gm2_get_github_comments($repo, $pr);
            if (is_wp_error($comments)) {
                $this->error = $comments->get_error_message();
                return [];
            }
            return $comments;
        }
        return [];
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $repo = isset($_GET['repo']) ? sanitize_text_field(wp_unslash($_GET['repo'])) : get_option('gm2_last_repo', '');
        $pr   = isset($_GET['pr']) ? absint($_GET['pr']) : 0;
        echo '<div class="wrap"><h1>' . esc_html__('PR Reviews', 'gm2-wordpress-suite') . '</h1>';
        echo '<form method="get"><input type="hidden" name="page" value="gm2-github-comments" />';
        echo '<p><label>' . esc_html__('Repository (owner/repo)', 'gm2-wordpress-suite') . ' <input type="text" name="repo" value="' . esc_attr($repo) . '" /></label></p>';
        echo '<p><label>' . esc_html__('PR Number', 'gm2-wordpress-suite') . ' <input type="number" name="pr" value="' . ($pr > 0 ? esc_attr($pr) : '') . '" /></label></p>';
        echo '<p><input type="submit" class="button button-primary" value="' . esc_attr__('Load', 'gm2-wordpress-suite') . '" /></p></form>';
        if ($repo === '' || $pr <= 0) {
            echo '<p>' . esc_html__('No PR selected', 'gm2-wordpress-suite') . '</p>';
        }
        echo '<div id="gm2-github-comments-root"></div></div>';
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

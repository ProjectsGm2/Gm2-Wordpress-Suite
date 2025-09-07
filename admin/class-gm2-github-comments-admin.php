<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Github_Comments_Admin {
    private $error = '';
    private $token_result = null;

    public function run() {
        add_action('admin_menu', [ $this, 'add_menu' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_scripts' ]);
        add_action('wp_ajax_gm2_apply_patch', [ $this, 'ajax_apply_patch' ]);
        add_action('wp_ajax_gm2_get_github_comments', [ $this, 'ajax_get_comments' ]);
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
        if ($this->token_result === null) {
            $client             = new Gm2_Github_Client();
            $this->token_result = $client->validate_token();
        }
        if (is_wp_error($this->token_result)) {
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
                'commentsNonce' => wp_create_nonce('gm2_get_github_comments'),
                'comments' => [],
                'error'    => '',
            ]
        );
    }

    private function get_comments() {
        $repo       = isset($_GET['repo']) ? sanitize_text_field(wp_unslash($_GET['repo'])) : get_option('gm2_last_repo', '');
        $pr         = isset($_GET['pr']) ? sanitize_text_field(wp_unslash($_GET['pr'])) : '';
        $this->error = '';
        if ($repo !== '') {
            update_option('gm2_last_repo', $repo);
        }
        if ($repo === '') {
            return [];
        }
        $client = new Gm2_Github_Client();
        if ($pr === '' || $pr === '0') {
            $numbers = $client->list_open_pr_numbers($repo);
            if (is_wp_error($numbers)) {
                return $numbers;
            }
            if (empty($numbers)) {
                return [];
            }
            $pr = (string) $numbers[0];
            $_GET['pr'] = $pr;
        }
        if ($pr === 'all') {
            $numbers = $client->list_open_pr_numbers($repo);
            if (is_wp_error($numbers)) {
                return $numbers;
            }
            $comments = [];
            foreach ($numbers as $number) {
                $pr_comments = gm2_get_github_comments($repo, $number);
                if (is_wp_error($pr_comments)) {
                    return $pr_comments;
                }
                $comments = array_merge($comments, $pr_comments);
            }
            return $comments;
        }
        $pr_number = absint($pr);
        if ($pr_number > 0) {
            $comments = gm2_get_github_comments($repo, $pr_number);
            if (is_wp_error($comments)) {
                return $comments;
            }
            return $comments;
        }
        return [];
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $repo   = get_option('gm2_last_repo', '');
        $client = new Gm2_Github_Client();
        $this->token_result = $client->validate_token();
        echo '<div class="wrap"><h1>' . esc_html__('PR Reviews', 'gm2-wordpress-suite') . '</h1>';
        if (is_wp_error($this->token_result)) {
            echo '<div class="notice notice-error"><p>' . esc_html($this->token_result->get_error_message());
            if ($this->token_result->get_error_code() === 'github_no_token') {
                $settings_url = esc_url(admin_url('admin.php?page=gm2-github-settings'));
                echo ' <a href="' . $settings_url . '">' . esc_html__('Configure your token on the GitHub settings page', 'gm2-wordpress-suite') . '</a>';
            }
            echo '</p></div>';
        } else {
            $login = isset($this->token_result['login']) ? $this->token_result['login'] : '';
            if ($login !== '') {
                echo '<div class="notice notice-success"><p>' . sprintf(esc_html__('Connected to GitHub as %s', 'gm2-wordpress-suite'), esc_html($login)) . '</p></div>';
            }
        }
        echo '<p><label>' . esc_html__('Repository (owner/repo)', 'gm2-wordpress-suite') . ' <input type="text" id="gm2-repo" value="' . esc_attr($repo) . '" /></label></p>';

        $numbers = [];
        if ($repo !== '' && !is_wp_error($this->token_result)) {
            $numbers = $client->list_open_pr_numbers($repo);
            if (is_wp_error($numbers)) {
                echo '<div class="notice notice-error"><p>' . esc_html($numbers->get_error_message()) . '</p></div>';
                $numbers = [];
            }
        }

        echo '<p><label>' . esc_html__('PR Number', 'gm2-wordpress-suite') . ' <select id="gm2-pr">';
        echo '<option value="all">' . esc_html__('All PRs', 'gm2-wordpress-suite') . '</option>';
        foreach ($numbers as $number) {
            echo '<option value="' . esc_attr($number) . '">' . esc_html($number) . '</option>';
        }
        echo '</select></label></p>';

        echo '<p><button type="button" class="button button-primary" id="gm2-load-comments">' . esc_html__('Load', 'gm2-wordpress-suite') . '</button></p>';
        echo '<div id="gm2-github-comments-root"></div></div>';
    }

    public function ajax_get_comments() {
        check_ajax_referer('gm2_get_github_comments', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to fetch comments.', 'gm2-wordpress-suite'));
        }
        $repo = isset($_POST['repo']) ? sanitize_text_field(wp_unslash($_POST['repo'])) : '';
        $pr   = isset($_POST['pr']) ? sanitize_text_field(wp_unslash($_POST['pr'])) : '';
        $_GET['repo'] = $repo;
        $_GET['pr']   = $pr;
        $comments = $this->get_comments();
        if (is_wp_error($comments)) {
            wp_send_json_error($comments->get_error_message());
        }
        wp_send_json_success($comments);
    }

    public function ajax_apply_patch() {
        check_ajax_referer('gm2_apply_patch', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to apply patches.', 'gm2-wordpress-suite'));
        }

        $repo = isset($_POST['repo']) ? sanitize_text_field(wp_unslash($_POST['repo'])) : '';
        $pr   = isset($_POST['pr']) ? sanitize_text_field(wp_unslash($_POST['pr'])) : '';
        $_GET['repo'] = $repo;
        $_GET['pr']   = $pr;

        $patches = isset($_POST['patches']) ? json_decode(wp_unslash($_POST['patches']), true) : null;
        if (!is_array($patches)) {
            $file  = isset($_POST['file']) ? sanitize_text_field(wp_unslash($_POST['file'])) : '';
            $patch = isset($_POST['patch']) ? wp_unslash($_POST['patch']) : '';
            $patches = [ [ 'file' => $file, 'patch' => $patch ] ];
        }

        $results  = [];
        $all_ok   = true;

        foreach ($patches as $p) {
            $file  = isset($p['file']) ? sanitize_text_field(wp_unslash($p['file'])) : '';
            $patch = isset($p['patch']) ? wp_unslash($p['patch']) : '';
            if ($file === '' || $patch === '') {
                $results[] = [
                    'file'    => $file,
                    'message' => __('Invalid patch data', 'gm2-wordpress-suite'),
                    'success' => false,
                ];
                $all_ok = false;
                continue;
            }
            $result = gm2_apply_patch($file, $patch);
            if (is_wp_error($result)) {
                $results[] = [
                    'file'    => $file,
                    'message' => $result->get_error_message(),
                    'success' => false,
                ];
                $all_ok = false;
            } else {
                $results[] = [
                    'file'    => $file,
                    'message' => __('Patch applied', 'gm2-wordpress-suite'),
                    'success' => true,
                ];
            }
        }

        $comments = $this->get_comments();
        if (is_wp_error($comments)) {
            $this->error = $comments->get_error_message();
            $comments    = [];
        }

        $response = [
            'results'  => $results,
            'comments' => $comments,
        ];
        if ($this->error !== '') {
            $response['message'] = $this->error;
            wp_send_json_error($response);
        }
        if ($all_ok) {
            $response['message'] = __('Patches applied', 'gm2-wordpress-suite');
            wp_send_json_success($response);
        }

        $response['message'] = __('Some patches failed', 'gm2-wordpress-suite');
        wp_send_json_error($response);
    }
}

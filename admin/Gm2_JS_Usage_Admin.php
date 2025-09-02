<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_JS_Usage_Admin {
    public function run() {
        add_action('admin_menu', [ $this, 'add_menu' ]);
        add_action('admin_post_gm2_js_usage_save', [ $this, 'save_overrides' ]);
    }

    public function add_menu() {
        add_submenu_page(
            'gm2-seo',
            __('Script Usage', 'gm2-wordpress-suite'),
            __('Script Usage', 'gm2-wordpress-suite'),
            'manage_options',
            'gm2-js-usage',
            [ $this, 'display_page' ]
        );
    }

    private function get_script_stats(): array {
        global $wpdb;
        $prefix = $wpdb->esc_like('_transient_aejs_ctx:') . '%';
        $sql    = $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix);
        $rows   = $wpdb->get_col($sql);
        $counts = [];
        $templates = [];
        foreach ($rows as $value) {
            $ctx = maybe_unserialize($value);
            if (!is_array($ctx)) {
                continue;
            }
            $page = $ctx['page_type'] ?? '';
            if ($page !== '') {
                $templates[$page] = true;
            }
            foreach ($ctx['scripts'] ?? [] as $handle) {
                if (!is_string($handle)) {
                    continue;
                }
                if (!isset($counts[$handle])) {
                    $counts[$handle] = 0;
                }
                $counts[$handle]++;
            }
        }
        arsort($counts);
        return [ $counts, array_keys($templates) ];
    }

    public function display_page() {
        [ $counts, $templates ] = $this->get_script_stats();
        $overrides = get_option('ae_js_overrides', []);
        echo '<div class="wrap"><h1>' . esc_html__( 'Script Usage', 'gm2-wordpress-suite' ) . '</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="gm2_js_usage_save" />';
        wp_nonce_field('gm2_js_usage_save');
        echo '<table class="widefat fixed"><thead><tr><th>' . esc_html__( 'Handle', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Count', 'gm2-wordpress-suite' ) . '</th>';
        foreach ($templates as $template) {
            echo '<th>' . esc_html($template) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($counts as $handle => $count) {
            echo '<tr><td>' . esc_html($handle) . '</td><td>' . (int) $count . '</td>';
            foreach ($templates as $template) {
                $checked = (isset($overrides[$handle]) && in_array($template, $overrides[$handle], true)) ? 'checked' : '';
                echo '<td><input type="checkbox" name="overrides[' . esc_attr($handle) . '][]" value="' . esc_attr($template) . '" ' . $checked . ' /></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
        submit_button(__('Save Changes', 'gm2-wordpress-suite'));
        echo '</form></div>';
    }

    public function save_overrides() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Access denied.', 'gm2-wordpress-suite'));
        }
        check_admin_referer('gm2_js_usage_save');
        $raw = $_POST['overrides'] ?? [];
        $overrides = [];
        if (is_array($raw)) {
            foreach ($raw as $handle => $templates) {
                $handle = sanitize_key($handle);
                if (!is_array($templates)) {
                    continue;
                }
                $clean = [];
                foreach ($templates as $t) {
                    $t = sanitize_key($t);
                    if ($t !== '') {
                        $clean[] = $t;
                    }
                }
                if ($clean) {
                    $overrides[$handle] = array_values(array_unique($clean));
                }
            }
        }
        update_option('ae_js_overrides', $overrides);
        wp_redirect(add_query_arg('updated', '1', admin_url('admin.php?page=gm2-js-usage')));
        exit;
    }
}

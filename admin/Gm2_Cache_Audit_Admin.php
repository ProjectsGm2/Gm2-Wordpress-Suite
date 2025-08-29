<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Cache_Audit_Admin {
    public function run() {
        add_action('admin_menu', [ $this, 'add_menu' ]);
        add_action('network_admin_menu', [ $this, 'add_network_menu' ]);
        add_action('admin_post_gm2_cache_audit_rescan', [ $this, 'handle_rescan' ]);
        add_action('admin_post_gm2_cache_audit_export', [ $this, 'handle_export' ]);
    }

    public function add_menu() {
        add_submenu_page(
            'gm2-seo',
            __('Cache Audit', 'gm2-wordpress-suite'),
            __('Cache Audit', 'gm2-wordpress-suite'),
            'manage_options',
            'gm2-cache-audit',
            [ $this, 'display_page' ]
        );
    }

    public function add_network_menu() {
        add_menu_page(
            __('SEO', 'gm2-wordpress-suite'),
            __('SEO', 'gm2-wordpress-suite'),
            'manage_network',
            'gm2-seo',
            '__return_null',
            'dashicons-chart-area'
        );
        add_submenu_page(
            'gm2-seo',
            __('Cache Audit', 'gm2-wordpress-suite'),
            __('Cache Audit', 'gm2-wordpress-suite'),
            'manage_network',
            'gm2-cache-audit',
            [ $this, 'display_page' ]
        );
    }

    public function handle_rescan() {
        $cap = is_network_admin() ? 'manage_network' : 'manage_options';
        if (!current_user_can($cap)) {
            wp_die(__('Access denied.', 'gm2-wordpress-suite'));
        }
        check_admin_referer('gm2_cache_audit_rescan');
        $site_id = isset($_POST['site_id']) ? (int) $_POST['site_id'] : 0;
        if ($site_id && is_network_admin()) {
            switch_to_blog($site_id);
        }
        Gm2_Cache_Audit::rescan();
        if ($site_id && is_network_admin()) {
            restore_current_blog();
            wp_redirect(network_admin_url('admin.php?page=gm2-cache-audit&site_id=' . $site_id . '&rescanned=1'));
        } else {
            wp_redirect(admin_url('admin.php?page=gm2-cache-audit&rescanned=1'));
        }
        exit;
    }

    private function filter_assets($assets) {
        $type   = isset($_GET['type']) ? sanitize_key($_GET['type']) : '';
        $host   = isset($_GET['host']) ? sanitize_key($_GET['host']) : '';
        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        $home_host = parse_url(home_url(), PHP_URL_HOST);

        return array_values(array_filter($assets, function($a) use ($type, $host, $status, $home_host) {
            $asset_host  = parse_url($a['url'], PHP_URL_HOST);
            $host_type   = ($asset_host === $home_host) ? 'same' : 'third';
            $asset_status = $a['needs_attention'] ? 'needs' : 'good';
            if ($type && $a['type'] !== $type) {
                return false;
            }
            if ($host && $host_type !== $host) {
                return false;
            }
            if ($status && $asset_status !== $status) {
                return false;
            }
            return true;
        }));
    }

    private function suggested_fix($asset, $host_type) {
        if ($host_type === 'third') {
            if ($asset['type'] === 'script') {
                return __('Defer/async', 'gm2-wordpress-suite');
            }
            return __('Self-host', 'gm2-wordpress-suite');
        }
        if (in_array('short_max_age', $asset['issues'], true) || in_array('missing_cache_control', $asset['issues'], true)) {
            return __('Set long TTL', 'gm2-wordpress-suite');
        }
        if (in_array('missing_immutable', $asset['issues'], true)) {
            return __('Add version', 'gm2-wordpress-suite');
        }
        return '';
    }

    public function display_page() {
        $site_id = is_network_admin() ? (int) ($_GET['site_id'] ?? 0) : 0;
        $switched = false;
        echo '<div class="wrap"><h1>' . esc_html__('Cache Audit', 'gm2-wordpress-suite') . '</h1>';
        if (is_network_admin()) {
            echo '<form method="get" style="margin-bottom:10px;">';
            echo '<input type="hidden" name="page" value="gm2-cache-audit" />';
            echo '<select name="site_id">';
            echo '<option value="0">' . esc_html__('Select Site', 'gm2-wordpress-suite') . '</option>';
            foreach (get_sites(['number' => 0]) as $site) {
                $label = $site->blogname ?: $site->domain . $site->path;
                echo '<option value="' . esc_attr($site->blog_id) . '"' . selected($site_id, (int) $site->blog_id, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select> ';
            submit_button(__('Load', 'gm2-wordpress-suite'), 'secondary', '', false);
            echo '</form>';
            if ($site_id) {
                switch_to_blog($site_id);
                $switched = true;
            } else {
                echo '</div>';
                return;
            }
        }
        $results = Gm2_Cache_Audit::get_results();
        $assets  = $this->filter_assets($results['assets'] ?? []);
        $home_host = parse_url(home_url(), PHP_URL_HOST);
        if (!empty($_GET['rescanned'])) {
            echo '<div class="updated notice"><p>' . esc_html__('Scan complete.', 'gm2-wordpress-suite') . '</p></div>';
        }

        echo '<form method="get" style="margin-bottom:10px;">';
        echo '<input type="hidden" name="page" value="gm2-cache-audit" />';
        if ($site_id) {
            echo '<input type="hidden" name="site_id" value="' . esc_attr($site_id) . '" />';
        }
        $type = isset($_GET['type']) ? sanitize_key($_GET['type']) : '';
        $host = isset($_GET['host']) ? sanitize_key($_GET['host']) : '';
        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        echo '<select name="type">';
        echo '<option value="">' . esc_html__('All Types', 'gm2-wordpress-suite') . '</option>';
        foreach (['script','style','image','font','other'] as $t) {
            echo '<option value="' . esc_attr($t) . '"' . selected($type, $t, false) . '>' . esc_html(ucfirst($t)) . '</option>';
        }
        echo '</select> ';
        echo '<select name="host">';
        echo '<option value="">' . esc_html__('All Hosts', 'gm2-wordpress-suite') . '</option>';
        echo '<option value="same"' . selected($host, 'same', false) . '>' . esc_html__('Same Origin', 'gm2-wordpress-suite') . '</option>';
        echo '<option value="third"' . selected($host, 'third', false) . '>' . esc_html__('Third-party', 'gm2-wordpress-suite') . '</option>';
        echo '</select> ';
        echo '<select name="status">';
        echo '<option value="">' . esc_html__('All Statuses', 'gm2-wordpress-suite') . '</option>';
        echo '<option value="good"' . selected($status, 'good', false) . '>' . esc_html__('Good', 'gm2-wordpress-suite') . '</option>';
        echo '<option value="needs"' . selected($status, 'needs', false) . '>' . esc_html__('Needs Attention', 'gm2-wordpress-suite') . '</option>';
        echo '</select> ';
        submit_button(__('Filter', 'gm2-wordpress-suite'), 'secondary', '', false);
        echo '</form>';

        $post_url = is_network_admin() ? network_admin_url('admin-post.php') : admin_url('admin-post.php');
        $export_url = wp_nonce_url(
            $post_url . '?action=gm2_cache_audit_export&type=' . $type . '&host=' . $host . '&status=' . $status . ($site_id ? '&site_id=' . $site_id : ''),
            'gm2_cache_audit_export'
        );
        echo '<a href="' . esc_url($export_url) . '" class="button">' . esc_html__('Export CSV', 'gm2-wordpress-suite') . '</a> ';
        echo '<form method="post" action="' . esc_url($post_url) . '" style="display:inline;">';
        wp_nonce_field('gm2_cache_audit_rescan');
        echo '<input type="hidden" name="action" value="gm2_cache_audit_rescan" />';
        if ($site_id) {
            echo '<input type="hidden" name="site_id" value="' . esc_attr($site_id) . '" />';
        }
        submit_button(__('Re-scan', 'gm2-wordpress-suite'), 'secondary', '', false);
        echo '</form>';

        echo '<table class="widefat striped"><thead><tr>';
        $cols = [
            'url'   => __('URL', 'gm2-wordpress-suite'),
            'type'  => __('Type', 'gm2-wordpress-suite'),
            'ttl'   => __('TTL', 'gm2-wordpress-suite'),
            'cc'    => __('Cache-Control', 'gm2-wordpress-suite'),
            'etag'  => __('ETag', 'gm2-wordpress-suite'),
            'lm'    => __('Last-Modified', 'gm2-wordpress-suite'),
            'size'  => __('Size KB', 'gm2-wordpress-suite'),
            'status'=> __('Status', 'gm2-wordpress-suite'),
            'fix'   => __('Suggested Fix', 'gm2-wordpress-suite'),
        ];
        foreach ($cols as $label) {
            echo '<th>' . esc_html($label) . '</th>';
        }
        echo '</tr></thead><tbody>';
        if (empty($assets)) {
            echo '<tr><td colspan="9">' . esc_html__('No results found.', 'gm2-wordpress-suite') . '</td></tr>';
        } else {
            foreach ($assets as $a) {
                $asset_host = parse_url($a['url'], PHP_URL_HOST);
                $host_type  = ($asset_host === $home_host) ? 'same' : 'third';
                $status_label = $a['needs_attention'] ? __('Needs Attention', 'gm2-wordpress-suite') : __('Good', 'gm2-wordpress-suite');
                $fix = $this->suggested_fix($a, $host_type);
                $ttl = $a['ttl'] !== null ? intval($a['ttl']) : '';
                $size = $a['content_length'] ? round($a['content_length']/1024, 2) : '';
                $url_trunc = esc_html(wp_html_excerpt($a['url'], 60, '&hellip;'));
                echo '<tr>';
                echo '<td><a href="' . esc_url($a['url']) . '" target="_blank">' . $url_trunc . '</a></td>';
                echo '<td>' . esc_html($a['type']) . '</td>';
                echo '<td>' . esc_html($ttl) . '</td>';
                echo '<td>' . esc_html($a['cache_control']) . '</td>';
                echo '<td>' . esc_html($a['etag']) . '</td>';
                echo '<td>' . esc_html($a['last_modified']) . '</td>';
                echo '<td>' . esc_html($size) . '</td>';
                echo '<td>' . esc_html($status_label) . '</td>';
                echo '<td>' . esc_html($fix) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        if ($switched) {
            restore_current_blog();
        }
        echo '</div>';
    }

    public function handle_export() {
        $cap = is_network_admin() ? 'manage_network' : 'manage_options';
        if (!current_user_can($cap)) {
            wp_die(__('Access denied.', 'gm2-wordpress-suite'));
        }
        check_admin_referer('gm2_cache_audit_export');
        $site_id = isset($_GET['site_id']) ? (int) $_GET['site_id'] : 0;
        if ($site_id && is_network_admin()) {
            switch_to_blog($site_id);
        }
        $results = Gm2_Cache_Audit::get_results();
        $assets  = $this->filter_assets($results['assets'] ?? []);
        $this->export_csv($assets);
        if ($site_id && is_network_admin()) {
            restore_current_blog();
        }
        exit;
    }

    private function export_csv($assets) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="gm2-cache-audit.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['URL','Type','TTL','Cache-Control','ETag','Last-Modified','Size KB','Status','Suggested Fix']);
        $home_host = parse_url(home_url(), PHP_URL_HOST);
        foreach ($assets as $a) {
            $asset_host = parse_url($a['url'], PHP_URL_HOST);
            $host_type  = ($asset_host === $home_host) ? 'same' : 'third';
            $status_label = $a['needs_attention'] ? 'Needs Attention' : 'Good';
            $fix = $this->suggested_fix($a, $host_type);
            $ttl = $a['ttl'] !== null ? intval($a['ttl']) : '';
            $size = $a['content_length'] ? round($a['content_length']/1024, 2) : '';
            fputcsv($out, [$a['url'], $a['type'], $ttl, $a['cache_control'], $a['etag'], $a['last_modified'], $size, $status_label, $fix]);
        }
        fclose($out);
    }
}

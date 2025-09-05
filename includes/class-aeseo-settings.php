<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class AESEO_Settings {
    public static function register(): void {
        add_action('admin_post_gm2_lcp_settings', [__CLASS__, 'save_lcp_settings']);
    }

    public static function save_lcp_settings(): void {
        check_admin_referer('gm2_lcp_settings');

        $defaults = [
            'remove_lazy_on_lcp'       => '0',
            'add_fetchpriority_high'   => '0',
            'force_width_height'       => '0',
            'responsive_picture_nextgen' => '0',
            'add_preconnect'           => '0',
            'add_preload'              => '0',
        ];

        $submitted = isset($_POST['aeseo_lcp_settings']) && is_array($_POST['aeseo_lcp_settings']) ? $_POST['aeseo_lcp_settings'] : [];
        $sanitized = [];
        foreach ($defaults as $key => $value) {
            $sanitized[$key] = isset($submitted[$key]) && $submitted[$key] === '1' ? '1' : '0';
        }

        $settings = array_merge($defaults, $sanitized);
        update_option('aeseo_lcp_settings', $settings);

        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = admin_url('admin.php?page=gm2-lcp-optimization');
        }
        $redirect = add_query_arg('settings-updated', 'true', $redirect);
        wp_safe_redirect($redirect);
        exit;
    }
}

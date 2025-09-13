<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class AESEO_Settings {
    public static function register(): void {
        add_action('admin_post_gm2_lcp_settings', [__CLASS__, 'save_lcp_settings']);
        add_action('admin_post_gm2_cls_reservations', [__CLASS__, 'save_cls_reservations']);
        add_action('admin_post_gm2_cls_fonts', [__CLASS__, 'save_cls_fonts']);
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
            'fix_media_dimensions'     => '1',
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

    public static function save_cls_reservations(): void {
        check_admin_referer('gm2_cls_reservations');

        $rows = isset($_POST['cls_reservations']) && is_array($_POST['cls_reservations']) ? $_POST['cls_reservations'] : [];
        $sanitized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $selector = sanitize_text_field($row['selector'] ?? '');
            if ($selector === '') {
                continue;
            }
            $min = isset($row['min']) ? absint($row['min']) : 0;
            $unreserve = isset($row['unreserve']) ? '1' : '0';
            $sanitized[] = [
                'selector'  => $selector,
                'min'       => $min,
                'unreserve' => $unreserve,
            ];
        }

        update_option('plugin_cls_reservations', $sanitized);

        $sticky_header = isset($_POST['cls_sticky_header']) && $_POST['cls_sticky_header'] === '1' ? '1' : '0';
        update_option('plugin_cls_sticky_header', $sticky_header);

        $sticky_header_selector = isset($_POST['cls_sticky_header_selector']) ? sanitize_text_field((string) $_POST['cls_sticky_header_selector']) : '';
        update_option('plugin_cls_sticky_header_selector', $sticky_header_selector);

        $sticky_footer = isset($_POST['cls_sticky_footer']) && $_POST['cls_sticky_footer'] === '1' ? '1' : '0';
        update_option('plugin_cls_sticky_footer', $sticky_footer);

        $sticky_footer_selector = isset($_POST['cls_sticky_footer_selector']) ? sanitize_text_field((string) $_POST['cls_sticky_footer_selector']) : '';
        update_option('plugin_cls_sticky_footer_selector', $sticky_footer_selector);

        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = admin_url('admin.php?page=gm2-cls-reservations');
        }
        $redirect = add_query_arg('settings-updated', 'true', $redirect);
        wp_safe_redirect($redirect);
        exit;
    }

    public static function save_cls_fonts(): void {
        check_admin_referer('gm2_cls_fonts');

        $enabled = isset($_POST['cls_fonts_enabled']) && $_POST['cls_fonts_enabled'] === '1' ? '1' : '0';
        update_option('plugin_cls_fonts_enabled', $enabled);

        if (isset($_POST['cls_fonts_clear'])) {
            delete_option('plugin_cls_critical_fonts');
            delete_transient('plugin_cls_font_faces');
        } else {
            $urls = isset($_POST['cls_fonts']) && is_array($_POST['cls_fonts']) ? array_slice($_POST['cls_fonts'], 0, 3) : [];
            $urls = array_map('esc_url_raw', $urls);
            $available = \Plugin\CLS\Fonts\get_discovered_fonts();
            $fonts = [];
            foreach ($urls as $url) {
                foreach ($available as $font) {
                    if (is_array($font) && ($font['url'] ?? '') === $url) {
                        $fonts[] = $font;
                        break;
                    }
                }
                if (count($fonts) >= 3) {
                    break;
                }
            }
            update_option('plugin_cls_critical_fonts', $fonts);
        }

        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = admin_url('admin.php?page=gm2-cls-fonts');
        }
        $redirect = add_query_arg('settings-updated', 'true', $redirect);
        wp_safe_redirect($redirect);
        exit;
    }
}

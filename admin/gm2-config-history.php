<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin UI to view and restore configuration history.
 */
function gm2_config_history_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Permission denied', 'gm2-wordpress-suite'));
    }

    $options = [ 'gm2_custom_posts_config', 'gm2_field_groups' ];

    if (!empty($_POST['restore_option']) && !empty($_POST['version'])) {
        check_admin_referer('gm2_restore_option');
        $opt = sanitize_key($_POST['restore_option']);
        $ver = intval($_POST['version']);
        if (gm2_restore_option_version($opt, $ver)) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Version restored.', 'gm2-wordpress-suite') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to restore version.', 'gm2-wordpress-suite') . '</p></div>';
        }
    }

    echo '<div class="wrap"><h1>' . esc_html__('GM2 Config History', 'gm2-wordpress-suite') . '</h1>';
    foreach ($options as $option) {
        $history = gm2_get_option_history($option);
        echo '<h2>' . esc_html($option) . '</h2>';
        if ($history) {
            echo '<table class="widefat"><thead><tr><th>' . esc_html__('Version', 'gm2-wordpress-suite') . '</th><th>' . esc_html__('Date', 'gm2-wordpress-suite') . '</th><th>' . esc_html__('Actions', 'gm2-wordpress-suite') . '</th></tr></thead><tbody>';
            foreach ($history as $item) {
                $date = wp_date(get_option('date_format') . ' ' . get_option('time_format'), $item['timestamp']);
                echo '<tr><td>' . esc_html($item['version']) . '</td><td>' . esc_html($date) . '</td><td>';
                echo '<form method="post" style="display:inline">';
                wp_nonce_field('gm2_restore_option');
                echo '<input type="hidden" name="restore_option" value="' . esc_attr($option) . '" />';
                echo '<input type="hidden" name="version" value="' . esc_attr($item['version']) . '" />';
                echo '<button type="submit" class="button">' . esc_html__('Restore', 'gm2-wordpress-suite') . '</button>';
                echo '</form>';
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__('No history found.', 'gm2-wordpress-suite') . '</p>';
        }
    }
    echo '</div>';
}

function gm2_register_config_history_page() {
    add_submenu_page('gm2-custom-posts', __('Config History', 'gm2-wordpress-suite'), __('Config History', 'gm2-wordpress-suite'), 'manage_options', 'gm2_config_history', 'gm2_config_history_page');
}
add_action('admin_menu', 'gm2_register_config_history_page');

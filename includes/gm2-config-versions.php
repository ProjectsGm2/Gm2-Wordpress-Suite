<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Store versioned history of gm2 configuration options.
 */
function gm2_record_option_version($old_value, $value, $option) {
    if ($old_value === $value) {
        return;
    }
    $history_option = $option . '_history';
    $history = get_option($history_option, []);
    if (!is_array($history)) {
        $history = [];
    }
    $last = end($history);
    $version = isset($last['version']) ? (int) $last['version'] + 1 : 1;
    $history[] = [
        'version' => $version,
        'timestamp' => time(),
        'data' => $value,
    ];
    update_option($history_option, $history);
}

add_action('update_option_gm2_custom_posts_config', 'gm2_record_option_version', 10, 3);
add_action('update_option_gm2_field_groups', 'gm2_record_option_version', 10, 3);

/**
 * Retrieve history for an option.
 *
 * @param string $option Option name.
 * @return array
 */
function gm2_get_option_history($option) {
    $history = get_option($option . '_history', []);
    return is_array($history) ? $history : [];
}

/**
 * Restore an option to a previous version.
 *
 * @param string $option  Option name.
 * @param int    $version Version number to restore.
 * @return bool True on success.
 */
function gm2_restore_option_version($option, $version) {
    $history = gm2_get_option_history($option);
    foreach ($history as $item) {
        if ((int) ($item['version'] ?? 0) === (int) $version) {
            update_option($option, $item['data']);
            return true;
        }
    }
    return false;
}

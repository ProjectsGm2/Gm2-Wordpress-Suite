<?php
if (!defined('ABSPATH')) {
    exit;
}

$scripts = wp_scripts();
$handles = $scripts ? $scripts->queue : [];
$disabled = get_option('gm2_third_party_disabled', []);
if (!is_array($disabled)) {
    $disabled = [];
}

if (isset($_POST['gm2_tpa_nonce']) && wp_verify_nonce($_POST['gm2_tpa_nonce'], 'gm2_tpa_save')) {
    $new_disabled = [];
    foreach ($handles as $h) {
        if (!isset($_POST['allowed'][$h])) {
            $new_disabled[] = $h;
        }
    }
    update_option('gm2_third_party_disabled', $new_disabled);
    $disabled = $new_disabled;
    echo '<div class="updated notice"><p>' . esc_html__( 'Settings saved.', 'gm2-wordpress-suite' ) . '</p></div>';
}

echo '<div class="wrap"><h1>' . esc_html__( 'Script Audit', 'gm2-wordpress-suite' ) . '</h1>';

if ($handles) {
    echo '<form method="post">';
    wp_nonce_field('gm2_tpa_save', 'gm2_tpa_nonce');
    echo '<table class="widefat fixed"><thead><tr>';
    echo '<th>' . esc_html__( 'Handle', 'gm2-wordpress-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Domain', 'gm2-wordpress-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Size (KB)', 'gm2-wordpress-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Enabled', 'gm2-wordpress-suite' ) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($handles as $handle) {
        $reg = $scripts->registered[$handle] ?? null;
        if (!$reg) {
            continue;
        }
        $src = $reg->src;
        if ($src && !preg_match('#^https?://#', $src)) {
            $src = site_url($src);
        }
        $domain = $src ? (parse_url($src, PHP_URL_HOST) ?: '') : '';
        $size = 0;
        if ($src) {
            $url = $src;
            if (strpos($url, home_url()) === 0) {
                $path = ABSPATH . ltrim(parse_url($url, PHP_URL_PATH), '/');
                if (file_exists($path)) {
                    $size = filesize($path);
                }
            } else {
                $response = wp_remote_head($url);
                if (!is_wp_error($response)) {
                    $len = wp_remote_retrieve_header($response, 'content-length');
                    if ($len) {
                        $size = (int) $len;
                    }
                }
            }
        }
        $size = $size > 0 ? round($size / 1024, 1) : '';
        echo '<tr>';
        echo '<td>' . esc_html($handle) . '</td>';
        echo '<td>' . esc_html($domain) . '</td>';
        echo '<td>' . esc_html($size) . '</td>';
        echo '<td><input type="checkbox" name="allowed[' . esc_attr($handle) . ']" value="1"' . checked(!in_array($handle, $disabled, true), true, false) . '></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    submit_button();
    echo '</form>';
} else {
    echo '<p>' . esc_html__( 'No scripts detected.', 'gm2-wordpress-suite' ) . '</p>';
}

echo '</div>';

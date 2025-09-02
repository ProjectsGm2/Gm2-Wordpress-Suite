<?php
if (!defined('ABSPATH')) {
    exit;
}

$usage = get_option('ae_js_script_usage', []);
if (!is_array($usage) || empty($usage)) {
    echo '<p>' . esc_html__( 'No script usage data recorded yet.', 'gm2-wordpress-suite' ) . '</p>';
    return;
}
arsort($usage);
$templates = [
    'front_page' => esc_html__( 'Front Page', 'gm2-wordpress-suite' ),
    'singular'   => esc_html__( 'Singular', 'gm2-wordpress-suite' ),
    'product'    => esc_html__( 'Product', 'gm2-wordpress-suite' ),
];
$allow = get_option('ae_js_template_allow', []);

echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
wp_nonce_field('gm2_js_usage_save', 'gm2_js_usage_nonce');
echo '<input type="hidden" name="action" value="gm2_js_usage_settings" />';

echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'Handle', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Count', 'gm2-wordpress-suite' ) . '</th>';
foreach ($templates as $key => $label) {
    echo '<th>' . esc_html($label) . '</th>';
}
echo '</tr></thead><tbody>';
foreach ($usage as $handle => $count) {
    echo '<tr><td>' . esc_html($handle) . '</td><td>' . intval($count) . '</td>';
    foreach ($templates as $key => $label) {
        $checked = isset($allow[$handle]) && in_array($key, $allow[$handle], true) ? 'checked' : '';
        echo '<td><input type="checkbox" name="ae_js_template_allow[' . esc_attr($handle) . '][]" value="' . esc_attr($key) . '" ' . $checked . ' /></td>';
    }
    echo '</tr>';
}
echo '</tbody></table>';
submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
echo '</form>';

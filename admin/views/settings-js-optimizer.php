<?php
if (!defined('ABSPATH')) {
    exit;
}

$enable      = get_option('ae_js_enable_manager', '0');
$lazy        = get_option('ae_js_lazy_load', '0');
$replace     = get_option('ae_js_replacements', '0');
$debug       = get_option('ae_js_debug_log', '0');
$auto        = get_option('ae_js_auto_dequeue', '0');
$respect     = get_option('ae_js_respect_safe_mode', '1');
$allow_list  = get_option('ae_js_allow_list', []);
$deny_list   = get_option('ae_js_deny_list', []);
$map         = get_transient('ae_js_dependency_map');
$handles     = is_array($map) ? array_keys($map) : [];

echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
wp_nonce_field('gm2_js_optimizer_save', 'gm2_js_optimizer_nonce');
echo '<input type="hidden" name="action" value="gm2_js_optimizer_settings" />';

echo '<table class="form-table"><tbody>';
echo '<tr><th scope="row">' . esc_html__( 'Enable JS Manager', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_enable_manager" value="1" ' . checked($enable, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Lazy Load Scripts', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_lazy_load" value="1" ' . checked($lazy, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Enable Replacements', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_replacements" value="1" ' . checked($replace, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Debug Log', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_debug_log" value="1" ' . checked($debug, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Enable Per-Page Auto-Dequeue (Beta)', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_auto_dequeue" value="1" ' . checked($auto, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Respect Safe Mode param', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_respect_safe_mode" value="1" ' . checked($respect, '1', false) . ' /></td></tr>';
if (!empty($handles)) {
    sort($handles);
    echo '<tr><th scope="row">' . esc_html__( 'Allow List', 'gm2-wordpress-suite' ) . '</th><td><select multiple size="10" name="ae_js_allow_list[]">';
    foreach ($handles as $h) {
        echo '<option value="' . esc_attr($h) . '" ' . selected(is_array($allow_list) && in_array($h, $allow_list, true), true, false) . '>' . esc_html($h) . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><th scope="row">' . esc_html__( 'Deny List', 'gm2-wordpress-suite' ) . '</th><td><select multiple size="10" name="ae_js_deny_list[]">';
    foreach ($handles as $h) {
        echo '<option value="' . esc_attr($h) . '" ' . selected(is_array($deny_list) && in_array($h, $deny_list, true), true, false) . '>' . esc_html($h) . '</option>';
    }
    echo '</select></td></tr>';
} else {
    echo '<tr><th scope="row">' . esc_html__( 'Allow/Deny Lists', 'gm2-wordpress-suite' ) . '</th><td>' . esc_html__( 'No scripts detected yet.', 'gm2-wordpress-suite' ) . '</td></tr>';
}
echo '</tbody></table>';

submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
echo '</form>';

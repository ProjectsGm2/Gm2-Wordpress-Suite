<?php
if (!defined('ABSPATH')) {
    exit;
}

$enable      = get_option('ae_js_enable_manager', '0');
$lazy        = get_option('ae_js_lazy_load', '0');
$replace     = get_option('ae_js_replacements', '0');
$debug       = get_option('ae_js_debug_log', '0');
$auto        = get_option('ae_js_auto_dequeue', '0');
$safe_mode   = get_option('ae_js_respect_safe_mode', '0');
$allow       = get_option('ae_js_dequeue_allowlist', []);
$deny        = get_option('ae_js_dequeue_denylist', []);
if (!is_array($allow)) {
    $allow = [];
}
if (!is_array($deny)) {
    $deny = [];
}
$scripts    = wp_scripts();
$registered = $scripts instanceof \WP_Scripts ? array_keys($scripts->registered) : [];

echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
wp_nonce_field('gm2_js_optimizer_save', 'gm2_js_optimizer_nonce');
echo '<input type="hidden" name="action" value="gm2_js_optimizer_settings" />';

echo '<table class="form-table"><tbody>';
echo '<tr><th scope="row">' . esc_html__( 'Enable JS Manager', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_enable_manager" value="1" ' . checked($enable, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Lazy Load Scripts', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_lazy_load" value="1" ' . checked($lazy, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Enable Replacements', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_replacements" value="1" ' . checked($replace, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Debug Log', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_debug_log" value="1" ' . checked($debug, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Enable Per-Page Auto-Dequeue (Beta)', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_auto_dequeue" value="1" ' . checked($auto, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Respect Safe Mode param', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_respect_safe_mode" value="1" ' . checked($safe_mode, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Handle Allowlist', 'gm2-wordpress-suite' ) . '</th><td><select name="ae_js_dequeue_allowlist[]" multiple size="10" style="min-width:200px;">';
foreach ($registered as $handle) {
    echo '<option value="' . esc_attr($handle) . '" ' . selected(in_array($handle, $allow, true), true, false) . '>' . esc_html($handle) . '</option>';
}
echo '</select><p class="description">' . esc_html__( 'Always load selected handles.', 'gm2-wordpress-suite' ) . '</p></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Handle Denylist', 'gm2-wordpress-suite' ) . '</th><td><select name="ae_js_dequeue_denylist[]" multiple size="10" style="min-width:200px;">';
foreach ($registered as $handle) {
    echo '<option value="' . esc_attr($handle) . '" ' . selected(in_array($handle, $deny, true), true, false) . '>' . esc_html($handle) . '</option>';
}
echo '</select><p class="description">' . esc_html__( 'Never load selected handles.', 'gm2-wordpress-suite' ) . '</p></td></tr>';
echo '</tbody></table>';

submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
echo '</form>';

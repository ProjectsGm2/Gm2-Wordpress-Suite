<?php
if (!defined('ABSPATH')) {
    exit;
}

$enable      = get_option('ae_js_enable_manager', '0');
$lazy        = get_option('ae_js_lazy_load', '0');
$replace     = get_option('ae_js_replacements', '0');
$debug       = get_option('ae_js_debug_log', '0');

echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
wp_nonce_field('gm2_js_optimizer_save', 'gm2_js_optimizer_nonce');
echo '<input type="hidden" name="action" value="gm2_js_optimizer_settings" />';

echo '<table class="form-table"><tbody>';
echo '<tr><th scope="row">' . esc_html__( 'Enable JS Manager', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_enable_manager" value="1" ' . checked($enable, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Lazy Load Scripts', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_lazy_load" value="1" ' . checked($lazy, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Enable Replacements', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_replacements" value="1" ' . checked($replace, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Debug Log', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_debug_log" value="1" ' . checked($debug, '1', false) . ' /></td></tr>';
echo '</tbody></table>';

submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
echo '</form>';

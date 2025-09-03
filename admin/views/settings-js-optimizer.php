<?php
if (!defined('ABSPATH')) {
    exit;
}

$enable        = get_option('ae_js_enable_manager', '0');
$lazy          = get_option('ae_js_lazy_load', '0');
$lazy_recaptcha = get_option('ae_js_lazy_recaptcha', '0');
$lazy_analytics = get_option('ae_js_lazy_analytics', '0');
$analytics_id   = get_option('ae_js_analytics_id', '');
$gtm_id         = get_option('ae_js_gtm_id', '');
$fb_id          = get_option('ae_js_fb_id', '');
$consent_key    = get_option('ae_js_consent_key', 'aeConsent');
$consent_value  = get_option('ae_js_consent_value', 'allow_analytics');
$replace       = get_option('ae_js_replacements', '0');
$debug         = get_option('ae_js_debug_log', '0');
$auto          = get_option('ae_js_auto_dequeue', '0');
$safe_mode     = get_option('ae_js_respect_safe_mode', '0');
$nomodule      = get_option('ae_js_nomodule_legacy', '0');
$allow         = get_option('ae_js_dequeue_allowlist', []);
$deny          = get_option('ae_js_dequeue_denylist', []);
$jquery_demand = get_option('ae_js_jquery_on_demand', '0');
$jquery_allow  = get_option('ae_js_jquery_url_allow', '');
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
echo '<tr><th scope="row">' . esc_html__( 'Lazy-load reCAPTCHA', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_lazy_recaptcha" value="1" ' . checked($lazy_recaptcha, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Lazy-load Analytics/Tag Manager', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_lazy_analytics" value="1" ' . checked($lazy_analytics, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Analytics Measurement ID', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="ae_js_analytics_id" value="' . esc_attr($analytics_id) . '" placeholder="G-XXXX" /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'GTM ID', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="ae_js_gtm_id" value="' . esc_attr($gtm_id) . '" placeholder="GTM-XXXX" /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Facebook Pixel ID', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="ae_js_fb_id" value="' . esc_attr($fb_id) . '" /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Consent Mode key', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="ae_js_consent_key" value="' . esc_attr($consent_key) . '" /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Consent Mode value to watch', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="ae_js_consent_value" value="' . esc_attr($consent_value) . '" /><p class="description">' . esc_html__( 'Default value is allow_analytics', 'gm2-wordpress-suite' ) . '</p></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Enable Replacements', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_replacements" value="1" ' . checked($replace, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Debug Log', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_debug_log" value="1" ' . checked($debug, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Enable Per-Page Auto-Dequeue (Beta)', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_auto_dequeue" value="1" ' . checked($auto, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Respect Safe Mode param', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_respect_safe_mode" value="1" ' . checked($safe_mode, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Send Legacy (nomodule) Bundle', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_nomodule_legacy" value="1" ' . checked($nomodule, '1', false) . ' /><p class="description">' . esc_html__( 'Include an ES5 bundle for older browsers.', 'gm2-wordpress-suite' ) . '</p></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Load jQuery only when required', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_jquery_on_demand" value="1" ' . checked($jquery_demand, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Always include jQuery on these URLs (regex)', 'gm2-wordpress-suite' ) . '</th><td><textarea name="ae_js_jquery_url_allow" rows="5" cols="50">' . esc_textarea($jquery_allow) . '</textarea><p class="description">' . esc_html__( 'One pattern per line.', 'gm2-wordpress-suite' ) . '</p></td></tr>';
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

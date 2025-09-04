<?php
if (!defined('ABSPATH')) {
    exit;
}

$js_tab = isset($_GET['js-tab']) ? sanitize_key($_GET['js-tab']) : 'settings';
if ($js_tab === 'compatibility') {
    $file = GM2_PLUGIN_DIR . 'config/compat-defaults.php';
    $map = [];
    if (file_exists($file)) {
        $map = include $file;
    }
    if (!is_array($map)) {
        $map = [];
    }
    $overrides = get_option('ae_js_compat_overrides', []);
    if (!is_array($overrides)) {
        $overrides = [];
    }
    $names = [
        'elementor'       => 'Elementor',
        'woocommerce'     => 'WooCommerce',
        'contact-form-7'  => 'Contact Form 7',
        'seo-by-rank-math'=> 'SEO by Rank Math',
    ];
    $descriptions = [
        'elementor' => esc_html__( 'Always allow Elementor frontend scripts to ensure page builder widgets render correctly. Disabling may break layouts or widgets.', 'gm2-wordpress-suite' ),
        'woocommerce' => esc_html__( 'Allow core WooCommerce scripts for carts and checkout. Disabling may disrupt shopping functionality.', 'gm2-wordpress-suite' ),
        'contact-form-7' => esc_html__( 'Keep Contact Form 7 scripts for form validation and submission. Disabling may prevent forms from working.', 'gm2-wordpress-suite' ),
        'seo-by-rank-math' => esc_html__( 'Permit Rank Math scripts for SEO analysis features. Disabling may limit SEO functionality or cause errors.', 'gm2-wordpress-suite' ),
    ];
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('gm2_js_compatibility_save', 'gm2_js_compatibility_nonce');
    echo '<input type="hidden" name="action" value="gm2_js_compatibility_settings" />';
    echo '<table class="form-table"><tbody>';
    foreach ($map as $plugin => $handles) {
        $allowed = empty(array_intersect((array) $handles, $overrides));
        $label = $names[$plugin] ?? ucwords(str_replace('-', ' ', $plugin));
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>';
        echo '<label><input type="checkbox" name="ae_js_compat_plugins[]" value="' . esc_attr($plugin) . '" ' . checked($allowed, true, false) . ' /> ' . esc_html__( 'Allow by default', 'gm2-wordpress-suite' ) . '</label>';
        if (!empty($descriptions[$plugin])) {
            echo '<p class="description">' . esc_html($descriptions[$plugin]) . '</p>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
    submit_button( esc_html__( 'Save Compatibility', 'gm2-wordpress-suite' ) );
    echo '</form>';
    return;
}

$enable        = get_option('ae_js_enable_manager', '0');
$lazy          = get_option('ae_js_lazy_load', '0');
$lazy_recaptcha = get_option('ae_js_lazy_recaptcha', '0');
$lazy_analytics = get_option('ae_js_lazy_analytics', '0');
$analytics_id   = get_option('ae_js_analytics_id', '');
$gtm_id         = get_option('ae_js_gtm_id', '');
$fb_id          = get_option('ae_js_fb_id', '');
$recaptcha_key  = get_option('ae_recaptcha_site_key', '');
$hcaptcha_key   = get_option('ae_hcaptcha_site_key', '');
$consent_key    = get_option('ae_js_consent_key', 'aeConsent');
$consent_value  = get_option('ae_js_consent_value', 'allow_analytics');
$replace       = get_option('ae_js_replacements', '0');
$debug         = get_option('ae_js_debug_log', '0');
$console       = get_option('ae_js_console_log', '0');
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
echo '<tr><th scope="row">' . esc_html__( 'reCAPTCHA Site Key', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="ae_recaptcha_site_key" value="' . esc_attr($recaptcha_key) . '" /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'hCaptcha Site Key', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="ae_hcaptcha_site_key" value="' . esc_attr($hcaptcha_key) . '" /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Lazy-load Analytics/Tag Manager', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_lazy_analytics" value="1" ' . checked($lazy_analytics, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Analytics Measurement IDs', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="ae_js_analytics_id" value="' . esc_attr($analytics_id) . '" placeholder="G-XXXX,G-YYYY" /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'GTM ID', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="ae_js_gtm_id" value="' . esc_attr($gtm_id) . '" placeholder="GTM-XXXX" /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Facebook Pixel IDs', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="ae_js_fb_id" value="' . esc_attr($fb_id) . '" placeholder="12345,67890" /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Consent Mode key', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="ae_js_consent_key" value="' . esc_attr($consent_key) . '" /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Consent Mode value to watch', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="ae_js_consent_value" value="' . esc_attr($consent_value) . '" /><p class="description">' . esc_html__( 'Default value is allow_analytics', 'gm2-wordpress-suite' ) . '</p></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Enable Replacements', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_replacements" value="1" ' . checked($replace, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Debug Log', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_debug_log" value="1" ' . checked($debug, '1', false) . ' /></td></tr>';
echo '<tr><th scope="row">' . esc_html__( 'Log to console in dev', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_js_console_log" value="1" ' . checked($console, '1', false) . ' /></td></tr>';
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

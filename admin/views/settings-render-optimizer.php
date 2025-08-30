<?php
if (!defined('ABSPATH')) {
    exit;
}

$critical    = get_option('ae_seo_critical_css', '0');
$defer       = get_option('ae_seo_defer_js', '0');
$diff        = get_option('ae_seo_diff_serving', '0');
$combine     = get_option('ae_seo_combine_minify', '0');
$manual_css  = get_option('gm2_critical_css_manual', '');
$allow_css   = get_option('gm2_critical_css_allowlist', '');
$deny_css    = get_option('gm2_critical_css_denylist', '');
$allow_js    = get_option('gm2_defer_js_allowlist', '');
$deny_js     = get_option('gm2_defer_js_denylist', '');
$overrides   = get_option('gm2_defer_js_overrides', []);

if (!is_array($overrides)) {
    $overrides = [];
}

echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
wp_nonce_field('gm2_render_optimizer_save', 'gm2_render_optimizer_nonce');
echo '<input type="hidden" name="action" value="gm2_render_optimizer_settings" />';

echo '<table class="form-table"><tbody>';

echo '<tr><th scope="row">' . esc_html__( 'Enable Critical CSS', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_seo_critical_css" value="1" ' . checked($critical, '1', false) . ' /></td></tr>';

echo '<tr><th scope="row">' . esc_html__( 'Enable Defer JS', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_seo_defer_js" value="1" ' . checked($defer, '1', false) . ' /></td></tr>';

echo '<tr><th scope="row">' . esc_html__( 'Enable Differential Serving', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_seo_diff_serving" value="1" ' . checked($diff, '1', false) . ' /></td></tr>';

echo '<tr><th scope="row">' . esc_html__( 'Enable Combine & Minify', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_seo_combine_minify" value="1" ' . checked($combine, '1', false) . ' /></td></tr>';

echo '<tr><th colspan="2"><h2>' . esc_html__( 'Critical CSS', 'gm2-wordpress-suite' ) . '</h2></th></tr>';

echo '<tr><th scope="row">' . esc_html__( 'Manual Critical CSS', 'gm2-wordpress-suite' ) . '</th><td><textarea name="gm2_critical_css_manual" rows="5" class="large-text code">' . esc_textarea($manual_css) . '</textarea></td></tr>';

echo '<tr><th scope="row">' . esc_html__( 'Allowlist Handles', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="gm2_critical_css_allowlist" value="' . esc_attr($allow_css) . '" class="regular-text" /><p class="description">' . esc_html__( 'Comma-separated style handles to inline.', 'gm2-wordpress-suite' ) . '</p></td></tr>';

echo '<tr><th scope="row">' . esc_html__( 'Denylist Handles', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="gm2_critical_css_denylist" value="' . esc_attr($deny_css) . '" class="regular-text" /><p class="description">' . esc_html__( 'Comma-separated style handles to skip.', 'gm2-wordpress-suite' ) . '</p></td></tr>';

echo '<tr><th colspan="2"><h2>' . esc_html__( 'Defer JavaScript', 'gm2-wordpress-suite' ) . '</h2></th></tr>';

echo '<tr><th scope="row">' . esc_html__( 'Allowlist Handles', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="gm2_defer_js_allowlist" value="' . esc_attr($allow_js) . '" class="regular-text" /><p class="description">' . esc_html__( 'Comma-separated script handles to always defer.', 'gm2-wordpress-suite' ) . '</p></td></tr>';

echo '<tr><th scope="row">' . esc_html__( 'Denylist Handles', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="gm2_defer_js_denylist" value="' . esc_attr($deny_js) . '" class="regular-text" /><p class="description">' . esc_html__( 'Comma-separated script handles to exclude.', 'gm2-wordpress-suite' ) . '</p></td></tr>';

echo '<tr><th scope="row">' . esc_html__( 'Per-handle Overrides', 'gm2-wordpress-suite' ) . '</th><td><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Handle', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Attribute', 'gm2-wordpress-suite' ) . '</th></tr></thead><tbody>';
foreach ($overrides as $handle => $attr) {
    echo '<tr><td><input type="text" name="gm2_defer_js_handles[]" value="' . esc_attr($handle) . '" class="regular-text" /></td><td><select name="gm2_defer_js_attrs[]">';
    echo '<option value="defer" ' . selected($attr, 'defer', false) . '>' . esc_html__( 'Defer', 'gm2-wordpress-suite' ) . '</option>';
    echo '<option value="async" ' . selected($attr, 'async', false) . '>' . esc_html__( 'Async', 'gm2-wordpress-suite' ) . '</option>';
    echo '<option value="blocking" ' . selected($attr, 'blocking', false) . '>' . esc_html__( 'Blocking', 'gm2-wordpress-suite' ) . '</option>';
    echo '</select></td></tr>';
}

echo '<tr><td><input type="text" name="gm2_defer_js_handles[]" value="" class="regular-text" /></td><td><select name="gm2_defer_js_attrs[]"><option value="defer">' . esc_html__( 'Defer', 'gm2-wordpress-suite' ) . '</option><option value="async">' . esc_html__( 'Async', 'gm2-wordpress-suite' ) . '</option><option value="blocking" selected="selected">' . esc_html__( 'Blocking', 'gm2-wordpress-suite' ) . '</option></select></td></tr>';

echo '</tbody></table><p class="description">' . esc_html__( 'Leave handle blank to ignore. Choose Blocking to remove override.', 'gm2-wordpress-suite' ) . '</p></td></tr>';

echo '</tbody></table>';
submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
echo '</form>';

echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
wp_nonce_field('gm2_purge_critical_css');
echo '<input type="hidden" name="action" value="gm2_purge_critical_css" />';
submit_button(esc_html__( 'Purge & Rebuild Critical CSS', 'gm2-wordpress-suite' ), 'delete');
echo '</form>';

echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
wp_nonce_field('gm2_purge_optimizer_cache');
echo '<input type="hidden" name="action" value="gm2_purge_optimizer_cache" />';
submit_button(esc_html__( 'Purge Combined Assets', 'gm2-wordpress-suite' ), 'delete');
echo '</form>';

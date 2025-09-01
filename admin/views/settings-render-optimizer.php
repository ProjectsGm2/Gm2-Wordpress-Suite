<?php
if (!defined('ABSPATH')) {
    exit;
}

$critical   = get_option('ae_seo_ro_enable_critical_css', '0');
$strategy   = get_option('ae_seo_ro_critical_strategy', 'per_home_archive_single');
$async      = get_option('ae_seo_ro_async_css_method', 'preload_onload');
$css_map    = get_option('ae_seo_ro_critical_css_map', []);
$exclusions = get_option('ae_seo_ro_critical_css_exclusions', '');
$allow_domains = get_option('ae_seo_ro_defer_allow_domains', '');
$deny_domains  = get_option('ae_seo_ro_defer_deny_domains', '');
$post_types = get_post_types(['public' => true], 'objects');

echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
wp_nonce_field('gm2_render_optimizer_save', 'gm2_render_optimizer_nonce');
echo '<input type="hidden" name="action" value="gm2_render_optimizer_settings" />';

echo '<table class="form-table"><tbody>';

echo '<tr><th scope="row">' . esc_html__( 'Enable Critical CSS', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="ae_seo_ro_enable_critical_css" value="1" ' . checked($critical, '1', false) . ' /></td></tr>';

echo '<tr><th scope="row">' . esc_html__( 'Critical CSS Strategy', 'gm2-wordpress-suite' ) . '</th><td><select name="ae_seo_ro_critical_strategy">';
echo '<option value="per_home_archive_single" ' . selected($strategy, 'per_home_archive_single', false) . '>' . esc_html__( 'Home/Archive/Single', 'gm2-wordpress-suite' ) . '</option>';
echo '<option value="per_url" ' . selected($strategy, 'per_url', false) . '>' . esc_html__( 'Per URL', 'gm2-wordpress-suite' ) . '</option>';
echo '</select></td></tr>';

echo '<tr><th scope="row">' . esc_html__( 'Async CSS Method', 'gm2-wordpress-suite' ) . '</th><td><select name="ae_seo_ro_async_css_method">';
echo '<option value="preload_onload" ' . selected($async, 'preload_onload', false) . '>' . esc_html__( 'Preload + onload', 'gm2-wordpress-suite' ) . '</option>';
echo '<option value="media_swap" ' . selected($async, 'media_swap', false) . '>' . esc_html__( 'Media swap', 'gm2-wordpress-suite' ) . '</option>';
echo '</select></td></tr>';

echo '<tr><th scope="row">' . esc_html__( 'Home CSS', 'gm2-wordpress-suite' ) . '</th><td><textarea name="ae_seo_ro_critical_css_map[home]" rows="5" class="large-text code">' . esc_textarea($css_map['home'] ?? '') . '</textarea></td></tr>';

echo '<tr><th scope="row">' . esc_html__( 'Archive CSS', 'gm2-wordpress-suite' ) . '</th><td><textarea name="ae_seo_ro_critical_css_map[archive]" rows="5" class="large-text code">' . esc_textarea($css_map['archive'] ?? '') . '</textarea></td></tr>';

foreach ($post_types as $type) {
    $key   = 'single-' . $type->name;
    $label = sprintf(esc_html__( 'Single %s CSS', 'gm2-wordpress-suite' ), $type->labels->singular_name);
    $value = $css_map[$key] ?? '';
    echo '<tr><th scope="row">' . $label . '</th><td><textarea name="ae_seo_ro_critical_css_map[' . esc_attr($key) . ']" rows="5" class="large-text code">' . esc_textarea($value) . '</textarea></td></tr>';
}

echo '<tr><th scope="row">' . esc_html__( 'Excluded Handles', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="ae_seo_ro_critical_css_exclusions" value="' . esc_attr($exclusions) . '" class="regular-text" /><p class="description">' . esc_html__( 'Comma-separated style handles to skip.', 'gm2-wordpress-suite' ) . '</p></td></tr>';

echo '<tr><th scope="row">' . esc_html__( 'Allow Domains', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="ae_seo_ro_defer_allow_domains" value="' . esc_attr($allow_domains) . '" class="regular-text" /><p class="description">' . esc_html__( 'Comma-separated hostnames to always async/defer.', 'gm2-wordpress-suite' ) . '</p></td></tr>';

echo '<tr><th scope="row">' . esc_html__( 'Deny Domains', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="ae_seo_ro_defer_deny_domains" value="' . esc_attr($deny_domains) . '" class="regular-text" /><p class="description">' . esc_html__( 'Comma-separated hostnames to exclude from defer.', 'gm2-wordpress-suite' ) . '</p></td></tr>';

echo '</tbody></table>';

echo '<p class="description"><a href="#">' . esc_html__( 'Learn how to auto-generate', 'gm2-wordpress-suite' ) . '</a></p>';

submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
echo '</form>';

echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
wp_nonce_field('gm2_purge_critical_css');
echo '<input type="hidden" name="action" value="gm2_purge_critical_css" />';
submit_button(esc_html__( 'Purge & Rebuild Critical CSS', 'gm2-wordpress-suite' ), 'delete');
echo '</form>';

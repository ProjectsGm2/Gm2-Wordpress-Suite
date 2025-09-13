<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('aeseo_lcp_settings', []);
if (!is_array($settings)) {
    $settings = [];
}

$defaults = [
    'remove_lazy_on_lcp'       => '0',
    'add_fetchpriority_high'   => '0',
    'force_width_height'       => '0',
    'responsive_picture_nextgen' => '0',
    'add_preconnect'           => '0',
    'add_preload'              => '0',
    'fix_media_dimensions'     => '1',
];
$settings = array_merge($defaults, $settings);

echo '<div class="wrap">';
echo '<h1>' . esc_html__('LCP Optimization', 'gm2-wordpress-suite') . '</h1>';
if (isset($_GET['settings-updated'])) {
    echo '<div id="message" class="updated notice is-dismissible"><p>' . esc_html__('Settings saved.', 'gm2-wordpress-suite') . '</p></div>';
}
echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
wp_nonce_field('gm2_lcp_settings');
echo '<input type="hidden" name="action" value="gm2_lcp_settings" />';

echo '<table class="form-table"><tbody>';

$fields = [
    'remove_lazy_on_lcp'       => esc_html__('Remove lazy-loading on LCP image', 'gm2-wordpress-suite'),
    'add_fetchpriority_high'   => esc_html__('Add fetchpriority="high" to LCP image', 'gm2-wordpress-suite'),
    'force_width_height'       => esc_html__('Force width/height attributes', 'gm2-wordpress-suite'),
    'responsive_picture_nextgen' => esc_html__('Serve responsive <picture> with next-gen formats', 'gm2-wordpress-suite'),
    'add_preconnect'           => esc_html__('Preconnect to LCP origin', 'gm2-wordpress-suite'),
    'add_preload'              => esc_html__('Preload LCP image', 'gm2-wordpress-suite'),
    'fix_media_dimensions'     => esc_html__('Fix image & media dimensions to prevent layout shifts', 'gm2-wordpress-suite'),
];

foreach ($fields as $key => $label) {
    $checked = $settings[$key] === '1' ? 'checked="checked"' : '';
    echo '<tr><th scope="row">' . $label . '</th><td><input type="checkbox" name="aeseo_lcp_settings[' . esc_attr($key) . ']" value="1" ' . $checked . ' /></td></tr>';
}

echo '</tbody></table>';

submit_button(esc_html__('Save Settings', 'gm2-wordpress-suite'));
echo '</form>';
echo '</div>';

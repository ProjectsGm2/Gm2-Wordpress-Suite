<?php
if (!defined('ABSPATH')) {
    exit;
}

\Plugin\CLS\Fonts\get_discovered_fonts();
$enabled  = get_option('plugin_cls_fonts_enabled', '1');
$selected = get_option('plugin_cls_critical_fonts', []);
if (!is_array($selected)) {
    $selected = [];
}
$selected_map = [];
foreach ($selected as $font) {
    if (is_array($font) && isset($font['url'])) {
        $selected_map[$font['url']] = true;
    }
}
$available = \Plugin\CLS\Fonts\get_discovered_fonts();

echo '<div class="wrap">';
echo '<h1>' . esc_html__( 'Critical Font Preload', 'gm2-wordpress-suite' ) . '</h1>';
if (isset($_GET['settings-updated'])) {
    echo '<div id="message" class="updated notice is-dismissible"><p>' . esc_html__( 'Settings saved.', 'gm2-wordpress-suite' ) . '</p></div>';
}
echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
wp_nonce_field( 'gm2_cls_fonts' );
echo '<input type="hidden" name="action" value="gm2_cls_fonts" />';

echo '<p><label><input type="checkbox" name="cls_fonts_enabled" value="1" ' . checked( $enabled, '1', false ) . ' /> ' . esc_html__( 'Enable Critical Font Preload', 'gm2-wordpress-suite' ) . '</label></p>';

echo '<h2>' . esc_html__( 'Select Fonts to Preload', 'gm2-wordpress-suite' ) . '</h2>';
if (!empty($available)) {
    foreach ($available as $font) {
        $url = $font['url'] ?? '';
        if ($url === '') {
            continue;
        }
        $family = $font['family'] ?? '';
        $weight = $font['weight'] ?? '';
        $style  = $font['style'] ?? '';
        $label  = trim( $family . ' ' . $weight . ( $style && $style !== 'normal' ? ' ' . $style : '' ) );
        $checked = isset( $selected_map[ $url ] );
        echo '<p><label><input type="checkbox" class="cls-font-checkbox" name="cls_fonts[]" value="' . esc_attr( $url ) . '" ' . checked( $checked, true, false ) . ' /> ' . esc_html( $label ) . '</label></p>';
    }
} else {
    echo '<p>' . esc_html__( 'No fonts discovered yet.', 'gm2-wordpress-suite' ) . '</p>';
}

submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
echo ' ';
submit_button( esc_html__( 'Clear learned fonts', 'gm2-wordpress-suite' ), 'secondary', 'cls_fonts_clear', false );
echo '</form>';
?>
<script>
(function(){
    const limit = 3;
    const boxes = document.querySelectorAll('.cls-font-checkbox');
    function update(){
        const checked = Array.from(boxes).filter(b => b.checked).length;
        boxes.forEach(b => {
            if(!b.checked){
                b.disabled = checked >= limit;
            }
        });
    }
    boxes.forEach(b => b.addEventListener('change', update));
    update();
})();
</script>
</div>

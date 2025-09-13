<?php
if (!defined('ABSPATH')) {
    exit;
}

$reservations = get_option('plugin_cls_reservations', []);
if (!is_array($reservations)) {
    $reservations = [];
}
if (empty($reservations)) {
    $reservations[] = ['selector' => '', 'min' => '', 'unreserve' => '1'];
}
$sticky_header = get_option('plugin_cls_sticky_header', '0');
$sticky_footer = get_option('plugin_cls_sticky_footer', '0');

echo '<div class="wrap">';
echo '<h1>' . esc_html__('Layout Reservations', 'gm2-wordpress-suite') . '</h1>';
if (isset($_GET['settings-updated'])) {
    echo '<div id="message" class="updated notice is-dismissible"><p>' . esc_html__('Settings saved.', 'gm2-wordpress-suite') . '</p></div>';
}

echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
wp_nonce_field('gm2_cls_reservations');
echo '<input type="hidden" name="action" value="gm2_cls_reservations" />';

echo '<table class="widefat fixed" id="cls-reservations-table">';
echo '<thead><tr><th>' . esc_html__('Selector', 'gm2-wordpress-suite') . '</th><th>' . esc_html__('Min Height (px)', 'gm2-wordpress-suite') . '</th><th>' . esc_html__('Unreserve when loaded', 'gm2-wordpress-suite') . '</th><th></th></tr></thead>';
echo '<tbody>';
foreach ($reservations as $row) {
    $selector = esc_attr($row['selector'] ?? '');
    $min = isset($row['min']) ? (int) $row['min'] : '';
    $checked = (!isset($row['unreserve']) || $row['unreserve'] === '1' || $row['unreserve'] === 1) ? 'checked="checked"' : '';
    echo '<tr><td><input type="text" name="cls_reservations[][selector]" value="' . $selector . '" /></td><td><input type="number" class="small-text" name="cls_reservations[][min]" value="' . esc_attr($min) . '" /></td><td><input type="checkbox" name="cls_reservations[][unreserve]" value="1" ' . $checked . ' /></td><td><button type="button" class="button remove-reservation">&times;</button></td></tr>';
}
echo '</tbody>';
echo '</table>';

echo '<p><button type="button" class="button" id="add-reservation">' . esc_html__('Add Reservation', 'gm2-wordpress-suite') . '</button></p>';

echo '<h2>' . esc_html__('Sticky Elements', 'gm2-wordpress-suite') . '</h2>';
echo '<p><label><input type="checkbox" name="cls_sticky_header" value="1" ' . checked($sticky_header, '1', false) . ' /> ' . esc_html__('Reserve space for sticky header', 'gm2-wordpress-suite') . '</label></p>';
echo '<p><label><input type="checkbox" name="cls_sticky_footer" value="1" ' . checked($sticky_footer, '1', false) . ' /> ' . esc_html__('Reserve space for sticky footer', 'gm2-wordpress-suite') . '</label></p>';

submit_button(esc_html__('Save Settings', 'gm2-wordpress-suite'));
echo '</form>';
?>
<script>
(function(){
    const tableBody = document.querySelector('#cls-reservations-table tbody');
    document.getElementById('add-reservation').addEventListener('click', () => {
        const row = document.createElement('tr');
        row.innerHTML = '<td><input type="text" name="cls_reservations[][selector]" value="" /></td>' +
            '<td><input type="number" class="small-text" name="cls_reservations[][min]" value="" /></td>' +
            '<td><input type="checkbox" name="cls_reservations[][unreserve]" value="1" checked="checked" /></td>' +
            '<td><button type="button" class="button remove-reservation">&times;</button></td>';
        tableBody.appendChild(row);
    });
    tableBody.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-reservation')) {
            e.preventDefault();
            e.target.closest('tr').remove();
        }
    });
})();
</script>
<?php
echo '</div>';

<?php
if ($this->is_locked()) {
    $this->display_locked_page(esc_html__( 'Schema Mapping', 'gm2-wordpress-suite' ));
    return;
}
if (!$this->can_manage()) {
    wp_die(esc_html__( 'Permission denied', 'gm2-wordpress-suite' ));
}

echo '<div class="wrap">';
echo '<h1>' . esc_html__( 'Schema Mapping', 'gm2-wordpress-suite' ) . '</h1>';

echo '<p><label>' . esc_html__( 'Post Type', 'gm2-wordpress-suite' ) . '<br /><select id="gm2-schema-cpt"></select></label></p>';

echo '<p><label>' . esc_html__( 'Schema @type', 'gm2-wordpress-suite' ) . '<br /><input type="text" id="gm2-schema-type" class="regular-text" /></label></p>';

$presets = [ 'LocalBusiness', 'Event', 'RealEstateListing', 'JobPosting', 'Course' ];
echo '<p><label>' . esc_html__( 'Preset', 'gm2-wordpress-suite' ) . '<br /><select id="gm2-preset"><option value="">' . esc_html__( 'None', 'gm2-wordpress-suite' ) . '</option>';
foreach ($presets as $preset) {
    echo '<option value="' . esc_attr($preset) . '">' . esc_html($preset) . '</option>';
}
echo '</select></label></p>';
echo '<p class="description">' . esc_html__( 'Presets now cover LocalBusiness geo/opening hours, Event offers and organizers, real estate offers and location details, job location/base salary, and Course instance scheduling. Use dotted property paths (e.g. courseInstance.startDate) for nested schema fields.', 'gm2-wordpress-suite' ) . '</p>';

echo '<table class="widefat fixed" id="gm2-schema-table"><thead><tr><th>' . esc_html__( 'Property', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Field Key', 'gm2-wordpress-suite' ) . '</th><th></th></tr></thead><tbody></tbody></table>';

echo '<p><button type="button" class="button" id="gm2-add-row">' . esc_html__( 'Add Property', 'gm2-wordpress-suite' ) . '</button></p>';

echo '<p><button type="button" class="button button-primary" id="gm2-save-schema">' . esc_html__( 'Save Mapping', 'gm2-wordpress-suite' ) . '</button></p>';

echo '<datalist id="gm2-field-options"></datalist>';

echo '</div>';


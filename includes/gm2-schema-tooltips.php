<?php
/**
 * Inline schema tooltips and contextual help helpers.
 */

if (!function_exists('gm2_schema_tooltip')) {
    /**
     * Wrap a label with schema tooltip data.
     *
     * @param string $schema Schema description.
     * @param string $label  Text to display.
     * @return string HTML span with tooltip data.
     */
    function gm2_schema_tooltip($schema, $label)
    {
        $schema_attr = esc_attr($schema);
        $label_text  = esc_html($label);
        return '<span class="gm2-schema-field" data-schema="' . $schema_attr . '">' . $label_text . '</span>';
    }
}

if (!function_exists('gm2_enqueue_schema_tooltips')) {
    /**
     * Enqueue script that enables schema tooltips.
     */
    function gm2_enqueue_schema_tooltips()
    {
        wp_enqueue_script(
            'gm2-schema-tooltips',
            GM2_PLUGIN_URL . 'admin/js/gm2-schema-tooltips.js',
            ['jquery'],
            GM2_VERSION,
            true
        );
    }
    add_action('admin_enqueue_scripts', 'gm2_enqueue_schema_tooltips');
}

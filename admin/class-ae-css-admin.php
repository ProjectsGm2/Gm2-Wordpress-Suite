<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

use AE\CSS\AE_CSS_Optimizer;

/**
 * Admin interface for CSS Optimization settings.
 */
class AE_CSS_Admin {
    /**
     * Hook registrations.
     */
    public function run(): void {
        add_action('admin_init', [ $this, 'register_settings' ]);
        add_action('admin_menu', [ $this, 'add_menu' ]);
    }

    /**
     * Register setting and sanitization.
     */
    public function register_settings(): void {
        register_setting(
            'ae_css',
            'ae_css_settings',
            [
                'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
                'default'           => [],
            ]
        );
    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $input Raw submitted settings.
     * @return array Sanitized settings merged with existing.
     */
    public static function sanitize_settings($input): array {
        $current = get_option('ae_css_settings', [ 'flags' => [], 'critical' => [], 'queue' => [] ]);
        if (!is_array($current)) {
            $current = [ 'flags' => [], 'critical' => [], 'queue' => [] ];
        }

        $flags = [ 'woo', 'elementor' ];
        $sanitized_flags = [];
        if (isset($input['flags']) && is_array($input['flags'])) {
            foreach ($flags as $flag) {
                $sanitized_flags[$flag] = isset($input['flags'][$flag]) && $input['flags'][$flag] === '1' ? '1' : '0';
            }
        } else {
            foreach ($flags as $flag) {
                $sanitized_flags[$flag] = '0';
            }
        }

        $safelist = isset($input['safelist']) ? sanitize_textarea_field($input['safelist']) : '';

        $exclude = [];
        if (!empty($input['exclude_handles']) && is_array($input['exclude_handles'])) {
            $exclude = array_values(array_unique(array_map('sanitize_key', $input['exclude_handles'])));
        }

        $include = [];
        if (!empty($input['include_above_the_fold_handles']) && is_array($input['include_above_the_fold_handles'])) {
            $include = array_values(array_unique(array_map('sanitize_key', $input['include_above_the_fold_handles'])));
        }

        $current['flags'] = $sanitized_flags;
        $current['safelist'] = $safelist;
        $current['exclude_handles'] = $exclude;
        $current['include_above_the_fold_handles'] = $include;

        return $current;
    }

    /**
     * Add submenu page.
     */
    public function add_menu(): void {
        add_submenu_page(
            'gm2-seo',
            __( 'CSS Optimization', 'gm2-wordpress-suite' ),
            __( 'CSS Optimization', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-css-optimization',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Render settings page.
     */
    public function render_page(): void {
        $settings = get_option('ae_css_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $flags = $settings['flags'] ?? [];
        $safelist = $settings['safelist'] ?? '';
        $exclude = $settings['exclude_handles'] ?? [];
        $include = $settings['include_above_the_fold_handles'] ?? [];

        $all_handles = [];
        $styles = wp_styles();
        if ($styles instanceof \WP_Styles) {
            $all_handles = array_keys($styles->registered);
        }

        $flag_labels = [
            'woo'       => __( 'Dequeue WooCommerce styles on non-WooCommerce pages', 'gm2-wordpress-suite' ),
            'elementor' => __( 'Dequeue Elementor styles on non-Elementor pages', 'gm2-wordpress-suite' ),
        ];

        $has_node = AE_CSS_Optimizer::has_node_capability();
        $badge_text = $has_node
            ? __( 'Node tools available (PurgeCSS/Penthouse)', 'gm2-wordpress-suite' )
            : __( 'PHP fallback mode', 'gm2-wordpress-suite' );
        $badge_color = $has_node ? '#46b450' : '#dc3232';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'CSS Optimization', 'gm2-wordpress-suite' ) . '</h1>';
        echo '<p><span style="display:inline-block;padding:2px 6px;color:#fff;border-radius:3px;background:' . esc_attr($badge_color) . ';">' . esc_html($badge_text) . '</span></p>';

        echo '<form method="post" action="options.php">';
        settings_fields('ae_css');

        echo '<table class="form-table"><tbody>';
        // Flags
        echo '<tr><th scope="row">' . esc_html__( 'Automatic dequeues', 'gm2-wordpress-suite' ) . '</th><td>';
        foreach ($flag_labels as $key => $label) {
            $checked = !empty($flags[$key]) && $flags[$key] === '1' ? 'checked="checked"' : '';
            echo '<label style="display:block;"><input type="checkbox" name="ae_css_settings[flags][' . esc_attr($key) . ']" value="1" ' . $checked . ' /> ' . esc_html($label) . '</label>';
        }
        echo '</td></tr>';

        // Safelist textarea
        echo '<tr><th scope="row"><label for="ae-css-safelist">' . esc_html__( 'Safelist', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><textarea id="ae-css-safelist" name="ae_css_settings[safelist]" rows="5" cols="50" class="large-text code">' . esc_textarea($safelist) . '</textarea><p class="description">' . esc_html__( 'One selector per line to always keep.', 'gm2-wordpress-suite' ) . '</p></td></tr>';

        // Exclude handles
        echo '<tr><th scope="row"><label for="ae-css-exclude">' . esc_html__( 'Exclude Handles', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><select id="ae-css-exclude" name="ae_css_settings[exclude_handles][]" multiple size="10" style="width:100%;">';
        foreach ($all_handles as $handle) {
            $selected = in_array($handle, $exclude, true) ? 'selected="selected"' : '';
            echo '<option value="' . esc_attr($handle) . '" ' . $selected . '>' . esc_html($handle) . '</option>';
        }
        echo '</select></td></tr>';

        // Include above the fold handles
        echo '<tr><th scope="row"><label for="ae-css-include">' . esc_html__( 'Include Above The Fold', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><select id="ae-css-include" name="ae_css_settings[include_above_the_fold_handles][]" multiple size="10" style="width:100%;">';
        foreach ($all_handles as $handle) {
            $selected = in_array($handle, $include, true) ? 'selected="selected"' : '';
            echo '<option value="' . esc_attr($handle) . '" ' . $selected . '>' . esc_html($handle) . '</option>';
        }
        echo '</select></td></tr>';

        echo '</tbody></table>';

        submit_button();
        echo '</form>';
        echo '</div>';
    }
}

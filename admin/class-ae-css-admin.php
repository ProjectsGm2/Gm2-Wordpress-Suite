<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

use AE\CSS\AE_CSS_Optimizer;
use AE\CSS\AE_CSS_Queue;

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
        add_action('admin_post_ae_css_purge', [ $this, 'handle_purge_request' ]);
        add_action('admin_post_ae_css_generate_critical', [ $this, 'handle_generate_critical_request' ]);
        add_action('admin_notices', [ $this, 'show_queue_notices' ]);
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
                'default'           => [
                    'flags'                         => [],
                    'safelist'                      => '',
                    'exclude_handles'               => [],
                    'include_above_the_fold_handles'=> [],
                    'generate_critical'             => '0',
                    'async_load_noncritical'        => '0',
                    'woocommerce_smart_enqueue'     => '0',
                    'elementor_smart_enqueue'       => '0',
                    'critical'                      => [],
                ],
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
        $defaults = [
            'flags'                         => [],
            'safelist'                      => '',
            'exclude_handles'               => [],
            'include_above_the_fold_handles'=> [],
            'generate_critical'             => '0',
            'async_load_noncritical'        => '0',
            'woocommerce_smart_enqueue'     => '0',
            'elementor_smart_enqueue'       => '0',
            'critical'                      => [],
        ];
        $current = get_option('ae_css_settings', $defaults);
        if (!is_array($current)) {
            $current = $defaults;
        }

        $sanitized_flags = [];
        $flag_inputs    = isset($input['flags']) && is_array($input['flags']) ? $input['flags'] : [];
        $known_flags    = array_unique(array_merge(array_keys($current['flags']), array_keys($flag_inputs)));
        foreach ($known_flags as $flag) {
            $key = sanitize_key($flag);
            $sanitized_flags[$key] = isset($flag_inputs[$flag]) && $flag_inputs[$flag] === '1' ? '1' : '0';
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

        $generate  = isset($input['generate_critical']) && $input['generate_critical'] === '1' ? '1' : '0';
        $async     = isset($input['async_load_noncritical']) && $input['async_load_noncritical'] === '1' ? '1' : '0';
        $woo       = isset($input['woocommerce_smart_enqueue']) && $input['woocommerce_smart_enqueue'] === '1' ? '1' : '0';
        $elementor = isset($input['elementor_smart_enqueue']) && $input['elementor_smart_enqueue'] === '1' ? '1' : '0';

        $current['flags']                        = $sanitized_flags;
        $current['safelist']                     = $safelist;
        $current['exclude_handles']              = $exclude;
        $current['include_above_the_fold_handles']= $include;
        $current['generate_critical']            = $generate;
        $current['async_load_noncritical']       = $async;
        $current['woocommerce_smart_enqueue']    = $woo;
        $current['elementor_smart_enqueue']      = $elementor;

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
     * Handle immediate PurgeCSS requests by scheduling a cron event.
     */
    public function handle_purge_request(): void {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        check_admin_referer('ae_css_purge');
        $dir    = get_stylesheet_directory();
        $status = get_option('ae_css_job_status', []);
        $status['purge'] = [ 'status' => 'queued', 'message' => '' ];
        update_option('ae_css_job_status', $status, false);
        wp_schedule_single_event(time() + 1, 'ae_css_run_purgecss', [ $dir ]);
        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=gm2-css-optimization'));
        exit;
    }

    /**
     * Handle critical CSS generation requests for a URL.
     */
    public function handle_generate_critical_request(): void {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        check_admin_referer('ae_css_critical');
        $url = isset($_POST['critical_url']) ? esc_url_raw(wp_unslash($_POST['critical_url'])) : '';
        if ($url !== '') {
            AE_CSS_Queue::get_instance()->enqueue('critical', [ 'url' => $url ]);
        }
        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=gm2-css-optimization'));
        exit;
    }

    /**
     * Display status messages for background CSS jobs.
     */
    public function show_queue_notices(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $status = get_option('ae_css_job_status', []);
        foreach ($status as $job => $data) {
            if (empty($data['status']) || $data['status'] === 'idle') {
                continue;
            }
            $msg = ucfirst($job) . ': ' . $data['status'];
            if (!empty($data['message'])) {
                $msg .= ' - ' . $data['message'];
            }
            echo '<div class="notice notice-info"><p>' . esc_html($msg) . '</p></div>';
        }
    }

    /**
     * Render settings page.
     */
    public function render_page(): void {
        $settings = get_option('ae_css_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $flags   = $settings['flags'] ?? [];
        $safelist = $settings['safelist'] ?? '';
        $exclude = $settings['exclude_handles'] ?? [];
        $include = $settings['include_above_the_fold_handles'] ?? [];
        $generate = $settings['generate_critical'] ?? '0';
        $async    = $settings['async_load_noncritical'] ?? '0';
        $woo_smart = $settings['woocommerce_smart_enqueue'] ?? '0';
        $elementor_smart = $settings['elementor_smart_enqueue'] ?? '0';

        $all_handles = [];
        $styles = wp_styles();
        if ($styles instanceof \WP_Styles) {
            $all_handles = array_keys($styles->registered);
        }

        $flag_labels = [
            'woo'       => __( 'Force keep WooCommerce styles', 'gm2-wordpress-suite' ),
            'elementor' => __( 'Force keep Elementor styles', 'gm2-wordpress-suite' ),
        ];

        $has_node = AE_CSS_Optimizer::has_node_capability();
        $badge_text = $has_node
            ? __( 'Node tools available (PurgeCSS/Penthouse)', 'gm2-wordpress-suite' )
            : __( 'PHP fallback in use', 'gm2-wordpress-suite' );
        $badge_color = $has_node ? '#46b450' : '#dc3232';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'CSS Optimization', 'gm2-wordpress-suite' ) . '</h1>';
        echo '<p><span style="display:inline-block;padding:2px 6px;color:#fff;border-radius:3px;background:' . esc_attr($badge_color) . ';">' . esc_html($badge_text) . '</span></p>';

        echo '<form method="post" action="options.php">';
        settings_fields('ae_css');

        echo '<table class="form-table"><tbody>';
        // Flags
        echo '<tr><th scope="row">' . esc_html__( 'Force Keep Styles', 'gm2-wordpress-suite' ) . '</th><td>';
        foreach ($flag_labels as $key => $label) {
            $checked = !empty($flags[$key]) && $flags[$key] === '1' ? 'checked="checked"' : '';
            echo '<label style="display:block;"><input type="checkbox" name="ae_css_settings[flags][' . esc_attr($key) . ']" value="1" ' . $checked . ' /> ' . esc_html($label) . '</label>';
        }
        echo '</td></tr>';

        // Critical and async toggles
        echo '<tr><th scope="row">' . esc_html__( 'Critical & Async CSS', 'gm2-wordpress-suite' ) . '</th><td>';
        $checked = $generate === '1' ? 'checked="checked"' : '';
        echo '<label style="display:block;"><input type="checkbox" name="ae_css_settings[generate_critical]" value="1" ' . $checked . ' /> ' . esc_html__( 'Inline critical CSS when available', 'gm2-wordpress-suite' ) . '</label>';
        $checked = $async === '1' ? 'checked="checked"' : '';
        echo '<label style="display:block;"><input type="checkbox" name="ae_css_settings[async_load_noncritical]" value="1" ' . $checked . ' /> ' . esc_html__( 'Load non-critical CSS asynchronously', 'gm2-wordpress-suite' ) . '</label>';
        echo '</td></tr>';

        // Smart enqueue toggles
        echo '<tr><th scope="row">' . esc_html__( 'Smart Enqueue', 'gm2-wordpress-suite' ) . '</th><td>';
        $checked = $woo_smart === '1' ? 'checked="checked"' : '';
        echo '<label style="display:block;"><input type="checkbox" name="ae_css_settings[woocommerce_smart_enqueue]" value="1" ' . $checked . ' /> ' . esc_html__( 'Only load WooCommerce styles on WooCommerce pages', 'gm2-wordpress-suite' ) . '</label>';
        $checked = $elementor_smart === '1' ? 'checked="checked"' : '';
        echo '<label style="display:block;"><input type="checkbox" name="ae_css_settings[elementor_smart_enqueue]" value="1" ' . $checked . ' /> ' . esc_html__( 'Only load Elementor styles on Elementor pages', 'gm2-wordpress-suite' ) . '</label>';
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
        echo '</select><p class="description">' . esc_html__( 'Styles to ignore during optimization.', 'gm2-wordpress-suite' ) . '</p></td></tr>';

        // Include above the fold handles
        echo '<tr><th scope="row"><label for="ae-css-include">' . esc_html__( 'Include Above The Fold', 'gm2-wordpress-suite' ) . '</label></th>';
        echo '<td><select id="ae-css-include" name="ae_css_settings[include_above_the_fold_handles][]" multiple size="10" style="width:100%;">';
        foreach ($all_handles as $handle) {
            $selected = in_array($handle, $include, true) ? 'selected="selected"' : '';
            echo '<option value="' . esc_attr($handle) . '" ' . $selected . '>' . esc_html($handle) . '</option>';
        }
        echo '</select><p class="description">' . esc_html__( 'Styles always kept above the fold.', 'gm2-wordpress-suite' ) . '</p></td></tr>';

        echo '</tbody></table>';
        submit_button();
        echo '</form>';

        echo '<hr />';

        // PurgeCSS button.
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ae_css_purge');
        echo '<input type="hidden" name="action" value="ae_css_purge" />';
        submit_button(__( 'Run PurgeCSS now', 'gm2-wordpress-suite' ), 'secondary');
        echo '</form>';

        // Critical CSS generation button.
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:15px;">';
        wp_nonce_field('ae_css_critical');
        echo '<input type="hidden" name="action" value="ae_css_generate_critical" />';
        echo '<input type="url" name="critical_url" class="regular-text" placeholder="https://example.com" required /> ';
        submit_button(__( 'Generate Critical CSS for this URL', 'gm2-wordpress-suite' ), 'secondary');
        echo '</form>';

        echo '</div>';
    }
}

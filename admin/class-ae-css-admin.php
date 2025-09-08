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
    private const DEFAULTS = [
        'flags'                         => [],
        'safelist'                      => [],
        'exclude_handles'               => [],
        'include_above_the_fold_handles'=> [],
        'generate_critical'             => '0',
        'async_load_noncritical'        => '0',
        'woocommerce_smart_enqueue'     => '0',
        'elementor_smart_enqueue'       => '0',
        'critical'                      => [],
        'logs'                          => [],
    ];

    /**
     * Hook registrations.
     */
    public function run(): void {
        add_action('admin_init', [ $this, 'register_settings' ]);
        add_action('admin_menu', [ $this, 'add_menu' ]);
        add_action('admin_post_ae_css_purge', [ $this, 'handle_purge_request' ]);
        add_action('admin_post_ae_css_generate_critical', [ $this, 'handle_generate_critical_request' ]);
        add_action('admin_notices', [ $this, 'show_queue_notices' ]);
        add_action('wp_ajax_ae_css_estimate_savings', [ $this, 'ajax_estimate_savings' ]);
        add_action('load-gm2-css-optimization', [ $this, 'add_help_tabs' ]);
    }

    /**
     * Register contextual help tabs for the CSS Optimization screen.
     */
    public function add_help_tabs(): void {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $content = <<<'HTML'
<p><code>ae/css/safelist</code> – Add selectors to the PurgeCSS safelist.</p>
<pre><code>add_filter( 'ae/css/safelist', function ( $list ) {
    $list[] = '.keep-me';
    return $list;
} );</code></pre>
<p><code>ae/css/exclude_handles</code> – Prevent handles from async loading.</p>
<pre><code>add_filter( 'ae/css/exclude_handles', function ( $handles ) {
    $handles[] = 'plugin-style';
    return $handles;
} );</code></pre>
<p><code>ae/css/force_keep_style</code> – Always keep a style handle enqueued.</p>
<pre><code>add_filter( 'ae/css/force_keep_style', function ( $keep, $handle ) {
    return $handle === 'my-style' ? true : $keep;
}, 10, 2 );</code></pre>
<p><code>ae/css/elementor_allow</code> – Allow specific Elementor handles when smart enqueue runs.</p>
<pre><code>add_filter( 'ae/css/elementor_allow', function ( $allow ) {
    $allow[] = 'elementor-frontend';
    return $allow;
} );</code></pre>
<p>All controls persist in the <code>ae_css_settings</code> option.</p>
HTML;

        $screen->add_help_tab([
            'id'      => 'gm2-css-hooks',
            'title'   => __('Hooks', 'gm2-wordpress-suite'),
            'content' => $content,
        ]);
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
                'default'           => self::DEFAULTS,
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
        $defaults = self::DEFAULTS;
        $current  = get_option('ae_css_settings', $defaults);
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

        if (array_key_exists('safelist', $input)) {
            $raw      = $input['safelist'];
            $safelist = [];
            if (is_array($raw)) {
                foreach ($raw as $line) {
                    $line = trim((string) $line);
                    if ($line === '') {
                        continue;
                    }
                    if (strlen($line) >= 2 && $line[0] === '/' && substr($line, -1) === '/') {
                        $safelist[] = $line;
                    } else {
                        $safelist[] = sanitize_text_field($line);
                    }
                }
            } else {
                $safelist_input = sanitize_textarea_field($raw);
                if ($safelist_input !== '') {
                    $lines = preg_split('/\r\n|\r|\n/', $safelist_input);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }
                        if (strlen($line) >= 2 && $line[0] === '/' && substr($line, -1) === '/') {
                            $safelist[] = $line;
                        } else {
                            $safelist[] = sanitize_text_field($line);
                        }
                    }
                }
            }
            $current['safelist'] = $safelist;
        }

        $current['flags']                        = $sanitized_flags;

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

        $current['exclude_handles']              = $exclude;
        $current['include_above_the_fold_handles']= $include;
        $current['generate_critical']            = $generate;
        $current['async_load_noncritical']       = $async;
        $current['woocommerce_smart_enqueue']    = $woo;
        $current['elementor_smart_enqueue']      = $elementor;

        if (isset($input['logs']) && is_array($input['logs'])) {
            $logs = [];
            foreach ($input['logs'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $logs[] = [
                    'timestamp' => isset($entry['timestamp']) ? sanitize_text_field($entry['timestamp']) : '',
                    'action'    => isset($entry['action']) ? sanitize_text_field($entry['action']) : '',
                    'details'   => isset($entry['details']) ? sanitize_text_field($entry['details']) : '',
                ];
            }
            $current['logs'] = $logs;
        }

        if (!isset($current['safelist']) || !is_array($current['safelist'])) {
            $current['safelist'] = [];
        }
        if (!isset($current['logs']) || !is_array($current['logs'])) {
            $current['logs'] = [];
        }

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
     * AJAX handler for estimated savings refresh.
     */
    public function ajax_estimate_savings(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('forbidden', 403);
        }
        check_ajax_referer('ae_css_admin', 'nonce');
        wp_send_json_success($this->calculate_estimated_savings());
    }

    /**
     * Calculate bytes and estimated savings for current page styles.
     *
     * @return array{
     *   total:int,
     *   purged:int,
     *   diff:int,
     *   percent:float
     * }
     */
    private function calculate_estimated_savings(): array {
        $total     = 0;
        $css_paths = [];
        $styles    = wp_styles();
        if ($styles instanceof \WP_Styles) {
            foreach ($styles->registered as $style) {
                $src = $style->src ?? '';
                if ($src === '') {
                    continue;
                }
                $src_path = wp_parse_url($src, PHP_URL_PATH);
                $path     = '';
                if ($src_path !== false && strpos($src, home_url()) === 0) {
                    $home_path = wp_parse_url(home_url(), PHP_URL_PATH);
                    $path      = ABSPATH . ltrim(substr($src_path, strlen($home_path)), '/');
                } elseif ($src_path !== false && strpos($src, '://') === false) {
                    $path = ABSPATH . ltrim($src_path, '/');
                }
                if ($path !== '' && file_exists($path)) {
                    $css_paths[] = $path;
                    $total      += (int) filesize($path);
                }
            }
        }

        $html = '';
        $res  = wp_remote_get(home_url('/'));
        if (!is_wp_error($res)) {
            $html = (string) wp_remote_retrieve_body($res);
        }
        $purged    = 0;
        $settings  = get_option('ae_css_settings', []);
        $safelist  = [];
        if (is_array($settings) && isset($settings['safelist'])) {
            $safelist = (array) $settings['safelist'];
        }
        if ($html !== '' && !empty($css_paths)) {
            $purged_css = AE_CSS_Optimizer::purgecss_analyze($css_paths, [ $html ], $safelist);
            $purged     = strlen($purged_css);
        }
        $diff    = max($total - $purged, 0);
        $percent = $total > 0 ? round(($diff / $total) * 100, 2) : 0.0;
        return [
            'total'   => $total,
            'purged'  => $purged,
            'diff'    => $diff,
            'percent' => $percent,
        ];
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

        $settings = get_option('ae_css_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $logs   = isset($settings['logs']) && is_array($settings['logs']) ? $settings['logs'] : [];
        $logs[] = [
            'timestamp' => (string) current_time('timestamp'),
            'action'    => 'purge',
            'details'   => $dir,
        ];
        if (count($logs) > 10) {
            $logs = array_slice($logs, -10);
        }
        $settings['logs'] = $logs;
        update_option('ae_css_settings', $settings);

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
        $settings = get_option('ae_css_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $logs   = isset($settings['logs']) && is_array($settings['logs']) ? $settings['logs'] : [];
        $logs[] = [
            'timestamp' => (string) current_time('timestamp'),
            'action'    => 'critical',
            'details'   => $url,
        ];
        if (count($logs) > 10) {
            $logs = array_slice($logs, -10);
        }
        $settings['logs'] = $logs;
        update_option('ae_css_settings', $settings);

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
        $queue  = get_option('ae_css_queue', []);
        if (!is_array($queue)) {
            $queue = [];
        }
        $status = get_option('ae_css_job_status', []);
        if (!is_array($status)) {
            $status = [];
        }

        foreach ($queue as $job) {
            $type = $job['type'] ?? '';
            if (in_array($type, [ 'snapshot', 'purge', 'critical' ], true) && !isset($status[$type])) {
                $status[$type] = [ 'status' => 'queued', 'message' => '' ];
            }
        }

        foreach ([ 'snapshot', 'purge', 'critical' ] as $job) {
            $data   = $status[$job] ?? null;
            $state  = is_array($data) ? ($data['status'] ?? '') : '';
            if ($state === '' || $state === 'idle') {
                continue;
            }
            $msg = ucfirst($job) . ': ' . $state;
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
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        $settings = get_option('ae_css_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $flags    = $settings['flags'] ?? [];
        $safelist = $settings['safelist'] ?? [];
        if (!is_array($safelist)) {
            $safelist = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $safelist)));
        }
        $safelist_str = implode("\n", $safelist);
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

        wp_enqueue_script('jquery');
        $estimate = $this->calculate_estimated_savings();
        $nonce    = wp_create_nonce('ae_css_admin');
        echo '<div id="ae-css-estimate" class="notice notice-info" data-nonce="' . esc_attr($nonce) . '">';
        echo '<p><strong>' . esc_html__( 'Estimated savings', 'gm2-wordpress-suite' ) . ':</strong> <span id="ae-css-estimate-text">' . esc_html($estimate['diff']) . ' bytes (' . esc_html($estimate['percent']) . '%)</span> <button type="button" id="ae-css-refresh" class="button">' . esc_html__( 'Refresh', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '</div>';
        echo '<script>jQuery(function($){$(\'#ae-css-refresh\').on(\'click\',function(e){e.preventDefault();var b=$(this);b.prop(\'disabled\',true);$.post(ajaxurl,{action:\'ae_css_estimate_savings\',nonce:$(\'#ae-css-estimate\').data(\'nonce\')}).done(function(r){if(r&&r.success){$(\'#ae-css-estimate-text\').text(r.data.diff+" bytes ("+r.data.percent+"%)");}else{$(\'#ae-css-estimate-text\').text(r&&r.data?r.data:\'Error\');}}).fail(function(){$(\'#ae-css-estimate-text\').text(\'Error\');}).always(function(){b.prop(\'disabled\',false);});});});</script>';

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
        echo '<td><textarea id="ae-css-safelist" name="ae_css_settings[safelist]" rows="5" cols="50" class="large-text code">' . esc_textarea($safelist_str) . '</textarea><p class="description">' . esc_html__( 'One selector or /regex/ per line to always keep.', 'gm2-wordpress-suite' ) . '</p></td></tr>';

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
        $logs = isset($settings['logs']) && is_array($settings['logs']) ? $settings['logs'] : [];
        if (!empty($logs)) {
            echo '<h2>' . esc_html__( 'Recent actions', 'gm2-wordpress-suite' ) . '</h2><ul>';
            foreach (array_reverse($logs) as $entry) {
                echo '<li>' . $this->format_log_entry($entry) . '</li>';
            }
            echo '</ul>';
        }

        echo '</div>';
    }

    /**
     * Format and sanitize a log entry for display.
     *
     * @param array $entry Log entry.
     * @return string Formatted line ready for output.
     */
    private function format_log_entry(array $entry): string {
        $ts = isset($entry['timestamp']) ? (int) $entry['timestamp'] : 0;
        $timestamp = $ts > 0 ? date_i18n('Y-m-d H:i:s', $ts) : '';
        $action    = isset($entry['action']) ? sanitize_text_field($entry['action']) : '';
        $details   = isset($entry['details']) ? sanitize_text_field($entry['details']) : '';
        $line      = $timestamp !== '' ? $timestamp . ' - ' : '';
        $line     .= $action;
        if ($details !== '') {
            $line .= ': ' . $details;
        }
        return esc_html($line);
    }
}

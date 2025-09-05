<?php

namespace Gm2;


if (!defined('ABSPATH')) {
    exit;
}

class Gm2_SEO_Admin {
    private $elementor_initialized = false;
    private static $notices = [];
    const AI_CRON_HOOK = 'gm2_ai_batch_process';
    const AI_QUEUE_OPTION = 'gm2_ai_batch_queue';
    const AI_TAX_CRON_HOOK = 'gm2_ai_tax_batch_process';
    const AI_TAX_QUEUE_OPTION = 'gm2_ai_tax_batch_queue';

    public function __construct() {
        add_action('wp_ajax_gm2_bulk_ai_reset', [$this, 'ajax_bulk_ai_reset']);
        add_action('wp_ajax_gm2_bulk_ai_tax_reset', [$this, 'ajax_bulk_ai_tax_reset']);
        add_action('wp_ajax_gm2_bulk_ai_clear', [$this, 'ajax_bulk_ai_clear']);
        add_action('wp_ajax_gm2_bulk_ai_tax_clear', [$this, 'ajax_bulk_ai_tax_clear']);
        add_action('wp_ajax_gm2_bulk_ai_fetch_ids', [$this, 'ajax_bulk_ai_fetch_ids']);
        add_action('wp_ajax_gm2_bulk_ai_tax_fetch_ids', [$this, 'ajax_bulk_ai_tax_fetch_ids']);
    }

    private function debug_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message);
        }
    }

    private function infer_brand_name(int $post_id): string {
        return gm2_infer_brand_name($post_id);
    }

    private function migrate_js_option_names(): void {
        $map = [
            'ae_gtag_id'        => 'ae_js_analytics_id',
            'ae_gtm_id'         => 'ae_js_gtm_id',
            'ae_fbq_id'         => 'ae_js_fb_id',
            'ae_lazy_recaptcha' => 'ae_js_lazy_recaptcha',
        ];
        foreach ($map as $old => $new) {
            $value = get_option($old, null);
            if ($value !== null) {
                update_option($new, $value);
                delete_option($old);
            }
        }
        $legacy = [
            get_option('ae_lazy_gtag', null),
            get_option('ae_lazy_gtm', null),
            get_option('ae_lazy_fbq', null),
        ];
        if (in_array('1', $legacy, true)) {
            update_option('ae_js_lazy_analytics', '1');
        }
        foreach (['ae_lazy_gtag', 'ae_lazy_gtm', 'ae_lazy_fbq'] as $old) {
            delete_option($old);
        }
    }
    public function run() {
        add_option('ae_seo_ro_enable_critical_css', '0');
        add_option('ae_seo_ro_enable_defer_js', '0');
        add_option('ae_seo_ro_enable_diff_serving', '1');
        add_option('ae_seo_defer_js', '0');
        add_option('ae_seo_diff_serving', '0');
        add_option('ae_seo_ro_enable_combine_css', '0');
        add_option('ae_seo_ro_enable_combine_js', '0');
        add_option('ae_seo_ro_critical_strategy', 'per_home_archive_single');
        add_option('ae_seo_ro_critical_css_map', []);
        add_option('ae_seo_ro_async_css_method', 'preload_onload');
        add_option('ae_seo_ro_critical_css_exclusions', []);
        add_option('gm2_defer_js_allowlist', '');
        add_option('gm2_defer_js_denylist', '');
        add_option('gm2_defer_js_overrides', []);
        add_option('ae_seo_ro_defer_allow_domains', '');
        add_option('ae_seo_ro_defer_deny_domains', '');
        add_option('ae_seo_ro_defer_respect_in_footer', '0');
        add_option('ae_seo_ro_defer_preserve_jquery', '1');
        add_option('ae_js_enable_manager', '0');
        add_option('ae_js_lazy_load', '0');
        add_option('ae_js_lazy_recaptcha', '0');
        add_option('ae_js_lazy_analytics', '0');
        add_option('ae_js_analytics_id', '');
        add_option('ae_js_gtm_id', '');
        add_option('ae_js_fb_id', '');
        add_option('ae_recaptcha_site_key', '');
        add_option('ae_hcaptcha_site_key', '');
        add_option('ae_js_consent_key', 'aeConsent');
        add_option('ae_js_consent_value', 'allow_analytics');
        add_option('ae_js_replacements', '0');
        add_option('ae_js_debug_log', '0');
        add_option('ae_js_console_log', '0');
        add_option('ae_js_auto_dequeue', '0');
        add_option('ae_js_respect_safe_mode', '0');
        add_option('ae_js_nomodule_legacy', '0');
        add_option('ae_js_dequeue_allowlist', []);
        add_option('ae_js_dequeue_denylist', []);
        add_option('ae_js_jquery_on_demand', '0');
        add_option('ae_js_jquery_url_allow', '');
        add_option('ae_js_compat_overrides', []);
        add_option('ae_perf_worker', '0');
        add_option('ae_perf_long_tasks', '0');
        add_option('ae_perf_layout_thrash', '0');
        add_option('ae_perf_no_thrash', '0');
        add_option('ae_perf_passive_listeners', '0');
        add_option('ae_perf_dom_audit', '0');

        $this->migrate_js_option_names();

        add_action('admin_menu', [$this, 'add_settings_pages']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('wp_after_insert_post', [$this, 'save_post_meta'], 100, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_editor_scripts']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_taxonomy_scripts']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_gm2_sitemap_settings', [$this, 'handle_sitemap_form']);
        add_action('admin_post_gm2_meta_tags_settings', [$this, 'handle_meta_tags_form']);
        add_action('admin_post_gm2_schema_settings', [$this, 'handle_schema_form']);
        add_action('admin_post_gm2_save_custom_schema', [$this, 'handle_custom_schema_save']);
        add_action('admin_post_gm2_delete_custom_schema', [$this, 'handle_custom_schema_delete']);
        add_action('admin_post_gm2_performance_settings', [$this, 'handle_performance_form']);
        add_action('admin_post_gm2_insert_cache_rules', [$this, 'handle_insert_cache_rules']);
        add_action('admin_post_gm2_remove_cache_rules', [$this, 'handle_remove_cache_rules']);
        add_action('admin_post_gm2_purge_critical_css', [$this, 'handle_purge_critical_css']);
        add_action('admin_post_gm2_purge_js_map', [$this, 'handle_purge_js_map']);
        add_action('admin_post_gm2_purge_optimizer_cache', [$this, 'handle_purge_optimizer_cache']);
        add_action('admin_post_gm2_redirects', [$this, 'handle_redirects_form']);
        add_action('admin_post_gm2_generate_nginx_cache', [$this, 'handle_generate_nginx_cache']);
        add_action('admin_post_gm2_verify_nginx_cache', [$this, 'handle_verify_nginx_cache']);
        add_action('admin_post_gm2_content_rules', [$this, 'handle_content_rules_form']);
        add_action('admin_post_gm2_guideline_rules', [$this, 'handle_guideline_rules_form']);
        add_action('admin_post_gm2_general_settings', [$this, 'handle_general_settings_form']);
        add_action('admin_post_gm2_keyword_settings', [$this, 'handle_keyword_settings_form']);
        add_action('admin_post_gm2_bulk_ai_export', [$this, 'handle_bulk_ai_export']);
        add_action('admin_post_gm2_bulk_ai_tax_export', [$this, 'handle_bulk_ai_tax_export']);
        add_action('admin_post_gm2_google_test', [$this, 'handle_google_test_connection']);
        add_action('admin_post_gm2_clear_404_logs', [$this, 'handle_clear_404_logs']);
        add_action('admin_post_gm2_reset_seo', [$this, 'handle_reset_seo']);
        add_action('admin_post_gm2_export_settings', [$this, 'handle_export_settings']);
        add_action('admin_post_gm2_import_settings', [$this, 'handle_import_settings']);
        add_action('admin_post_gm2_render_optimizer_settings', [$this, 'handle_render_optimizer_form']);
        add_action('admin_post_gm2_js_optimizer_settings', [$this, 'handle_js_optimizer_form']);
        add_action('admin_post_gm2_js_compatibility_settings', [$this, 'handle_js_compatibility_form']);

        add_action('wp_ajax_gm2_purge_critical_css', [$this, 'ajax_purge_critical_css']);
        add_action('wp_ajax_gm2_purge_js_map', [$this, 'ajax_purge_js_map']);
        add_action('wp_ajax_gm2_purge_optimizer_cache', [$this, 'ajax_purge_optimizer_cache']);
        add_action('wp_ajax_gm2_clear_optimizer_diagnostics', [$this, 'ajax_clear_optimizer_diagnostics']);

        add_action('wp_ajax_gm2_check_rules', [$this, 'ajax_check_rules']);
        add_action('wp_ajax_gm2_keyword_ideas', [$this, 'ajax_keyword_ideas']);
        add_action('wp_ajax_gm2_research_content_rules', [$this, 'ajax_research_content_rules']);
        add_action('wp_ajax_gm2_research_guideline_rules', [$this, 'ajax_research_guideline_rules']);
        add_action('wp_ajax_gm2_ai_research', [$this, 'ajax_ai_research']);
        add_action('wp_ajax_gm2_ai_research_clear', [$this, 'ajax_ai_research_clear']);
        add_action('wp_ajax_gm2_ai_generate_tax_description', [$this, 'ajax_generate_tax_description']);
        add_action('wp_ajax_gm2_bulk_ai_apply', [$this, 'ajax_bulk_ai_apply']);
        add_action('wp_ajax_gm2_bulk_ai_apply_batch', [$this, 'ajax_bulk_ai_apply_batch']);
        add_action('wp_ajax_gm2_bulk_ai_undo', [$this, 'ajax_bulk_ai_undo']);
        add_action('wp_ajax_gm2_bulk_ai_tax_apply', [$this, 'ajax_bulk_ai_tax_apply']);
        add_action('wp_ajax_gm2_bulk_ai_tax_apply_batch', [$this, 'ajax_bulk_ai_tax_apply_batch']);
        add_action('wp_ajax_gm2_bulk_ai_tax_undo', [$this, 'ajax_bulk_ai_tax_undo']);
        add_action('wp_ajax_gm2_ai_batch_schedule', [$this, 'ajax_ai_batch_schedule']);
        add_action('wp_ajax_gm2_ai_batch_cancel', [$this, 'ajax_ai_batch_cancel']);
        add_action('wp_ajax_gm2_ai_tax_batch_schedule', [$this, 'ajax_ai_tax_batch_schedule']);
        add_action('wp_ajax_gm2_ai_tax_batch_cancel', [$this, 'ajax_ai_tax_batch_cancel']);
        add_action('wp_ajax_gm2_schema_preview', [$this, 'ajax_schema_preview']);
        add_action(self::AI_CRON_HOOK, [$this, 'cron_process_ai_queue']);
        add_action(self::AI_TAX_CRON_HOOK, [$this, 'cron_process_ai_tax_queue']);

        add_action('add_attachment', [$this, 'auto_fill_alt_on_upload']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('admin_notices', [$this, 'dom_extension_warning']);
        add_action('admin_notices', [$this, 'openssl_extension_warning']);
        add_action('add_attachment', [$this, 'compress_image_on_upload'], 20);
        add_action('save_post', [$this, 'auto_fill_product_alt'], 20, 3);

        add_action('transition_post_status', [$this, 'maybe_generate_sitemap'], 10, 3);

        add_action('init', [$this, 'register_taxonomy_hooks'], 20);

        if (did_action('elementor/loaded')) {
            $this->setup_elementor_integration();
        } else {
            add_action('elementor/loaded', [$this, 'setup_elementor_integration']);
        }

        if (get_option('gm2_clean_slugs', '0') === '1') {
            add_filter('sanitize_title', [$this, 'clean_slug'], 20, 3);
        }

        add_filter('bulk_actions-edit-post', [$this, 'register_clean_slug_bulk_action']);
        add_filter('handle_bulk_actions-edit-post', [$this, 'redirect_clean_slug_bulk_action'], 10, 3);
        add_action('admin_post_gm2_bulk_clean_slugs', [$this, 'handle_bulk_clean_slugs']);
        add_action('load-edit.php', [$this, 'maybe_confirm_clean_slugs']);
        add_action('admin_notices', [$this, 'maybe_show_clean_slug_notice']);
        add_action('admin_notices', [$this, 'maybe_show_google_test_notice']);
    }

    public function maybe_generate_sitemap($new_status = null, $old_status = null, $post = null) {
        if (is_null($new_status) && is_null($old_status)) {
            gm2_generate_sitemap();
            return;
        }

        if ($new_status === 'publish' || $old_status === 'publish') {
            $result = gm2_generate_sitemap();
            if (is_wp_error($result)) {
                self::add_notice($result->get_error_message());
            }
        }
    }

    public function get_supported_post_types() {
        $args  = [
            'public'             => true,
            'show_ui'            => true,
            'exclude_from_search' => false,
        ];
        $types = get_post_types($args, 'names');
        unset($types['attachment']);
        /**
         * Filter the list of post types that should receive GM2 SEO features.
         *
         * @param string[] $types Array of post type slugs.
         */
        $types = apply_filters('gm2_supported_post_types', array_values($types));
        return $types;
    }

    public function get_supported_taxonomies() {
        $taxonomies = ['category'];
        if (taxonomy_exists('product_cat')) {
            $taxonomies[] = 'product_cat';
        }
        if (taxonomy_exists('brand')) {
            $taxonomies[] = 'brand';
        }
        if (taxonomy_exists('product_brand')) {
            $taxonomies[] = 'product_brand';
        }
        return $taxonomies;
    }

    /**
     * Provide a short, human readable classification for a taxonomy.
     *
     * Returns one of the following labels based on the taxonomy's
     * registered object types:
     * - `post category` for the built in `category` taxonomy.
     * - `product category` for WooCommerce's `product_cat` taxonomy.
     * - `product taxonomy` if attached to the `product` post type.
     * - `post taxonomy` when used only with posts.
     * - `custom taxonomy` for anything else.
     *
     * @param string $taxonomy Taxonomy slug.
     * @return string Taxonomy classification or empty string if unknown.
     */
    private function describe_taxonomy_type($taxonomy) {
        $tax_obj = get_taxonomy($taxonomy);
        if (!$tax_obj) {
            return '';
        }
        if ($taxonomy === 'category') {
            return 'post category';
        }
        if ($taxonomy === 'product_cat') {
            return 'product category';
        }
        $objects = (array) $tax_obj->object_type;
        if (in_array('product', $objects, true)) {
            return 'product taxonomy';
        }
        if ($objects === ['post']) {
            return 'post taxonomy';
        }
        return 'custom taxonomy';
    }

    public function register_taxonomy_hooks() {
        $taxonomies = $this->get_supported_taxonomies();
        foreach ($taxonomies as $tax) {
            add_action("{$tax}_add_form_fields", [$this, 'render_taxonomy_meta_box']);
            add_action("{$tax}_edit_form_fields", [$this, 'render_taxonomy_meta_box']);
            add_action("create_{$tax}", [$this, 'save_taxonomy_meta']);
            add_action("edited_{$tax}", [$this, 'save_taxonomy_meta']);
            add_action("created_{$tax}", [$this, 'maybe_generate_sitemap'], 10, 0);
            add_action("edited_{$tax}", [$this, 'maybe_generate_sitemap'], 10, 0);
            add_action("delete_{$tax}", [$this, 'maybe_generate_sitemap'], 10, 0);
        }
    }

    public function setup_elementor_integration() {
        if ($this->elementor_initialized) {
            return;
        }
        $this->elementor_initialized = true;

        require_once GM2_PLUGIN_DIR . 'admin/Gm2_Elementor.php';
        new Gm2_Elementor($this);
        add_action('elementor/editor/before_enqueue_scripts', [$this, 'enqueue_elementor_scripts']);
        add_action('elementor/editor/footer', [$this, 'output_elementor_panel']);
    }

    // Backwards compatibility with older hooks
    public function register_elementor_hooks() {
        $this->setup_elementor_integration();
    }

    public function sanitize_customer_id($value) {
        return preg_replace('/\D/', '', $value);
    }

    public function sanitize_css($value) {
        $value = wp_strip_all_tags($value);
        $max   = 100000;
        if (strlen($value) > $max) {
            self::add_notice(
                sprintf(
                    /* translators: %d: maximum length */
                    __( 'CSS length exceeds %d characters and has been truncated.', 'gm2-wordpress-suite' ),
                    $max
                )
            );
            $value = substr($value, 0, $max);
        }
        return $value;
    }

    public function sanitize_css_map($value) {
        if (!is_array($value)) {
            return [];
        }
        $sanitized = [];
        foreach ($value as $key => $css) {
            $sanitized[sanitize_key($key)] = $this->sanitize_css($css);
        }
        return $sanitized;
    }

    public function sanitize_handle_array($value) {
        if (!is_array($value)) {
            return [];
        }
        $sanitized = [];
        foreach ($value as $handle) {
            $handle = sanitize_key($handle);
            if ($handle !== '') {
                $sanitized[] = $handle;
            }
        }
        return $sanitized;
    }

    public function clean_slug($slug, $raw_title = '', $context = '') {
        $stop = get_option('gm2_slug_stopwords', '');
        $words = array_filter(array_map('trim', preg_split('/[\s,]+/', strtolower($stop))));
        if ($words) {
            $pattern = '/(?:^|-)(?:' . implode('|', array_map('preg_quote', $words)) . ')(?:-|$)/i';
            $slug = preg_replace($pattern, '-', $slug);
        }
        $slug = trim(preg_replace('/-+/', '-', $slug), '-');
        return $slug;
    }

    public function register_clean_slug_bulk_action($actions) {
        $actions['gm2_bulk_clean_slugs'] = esc_html__( 'Clean Slugs', 'gm2-wordpress-suite' );
        return $actions;
    }

    public function redirect_clean_slug_bulk_action($redirect, $action, $post_ids) {
        if ($action === 'gm2_bulk_clean_slugs') {
            $redirect = add_query_arg('gm2_clean_slugs_ids', implode(',', array_map('absint', $post_ids)), $redirect);
        }
        return $redirect;
    }

    public function maybe_confirm_clean_slugs() {
        if (!isset($_GET['gm2_clean_slugs_ids'])) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'edit-post') {
            return;
        }

        $ids  = array_filter(array_map('absint', explode(',', $_GET['gm2_clean_slugs_ids'])));
        $count = count($ids);
        if (!$count) {
            return;
        }

        $nonce = wp_create_nonce('gm2_bulk_clean_slugs');
        $confirm_url = add_query_arg([
            'action' => 'gm2_bulk_clean_slugs',
        ], admin_url('admin-post.php'));

        echo '<div class="notice notice-warning"><p>' .
            sprintf(esc_html(_n('Clean slugs for %d selected post?', 'Clean slugs for %d selected posts?', $count, 'gm2-wordpress-suite')), $count) .
            '</p><p><form method="post" action="' . esc_url($confirm_url) . '">' .
            '<input type="hidden" name="ids" value="' . esc_attr(implode(',', $ids)) . '" />' .
            wp_nonce_field('gm2_bulk_clean_slugs', '_wpnonce', true, false) .
            get_submit_button(esc_html__('Confirm', 'gm2-wordpress-suite'), 'primary', 'submit', false) .
            ' <a href="' . esc_url(remove_query_arg('gm2_clean_slugs_ids')) . '" class="button">' . esc_html__('Cancel', 'gm2-wordpress-suite') . '</a>' .
            '</form></p></div>';
    }

    public function handle_bulk_clean_slugs() {
        if (!current_user_can('edit_posts')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        check_admin_referer('gm2_bulk_clean_slugs');

        $ids   = array_filter(array_map('absint', explode(',', $_POST['ids'] ?? '')));
        $count = 0;
        foreach ($ids as $post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                continue;
            }
            $post   = get_post($post_id);
            if (!$post) {
                continue;
            }
            $clean  = $this->clean_slug($post->post_name);
            if ($clean !== $post->post_name) {
                wp_update_post(['ID' => $post_id, 'post_name' => $clean]);
                $count++;
            }
        }

        $url = add_query_arg('gm2_slugs_cleaned', $count, admin_url('edit.php'));
        wp_redirect($url);
        if (defined('GM2_TESTING') && GM2_TESTING) {
            return;
        }
        exit;
    }

    public function maybe_show_clean_slug_notice() {
        if (isset($_GET['gm2_slugs_cleaned'])) {
            $count = absint($_GET['gm2_slugs_cleaned']);
            echo '<div class="notice notice-success is-dismissible"><p>' .
                sprintf(esc_html(_n('%d slug cleaned.', '%d slugs cleaned.', $count, 'gm2-wordpress-suite')), $count) .
                '</p></div>';
        }
    }

    public function maybe_show_google_test_notice() {
        if (isset($_GET['gm2_google_test'])) {
            $msg   = sanitize_text_field(wp_unslash($_GET['gm2_google_test']));
            $error = isset($_GET['gm2_google_test_error']) ? absint($_GET['gm2_google_test_error']) : 0;
            $type  = $error ? 'error' : 'success';
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($msg) . '</p></div>';
        }
    }

    public function add_settings_pages() {
        $hook = add_menu_page(
            esc_html__( 'Gm2 SEO', 'gm2-wordpress-suite' ),
            esc_html__( 'Gm2 SEO', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-seo',
            [$this, 'display_dashboard']
        );
        if ( $hook ) {
            add_action( 'load-' . $hook, [ $this, 'add_settings_help' ] );
        }

        if (get_option('gm2_enable_google_oauth', '1') === '1') {
            add_submenu_page(
                'gm2-seo',
                esc_html__( 'Connect Google Account', 'gm2-wordpress-suite' ),
                esc_html__( 'Connect Google Account', 'gm2-wordpress-suite' ),
                'manage_options',
                'gm2-google-connect',
                [$this, 'display_google_connect_page']
            );
        }

        add_submenu_page(
            'gm2-seo',
            esc_html__( 'Search Console', 'gm2-wordpress-suite' ),
            esc_html__( 'Search Console', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-search-console',
            [$this, 'display_search_console_page']
        );

        add_submenu_page(
            'gm2-seo',
            esc_html__( 'Robots.txt', 'gm2-wordpress-suite' ),
            esc_html__( 'Robots.txt', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-robots',
            [$this, 'display_robots_page']
        );

        add_submenu_page(
            'gm2-seo',
            esc_html__( 'Script Audit', 'gm2-wordpress-suite' ),
            esc_html__( 'Script Audit', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-script-audit',
            [ $this, 'display_script_audit_page' ]
        );

        add_submenu_page(
            'gm2-seo',
            esc_html__( 'LCP Optimization', 'gm2-wordpress-suite' ),
            esc_html__( 'LCP Optimization', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-lcp-optimization',
            [ $this, 'display_lcp_settings_page' ]
        );

        add_submenu_page(
            'gm2-ai',
            esc_html__( 'Bulk AI Review', 'gm2-wordpress-suite' ),
            esc_html__( 'Bulk AI Review', 'gm2-wordpress-suite' ),
            'edit_posts',
            'gm2-bulk-ai-review',
            [$this, 'display_bulk_ai_page']
        );

        $cap = apply_filters('gm2_bulk_ai_tax_capability', 'manage_categories');
        add_submenu_page(
            'gm2-ai',
            esc_html__( 'Bulk AI Taxonomies', 'gm2-wordpress-suite' ),
            esc_html__( 'Bulk AI Taxonomies', 'gm2-wordpress-suite' ),
            $cap,
            'gm2-bulk-ai-taxonomies',
            [$this, 'display_bulk_ai_tax_page']
        );
    }

    public function register_settings() {
        register_setting('gm2_seo_options', 'gm2_ga_measurement_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_search_console_verification', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_twitter_site', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_twitter_creator', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_org_name', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_org_logo', [
            'sanitize_callback' => 'esc_url_raw',
        ]);
        register_setting('gm2_seo_options', 'gm2_site_search_url', [
            'sanitize_callback' => 'esc_url_raw',
        ]);
        register_setting('gm2_seo_options', 'gm2_gads_developer_token', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_gads_client_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_gads_client_secret', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_gads_refresh_token', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_gads_customer_id', [
            'sanitize_callback' => [$this, 'sanitize_customer_id'],
        ]);
        register_setting('gm2_seo_options', 'gm2_gads_login_customer_id', [
            'sanitize_callback' => [$this, 'sanitize_customer_id'],
        ]);
        register_setting('gm2_seo_options', 'gm2_gads_language', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_gads_geo_target', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_sc_query_limit', [
            'sanitize_callback' => 'absint',
        ]);
        register_setting('gm2_seo_options', 'gm2_sc_client_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_sc_client_secret', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_sc_refresh_token', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_sc_service_account_json', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_sc_auto', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_analytics_days', [
            'sanitize_callback' => 'absint',
        ]);
        register_setting('gm2_seo_options', 'gm2_analytics_retention_days', [
            'sanitize_callback' => 'absint',
        ]);
        register_setting('gm2_seo_options', 'gm2_clean_slugs', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_slug_stopwords', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'ae_seo_ro_enable_critical_css', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_seo_ro_critical_strategy', [
            'sanitize_callback' => 'sanitize_key',
        ]);
        register_setting('gm2_seo_options', 'ae_seo_ro_critical_css_map', [
            'sanitize_callback' => [ $this, 'sanitize_css_map' ],
        ]);
        register_setting('gm2_seo_options', 'ae_seo_ro_async_css_method', [
            'sanitize_callback' => 'sanitize_key',
        ]);
        register_setting('gm2_seo_options', 'ae_seo_ro_critical_css_exclusions', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_enable_manager', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_lazy_load', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_lazy_recaptcha', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_lazy_analytics', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_analytics_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_gtm_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_fb_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_recaptcha_site_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_hcaptcha_site_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_consent_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_consent_value', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_replacements', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_debug_log', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_console_log', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_auto_dequeue', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_respect_safe_mode', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_dequeue_allowlist', [
            'sanitize_callback' => [ $this, 'sanitize_handle_array' ],
        ]);
        register_setting('gm2_seo_options', 'ae_js_dequeue_denylist', [
            'sanitize_callback' => [ $this, 'sanitize_handle_array' ],
        ]);
        register_setting('gm2_seo_options', 'ae_js_jquery_on_demand', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'ae_js_jquery_url_allow', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_tax_desc_prompt', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_tax_min_length', [
            'sanitize_callback' => 'absint',
        ]);
        register_setting('gm2_seo_options', 'gm2_bulk_ai_page_size', [
            'sanitize_callback' => 'absint',
        ]);
        register_setting('gm2_seo_options', 'gm2_bulk_ai_status', [
            'sanitize_callback' => 'sanitize_key',
        ]);
        register_setting('gm2_seo_options', 'gm2_bulk_ai_seo_status', [
            'sanitize_callback' => 'sanitize_key',
        ]);
        register_setting('gm2_seo_options', 'gm2_bulk_ai_post_type', [
            'sanitize_callback' => 'sanitize_key',
        ]);
        register_setting('gm2_seo_options', 'gm2_bulk_ai_term', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_bulk_ai_missing_title', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_bulk_ai_missing_description', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_bulk_ai_tax_missing_title', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_bulk_ai_tax_missing_description', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_business_model', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_industry_category', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_target_audience', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_unique_selling_points', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_revenue_streams', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_primary_goal', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_brand_voice', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_competitors', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_core_offerings', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_geographic_focus', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_keyword_data', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_competitor_landscape', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_success_metrics', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_buyer_personas', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_project_description', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_custom_prompts', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_context_ai_prompt', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_project_description', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);

        register_setting('gm2_robots_options', 'gm2_robots_txt', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);

        add_settings_section(
            'gm2_seo_main',
            '',
            '__return_false',
            'gm2_seo'
        );

        add_settings_field(
            'gm2_ga_measurement_id',
            'Google Analytics Measurement ID',
            function () {
                $value = get_option('gm2_ga_measurement_id', '');
                echo '<input type="text" name="gm2_ga_measurement_id" value="' . esc_attr($value) . '" class="regular-text" />';
                echo '<p class="description">Use <strong>SEO → Connect Google Account</strong> to fetch available IDs.</p>';
            },
            'gm2_seo',
            'gm2_seo_main'
        );

        add_settings_field(
            'gm2_twitter_site',
            'Twitter Site',
            function () {
                $value = get_option('gm2_twitter_site', '');
                echo '<input type="text" name="gm2_twitter_site" value="' . esc_attr($value) . '" class="regular-text" />';
            },
            'gm2_seo',
            'gm2_seo_main'
        );

        add_settings_field(
            'gm2_twitter_creator',
            'Twitter Creator',
            function () {
                $value = get_option('gm2_twitter_creator', '');
                echo '<input type="text" name="gm2_twitter_creator" value="' . esc_attr($value) . '" class="regular-text" />';
            },
            'gm2_seo',
            'gm2_seo_main'
        );

        add_settings_field(
            'gm2_org_name',
            'Organization Name',
            function () {
                $value = get_option('gm2_org_name', '');
                echo '<input type="text" name="gm2_org_name" value="' . esc_attr($value) . '" class="regular-text" />';
            },
            'gm2_seo',
            'gm2_seo_main'
        );

        add_settings_field(
            'gm2_org_logo',
            'Organization Logo URL',
            function () {
                $value = get_option('gm2_org_logo', '');
                echo '<input type="url" name="gm2_org_logo" value="' . esc_attr($value) . '" class="regular-text" />';
            },
            'gm2_seo',
            'gm2_seo_main'
        );

        add_settings_field(
            'gm2_site_search_url',
            'Site Search URL',
            function () {
                $value = get_option('gm2_site_search_url', '');
                echo '<input type="url" name="gm2_site_search_url" value="' . esc_attr($value) . '" class="regular-text" />';
                echo '<p class="description">' . esc_html__( 'Include {search_term_string} in place of the query.', 'gm2-wordpress-suite' ) . '</p>';
            },
            'gm2_seo',
            'gm2_seo_main'
        );

        add_settings_field(
            'gm2_search_console_verification',
            'Search Console Verification Code',
            function () {
                $value = get_option('gm2_search_console_verification', '');
                echo '<input type="text" name="gm2_search_console_verification" value="' . esc_attr($value) . '" class="regular-text" />';
                echo '<p class="description">Log in to <a href="https://search.google.com/search-console" target="_blank">Search Console</a>, open <strong>Settings → Ownership verification</strong> and choose the <em>HTML tag</em> option. Copy the code shown there and paste it here.</p>';
            },
            'gm2_seo',
            'gm2_seo_main'
        );

        add_settings_field(
            'gm2_gads_developer_token',
            'Google Ads Developer Token',
            function () {
                $value = get_option('gm2_gads_developer_token', '');
                echo '<input type="text" name="gm2_gads_developer_token" value="' . esc_attr($value) . '" class="regular-text" />';
                echo '<p class="description">Sign in at <a href="https://ads.google.com/aw/apicenter" target="_blank">Google Ads</a> and open <strong>Tools & Settings → Setup → API Center</strong> (manager account required). Copy your <strong>Developer token</strong> and paste it here.</p>';
            },
            'gm2_seo',
            'gm2_seo_main'
        );
        // Client ID, secret and refresh token fields are now managed via OAuth
        // and hidden from the settings screen to avoid manual entry.
        add_settings_field(
            'gm2_gads_customer_id',
            'Google Ads Customer ID',
            function () {
                $value = get_option('gm2_gads_customer_id', '');
                echo '<input type="text" name="gm2_gads_customer_id" value="' . esc_attr($value) . '" class="regular-text" />';
                echo '<p class="description">Use <strong>SEO → Connect Google Account</strong> to fetch available IDs.</p>';
            },
            'gm2_seo',
            'gm2_seo_main'
        );

        add_settings_field(
            'gm2_clean_slugs',
            esc_html__( 'Clean Slugs', 'gm2-wordpress-suite' ),
            function () {
                $value = get_option('gm2_clean_slugs', '0');
                echo '<label><input type="checkbox" name="gm2_clean_slugs" value="1" ' . checked($value, '1', false) . '> ' . esc_html__( 'Remove stopwords', 'gm2-wordpress-suite' ) . '</label>';
            },
            'gm2_seo',
            'gm2_seo_main'
        );

        add_settings_field(
            'gm2_slug_stopwords',
            esc_html__( 'Slug Stopwords', 'gm2-wordpress-suite' ),
            function () {
                $value = get_option('gm2_slug_stopwords', '');
                echo '<textarea name="gm2_slug_stopwords" rows="3" class="large-text">' . esc_textarea($value) . '</textarea>';
                echo '<p class="description">' . esc_html__( 'Space or comma separated list.', 'gm2-wordpress-suite' ) . '</p>';
            },
            'gm2_seo',
            'gm2_seo_main'
        );

        add_settings_field(
            'gm2_tax_desc_prompt',
            'Taxonomy Description Prompt',
            function () {
                $value = get_option('gm2_tax_desc_prompt', __( 'Write a short SEO description for the term "{name}".', 'gm2-wordpress-suite' ) );
                echo '<textarea name="gm2_tax_desc_prompt" rows="3" class="large-text">' . esc_textarea($value) . '</textarea>';
                echo '<p class="description">' . esc_html__( 'Available tags: {name}, {taxonomy}', 'gm2-wordpress-suite' ) . '</p>';
            },
            'gm2_seo',
            'gm2_seo_main'
        );
    }

    public function display_dashboard() {
        $tabs = [
            'general'     => esc_html__( 'General', 'gm2-wordpress-suite' ),
            'meta'        => esc_html__( 'Meta Tags', 'gm2-wordpress-suite' ),
            'sitemap'     => esc_html__( 'Sitemap', 'gm2-wordpress-suite' ),
            'redirects'   => esc_html__( 'Redirects', 'gm2-wordpress-suite' ),
            'schema'      => esc_html__( 'Structured Data', 'gm2-wordpress-suite' ),
            'performance' => esc_html__( 'Performance', 'gm2-wordpress-suite' ),
            'keywords'    => esc_html__( 'Keyword Research', 'gm2-wordpress-suite' ),
            'analytics'   => esc_html__( 'Analytics', 'gm2-wordpress-suite' ),
            'rules'       => esc_html__( 'Content Rules', 'gm2-wordpress-suite' ),
            'guidelines'  => esc_html__( 'SEO Guidelines', 'gm2-wordpress-suite' ),
            'taxonomies'  => esc_html__( 'Taxonomies', 'gm2-wordpress-suite' ),
            'context'     => esc_html__( 'Context', 'gm2-wordpress-suite' ),
        ];

        $active = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        if (!isset($tabs[$active])) {
            $active = 'general';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'SEO Settings', 'gm2-wordpress-suite' ) . '</h1>';
        if ( current_user_can( 'manage_options' ) ) {
            $readme = plugins_url( 'readme.txt', GM2_PLUGIN_DIR . 'gm2-wordpress-suite.php' );
            $url    = esc_url( $readme . '#wp-debugging' );
            echo '<p class="description">' .
                sprintf(
                    /* translators: 1: opening link tag, 2: closing link tag */
                    esc_html__( 'If AI Research fails, please enable WordPress debugging as explained in the %1$sWP Debugging%2$s section of the readme. Check %3$s for errors.', 'gm2-wordpress-suite' ),
                    '<a href="' . $url . '" target="_blank">',
                    '</a>',
                    '<code>wp-content/debug.log</code>'
                ) .
                '</p>';
        }
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $slug => $label) {
            $class = $active === $slug ? ' nav-tab-active' : '';
            $url   = admin_url('admin.php?page=gm2-seo&tab=' . $slug);
            echo '<a href="' . esc_url($url) . '" class="nav-tab' . $class . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';

        if (!empty($_GET['reset'])) {
            echo '<div class="updated notice"><p>' . esc_html__( 'Settings reset to defaults.', 'gm2-wordpress-suite' ) . '</p></div>';
        }

        if ($active === 'meta') {
            $variants       = get_option('gm2_noindex_variants', '0');
            $oos            = get_option('gm2_noindex_oos', '0');
            $canon_parent   = get_option('gm2_variation_canonical_parent', '0');
            $meta_keywords  = get_option('gm2_meta_keywords_enabled', '0');
            if (!empty($_GET['updated'])) {
                echo '<div class="updated notice"><p>' . esc_html__('Settings saved.', 'gm2-wordpress-suite') . '</p></div>';
            }

            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_meta_tags_save', 'gm2_meta_tags_nonce');
            echo '<input type="hidden" name="action" value="gm2_meta_tags_settings" />';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">' . esc_html__( 'Noindex product variants', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_noindex_variants" value="1" ' . checked($variants, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Noindex out-of-stock products', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_noindex_oos" value="1" ' . checked($oos, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Variation canonical points to parent', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_variation_canonical_parent" value="1" ' . checked($canon_parent, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Enable meta keywords tag', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_meta_keywords_enabled" value="1" ' . checked($meta_keywords, '1', false) . '></td></tr>';
            echo '</tbody></table>';
            submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
            echo '</form>';
        } elseif ($active === 'sitemap') {
            $enabled   = get_option('gm2_sitemap_enabled', '1');
            $frequency = get_option('gm2_sitemap_frequency', 'daily');
            $path      = get_option('gm2_sitemap_path', ABSPATH . 'sitemap.xml');
            $max_urls  = get_option('gm2_sitemap_max_urls', 1000);
            if (!empty($_GET['updated'])) {
                echo '<div class="updated notice"><p>' . esc_html__('Settings saved.', 'gm2-wordpress-suite') . '</p></div>';
            }

            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_sitemap_save', 'gm2_sitemap_nonce');
            echo '<input type="hidden" name="action" value="gm2_sitemap_settings" />';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">' . esc_html__( 'Enable Sitemap', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_sitemap_enabled" value="1" ' . checked($enabled, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Update Frequency', 'gm2-wordpress-suite' ) . '</th><td><select name="gm2_sitemap_frequency">';
            $options = [ 'daily' => esc_html__( 'Daily', 'gm2-wordpress-suite' ), 'weekly' => esc_html__( 'Weekly', 'gm2-wordpress-suite' ), 'monthly' => esc_html__( 'Monthly', 'gm2-wordpress-suite' ) ];
            foreach ($options as $opt => $label) {
                echo '<option value="' . esc_attr($opt) . '" ' . selected($frequency, $opt, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Sitemap Path', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="gm2_sitemap_path" value="' . esc_attr($path) . '" placeholder="' . esc_attr(ABSPATH . 'sitemap.xml') . '" class="regular-text" /> <span class="dashicons dashicons-info" title="' . esc_attr__( 'Path must be writable by WordPress', 'gm2-wordpress-suite' ) . '"></span></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Max URLs per File', 'gm2-wordpress-suite' ) . '</th><td><input type="number" name="gm2_sitemap_max_urls" value="' . esc_attr($max_urls) . '" class="small-text" /></td></tr>';
            echo '</tbody></table>';
            submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
            echo '<input type="submit" name="gm2_regenerate" class="button" value="' . esc_attr__( 'Regenerate Sitemap', 'gm2-wordpress-suite' ) . '" />';
            echo '</form>';
        } elseif ($active === 'schema') {
            $product       = get_option('gm2_schema_product', '1');
            $brand         = get_option('gm2_schema_brand', '1');
            $breadcrumbs   = get_option('gm2_schema_breadcrumbs', '1');
            $taxonomy_list = get_option('gm2_schema_taxonomy', '1');
            $article       = get_option('gm2_schema_article', '1');
            $review        = get_option('gm2_schema_review', '1');
            $footer_bc     = get_option('gm2_show_footer_breadcrumbs', '1');
            if (!empty($_GET['updated'])) {
                echo '<div class="updated notice"><p>' . esc_html__('Settings saved.', 'gm2-wordpress-suite') . '</p></div>';
            }

            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_schema_save', 'gm2_schema_nonce');
            echo '<input type="hidden" name="action" value="gm2_schema_settings" />';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">' . esc_html__( 'Product Schema', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_schema_product" value="1" ' . checked($product, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Brand Schema', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_schema_brand" value="1" ' . checked($brand, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Breadcrumb Schema', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_schema_breadcrumbs" value="1" ' . checked($breadcrumbs, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Taxonomy ItemList Schema', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_schema_taxonomy" value="1" ' . checked($taxonomy_list, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Article Schema', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_schema_article" value="1" ' . checked($article, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Show Breadcrumbs in Footer', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_show_footer_breadcrumbs" value="1" ' . checked($footer_bc, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Review Schema', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_schema_review" value="1" ' . checked($review, '1', false) . '></td></tr>';
            echo '</tbody></table>';

            $pt = get_option('gm2_schema_template_product', wp_json_encode(Gm2_SEO_Public::default_product_template(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $bt = get_option('gm2_schema_template_brand', wp_json_encode(Gm2_SEO_Public::default_brand_template(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $bct = get_option('gm2_schema_template_breadcrumb', wp_json_encode(Gm2_SEO_Public::default_breadcrumb_template(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $tt = get_option('gm2_schema_template_taxonomy', wp_json_encode(Gm2_SEO_Public::default_taxonomy_template(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $at = get_option('gm2_schema_template_article', wp_json_encode(Gm2_SEO_Public::default_article_template(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $rt = get_option('gm2_schema_template_review', wp_json_encode(Gm2_SEO_Public::default_review_template(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            echo '<h2>' . esc_html__( 'JSON-LD Templates', 'gm2-wordpress-suite' ) . '</h2>';
            $ph = Gm2_SEO_Public::get_placeholders();
            if ($ph) {
                echo '<p>' . esc_html__( 'Available placeholders:', 'gm2-wordpress-suite' ) . '</p><ul>';
                foreach ($ph as $token => $desc) {
                    echo '<li><code>' . esc_html($token) . '</code> ' . esc_html($desc) . '</li>';
                }
                echo '</ul>';
            }
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">' . esc_html__( 'Product Template', 'gm2-wordpress-suite' ) . '</th><td><textarea name="gm2_schema_template_product" rows="6" class="large-text code">' . esc_textarea($pt) . '</textarea></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Brand Template', 'gm2-wordpress-suite' ) . '</th><td><textarea name="gm2_schema_template_brand" rows="6" class="large-text code">' . esc_textarea($bt) . '</textarea></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Breadcrumb Template', 'gm2-wordpress-suite' ) . '</th><td><textarea name="gm2_schema_template_breadcrumb" rows="6" class="large-text code">' . esc_textarea($bct) . '</textarea></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Taxonomy ItemList Template', 'gm2-wordpress-suite' ) . '</th><td><textarea name="gm2_schema_template_taxonomy" rows="6" class="large-text code">' . esc_textarea($tt) . '</textarea></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Article Template', 'gm2-wordpress-suite' ) . '</th><td><textarea name="gm2_schema_template_article" rows="6" class="large-text code">' . esc_textarea($at) . '</textarea></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Review Template', 'gm2-wordpress-suite' ) . '</th><td><textarea name="gm2_schema_template_review" rows="6" class="large-text code">' . esc_textarea($rt) . '</textarea></td></tr>';
            echo '</tbody></table>';

            submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
            echo '</form>';
            $this->render_custom_schema_admin();
        } elseif ($active === 'redirects') {
            $redirects = get_option('gm2_redirects', []);
            if (!empty($_GET['logs_cleared'])) {
                echo '<div class="updated notice"><p>' . esc_html__( '404 logs cleared.', 'gm2-wordpress-suite' ) . '</p></div>';
            }
            if (!empty($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
                $id = absint($_GET['id']);
                check_admin_referer('gm2_delete_redirect_' . $id);
                if (isset($redirects[$id])) {
                    unset($redirects[$id]);
                    update_option('gm2_redirects', array_values($redirects));
                    echo '<div class="updated"><p>' . esc_html__('Redirect deleted.', 'gm2-wordpress-suite') . '</p></div>';
                    $redirects = array_values($redirects);
                }
            }

            $source_prefill = isset($_GET['source']) ? esc_url_raw($_GET['source']) : '';
            if (!empty($_GET['updated'])) {
                echo '<div class="updated notice"><p>' . esc_html__('Redirect saved.', 'gm2-wordpress-suite') . '</p></div>';
            }
            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_redirects_save', 'gm2_redirects_nonce');
            echo '<input type="hidden" name="action" value="gm2_redirects" />';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row"><label for="gm2_redirect_source">' . esc_html__( 'Source URL', 'gm2-wordpress-suite' ) . '</label></th><td><input name="gm2_redirect_source" type="text" id="gm2_redirect_source" value="' . esc_attr($source_prefill) . '" class="regular-text" required></td></tr>';
            echo '<tr><th scope="row"><label for="gm2_redirect_target">' . esc_html__( 'Target URL', 'gm2-wordpress-suite' ) . '</label></th><td><input name="gm2_redirect_target" type="url" id="gm2_redirect_target" class="regular-text" required></td></tr>';
            echo '<tr><th scope="row"><label for="gm2_redirect_type">' . esc_html__( 'Type', 'gm2-wordpress-suite' ) . '</label></th><td><select name="gm2_redirect_type" id="gm2_redirect_type"><option value="301">301</option><option value="302">302</option></select></td></tr>';
            echo '</tbody></table>';
            submit_button( esc_html__( 'Add Redirect', 'gm2-wordpress-suite' ) );
            echo '</form>';

            echo '<h2>' . esc_html__( 'Existing Redirects', 'gm2-wordpress-suite' ) . '</h2>';
            echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'Source', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Target', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Type', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Actions', 'gm2-wordpress-suite' ) . '</th></tr></thead><tbody>';
            if ($redirects) {
                foreach ($redirects as $index => $r) {
                    $delete_url = wp_nonce_url(admin_url('admin.php?page=gm2-seo&tab=redirects&action=delete&id=' . $index), 'gm2_delete_redirect_' . $index);
                    echo '<tr>';
                    echo '<td>' . esc_html($r['source']) . '</td>';
                    echo '<td>' . esc_html($r['target']) . '</td>';
                    echo '<td>' . esc_html($r['type']) . '</td>';
                    echo '<td><a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure?', 'gm2-wordpress-suite' ) ) . '\');">' . esc_html__( 'Delete', 'gm2-wordpress-suite' ) . '</a></td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="4">' . esc_html__( 'No redirects found.', 'gm2-wordpress-suite' ) . '</td></tr>';
            }
            echo '</tbody></table>';

            $logs = get_option('gm2_404_logs', []);
            if ($logs) {
                echo '<h2>' . esc_html__( '404 Logs', 'gm2-wordpress-suite' ) . '</h2>';
                echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'URL', 'gm2-wordpress-suite' ) . '</th></tr></thead><tbody>';
                foreach ($logs as $log) {
                    $link = admin_url('admin.php?page=gm2-seo&tab=redirects&source=' . urlencode($log));
                    echo '<tr><td><a href="' . esc_url($link) . '">' . esc_html($log) . '</a></td></tr>';
                }
                echo '</tbody></table>';
            }

            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_clear_404_logs');
            echo '<input type="hidden" name="action" value="gm2_clear_404_logs" />';
            submit_button( esc_html__( 'Clear 404 Logs', 'gm2-wordpress-suite' ), 'delete' );
            echo '</form>';
        } elseif ($active === 'performance') {
            $subtab = isset($_GET['subtab']) ? sanitize_key($_GET['subtab']) : '';
            $perf_tabs = [
                '' => esc_html__( 'General', 'gm2-wordpress-suite' ),
                'render-optimizer' => esc_html__( 'Render Optimizer', 'gm2-wordpress-suite' ),
                'javascript' => esc_html__( 'JavaScript', 'gm2-wordpress-suite' ),
            ];
            echo '<h2 class="nav-tab-wrapper">';
            foreach ($perf_tabs as $slug => $label) {
                $url = admin_url('admin.php?page=gm2-seo&tab=performance' . ($slug ? '&subtab=' . $slug : ''));
                $cls = ($subtab === $slug) ? ' nav-tab-active' : '';
                echo '<a href="' . esc_url($url) . '" class="nav-tab' . $cls . '">' . esc_html($label) . '</a>';
            }
            echo '</h2>';

            if ($subtab === 'render-optimizer') {
                require GM2_PLUGIN_DIR . 'admin/views/settings-render-optimizer.php';
            } elseif ($subtab === 'javascript') {
                $js_tab = isset($_GET['js-tab']) ? sanitize_key($_GET['js-tab']) : 'settings';
                $js_tabs = [
                    'settings'      => esc_html__( 'Settings', 'gm2-wordpress-suite' ),
                    'compatibility' => esc_html__( 'Compatibility', 'gm2-wordpress-suite' ),
                    'report'        => esc_html__( 'Performance Report', 'gm2-wordpress-suite' ),
                ];
                echo '<h3 class="nav-tab-wrapper" style="margin-top:20px;">';
                foreach ($js_tabs as $slug => $label) {
                    $url = admin_url('admin.php?page=gm2-seo&tab=performance&subtab=javascript&js-tab=' . $slug);
                    $cls = ($js_tab === $slug) ? ' nav-tab-active' : '';
                    echo '<a href="' . esc_url($url) . '" class="nav-tab' . $cls . '">' . esc_html($label) . '</a>';
                }
                echo '</h3>';
                if ($js_tab === 'report') {
                    require GM2_PLUGIN_DIR . 'admin/views/js-performance-report.php';
                } else {
                    require GM2_PLUGIN_DIR . 'admin/views/settings-js-optimizer.php';
                }
            } else {
                $auto_fill = get_option('gm2_auto_fill_alt', '0');
                $clean_files = get_option('gm2_clean_image_filenames', '0');
                $enable_comp = get_option('gm2_enable_compression', '0');
                $api_key    = get_option('gm2_compression_api_key', '');
                $api_url   = get_option('gm2_compression_api_url', 'https://api.example.com/compress');
                $min_html  = get_option('gm2_minify_html', '0');
                $min_css   = get_option('gm2_minify_css', '0');
                $min_js    = get_option('gm2_minify_js', '0');
                $pretty_versions = get_option('gm2_pretty_versioned_urls', '0');
                $ps_key    = get_option('gm2_pagespeed_api_key', '');
                $ps_scores = get_option('gm2_pagespeed_scores', []);
                $perf_worker = get_option('ae_perf_worker', '0');
                $perf_long   = get_option('ae_perf_long_tasks', '0');
                $perf_layout = get_option('ae_perf_layout_thrash', '0');
                $perf_no_thrash = get_option('ae_perf_no_thrash', '0');
                $perf_passive = get_option('ae_perf_passive_listeners', '0');
                $perf_dom = get_option('ae_perf_dom_audit', '0');
                $script_attrs = get_option('gm2_script_attributes', []);
                $rm_vendors = get_option('gm2_remote_mirror_vendors', []);
                $rm_fb     = !empty($rm_vendors['facebook']);
                $rm_google = !empty($rm_vendors['google']);
                $rm_custom = get_option('gm2_remote_mirror_custom_urls', []);
                if (!is_array($rm_custom)) {
                    $rm_custom = [];
                }
                $mirror = Gm2_Remote_Mirror::init();
                if (!empty($_GET['updated'])) {
                    echo '<div class="updated notice"><p>' . esc_html__('Settings saved.', 'gm2-wordpress-suite') . '</p></div>';
                }

                if (!empty($_GET['nginx_cache_written'])) {
                    echo '<div class="updated notice"><p>' . esc_html__('Snippet saved.', 'gm2-wordpress-suite') . '</p></div>';
                }
                if (!empty($_GET['critical_css_purged'])) {
                    echo '<div class="updated notice"><p>' . esc_html__('Critical CSS purged.', 'gm2-wordpress-suite') . '</p></div>';
                }
                if (!empty($_GET['optimizer_cache_purged'])) {
                    echo '<div class="updated notice"><p>' . esc_html__('Asset cache purged.', 'gm2-wordpress-suite') . '</p></div>';
                }
                if (isset($_GET['nginx_cache_verified'])) {
                    if ($_GET['nginx_cache_verified']) {
                        echo '<div class="updated notice"><p>' . esc_html__('Headers verified.', 'gm2-wordpress-suite') . '</p></div>';
                    } else {
                        echo '<div class="error notice"><p>' . esc_html__('Headers not found.', 'gm2-wordpress-suite') . '</p></div>';
                    }
                }

                echo '<h2>' . esc_html__('Cache Headers', 'gm2-wordpress-suite') . '</h2>';
                if (Gm2_Cache_Headers_Nginx::is_supported_server()) {
                    $file = Gm2_Cache_Headers_Nginx::get_file_path();
                    if (Gm2_Cache_Headers_Nginx::verify()) {
                        $status = esc_html__('Verified', 'gm2-wordpress-suite');
                    } elseif (Gm2_Cache_Headers_Nginx::rules_exist()) {
                        $status = esc_html__('Generated', 'gm2-wordpress-suite');
                    } else {
                        $status = esc_html__('Not generated', 'gm2-wordpress-suite');
                    }
                    echo '<p>' . sprintf(esc_html__('Status: %s', 'gm2-wordpress-suite'), $status) . '</p>';
                    echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
                    wp_nonce_field('gm2_generate_nginx_cache');
                    echo '<input type="hidden" name="action" value="gm2_generate_nginx_cache" />';
                    submit_button(esc_html__('Save Snippet', 'gm2-wordpress-suite'));
                    echo '</form>';
                    echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
                    wp_nonce_field('gm2_verify_nginx_cache');
                    echo '<input type="hidden" name="action" value="gm2_verify_nginx_cache" />';
                    submit_button(esc_html__('Verify', 'gm2-wordpress-suite'), 'secondary');
                    echo '</form>';
                    echo '<p><strong>' . esc_html__('How to apply', 'gm2-wordpress-suite') . '</strong></p>';
                    echo '<ol>';
                    echo '<li>' . esc_html__('Save the snippet to generate the configuration file.', 'gm2-wordpress-suite') . '</li>';
                    echo '<li>' . esc_html__('Include the file in your Nginx server block:', 'gm2-wordpress-suite') . '<br><code>include ' . esc_html($file) . ';</code></li>';
                    echo '<li>' . esc_html__('Reload Nginx.', 'gm2-wordpress-suite') . '</li>';
                    echo '</ol>';
                    echo '<textarea readonly class="large-text code" rows="12">' . esc_textarea(Gm2_Cache_Headers_Nginx::$rules) . '</textarea>';
                } else {
                    if (Gm2_Cache_Headers_Apache::cdn_sets_cache_headers()) {
                        $status = esc_html__('CDN detected', 'gm2-wordpress-suite');
                    } elseif (Gm2_Cache_Headers_Apache::rules_exist()) {
                        $status = esc_html__('Written', 'gm2-wordpress-suite');
                    } else {
                        $status = esc_html__('Not written', 'gm2-wordpress-suite');
                    }
                    echo '<p>' . sprintf(esc_html__('Status: %s', 'gm2-wordpress-suite'), $status) . '</p>';
                    echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
                    wp_nonce_field('gm2_insert_cache_rules');
                    echo '<input type="hidden" name="action" value="gm2_insert_cache_rules" />';
                    submit_button(esc_html__('Insert Cache Rules', 'gm2-wordpress-suite'));
                    echo '</form>';
                    echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
                    wp_nonce_field('gm2_remove_cache_rules');
                    echo '<input type="hidden" name="action" value="gm2_remove_cache_rules" />';
                    submit_button(esc_html__('Remove Rules', 'gm2-wordpress-suite'), 'delete');
                    echo '</form>';
                    if (!wp_is_writable(ABSPATH . '.htaccess')) {
                        echo '<p>' . esc_html__('Add the following to your .htaccess file:', 'gm2-wordpress-suite') . '</p>';
                        echo '<textarea readonly class="large-text code" rows="15">' . esc_textarea(Gm2_Cache_Headers_Apache::$rules) . '</textarea>';
                    }
                }
                echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
                wp_nonce_field('gm2_performance_save', 'gm2_performance_nonce');
                echo '<input type="hidden" name="action" value="gm2_performance_settings" />';
                echo '<table class="form-table"><tbody>';
                echo '<tr><th scope="row">' . esc_html__( 'Auto-fill missing alt text', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="gm2_auto_fill_alt" value="1" ' . checked($auto_fill, '1', false) . '> ' . esc_html__( 'Use product title', 'gm2-wordpress-suite' ) . '</label></td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Clean Image Filenames', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="gm2_clean_image_filenames" value="1" ' . checked($clean_files, '1', false) . '> ' . esc_html__( 'Sanitize on upload', 'gm2-wordpress-suite' ) . '</label></td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Enable Image Compression', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_enable_compression" value="1" ' . checked($enable_comp, '1', false) . '></td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Compression API Key', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="gm2_compression_api_key" value="' . esc_attr($api_key) . '" class="regular-text" /></td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Compression API URL', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="gm2_compression_api_url" value="' . esc_attr($api_url) . '" class="regular-text" /></td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Minify HTML', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="gm2_minify_html" value="1" ' . checked($min_html, '1', false) . '></label></td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Minify CSS', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="gm2_minify_css" value="1" ' . checked($min_css, '1', false) . '></label></td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Minify JS', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="gm2_minify_js" value="1" ' . checked($min_js, '1', false) . '></label></td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Pretty Versioned URLs', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="gm2_pretty_versioned_urls" value="1" ' . checked($pretty_versions, '1', false) . '> ' . esc_html__( 'Transform file.css?ver=123 into file.v123.css', 'gm2-wordpress-suite' ) . '</label></td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'PageSpeed API Key', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="gm2_pagespeed_api_key" value="' . esc_attr($ps_key) . '" class="regular-text" />';
                if (!empty($ps_scores['mobile']) || !empty($ps_scores['desktop'])) {
                    $time = !empty($ps_scores['time']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $ps_scores['time']) : '';
                    echo '<p>Mobile: ' . esc_html($ps_scores['mobile'] ?? '') . ' Desktop: ' . esc_html($ps_scores['desktop'] ?? '') . ' ' . esc_html($time) . '</p>';
                }
                echo '</td></tr>';

                echo '<tr><th colspan="2"><h2>' . esc_html__( 'Performance', 'gm2-wordpress-suite' ) . '</h2></th></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Enable Web Worker offloading', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="ae_perf_worker" value="1" ' . checked($perf_worker, '1', false) . '></label></td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Break up long tasks', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="ae_perf_long_tasks" value="1" ' . checked($perf_long, '1', false) . '></label></td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Prevent layout thrash', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="ae_perf_layout_thrash" value="1" ' . checked($perf_layout, '1', false) . '></label></td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Batch DOM reads & writes', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="ae_perf_no_thrash" value="1" ' . checked($perf_no_thrash, '1', false) . '></label></td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Passive scroll/touch listeners', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="ae_perf_passive_listeners" value="1" ' . checked($perf_passive, '1', false) . '></label></td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'DOM size audit', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="ae_perf_dom_audit" value="1" ' . checked($perf_dom, '1', false) . '></label></td></tr>';

                echo '<tr><th colspan="2"><h2>' . esc_html__( 'Script Loading', 'gm2-wordpress-suite' ) . '</h2></th></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Presets', 'gm2-wordpress-suite' ) . '</th><td><select name="gm2_script_attr_preset">';
                echo '<option value="">' . esc_html__( '-- Select Preset --', 'gm2-wordpress-suite' ) . '</option>';
                echo '<option value="defer_third">' . esc_html__( 'Defer all third-party', 'gm2-wordpress-suite' ) . '</option>';
                echo '<option value="conservative">' . esc_html__( 'Conservative', 'gm2-wordpress-suite' ) . '</option>';
                echo '</select> ';
                echo '<button type="submit" class="button" name="gm2_script_attr_apply_preset" value="1">' . esc_html__( 'Apply', 'gm2-wordpress-suite' ) . '</button> ';
                echo '<button type="submit" class="button" name="gm2_script_attr_reset" value="1">' . esc_html__( 'Reset to Defaults', 'gm2-wordpress-suite' ) . '</button>';
                echo '</td></tr>';

                echo '<tr><th scope="row">' . esc_html__( 'Per-handle Overrides', 'gm2-wordpress-suite' ) . '</th><td><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Handle', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Attribute', 'gm2-wordpress-suite' ) . '</th></tr></thead><tbody>';
                foreach ($script_attrs as $handle => $attr) {
                    echo '<tr><td><input type="text" name="gm2_script_attr_handles[]" value="' . esc_attr($handle) . '" class="regular-text" /></td><td><select name="gm2_script_attr_values[]">';
                    echo '<option value="">' . esc_html__( 'Default', 'gm2-wordpress-suite' ) . '</option>';
                    echo '<option value="blocking" ' . selected($attr, 'blocking', false) . '>' . esc_html__( 'Blocking', 'gm2-wordpress-suite' ) . '</option>';
                    echo '<option value="defer" ' . selected($attr, 'defer', false) . '>' . esc_html__( 'Defer', 'gm2-wordpress-suite' ) . '</option>';
                    echo '<option value="async" ' . selected($attr, 'async', false) . '>' . esc_html__( 'Async', 'gm2-wordpress-suite' ) . '</option>';
                    echo '</select></td></tr>';
                }
                echo '<tr><td><input type="text" name="gm2_script_attr_handles[]" value="" class="regular-text" /></td><td><select name="gm2_script_attr_values[]">';
                echo '<option value="">' . esc_html__( 'Default', 'gm2-wordpress-suite' ) . '</option>';
                echo '<option value="blocking" selected="selected">' . esc_html__( 'Blocking', 'gm2-wordpress-suite' ) . '</option>';
                echo '<option value="defer">' . esc_html__( 'Defer', 'gm2-wordpress-suite' ) . '</option>';
                echo '<option value="async">' . esc_html__( 'Async', 'gm2-wordpress-suite' ) . '</option>';
                echo '</select></td></tr>';
                echo '</tbody></table><p class="description">' . esc_html__( 'Leave handle blank to ignore. Choose Default to remove override.', 'gm2-wordpress-suite' ) . '</p></td></tr>';

                echo '<tr><th colspan="2"><h2>' . esc_html__( 'Remote Mirror', 'gm2-wordpress-suite' ) . '</h2></th></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Facebook Pixel', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="gm2_remote_mirror_vendors[facebook]" value="1" ' . checked($rm_fb, true, false) . '> ' . esc_html__( 'Enable', 'gm2-wordpress-suite' ) . '</label><p class="description"><a href="https://www.facebook.com/legal/terms/plain_text_terms" target="_blank">' . esc_html__( 'Terms of Service', 'gm2-wordpress-suite' ) . '</a></p></td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Google gtag', 'gm2-wordpress-suite' ) . '</th><td><label><input type="checkbox" name="gm2_remote_mirror_vendors[google]" value="1" ' . checked($rm_google, true, false) . '> ' . esc_html__( 'Enable', 'gm2-wordpress-suite' ) . '</label><p class="description"><a href="https://marketingplatform.google.com/about/analytics/terms/us/" target="_blank">' . esc_html__( 'Terms of Service', 'gm2-wordpress-suite' ) . '</a></p>';
                if ($rm_google) {
                    echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Ensure Google terms permit mirroring gtag.js.', 'gm2-wordpress-suite' ) . '</p></div>';
                }
                echo '</td></tr>';
                echo '<tr><th scope="row">' . esc_html__( 'Custom Script URLs', 'gm2-wordpress-suite' ) . '</th><td><textarea name="gm2_remote_mirror_custom_urls" rows="5" class="large-text">' . esc_textarea(implode("\n", $rm_custom)) . '</textarea><p class="description">' . esc_html__( 'One URL per line.', 'gm2-wordpress-suite' ) . '</p></td></tr>';

                echo '</tbody></table>';
                echo '<p class="description">' . esc_html__( 'SHA-256 hashes are shown for mirrored files. SRI may break after vendor updates.', 'gm2-wordpress-suite' ) . '</p>';
                $registry = $mirror->get_registry();
                if (!empty($registry)) {
                    echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Vendor', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'URL', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'SHA-256', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Last Fetch', 'gm2-wordpress-suite' ) . '</th></tr></thead><tbody>';
                    foreach ($registry as $vendor => $data) {
                        $enabled = ($vendor === 'custom') ? !empty($rm_custom) : !empty($rm_vendors[$vendor]);
                        foreach ($data['urls'] as $url) {
                            $filename = basename(parse_url($url, PHP_URL_PATH) ?? '');
                            $path = $mirror->get_local_path($vendor, $filename);
                            $hash = file_exists($path) ? hash_file('sha256', $path) : '';
                            $time = file_exists($path) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($path)) : '';
                            $label = $enabled ? $vendor : $vendor . ' (' . esc_html__( 'disabled', 'gm2-wordpress-suite' ) . ')';
                            echo '<tr><td>' . esc_html($label) . '</td><td>' . esc_html($url) . '</td><td><code>' . esc_html($hash) . '</code></td><td>' . esc_html($time) . '</td></tr>';
                        }
                    }
                    echo '</tbody></table>';
                }

                submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
                echo ' <input type="submit" name="gm2_test_pagespeed" class="button" value="' . esc_attr__( 'Test Page Speed', 'gm2-wordpress-suite' ) . '" />';
                echo '</form>';

                echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
                wp_nonce_field('gm2_purge_critical_css');
                echo '<input type="hidden" name="action" value="gm2_purge_critical_css" />';
                submit_button(esc_html__('Purge & Rebuild Critical CSS', 'gm2-wordpress-suite'), 'delete');
                echo '</form>';

                echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
                wp_nonce_field('gm2_purge_optimizer_cache');
                echo '<input type="hidden" name="action" value="gm2_purge_optimizer_cache" />';
                submit_button(esc_html__('Purge Combined Assets', 'gm2-wordpress-suite'), 'delete');
                echo '</form>';
            }
        } elseif ($active === 'keywords') {
            $enabled = trim(get_option('gm2_gads_developer_token', '')) !== '' &&
                trim(get_option('gm2_gads_customer_id', '')) !== '' &&
                get_option('gm2_google_refresh_token', '') !== '';

            $lang   = get_option('gm2_gads_language', 'languageConstants/1000');
            $geo    = get_option('gm2_gads_geo_target', 'geoTargetConstants/2840');
            $login  = get_option('gm2_gads_login_customer_id', '');
            $limit     = get_option('gm2_sc_query_limit', 10);
            $days      = get_option('gm2_analytics_days', 30);
            $retention = get_option('gm2_analytics_retention_days', 30);

            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_keyword_settings_save', 'gm2_keyword_settings_nonce');
            echo '<input type="hidden" name="action" value="gm2_keyword_settings" />';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">' . esc_html__( 'Language Constant', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="gm2_gads_language" id="gm2_gads_language" value="' . esc_attr($lang) . '" class="regular-text" /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Geo Target Constant', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="gm2_gads_geo_target" id="gm2_gads_geo_target" value="' . esc_attr($geo) . '" class="regular-text" /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Login Customer ID', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="gm2_gads_login_customer_id" id="gm2_gads_login_customer_id" value="' . esc_attr($login) . '" class="regular-text" /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Search Console Query Limit', 'gm2-wordpress-suite' ) . '</th><td><input type="number" name="gm2_sc_query_limit" id="gm2_sc_query_limit" value="' . esc_attr($limit) . '" class="small-text" /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Analytics Days', 'gm2-wordpress-suite' ) . '</th><td><input type="number" name="gm2_analytics_days" id="gm2_analytics_days" value="' . esc_attr($days) . '" class="small-text" /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Analytics Retention Days', 'gm2-wordpress-suite' ) . '</th><td><input type="number" name="gm2_analytics_retention_days" id="gm2_analytics_retention_days" value="' . esc_attr($retention) . '" class="small-text" /></td></tr>';
            echo '</tbody></table>';
            echo '<p class="description">' . esc_html__( 'Defaults: English / United States.', 'gm2-wordpress-suite' ) . '</p>';
            submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
            echo '</form>';

            echo '<form id="gm2-keyword-research-form">';
            echo '<p><label for="gm2_seed_keyword">' . esc_html__( 'Seed Keyword', 'gm2-wordpress-suite' ) . '</label>';
            echo '<input type="text" id="gm2_seed_keyword" class="regular-text" /></p>';
            echo '<p><button class="button" type="submit"' . ($enabled ? '' : ' disabled') . '>' . esc_html__( 'Generate Ideas', 'gm2-wordpress-suite' ) . '</button></p>';
            if (!$enabled) {
                echo '<p class="description">' . esc_html__('Google Ads credentials are not configured.', 'gm2-wordpress-suite') . '</p>';
            }
            echo '</form>';
            echo '<div class="notice notice-error hidden" id="gm2-keyword-msg"></div>';
            echo '<ul id="gm2-keyword-results"></ul>';

            $oauth = apply_filters('gm2_google_oauth_instance', new Gm2_Google_OAuth());
            if ($oauth->is_connected()) {
                $site   = home_url('/');
                $queries = $oauth->get_search_console_queries($site, $limit);
                $metrics = $oauth->get_analytics_metrics(get_option('gm2_ga_measurement_id', ''), $days);

                echo '<h3>Top Queries</h3>';
                if ($queries) {
                    echo '<ul class="gm2-top-queries">';
                    foreach ($queries as $q) {
                        echo '<li>' . esc_html($q) . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p>' . esc_html__('No queries found.', 'gm2-wordpress-suite') . '</p>';
                }

                echo '<h3>Analytics</h3>';
                if (!empty($metrics)) {
                    echo '<p>Sessions: ' . esc_html($metrics['sessions']) . '</p>';
                    echo '<p>Bounce Rate: ' . esc_html($metrics['bounce_rate']) . '</p>';
                } else {
                    echo '<p>' . esc_html__('No analytics data found.', 'gm2-wordpress-suite') . '</p>';
                }
            } else {
                echo '<p>' . esc_html__('Connect your Google account to fetch query and analytics data.', 'gm2-wordpress-suite') . '</p>';
            }
        } elseif ($active === 'analytics') {
            $limit  = get_option('gm2_sc_query_limit', 10);
            $days   = get_option('gm2_analytics_days', 30);
            $oauth  = apply_filters('gm2_google_oauth_instance', new Gm2_Google_OAuth());

            if ($oauth->is_connected()) {
                $site    = home_url('/');
                $prop    = get_option('gm2_ga_measurement_id', '');
                $queries = $oauth->get_search_console_metrics($site, $limit);
                $metrics = $oauth->get_analytics_metrics($prop, $days);
                $trends  = $oauth->get_analytics_trends($prop, $days);
                wp_enqueue_script(
                    'chart-js',
                    'https://cdn.jsdelivr.net/npm/chart.js',
                    [],
                    '4.4.2',
                    true
                );
                wp_enqueue_script(
                    'gm2-analytics',
                    GM2_PLUGIN_URL . 'admin/js/gm2-analytics.js',
                    [ 'jquery', 'chart-js' ],
                    file_exists( GM2_PLUGIN_DIR . 'admin/js/gm2-analytics.js' ) ? filemtime( GM2_PLUGIN_DIR . 'admin/js/gm2-analytics.js' ) : GM2_VERSION,
                    true
                );
                wp_localize_script(
                    'gm2-analytics',
                    'gm2Analytics',
                    [
                        'dates'       => $trends['dates'] ?? [],
                        'sessions'    => $trends['sessions'] ?? [],
                        'bounce_rate' => $trends['bounce_rate'] ?? [],
                        'queries'     => $queries,
                    ]
                );

                echo '<h3>' . esc_html__( 'Analytics Overview', 'gm2-wordpress-suite' ) . '</h3>';
                if (!empty($metrics)) {
                    echo '<p>Sessions: ' . esc_html($metrics['sessions']) . '</p>';
                    echo '<p>Bounce Rate: ' . esc_html($metrics['bounce_rate']) . '</p>';
                    echo '<canvas id="gm2-analytics-trend" width="400" height="200" aria-hidden="true"></canvas>';
                } else {
                    echo '<p>' . esc_html__('No analytics data found.', 'gm2-wordpress-suite') . '</p>';
                }

                echo '<h3>' . esc_html__( 'Top Queries', 'gm2-wordpress-suite' ) . '</h3>';
                if ($queries) {
                    echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'Query', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Clicks', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Impressions', 'gm2-wordpress-suite' ) . '</th></tr></thead><tbody>';
                    foreach ($queries as $row) {
                        echo '<tr><td>' . esc_html($row['query']) . '</td><td>' . esc_html($row['clicks']) . '</td><td>' . esc_html($row['impressions']) . '</td></tr>';
                    }
                    echo '</tbody></table>';
                    echo '<canvas id="gm2-query-chart" width="400" height="200" aria-hidden="true"></canvas>';
                } else {
                    echo '<p>' . esc_html__('No search console data found.', 'gm2-wordpress-suite') . '</p>';
                }
            } else {
                echo '<p>' . esc_html__('Connect your Google account to view analytics.', 'gm2-wordpress-suite') . '</p>';
            }
        } elseif ($active === 'rules') {
            $all_rules = get_option('gm2_content_rules', []);
            if (!empty($_GET['updated'])) {
                echo '<div class="updated notice"><p>' . esc_html__('Rules saved.', 'gm2-wordpress-suite') . '</p></div>';
            }
            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_content_rules_save', 'gm2_content_rules_nonce');
            echo '<input type="hidden" name="action" value="gm2_content_rules" />';
            echo '<table class="form-table"><tbody>';
            $cats = [
                'seo_title'        => __( 'SEO Title', 'gm2-wordpress-suite' ),
                'seo_description'  => __( 'SEO Description', 'gm2-wordpress-suite' ),
                'focus_keywords'   => __( 'Focus Keywords', 'gm2-wordpress-suite' ),
                'long_tail_keywords' => __( 'Long Tail Keywords', 'gm2-wordpress-suite' ),
                'canonical_url'    => __( 'Canonical URL', 'gm2-wordpress-suite' ),
                'content'          => __( 'Content', 'gm2-wordpress-suite' ),
                'general'          => __( 'General', 'gm2-wordpress-suite' ),
            ];
            foreach ($this->get_supported_post_types() as $pt) {
                $label = get_post_type_object($pt)->labels->singular_name ?? ucfirst($pt);
                $vals  = $all_rules['post_' . $pt] ?? [];
                echo '<tr><th scope="row"><label>' . esc_html($label) . '</label></th><td>';
                foreach ($cats as $c => $clabel) {
                    $val = $vals[$c] ?? '';
                    $val = $this->flatten_rule_value($val);
                    echo '<p><label for="gm2_rule_post_' . esc_attr($pt . '_' . $c) . '">' . esc_html($clabel) . '</label><br />';
                    echo '<textarea id="gm2_rule_post_' . esc_attr($pt . '_' . $c) . '" name="gm2_content_rules[post_' . esc_attr($pt) . '][' . esc_attr($c) . ']" rows="3" class="large-text">' . esc_textarea($val) . '</textarea></p>';
                }
                echo '<p><button type="button" class="button gm2-research-rules" data-base="post_' . esc_attr($pt) . '">' . esc_html__( 'AI Research Content Rules', 'gm2-wordpress-suite' ) . '</button></p>';
                echo '</td></tr>';
            }
            foreach ($this->get_supported_taxonomies() as $tax) {
                $tax_obj = get_taxonomy($tax);
                if ($tax === 'category') {
                    $label = __('Post Category', 'gm2-wordpress-suite');
                } elseif ($tax === 'product_cat') {
                    $label = __('Product Category', 'gm2-wordpress-suite');
                } else {
                    $label = $tax_obj ? $tax_obj->labels->singular_name : $tax;
                }
                $vals = $all_rules['tax_' . $tax] ?? [];
                echo '<tr><th scope="row"><label>' . esc_html($label) . '</label></th><td>';
                foreach ($cats as $c => $clabel) {
                    $val = $vals[$c] ?? '';
                    $val = $this->flatten_rule_value($val);
                    echo '<p><label for="gm2_rule_tax_' . esc_attr($tax . '_' . $c) . '">' . esc_html($clabel) . '</label><br />';
                    echo '<textarea id="gm2_rule_tax_' . esc_attr($tax . '_' . $c) . '" name="gm2_content_rules[tax_' . esc_attr($tax) . '][' . esc_attr($c) . ']" rows="3" class="large-text">' . esc_textarea($val) . '</textarea></p>';
                }
                echo '<p><button type="button" class="button gm2-research-rules" data-base="tax_' . esc_attr($tax) . '">' . esc_html__( 'AI Research Content Rules', 'gm2-wordpress-suite' ) . '</button></p>';
                echo '</td></tr>';
            }
            $min_int = (int) get_option('gm2_min_internal_links', 1);
            $min_ext = (int) get_option('gm2_min_external_links', 1);
            echo '<tr><th scope="row">' . esc_html__( 'Minimum Internal Links', 'gm2-wordpress-suite' ) . '</th><td><input type="number" name="gm2_min_internal_links" value="' . esc_attr($min_int) . '" class="small-text" /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Minimum External Links', 'gm2-wordpress-suite' ) . '</th><td><input type="number" name="gm2_min_external_links" value="' . esc_attr($min_ext) . '" class="small-text" /></td></tr>';
            echo '</tbody></table>';
            submit_button( esc_html__( 'Save Rules', 'gm2-wordpress-suite' ) );
            echo '</form>';
        } elseif ($active === 'taxonomies') {
            echo '<form method="post" action="options.php">';
            settings_fields('gm2_seo_options');
            $min = (int) get_option('gm2_tax_min_length', 150);
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row"><label for="gm2_tax_min_length">' . esc_html__( 'Minimum Description Length', 'gm2-wordpress-suite' ) . '</label></th><td><input type="number" id="gm2_tax_min_length" name="gm2_tax_min_length" value="' . esc_attr($min) . '" class="small-text" /></td></tr>';
            echo '</tbody></table>';
            submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
            echo '</form>';
        } elseif ($active === 'context') {
            echo '<form method="post" action="options.php">';
            settings_fields('gm2_seo_options');

            echo '<table class="form-table"><tbody>';
            $fields = [
                'gm2_context_business_model'        => [
                    'label' => __( 'Business Model', 'gm2-wordpress-suite' ),
                    'type'  => 'textarea',
                    'desc'  => __( 'How does the company make money (product sales, services, subscriptions, ads, affiliate, hybrid)?', 'gm2-wordpress-suite' ),
                ],
                'gm2_context_industry_category'     => [
                    'label' => __( 'Industry Category', 'gm2-wordpress-suite' ),
                    'type'  => 'text',
                    'desc'  => __( 'Which industry best describes your business? If e-commerce, list key product categories and flagship items. For services, describe core offerings. For SaaS, summarize primary modules. Include your Google taxonomy label if known.', 'gm2-wordpress-suite' ),
                ],
                'gm2_context_target_audience'       => [
                    'label' => __( 'Target Audience', 'gm2-wordpress-suite' ),
                    'type'  => 'textarea',
                    'desc'  => __( 'Who are your core customer segments and where are they located?', 'gm2-wordpress-suite' ),
                ],
                'gm2_context_unique_selling_points' => [
                    'label' => __( 'Unique Selling Points', 'gm2-wordpress-suite' ),
                    'type'  => 'textarea',
                    'desc'  => __( 'What differentiates your brand from competitors in terms of price, quality, experience, or mission?', 'gm2-wordpress-suite' ),
                ],
                'gm2_context_revenue_streams'       => [
                    'label' => __( 'Revenue Streams', 'gm2-wordpress-suite' ),
                    'type'  => 'textarea',
                    'desc'  => __( 'List your main sources of revenue such as products, services, subscriptions, advertising, or affiliate programs.', 'gm2-wordpress-suite' ),
                ],
                'gm2_context_primary_goal'          => [
                    'label' => __( 'Primary Goal', 'gm2-wordpress-suite' ),
                    'type'  => 'textarea',
                    'desc'  => __( 'What is the website\'s main objective?', 'gm2-wordpress-suite' ),
                ],
                'gm2_context_brand_voice'           => [
                    'label' => __( 'Brand Voice', 'gm2-wordpress-suite' ),
                    'type'  => 'textarea',
                    'desc'  => __( 'Describe the desired style or tone (professional, casual, luxury, authoritative, playful, eco-friendly, etc.).', 'gm2-wordpress-suite' ),
                ],
                'gm2_context_competitors'           => [
                    'label' => __( 'Competitors', 'gm2-wordpress-suite' ),
                    'type'  => 'textarea',
                    'desc'  => __( 'List main online competitors and what makes your offer stronger or unique.', 'gm2-wordpress-suite' ),
                ],
                'gm2_context_core_offerings'        => [
                    'label' => __( 'Core Offerings', 'gm2-wordpress-suite' ),
                    'type'  => 'textarea',
                    'desc'  => __( 'What are the key products or services you provide?', 'gm2-wordpress-suite' ),
                ],
                'gm2_context_geographic_focus'      => [
                    'label' => __( 'Geographic Focus', 'gm2-wordpress-suite' ),
                    'type'  => 'textarea',
                    'desc'  => __( 'Which regions or locations do you primarily target?', 'gm2-wordpress-suite' ),
                ],
                'gm2_context_keyword_data'          => [
                    'label' => __( 'Keyword Data', 'gm2-wordpress-suite' ),
                    'type'  => 'textarea',
                    'desc'  => __( 'Do you have existing keyword research or rankings to share?', 'gm2-wordpress-suite' ),
                ],
                'gm2_context_competitor_landscape'  => [
                    'label' => __( 'Competitor Landscape', 'gm2-wordpress-suite' ),
                    'type'  => 'textarea',
                    'desc'  => __( 'How would you describe the competitive landscape in your niche?', 'gm2-wordpress-suite' ),
                ],
                'gm2_context_success_metrics'       => [
                    'label' => __( 'Success Metrics', 'gm2-wordpress-suite' ),
                    'type'  => 'textarea',
                    'desc'  => __( 'Which KPIs will track SEO success (sales, leads, traffic, rankings)?', 'gm2-wordpress-suite' ),
                ],
                'gm2_context_buyer_personas'        => [
                    'label' => __( 'Buyer Personas', 'gm2-wordpress-suite' ),
                    'type'  => 'textarea',
                    'desc'  => __( 'Describe your ideal buyers and their pain points.', 'gm2-wordpress-suite' ),
                ],
                'gm2_context_project_description'   => [
                    'label' => __( 'Project Description', 'gm2-wordpress-suite' ),
                    'type'  => 'textarea',
                    'desc'  => __( 'Short summary of your project or website. Used when other fields are empty.', 'gm2-wordpress-suite' ),
                ],
                'gm2_context_custom_prompts'        => [
                    'label' => __( 'Custom Prompts', 'gm2-wordpress-suite' ),
                    'type'  => 'textarea',
                    'desc'  => __( 'Default instructions appended to AI requests.', 'gm2-wordpress-suite' ),
                ],
            ];
            foreach ( $fields as $opt => $data ) {
                $val = get_option( $opt, '' );
                echo '<tr><th scope="row"><label for="' . esc_attr( $opt ) . '">' . esc_html( $data['label'] ) . '</label></th><td>';
                if ( $data['type'] === 'text' ) {
                    echo '<input type="text" id="' . esc_attr( $opt ) . '" name="' . esc_attr( $opt ) . '" value="' . esc_attr( $val ) . '" class="regular-text" />';
                } else {
                    echo '<textarea id="' . esc_attr( $opt ) . '" name="' . esc_attr( $opt ) . '" rows="3" class="large-text">' . esc_textarea( $val ) . '</textarea>';
                }
                if ( isset( $data['desc'] ) ) {
                    echo '<p class="description">' . esc_html( $data['desc'] ) . '</p>';
                }
                echo '</td></tr>';
            }
            $val     = get_option( 'gm2_context_ai_prompt', '' );
            $enabled = get_option( 'gm2_enable_chatgpt', '1' ) === '1';
            echo '<tr><th scope="row"><label for="gm2_context_ai_prompt">' . esc_html__( 'Business Context Prompt', 'gm2-wordpress-suite' ) . '</label></th><td>';
            echo '<textarea id="gm2_context_ai_prompt" name="gm2_context_ai_prompt" rows="4" class="large-text"' . disabled( $enabled, false, false ) . '>' . esc_textarea( $val ) . '</textarea>';
            if ( $enabled ) {
                echo '<p><button type="button" class="button gm2-build-ai-prompt">' . esc_html__( 'Generate AI Prompt', 'gm2-wordpress-suite' ) . '</button></p>';
            } else {
                echo '<p><em>' . esc_html__( 'ChatGPT is disabled.', 'gm2-wordpress-suite' ) . '</em></p>';
            }
            echo '<p class="description">' . esc_html__( 'Creates a single prompt summarizing your answers above. ChatGPT must be enabled and configured.', 'gm2-wordpress-suite' ) . '</p>';
            echo '</td></tr>';
            echo '</tbody></table>';
            submit_button( esc_html__( 'Save Context', 'gm2-wordpress-suite' ) );
            echo '</form>';
        } elseif ($active === 'guidelines') {
            $all_rules = get_option('gm2_guideline_rules', []);
            echo '<h2>' . esc_html__( 'Guideline Rules', 'gm2-wordpress-suite' ) . '</h2>';
            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_guideline_rules_save', 'gm2_guideline_rules_nonce');
            echo '<input type="hidden" name="action" value="gm2_guideline_rules" />';
            echo '<table class="form-table"><tbody>';
            $cats = [
                'seo_title'        => __( 'SEO Title', 'gm2-wordpress-suite' ),
                'seo_description'  => __( 'SEO Description', 'gm2-wordpress-suite' ),
                'focus_keywords'   => __( 'Focus Keywords', 'gm2-wordpress-suite' ),
                'long_tail_keywords' => __( 'Long Tail Keywords', 'gm2-wordpress-suite' ),
                'canonical_url'    => __( 'Canonical URL', 'gm2-wordpress-suite' ),
                'content'          => __( 'Content', 'gm2-wordpress-suite' ),
                'general'          => __( 'General', 'gm2-wordpress-suite' ),
            ];
            foreach ($this->get_supported_post_types() as $pt) {
                $label = get_post_type_object($pt)->labels->singular_name ?? ucfirst($pt);
                $vals  = $all_rules['post_' . $pt] ?? [];
                echo '<tr><th scope="row"><label>' . esc_html($label) . '</label></th><td>';
                foreach ($cats as $c => $clabel) {
                    $val = $vals[$c] ?? '';
                    $val = $this->flatten_rule_value($val);
                    echo '<p><label for="gm2_guideline_post_' . esc_attr($pt . '_' . $c) . '">' . esc_html($clabel) . '</label><br />';
                    echo '<textarea id="gm2_guideline_post_' . esc_attr($pt . '_' . $c) . '" name="gm2_guideline_rules[post_' . esc_attr($pt) . '][' . esc_attr($c) . ']" rows="3" class="large-text">' . esc_textarea($val) . '</textarea></p>';
                }
                echo '<p><button type="button" class="button gm2-research-guideline-rules" data-base="post_' . esc_attr($pt) . '">' . esc_html__( 'AI Research Guideline Rules', 'gm2-wordpress-suite' ) . '</button></p>';
                echo '</td></tr>';
            }
            foreach ($this->get_supported_taxonomies() as $tax) {
                $tax_obj = get_taxonomy($tax);
                if ($tax === 'category') {
                    $label = __('Post Category', 'gm2-wordpress-suite');
                } elseif ($tax === 'product_cat') {
                    $label = __('Product Category', 'gm2-wordpress-suite');
                } else {
                    $label = $tax_obj ? $tax_obj->labels->singular_name : $tax;
                }
                $vals = $all_rules['tax_' . $tax] ?? [];
                echo '<tr><th scope="row"><label>' . esc_html($label) . '</label></th><td>';
                foreach ($cats as $c => $clabel) {
                    $val = $vals[$c] ?? '';
                    $val = $this->flatten_rule_value($val);
                    echo '<p><label for="gm2_guideline_tax_' . esc_attr($tax . '_' . $c) . '">' . esc_html($clabel) . '</label><br />';
                    echo '<textarea id="gm2_guideline_tax_' . esc_attr($tax . '_' . $c) . '" name="gm2_guideline_rules[tax_' . esc_attr($tax) . '][' . esc_attr($c) . ']" rows="3" class="large-text">' . esc_textarea($val) . '</textarea></p>';
                }
                echo '<p><button type="button" class="button gm2-research-guideline-rules" data-base="tax_' . esc_attr($tax) . '">' . esc_html__( 'AI Research Guideline Rules', 'gm2-wordpress-suite' ) . '</button></p>';
                echo '</td></tr>';
            }
            echo '</tbody></table>';
            submit_button( esc_html__( 'Save Guideline Rules', 'gm2-wordpress-suite' ) );
            echo '</form>';
        } else {
            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_general_settings_save', 'gm2_general_settings_nonce');
            echo '<input type="hidden" name="action" value="gm2_general_settings" />';
            do_settings_sections('gm2_seo');
            submit_button();
            echo '</form>';
        }

        echo '<hr />';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        wp_nonce_field('gm2_export_settings');
        echo '<input type="hidden" name="action" value="gm2_export_settings" />';
        submit_button( esc_html__( 'Export Settings', 'gm2-wordpress-suite' ), 'secondary' );
        echo '</form>';

        echo '<form method="post" action="' . admin_url('admin-post.php') . '" enctype="multipart/form-data">';
        wp_nonce_field('gm2_import_settings');
        echo '<input type="hidden" name="action" value="gm2_import_settings" />';
        echo '<input type="file" name="gm2_settings_file" accept="application/json" /> ';
        submit_button( esc_html__( 'Import Settings', 'gm2-wordpress-suite' ), 'secondary' );
        echo '</form>';

        echo '<hr />';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        wp_nonce_field('gm2_reset_seo');
        echo '<input type="hidden" name="action" value="gm2_reset_seo" />';
        submit_button( esc_html__( 'Reset to Defaults', 'gm2-wordpress-suite' ), 'secondary' );
        echo '</form>';
        echo '</div>';
    }

    public function display_robots_page() {
        $content = get_option('gm2_robots_txt', '');
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Robots.txt', 'gm2-wordpress-suite' ) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('gm2_robots_options');
        echo '<textarea name="gm2_robots_txt" rows="10" class="large-text code">' . esc_textarea($content) . '</textarea>';
        submit_button( esc_html__( 'Save', 'gm2-wordpress-suite' ) );
        echo '</form>';
        echo '</div>';
    }

    public function display_bulk_ai_page() {
        if (!current_user_can('edit_posts')) {
            esc_html_e( 'Permission denied', 'gm2-wordpress-suite' );
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen) {
            $doc_url = plugins_url('docs/index.md', GM2_PLUGIN_DIR . 'gm2-wordpress-suite.php');
            $content  = '<p>' . esc_html__( 'Posts are queued and processed one at a time. Use "Analyze Selected" to queue items and "Cancel" to stop.', 'gm2-wordpress-suite' ) . '</p>';
            $content .= '<p>' . esc_html__( 'Click "Apply" or "Apply All" to accept suggestions. Row colors show status: white for new, yellow for analyzed, and green for applied.', 'gm2-wordpress-suite' ) . '</p>';
            $content .= '<p>' . sprintf(
                __( 'See the <a href="%s" target="_blank" rel="noopener">documentation</a> for more details.', 'gm2-wordpress-suite' ),
                esc_url($doc_url)
            ) . '</p>';
            $screen->add_help_tab([
                'id'      => 'gm2-bulk-ai-help',
                'title'   => __( 'Bulk AI Help', 'gm2-wordpress-suite' ),
                'content' => $content,
            ]);
        }

        $user_id       = get_current_user_id();
        $page_size     = max(1, absint(get_user_meta($user_id, 'gm2_bulk_ai_page_size', true) ?: 10));
        $status        = get_user_meta($user_id, 'gm2_bulk_ai_status', true) ?: 'publish';
        $post_type     = get_user_meta($user_id, 'gm2_bulk_ai_post_type', true) ?: 'all';
        $term_raw      = get_user_meta($user_id, 'gm2_bulk_ai_term', true);
        if (is_array($term_raw)) {
            $term = $term_raw;
        } elseif (is_string($term_raw) && $term_raw !== '') {
            $term = [];
            foreach (array_map('trim', explode(',', $term_raw)) as $pair) {
                if (strpos($pair, ':') === false) {
                    continue;
                }
                list($tax, $id) = explode(':', $pair);
                $tax = sanitize_key($tax);
                $term[$tax][] = absint($id);
            }
        } else {
            $term = [];
        }
        $seo_status    = get_user_meta($user_id, 'gm2_bulk_ai_seo_status', true) ?: 'all';
        $missing_title = get_option('gm2_bulk_ai_missing_title', '0');
        $missing_desc  = get_option('gm2_bulk_ai_missing_description', '0');

        if (isset($_POST['gm2_bulk_ai_save']) && check_admin_referer('gm2_bulk_ai_settings')) {
            $page_size     = max(1, absint($_POST['page_size'] ?? 10));
            $status        = sanitize_key($_POST['status'] ?? 'publish');
            $post_type     = sanitize_key($_POST['gm2_post_type'] ?? 'all');
            $raw_terms     = isset($_POST['gm2_term']) && is_array($_POST['gm2_term']) ? $_POST['gm2_term'] : [];
            $term          = [];
            foreach ($raw_terms as $tax => $ids) {
                $tax = sanitize_key($tax);
                if (!in_array($tax, $this->get_supported_taxonomies(), true)) {
                    continue;
                }
                $ids = is_array($ids) ? array_map('absint', $ids) : [absint($ids)];
                $ids = array_filter($ids);
                if ($ids) {
                    $term[$tax] = $ids;
                }
            }
            $seo_status    = sanitize_key($_POST['seo_status'] ?? 'all');
            $seo_status    = in_array($seo_status, ['all', 'complete', 'incomplete', 'has_ai'], true) ? $seo_status : 'all';
            $missing_title = isset($_POST['gm2_missing_title']) ? '1' : '0';
            $missing_desc  = isset($_POST['gm2_missing_description']) ? '1' : '0';
            update_user_meta($user_id, 'gm2_bulk_ai_page_size', $page_size);
            update_user_meta($user_id, 'gm2_bulk_ai_status', $status);
            update_user_meta($user_id, 'gm2_bulk_ai_post_type', $post_type);
            update_user_meta($user_id, 'gm2_bulk_ai_term', $term);
            update_user_meta($user_id, 'gm2_bulk_ai_seo_status', $seo_status);
            update_option('gm2_bulk_ai_missing_title', $missing_title);
            update_option('gm2_bulk_ai_missing_description', $missing_desc);
        }

        $args = [
            'page_size'     => $page_size,
            'status'        => $status,
            'post_type'     => $post_type,
            'terms'         => $term,
            'seo_status'    => $seo_status,
            'missing_title' => $missing_title,
            'missing_desc'  => $missing_desc,
        ];
        $table = new Gm2_Bulk_Ai_List_Table($this, $args);
        $table->prepare_items();

        echo '<div class="wrap" id="gm2-bulk-ai">';
        echo '<h1>' . esc_html__( 'Bulk AI Review', 'gm2-wordpress-suite' ) . '</h1>';
        echo '<p><a href="' . esc_url( admin_url( 'admin-post.php?action=gm2_bulk_ai_export' ) ) . '" class="button">' . esc_html__( 'Export CSV', 'gm2-wordpress-suite' ) . '</a></p>';
        echo '<p class="description">' . esc_html__( 'Select posts, click', 'gm2-wordpress-suite' ) . ' <strong>' . esc_html__( 'Analyze Selected', 'gm2-wordpress-suite' ) . '</strong> ' . esc_html__( 'to generate suggestions. Review the suggestions and choose what to apply.', 'gm2-wordpress-suite' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=gm2-bulk-ai-review' ) ) . '">';
        wp_nonce_field('gm2_bulk_ai_settings');

        // Row 1: page size, post status, SEO status.
        echo '<p><label>' . esc_html__( 'Posts per page', 'gm2-wordpress-suite' ) . ' <input type="number" name="page_size" value="' . esc_attr($page_size) . '" min="1"></label> ';
        echo '<label>' . esc_html__( 'Status', 'gm2-wordpress-suite' ) . ' <select name="status">';
        echo '<option value="publish"' . selected($status, 'publish', false) . '>' . esc_html__( 'Published', 'gm2-wordpress-suite' ) . '</option>';
        echo '<option value="draft"' . selected($status, 'draft', false) . '>' . esc_html__( 'Draft', 'gm2-wordpress-suite' ) . '</option>';
        echo '</select></label> ';
        echo '<label>' . esc_html__( 'SEO Status', 'gm2-wordpress-suite' ) . ' <select name="seo_status">';
        echo '<option value="all"' . selected($seo_status, 'all', false) . '>' . esc_html__( 'All', 'gm2-wordpress-suite' ) . '</option>';
        echo '<option value="complete"' . selected($seo_status, 'complete', false) . '>' . esc_html__( 'Complete', 'gm2-wordpress-suite' ) . '</option>';
        echo '<option value="incomplete"' . selected($seo_status, 'incomplete', false) . '>' . esc_html__( 'Incomplete', 'gm2-wordpress-suite' ) . '</option>';
        echo '<option value="has_ai"' . selected($seo_status, 'has_ai', false) . '>' . esc_html__( 'AI Suggestions', 'gm2-wordpress-suite' ) . '</option>';
        echo '</select></label></p>';

        // Row 2: post type and taxonomy filters.
        echo '<p><label>' . esc_html__( 'Post Type', 'gm2-wordpress-suite' ) . ' <select name="gm2_post_type">';
        echo '<option value="all"' . selected($post_type, 'all', false) . '>' . esc_html__( 'All', 'gm2-wordpress-suite' ) . '</option>';
        foreach ($this->get_supported_post_types() as $pt) {
            $obj  = get_post_type_object($pt);
            $name = $obj ? $obj->labels->singular_name : $pt;
            echo '<option value="' . esc_attr($pt) . '"' . selected($post_type, $pt, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select></label> ';
        foreach ($this->get_supported_taxonomies() as $tax) {
            $tax_obj = get_taxonomy($tax);
            if ('category' === $tax) {
                $label = __('Post Categories', 'gm2-wordpress-suite');
            } elseif ('product_cat' === $tax) {
                $label = __('Product Categories', 'gm2-wordpress-suite');
            } else {
                $label = $tax_obj ? $tax_obj->labels->name : $tax;
            }
            echo '<label>' . esc_html($label) . ' <select name="gm2_term[' . esc_attr($tax) . '][]" multiple size="5">';
            $selected_terms = $term[$tax] ?? [];
            $none_selected  = empty($selected_terms) ? 'selected="selected"' : '';
            echo '<option value="" ' . $none_selected . '>' . esc_html__( 'All', 'gm2-wordpress-suite' ) . '</option>';
            $dropdown_terms = get_terms([
                'taxonomy'   => $tax,
                'hide_empty' => false,
            ]);
            foreach ($dropdown_terms as $t) {
                $sel = in_array($t->term_id, $selected_terms, true) ? 'selected="selected"' : '';
                echo '<option value="' . esc_attr($t->term_id) . '" ' . $sel . '>' . esc_html($t->name) . '</option>';
            }
            echo '</select></label> ';
        }
        echo '</p>';

        // Row 3: missing metadata filters and save button.
        echo '<p><label><input type="checkbox" name="gm2_missing_title" value="1" ' . checked($missing_title, '1', false) . '> ' . esc_html__( 'Only posts missing SEO Title', 'gm2-wordpress-suite' ) . '</label> ';
        echo '<label><input type="checkbox" name="gm2_missing_description" value="1" ' . checked($missing_desc, '1', false) . '> ' . esc_html__( 'Only posts missing Description', 'gm2-wordpress-suite' ) . '</label> ';
        echo '&nbsp;&nbsp;';
        submit_button( esc_html__( 'Save', 'gm2-wordpress-suite' ), 'secondary', 'gm2_bulk_ai_save', false );
        echo '</p></form>';

        $buttons = '<button type="button" class="button gm2-bulk-analyze" aria-label="' . esc_attr__( 'Analyze Selected', 'gm2-wordpress-suite' ) . '">' . esc_html__( 'Analyze Selected', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button gm2-bulk-cancel">' . esc_html__( 'Cancel', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button gm2-bulk-select-filtered">' . esc_html__( 'Select All', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button gm2-bulk-select-analyzed" data-select="' . esc_attr__( 'Select Analyzed', 'gm2-wordpress-suite' ) . '" data-unselect="' . esc_attr__( 'Unselect Analyzed', 'gm2-wordpress-suite' ) . '">' . esc_html__( 'Select Analyzed', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button gm2-bulk-apply-all">' . esc_html__( 'Apply All', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button gm2-bulk-reset-all">' . esc_html__( 'Reset All', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button gm2-bulk-reset-selected">' . esc_html__( 'Reset Selected', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button gm2-bulk-reset-ai">' . esc_html__( 'Reset AI Suggestion', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button gm2-bulk-schedule">' . esc_html__( 'Schedule Batch', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button gm2-bulk-cancel-batch">' . esc_html__( 'Cancel Batch', 'gm2-wordpress-suite' ) . '</button>';

        // Row 4 (top action buttons).
        echo '<p class="gm2-bulk-actions">' . $buttons . '</p>';
        echo '<p><progress id="gm2-bulk-progress-bar-top" class="gm2-bulk-progress-bar" value="0" max="100" style="width:100%;display:none" role="progressbar" aria-live="polite"></progress></p>';
        echo '<p id="gm2-bulk-progress-top" class="gm2-bulk-progress"></p>';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="gm2-bulk-ai-review" />';
        $table->search_box( esc_html__( 'Search Title', 'gm2-wordpress-suite' ), 'gm2-bulk-search' );
        $table->display();
        echo '</form>';
        // Bottom action buttons.
        echo '<p class="gm2-bulk-actions">' . $buttons . ' <span id="gm2-bulk-apply-msg"></span></p>';
        echo '<p><progress id="gm2-bulk-progress-bar" class="gm2-bulk-progress-bar" value="0" max="100" style="width:100%;display:none" role="progressbar" aria-live="polite"></progress></p>';
        echo '<p id="gm2-bulk-progress" class="gm2-bulk-progress"></p>';
        echo '</div>';
    }

    private function render_bulk_ai_result($data, $post_id, $has_prev = false) {
        $html        = '';
        $suggestions = '';

        if (!empty($data['seo_title'])) {
            $suggestions .= '<p><label><input type="checkbox" class="gm2-apply" data-field="seo_title" data-value="' . esc_attr($data['seo_title']) . '"> ' . esc_html($data['seo_title']) . '</label></p>';
        }
        if (!empty($data['description'])) {
            $suggestions .= '<p><label><input type="checkbox" class="gm2-apply" data-field="seo_description" data-value="' . esc_attr($data['description']) . '"> ' . esc_html($data['description']) . '</label></p>';
        }
        if (!empty($data['slug'])) {
            $suggestions .= '<p><label><input type="checkbox" class="gm2-apply" data-field="slug" data-value="' . esc_attr($data['slug']) . '"> ' . esc_html__( 'Slug', 'gm2-wordpress-suite' ) . ': ' . esc_html($data['slug']) . '</label></p>';
        }
        if (!empty($data['page_name'])) {
            $suggestions .= '<p><label><input type="checkbox" class="gm2-apply" data-field="title" data-value="' . esc_attr($data['page_name']) . '"> ' . esc_html__( 'Title', 'gm2-wordpress-suite' ) . ': ' . esc_html($data['page_name']) . '</label></p>';
        }

        if ($suggestions !== '' || $has_prev) {
            if ($suggestions !== '') {
                $html .= '<p><label><input type="checkbox" class="gm2-row-select-all"> ' . esc_html__( 'Select all', 'gm2-wordpress-suite' ) . '</label></p>';
                $html .= $suggestions;
            }
            $html .= '<p><button class="button gm2-apply-btn" data-id="' . intval($post_id) . '" aria-label="' . esc_attr__( 'Apply', 'gm2-wordpress-suite' ) . '">' . esc_html__( 'Apply', 'gm2-wordpress-suite' ) . '</button> ';
            $html .= '<button class="button gm2-refresh-btn" data-id="' . intval($post_id) . '" aria-label="' . esc_attr__( 'Refresh', 'gm2-wordpress-suite' ) . '">' . esc_html__( 'Refresh', 'gm2-wordpress-suite' ) . '</button> ';
            $html .= '<button class="button gm2-clear-btn" data-id="' . intval($post_id) . '" aria-label="' . esc_attr__( 'Clear', 'gm2-wordpress-suite' ) . '">' . esc_html__( 'Clear', 'gm2-wordpress-suite' ) . '</button>';
            if ($has_prev) {
                $html .= ' <button class="button gm2-undo-btn" data-id="' . intval($post_id) . '" aria-label="' . esc_attr__( 'Undo', 'gm2-wordpress-suite' ) . '">' . esc_html__( 'Undo', 'gm2-wordpress-suite' ) . '</button>';
            }
            $html .= '</p>';
        }

        return $html;
    }

    public function display_bulk_ai_tax_page() {
        $cap = apply_filters('gm2_bulk_ai_tax_capability', 'manage_categories');
        if (!current_user_can($cap)) {
            esc_html_e( 'Permission denied', 'gm2-wordpress-suite' );
            return;
        }
        $user_id       = get_current_user_id();
        $page_size     = max(1, absint(get_user_meta($user_id, 'gm2_bulk_ai_tax_page_size', true) ?: 50));
        $status        = get_user_meta($user_id, 'gm2_bulk_ai_tax_status', true) ?: 'publish';
        $taxonomy      = get_user_meta($user_id, 'gm2_bulk_ai_tax_taxonomy', true) ?: 'all';
        $search        = get_option('gm2_bulk_ai_tax_search', '');
        $missing_title = get_option('gm2_bulk_ai_tax_missing_title', '0');
        $missing_desc  = get_option('gm2_bulk_ai_tax_missing_description', '0');
        $seo_status    = get_user_meta($user_id, 'gm2_bulk_ai_tax_seo_status', true) ?: 'all';

        if (isset($_POST['gm2_bulk_ai_tax_save']) && check_admin_referer('gm2_bulk_ai_tax_settings')) {
            $page_size     = max(1, absint($_POST['page_size'] ?? 50));
            $status        = sanitize_key($_POST['gm2_tax_status'] ?? 'publish');
            $taxonomy      = sanitize_key($_POST['gm2_taxonomy'] ?? 'all');
            $search        = sanitize_text_field($_POST['gm2_tax_search'] ?? '');
            $seo_status    = sanitize_key($_POST['gm2_tax_seo_status'] ?? 'all');
            $seo_status    = in_array($seo_status, ['all', 'complete', 'incomplete', 'has_ai'], true) ? $seo_status : 'all';
            $missing_title = isset($_POST['gm2_bulk_ai_tax_missing_title']) ? '1' : '0';
            $missing_desc  = isset($_POST['gm2_bulk_ai_tax_missing_description']) ? '1' : '0';
            update_user_meta($user_id, 'gm2_bulk_ai_tax_page_size', $page_size);
            update_user_meta($user_id, 'gm2_bulk_ai_tax_status', $status);
            update_user_meta($user_id, 'gm2_bulk_ai_tax_taxonomy', $taxonomy);
            update_user_meta($user_id, 'gm2_bulk_ai_tax_seo_status', $seo_status);
            update_option('gm2_bulk_ai_tax_search', $search);
            update_option('gm2_bulk_ai_tax_missing_title', $missing_title);
            update_option('gm2_bulk_ai_tax_missing_description', $missing_desc);
        }

        $paged = isset($_REQUEST['paged']) ? max(1, absint($_REQUEST['paged'])) : 1;
        $_REQUEST['paged'] = $paged;

        $args = [
            'page_size'            => $page_size,
            'status'               => $status,
            'taxonomy'             => $taxonomy,
            'search'               => $search,
            'seo_status'           => $seo_status,
            'missing_title'        => $missing_title,
            'missing_description'  => $missing_desc,
        ];
        $table = new Gm2_Bulk_Ai_Tax_List_Table($this, $args);
        $table->prepare_items();

        echo '<div class="wrap" id="gm2-bulk-ai-tax">';
        echo '<h1>' . esc_html__( 'Bulk AI Taxonomies', 'gm2-wordpress-suite' ) . '</h1>';
        echo '<p><a href="' . esc_url( admin_url( 'admin-post.php?action=gm2_bulk_ai_tax_export' ) ) . '" class="button">' . esc_html__( 'Export CSV', 'gm2-wordpress-suite' ) . '</a></p>';
        echo '<form method="post" action="' . esc_url( admin_url('admin.php?page=gm2-bulk-ai-taxonomies') ) . '">';
        wp_nonce_field('gm2_bulk_ai_tax_settings');
        // Row 1: page size, status, SEO status.
        echo '<p><label>' . esc_html__( 'Terms per page', 'gm2-wordpress-suite' ) . ' <input type="number" name="page_size" value="' . esc_attr($page_size) . '" min="1"></label> ';
        echo '<label>' . esc_html__( 'Status', 'gm2-wordpress-suite' ) . ' <select name="gm2_tax_status">';
        echo '<option value="publish"' . selected($status, 'publish', false) . '>' . esc_html__( 'Published', 'gm2-wordpress-suite' ) . '</option>';
        echo '<option value="draft"' . selected($status, 'draft', false) . '>' . esc_html__( 'Draft', 'gm2-wordpress-suite' ) . '</option>';
        echo '</select></label> ';
        echo '<label>' . esc_html__( 'SEO Status', 'gm2-wordpress-suite' ) . ' <select name="gm2_tax_seo_status">';
        echo '<option value="all"' . selected($seo_status, 'all', false) . '>' . esc_html__( 'All', 'gm2-wordpress-suite' ) . '</option>';
        echo '<option value="complete"' . selected($seo_status, 'complete', false) . '>' . esc_html__( 'Complete', 'gm2-wordpress-suite' ) . '</option>';
        echo '<option value="incomplete"' . selected($seo_status, 'incomplete', false) . '>' . esc_html__( 'Incomplete', 'gm2-wordpress-suite' ) . '</option>';
        echo '<option value="has_ai"' . selected($seo_status, 'has_ai', false) . '>' . esc_html__( 'AI Suggestions', 'gm2-wordpress-suite' ) . '</option>';
        echo '</select></label></p>';
        // Row 2: taxonomy, search, missing metadata filters.
        echo '<p><label>' . esc_html__( 'Taxonomy', 'gm2-wordpress-suite' ) . ' <select name="gm2_taxonomy">';
        echo '<option value="all"' . selected($taxonomy, 'all', false) . '>' . esc_html__( 'All', 'gm2-wordpress-suite' ) . '</option>';
        foreach ($this->get_supported_taxonomies() as $tax) {
            $obj = get_taxonomy($tax);
            $name = $obj ? $obj->labels->singular_name : $tax;
            if ('category' === $tax) {
                $name = __('Post Category', 'gm2-wordpress-suite');
            } elseif ('product_cat' === $tax) {
                $name = __('Product Category', 'gm2-wordpress-suite');
            }
            echo '<option value="' . esc_attr($tax) . '"' . selected($taxonomy, $tax, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select></label> ';
        echo '<label>' . esc_html__( 'Search', 'gm2-wordpress-suite' ) . ' <input type="text" name="gm2_tax_search" value="' . esc_attr($search) . '"></label> ';
        echo '<label><input type="checkbox" name="gm2_bulk_ai_tax_missing_title" value="1" ' . checked($missing_title, '1', false) . '> ' . esc_html__( 'Only terms missing SEO Title', 'gm2-wordpress-suite' ) . '</label> ';
        echo '<label><input type="checkbox" name="gm2_bulk_ai_tax_missing_description" value="1" ' . checked($missing_desc, '1', false) . '> ' . esc_html__( 'Only terms missing Description', 'gm2-wordpress-suite' ) . '</label> ';
        submit_button( esc_html__( 'Save', 'gm2-wordpress-suite' ), 'secondary', 'gm2_bulk_ai_tax_save', false );
        echo '</p></form>';

        $buttons = '<button type="button" class="button" id="gm2-bulk-term-analyze">' . esc_html__( 'Analyze Selected', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button" id="gm2-bulk-term-desc">' . esc_html__( 'Generate Descriptions', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button" id="gm2-bulk-term-cancel">' . esc_html__( 'Cancel', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button gm2-bulk-term-select-filtered" id="gm2-bulk-term-select-filtered">' . esc_html__( 'Select All', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button" id="gm2-bulk-term-select-analyzed" data-select-label="' . esc_attr__( 'Select Analyzed', 'gm2-wordpress-suite' ) . '" data-unselect-label="' . esc_attr__( 'Unselect Analyzed', 'gm2-wordpress-suite' ) . '">' . esc_html__( 'Select Analyzed', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button" id="gm2-bulk-term-apply-all">' . esc_html__( 'Apply All', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button" id="gm2-bulk-term-reset-all">' . esc_html__( 'Reset All', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button" id="gm2-bulk-term-reset-selected">' . esc_html__( 'Reset Selected', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button" id="gm2-bulk-term-reset-ai">' . esc_html__( 'Reset AI Suggestion', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button" id="gm2-bulk-term-schedule">' . esc_html__( 'Schedule Batch', 'gm2-wordpress-suite' ) . '</button> '
            . '<button type="button" class="button" id="gm2-bulk-term-cancel-batch">' . esc_html__( 'Cancel Batch', 'gm2-wordpress-suite' ) . '</button>';

        // Top action buttons.
        echo '<p class="gm2-bulk-actions">' . $buttons . '</p>';

        // Progress bar above the table.
        echo '<p><progress class="gm2-bulk-term-progress-bar" value="0" max="100" style="width:100%;display:none" role="progressbar" aria-live="polite"></progress></p>';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="gm2-bulk-ai-taxonomies" />';
        $table->display();
        echo '</form>';

        // Bottom action buttons.
        echo '<p class="gm2-bulk-actions">' . $buttons . '</p>';
        echo '<p id="gm2-bulk-term-msg"></p>';
        echo '<p><progress class="gm2-bulk-term-progress-bar" value="0" max="100" style="width:100%;display:none" role="progressbar" aria-live="polite"></progress></p>';
        echo '</div>';
    }

    public function handle_bulk_ai_export() {
        if (!current_user_can('edit_posts')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        $user_id       = get_current_user_id();
        $page_size     = max(1, absint(get_user_meta($user_id, 'gm2_bulk_ai_page_size', true) ?: 10));
        $status        = get_user_meta($user_id, 'gm2_bulk_ai_status', true) ?: 'publish';
        $post_type     = get_user_meta($user_id, 'gm2_bulk_ai_post_type', true) ?: 'all';
        $term_raw      = get_user_meta($user_id, 'gm2_bulk_ai_term', true);
        if (is_array($term_raw)) {
            $term = $term_raw;
        } elseif (is_string($term_raw) && $term_raw !== '') {
            $term = [];
            foreach (array_map('trim', explode(',', $term_raw)) as $pair) {
                if (strpos($pair, ':') === false) {
                    continue;
                }
                list($tax, $id) = explode(':', $pair);
                $tax = sanitize_key($tax);
                $term[$tax][] = absint($id);
            }
        } else {
            $term = [];
        }
        $seo_status    = get_user_meta($user_id, 'gm2_bulk_ai_seo_status', true) ?: 'all';
        $missing_title = get_option('gm2_bulk_ai_missing_title', '0');
        $missing_desc  = get_option('gm2_bulk_ai_missing_description', '0');
        $search_title  = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';

        $types = $this->get_supported_post_types();
        if ($post_type !== 'all' && in_array($post_type, $types, true)) {
            $types = [$post_type];
        }
        $args = [
            'post_type'      => $types,
            'post_status'    => $status,
            'posts_per_page' => -1,
        ];
        if ($search_title !== '') {
            $args['s'] = $search_title;
        }
        if ($term) {
            $taxonomies = $this->get_supported_taxonomies();
            $tax_query  = [];
            foreach ($term as $tax => $ids) {
                if (!in_array($tax, $taxonomies, true)) {
                    continue;
                }
                $ids = array_filter(array_map('absint', (array) $ids));
                if ($ids) {
                    $tax_query[] = [
                        'taxonomy' => $tax,
                        'field'    => 'term_id',
                        'terms'    => $ids,
                    ];
                }
            }
            if ($tax_query) {
                if (count($tax_query) > 1) {
                    $tax_query = array_merge(['relation' => 'AND'], $tax_query);
                }
                $args['tax_query'] = $tax_query;
            }
        }

        $meta_query = [];
        if ($missing_title === '1') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
            ];
        }
        if ($missing_desc === '1') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
            ];
        }
        if ($seo_status === 'complete') {
            $meta_query[] = [ 'key' => '_gm2_title', 'value' => '', 'compare' => '!=' ];
            $meta_query[] = [ 'key' => '_gm2_description', 'value' => '', 'compare' => '!=' ];
        } elseif ($seo_status === 'incomplete') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
                [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
            ];
        } elseif ($seo_status === 'has_ai') {
            $meta_query[] = [ 'key' => '_gm2_ai_research', 'value' => '', 'compare' => '!=' ];
        }
        if ($meta_query) {
            $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_query);
        }

        $query = new \WP_Query($args);

        $rows   = [ ['ID', 'Title', 'SEO Title', 'Description', 'Focus Keywords', 'Long Tail Keywords'] ];
        foreach ($query->posts as $post) {
            $data = [];
            $stored = get_post_meta($post->ID, '_gm2_ai_research', true);
            if ($stored) {
                $tmp = json_decode($stored, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                    $data = $tmp;
                }
            }
            $fk = isset($data['focus_keywords']) ? (is_array($data['focus_keywords']) ? implode(', ', $data['focus_keywords']) : $data['focus_keywords']) : '';
            $lt = isset($data['long_tail_keywords']) ? (is_array($data['long_tail_keywords']) ? implode(', ', $data['long_tail_keywords']) : $data['long_tail_keywords']) : '';
            $rows[] = [
                $post->ID,
                $post->post_title,
                $data['seo_title'] ?? '',
                $data['description'] ?? '',
                $fk,
                $lt,
            ];
        }

        \Gm2\Gm2_CSV_Helper::output($rows, 'gm2-bulk-ai.csv');

        if (defined('GM2_TESTING') && GM2_TESTING) {
            return;
        }
        exit;
    }

    public function handle_bulk_ai_tax_export() {
        $cap = apply_filters('gm2_bulk_ai_tax_capability', 'manage_categories');
        if (!current_user_can($cap)) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        $user_id       = get_current_user_id();
        $taxonomy      = get_user_meta($user_id, 'gm2_bulk_ai_tax_taxonomy', true) ?: 'all';
        $status        = get_user_meta($user_id, 'gm2_bulk_ai_tax_status', true) ?: 'publish';
        $search        = get_option('gm2_bulk_ai_tax_search', '');
        $missing_title = get_option('gm2_bulk_ai_tax_missing_title', '0');
        $missing_desc  = get_option('gm2_bulk_ai_tax_missing_description', '0');
        $seo_status    = get_user_meta($user_id, 'gm2_bulk_ai_tax_seo_status', true) ?: 'all';

        $tax_list = $this->get_supported_taxonomies();
        $tax_arg  = ($taxonomy === 'all') ? $tax_list : $taxonomy;

        $args = [
            'taxonomy'   => $tax_arg,
            'hide_empty' => false,
            'status'     => $status,
        ];
        if ($search !== '') {
            $args['search'] = $search;
        }

        $meta_query = [];
        if ($seo_status === 'complete') {
            $meta_query[] = [ 'key' => '_gm2_title', 'value' => '', 'compare' => '!=' ];
            $meta_query[] = [ 'key' => '_gm2_description', 'value' => '', 'compare' => '!=' ];
        } elseif ($seo_status === 'incomplete') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
                [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
            ];
        } elseif ($seo_status === 'has_ai') {
            $meta_query[] = [ 'key' => '_gm2_ai_research', 'value' => '', 'compare' => '!=' ];
        }
        if ($missing_title === '1') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
            ];
        }
        if ($missing_desc === '1') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
            ];
        }
        if ($meta_query) {
            $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_query);
        }

        $query = new \WP_Term_Query($args);

        $rows = [ ['term_id', 'name', 'seo_title', 'description', 'taxonomy'] ];
        foreach ($query->terms as $term) {
            $rows[] = [
                $term->term_id,
                $term->name,
                get_term_meta($term->term_id, '_gm2_title', true),
                get_term_meta($term->term_id, '_gm2_description', true),
                $term->taxonomy,
            ];
        }

        \Gm2\Gm2_CSV_Helper::output($rows, 'gm2-bulk-ai-tax.csv');

        if (defined('GM2_TESTING') && GM2_TESTING) {
            return;
        }
        exit;
    }

    public function display_google_connect_page() {
        $oauth = apply_filters('gm2_google_oauth_instance', new Gm2_Google_OAuth());

        $notice     = '';
        $properties = [];
        $accounts   = [];
        $help       = '<ul>'
            . '<li>' . esc_html__( 'Enable the Analytics Admin, Google Analytics (v3) for UA properties, Search Console, and Google Ads APIs for your OAuth client.', 'gm2-wordpress-suite' ) . '</li>'
            . '<li>' . esc_html__( 'Verify the connected Google account has access to the target properties and Ads accounts. The OAuth client may be created under a different Google account.', 'gm2-wordpress-suite' ) . '</li>'
            . '<li>' . esc_html__( 'Reconnect after updating permissions.', 'gm2-wordpress-suite' ) . '</li>'
            . '</ul>';

        if (isset($_POST['gm2_google_disconnect']) && wp_verify_nonce($_POST['gm2_google_disconnect'], 'gm2_google_disconnect')) {
            $oauth->disconnect();
            $notice = '<div class="updated notice"><p>' . esc_html__('Google account disconnected.', 'gm2-wordpress-suite') . '</p></div>';
        }

        if (isset($_POST['gm2_ga_property_nonce']) && wp_verify_nonce($_POST['gm2_ga_property_nonce'], 'gm2_ga_property_save')) {
            $prop = sanitize_text_field(wp_unslash($_POST['gm2_ga_property'] ?? ''));
            if ($prop !== '') {
                update_option('gm2_ga_measurement_id', $prop);
                $notice = '<div class="updated notice"><p>' . esc_html__('Analytics property saved.', 'gm2-wordpress-suite') . '</p></div>';
            }
        }

        if (isset($_POST['gm2_gads_account_nonce']) && wp_verify_nonce($_POST['gm2_gads_account_nonce'], 'gm2_gads_account_save')) {
            $acct = sanitize_text_field(wp_unslash($_POST['gm2_gads_account'] ?? ''));
            if ($acct !== '') {
                update_option('gm2_gads_customer_id', $acct);
                $notice = '<div class="updated notice"><p>' . esc_html__('Ads account saved.', 'gm2-wordpress-suite') . '</p></div>';
            }
        }

        if (isset($_GET['code'])) {
            $code = sanitize_text_field(wp_unslash($_GET['code']));
            if (isset($_GET['state'])) {
                $_GET['state'] = sanitize_text_field(wp_unslash($_GET['state']));
            }
            $result = $oauth->handle_callback($code);
            if (is_wp_error($result)) {
                $notice = '<div class="error notice"><p>' . esc_html($result->get_error_message()) . '</p>';
                if ('invalid_state' === $result->get_error_code()) {
                    $notice .= $help;
                }
                $notice .= '</div>';
            } elseif ($result) {
                $notice     = '<div class="updated notice"><p>' . esc_html__('Google account connected.', 'gm2-wordpress-suite') . '</p></div>';
                $properties = $oauth->list_analytics_properties();
                if (is_wp_error($properties)) {
                    $notice .= '<div class="error notice"><p>' . esc_html($properties->get_error_message()) . '</p>';
                    $data = $properties->get_error_data();
                    if (!empty($data['body'])) {
                        $notice .= '<pre>' . esc_html(trim($data['body'])) . '</pre>';
                    }
                    $notice .= $help . '</div>';
                } elseif (!empty($properties) && '' === get_option('gm2_ga_measurement_id', '')) {
                    update_option('gm2_ga_measurement_id', is_array($properties) ? array_key_first($properties) : '');
                }
                $accounts = $oauth->list_ads_accounts();
                if (is_wp_error($accounts)) {
                    $msg = '<div class="error notice"><p>' . esc_html($accounts->get_error_message()) . '</p>';
                    $data = $accounts->get_error_data();
                    if (!empty($data['body'])) {
                        $msg .= '<pre>' . esc_html(trim($data['body'])) . '</pre>';
                    }
                    if ('missing_developer_token' === $accounts->get_error_code()) {
                        $msg .= '<p>' . esc_html__( 'Sign in at Google Ads and open Tools & Settings → Setup → API Center (manager account required). Copy your Developer token and enter it in the Google Ads Developer Token field on the SEO settings page.', 'gm2-wordpress-suite' ) . '</p>';
                    } else {
                        $msg .= $help;
                    }
                    $msg .= '</div>';
                    $notice .= $msg;
                } elseif (!empty($accounts) && '' === get_option('gm2_gads_customer_id', '')) {
                    update_option('gm2_gads_customer_id', is_array($accounts) ? array_key_first($accounts) : '');
                }
            }
        }

        if ($oauth->is_connected()) {
            if (!$properties) {
                $properties = $oauth->list_analytics_properties();
            }
            if (is_wp_error($properties)) {
                $notice .= '<div class="error notice"><p>' . esc_html($properties->get_error_message()) . '</p>';
                $data = $properties->get_error_data();
                if (!empty($data['body'])) {
                    $notice .= '<pre>' . esc_html(trim($data['body'])) . '</pre>';
                }
                $notice .= $help . '</div>';
            }
            if (!$accounts) {
                $accounts = $oauth->list_ads_accounts();
            }
            if (is_wp_error($accounts)) {
                $msg = '<div class="error notice"><p>' . esc_html($accounts->get_error_message()) . '</p>';
                $data = $accounts->get_error_data();
                if (!empty($data['body'])) {
                    $msg .= '<pre>' . esc_html(trim($data['body'])) . '</pre>';
                }
                if ('missing_developer_token' === $accounts->get_error_code()) {
                    $msg .= '<p>' . esc_html__( 'Sign in at Google Ads and open Tools & Settings → Setup → API Center (manager account required). Copy your Developer token and enter it in the Google Ads Developer Token field on the SEO settings page.', 'gm2-wordpress-suite' ) . '</p>';
                } else {
                    $msg .= $help;
                }
                $msg .= '</div>';
                $notice .= $msg;
            }

            if (!is_wp_error($properties) && empty($properties)) {
                $notice .= '<div class="error notice"><p>' . esc_html__('No Analytics properties found.', 'gm2-wordpress-suite') . '</p>' . $help . '</div>';
            }
            if (!is_wp_error($accounts) && empty($accounts)) {
                $notice .= '<div class="error notice"><p>' . esc_html__('No Ads accounts found.', 'gm2-wordpress-suite') . '</p>' . $help . '</div>';
            }
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Connect Google Account', 'gm2-wordpress-suite' ) . '</h1>';
        $setup_url = admin_url( 'admin.php?page=gm2-google-oauth-setup' );
        echo '<p><a href="' . esc_url( $setup_url ) . '">' . esc_html__( 'Google OAuth Setup', 'gm2-wordpress-suite' ) . '</a></p>';
        echo $notice;
        if (!$oauth->is_connected()) {
            $url = esc_url($oauth->get_auth_url());
            echo '<a href="' . $url . '" class="button button-primary">' . esc_html__( 'Connect Google', 'gm2-wordpress-suite' ) . '</a>';
        } else {
            echo '<p>' . esc_html__( 'Google account connected.', 'gm2-wordpress-suite' ) . '</p>';
            if (is_array($properties) && $properties) {
                $current = get_option('gm2_ga_measurement_id', is_array($properties) ? array_key_first($properties) : '');
                echo '<form method="post">';
                wp_nonce_field('gm2_ga_property_save', 'gm2_ga_property_nonce');
                echo '<p><label for="gm2_ga_property">' . esc_html__('Select Analytics Property', 'gm2-wordpress-suite') . '</label> ';
                echo '<select id="gm2_ga_property" name="gm2_ga_property">';
                foreach ($properties as $pid => $pname) {
                    $label = $pname ? $pname . ' (' . $pid . ')' : $pid;
                    echo '<option value="' . esc_attr($pid) . '" ' . selected($current, $pid, false) . '>' . esc_html($label) . '</option>';
                }
                echo '</select></p>';
                echo '<p class="description">Measurement IDs are fetched automatically from your connected Google account.</p>';
                submit_button(__('Save Property', 'gm2-wordpress-suite'));
                echo '</form>';
            }
            if (is_array($accounts) && $accounts) {
                $current = get_option('gm2_gads_customer_id', is_array($accounts) ? array_key_first($accounts) : '');
                echo '<form method="post">';
                wp_nonce_field('gm2_gads_account_save', 'gm2_gads_account_nonce');
                echo '<p><label for="gm2_gads_account">' . esc_html__('Select Ads Account', 'gm2-wordpress-suite') . '</label> ';
                echo '<select id="gm2_gads_account" name="gm2_gads_account">';
                foreach ($accounts as $aid => $alabel) {
                    echo '<option value="' . esc_attr($aid) . '" ' . selected($current, $aid, false) . '>' . esc_html($alabel) . '</option>';
                }
                echo '</select></p>';
                  echo '<p class="description">' . esc_html__( 'Ads customer IDs are fetched automatically from your connected Google account.', 'gm2-wordpress-suite' ) . '</p>';
                submit_button(__('Save Ads Account', 'gm2-wordpress-suite'));
                echo '</form>';
            }

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('gm2_google_test', 'gm2_google_test_nonce');
            echo '<input type="hidden" name="action" value="gm2_google_test" />';
            submit_button(esc_html__('Test Connection', 'gm2-wordpress-suite'));
            echo '</form>';

            echo '<form method="post">';
            wp_nonce_field('gm2_google_disconnect', 'gm2_google_disconnect');
            submit_button(__('Disconnect Google', 'gm2-wordpress-suite'), 'delete');
            echo '</form>';
        }
        echo '</div>';
    }


    public function register_meta_boxes() {
        foreach ($this->get_supported_post_types() as $type) {
            add_meta_box(
                'gm2_seo_tabs',
                'SEO',
                [$this, 'render_seo_tabs_meta_box'],
                $type,
                'normal',
                'high'
            );

            add_meta_box(
                'aeseo_lcp_overrides',
                __( 'LCP Overrides', 'gm2-wordpress-suite' ),
                [ $this, 'render_lcp_overrides_meta_box' ],
                $type,
                'side',
                'default'
            );
        }
    }


    public function render_lcp_overrides_meta_box($post) {
        $post_id = ($post instanceof \WP_Post) ? $post->ID : 0;
        $override = $post_id ? get_post_meta($post_id, '_aeseo_lcp_override', true) : '';
        $disable  = $post_id ? get_post_meta($post_id, '_aeseo_lcp_disable', true) : '';
        ?>
        <p>
            <label for="aeseo_lcp_override"><?php esc_html_e('Override LCP image (URL or attachment ID):', 'gm2-wordpress-suite'); ?></label>
            <input type="text" class="widefat" name="aeseo_lcp_override" id="aeseo_lcp_override" value="<?php echo esc_attr($override); ?>" />
        </p>
        <p>
            <label>
                <input type="checkbox" name="aeseo_lcp_disable" <?php checked($disable, '1'); ?> />
                <?php esc_html_e('Disable LCP optimization on this page.', 'gm2-wordpress-suite'); ?>
            </label>
        </p>
        <?php
        wp_nonce_field('aeseo_lcp_meta', 'aeseo_lcp_meta_nonce');
    }


    public function render_taxonomy_meta_box($term) {
        $title               = '';
        $description         = '';
        $noindex             = '';
        $nofollow            = '';
        $canonical           = '';
        $focus_keywords      = '';
        $long_tail_keywords  = '';
        $search_intent       = '';
        $focus_limit         = '';
        $number_of_words     = '';
        $improve_readability = '1';
        $max_snippet         = '';
        $max_image_preview   = '';
        $max_video_preview   = '';
        $schema_type        = '';
        $schema_brand       = '';
        $schema_rating      = '';
        $taxonomy       = is_object($term) ? $term->taxonomy : (string) $term;

        if (is_object($term)) {
            $title          = get_term_meta($term->term_id, '_gm2_title', true);
            $description    = get_term_meta($term->term_id, '_gm2_description', true);
            $noindex        = get_term_meta($term->term_id, '_gm2_noindex', true);
            $nofollow       = get_term_meta($term->term_id, '_gm2_nofollow', true);
            $canonical        = get_term_meta($term->term_id, '_gm2_canonical', true);
            $focus_keywords   = get_term_meta($term->term_id, '_gm2_focus_keywords', true);
            $long_tail_keywords = get_term_meta($term->term_id, '_gm2_long_tail_keywords', true);
            $search_intent    = get_term_meta($term->term_id, '_gm2_search_intent', true);
            $focus_limit      = get_term_meta($term->term_id, '_gm2_focus_keyword_limit', true);
            $number_of_words  = get_term_meta($term->term_id, '_gm2_number_of_words', true);
            $improve_readability = get_term_meta($term->term_id, '_gm2_improve_readability', true);
            $max_snippet      = get_term_meta($term->term_id, '_gm2_max_snippet', true);
            $max_image_preview = get_term_meta($term->term_id, '_gm2_max_image_preview', true);
            $max_video_preview = get_term_meta($term->term_id, '_gm2_max_video_preview', true);
            $schema_type    = get_term_meta($term->term_id, '_gm2_schema_type', true);
            $schema_brand   = get_term_meta($term->term_id, '_gm2_schema_brand', true);
            $schema_rating  = get_term_meta($term->term_id, '_gm2_schema_rating', true);
        }

        if ($schema_type === '' && in_array($taxonomy, ['brand', 'product_brand'], true)) {
            $schema_type = 'brand';
        }

        wp_nonce_field('gm2_save_seo_meta', 'gm2_seo_nonce');

        $rules_option = get_option('gm2_content_rules', []);
        $rule_lines   = [];
        if (isset($rules_option['tax_' . $taxonomy]) && is_array($rules_option['tax_' . $taxonomy])) {
            foreach ($rules_option['tax_' . $taxonomy] as $txt) {
                $txt        = $this->flatten_rule_value($txt);
                $rule_lines = array_merge($rule_lines, array_filter(array_map('trim', explode("\n", $txt))));
            }
        }
        if (!$rule_lines) {
            $rule_lines = [
                __( 'Title length between 30 and 60 characters', 'gm2-wordpress-suite' ),
                __( 'Description length between 50 and 160 characters', 'gm2-wordpress-suite' ),
                __( 'Description has at least 150 words', 'gm2-wordpress-suite' ),
            ];
        }

        $desc_warning = '';
        if (is_object($term)) {
            $count = str_word_count(wp_strip_all_tags($term->description));
            if ($count < 150) {
                $desc_warning = sprintf( __( 'Description has %d words; recommended minimum is 150.', 'gm2-wordpress-suite' ), $count );
            }
        }

        $wrapper_start = $wrapper_end = '';
        if (is_object($term)) {
            $wrapper_start = '<tr class="form-field"><th colspan="2">';
            $wrapper_end   = '</th></tr>';
        } else {
            $wrapper_start = '<div class="form-field">';
            $wrapper_end   = '</div>';
        }

        echo $wrapper_start;
        echo '<div class="gm2-seo-tabs">';
        echo '<nav class="gm2-nav-tabs" role="tablist">';
        echo '<a href="#" class="gm2-nav-tab active" role="tab" aria-controls="gm2-seo-settings" aria-selected="true" data-tab="gm2-seo-settings">' . esc_html__( 'SEO Settings', 'gm2-wordpress-suite' ) . '</a>';
        echo '<a href="#" class="gm2-nav-tab" role="tab" aria-controls="gm2-content-analysis" aria-selected="false" data-tab="gm2-content-analysis">' . esc_html__( 'Content Analysis', 'gm2-wordpress-suite' ) . '</a>';
        echo '<a href="#" class="gm2-nav-tab" role="tab" aria-controls="gm2-schema" aria-selected="false" data-tab="gm2-schema">' . esc_html__( 'Schema', 'gm2-wordpress-suite' ) . '</a>';
        echo '<a href="#" class="gm2-nav-tab" role="tab" aria-controls="gm2-ai-seo" aria-selected="false" data-tab="gm2-ai-seo">' . esc_html__( 'AI SEO', 'gm2-wordpress-suite' ) . '</a>';
        echo '</nav>';

        echo '<div id="gm2-seo-settings" class="gm2-tab-panel active" role="tabpanel">';
        echo '<p><label for="gm2_seo_title">' . esc_html__( 'SEO Title', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_seo_title" name="gm2_seo_title" value="' . esc_attr($title) . '" placeholder="' . esc_attr__( 'Best Product Ever | My Brand', 'gm2-wordpress-suite' ) . '" class="widefat" /> <span class="dashicons dashicons-info" title="' . esc_attr__( 'Include main keyword and brand', 'gm2-wordpress-suite' ) . '"></span></p>';
        echo '<p><label for="gm2_seo_description">' . esc_html__( 'SEO Description', 'gm2-wordpress-suite' ) . '</label>';
        echo '<textarea id="gm2_seo_description" name="gm2_seo_description" class="widefat" rows="3" placeholder="' . esc_attr__( 'One sentence summary shown in search results', 'gm2-wordpress-suite' ) . '">' . esc_textarea($description) . '</textarea> <span class="dashicons dashicons-info" title="' . esc_attr__( 'Keep under 160 characters', 'gm2-wordpress-suite' ) . '"></span></p>';

        echo '<p><label for="gm2_focus_keywords">' . esc_html__( 'Focus Keywords (comma separated)', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_focus_keywords" name="gm2_focus_keywords" value="' . esc_attr($focus_keywords) . '" placeholder="' . esc_attr__( 'keyword1, keyword2', 'gm2-wordpress-suite' ) . '" class="widefat" /> <span class="dashicons dashicons-info" title="' . esc_attr__( 'Separate with commas', 'gm2-wordpress-suite' ) . '"></span></p>';
        echo '<p><label for="gm2_long_tail_keywords">' . esc_html__( 'Long Tail Keywords (comma separated)', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_long_tail_keywords" name="gm2_long_tail_keywords" value="' . esc_attr($long_tail_keywords) . '" placeholder="' . esc_attr__( 'longer keyword phrase', 'gm2-wordpress-suite' ) . '" class="widefat" /> <span class="dashicons dashicons-info" title="' . esc_attr__( 'Lower volume phrases', 'gm2-wordpress-suite' ) . '"></span></p>';
        $search_intent = get_post_meta($post->ID, '_gm2_search_intent', true);
        $focus_limit   = get_post_meta($post->ID, '_gm2_focus_keyword_limit', true);
        $number_of_words = get_post_meta($post->ID, '_gm2_number_of_words', true);
        $improve_readability = get_post_meta($post->ID, '_gm2_improve_readability', true);
        echo '<p><label for="gm2_search_intent">' . esc_html__( 'Search Intent', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_search_intent" name="gm2_search_intent" value="' . esc_attr($search_intent) . '" class="widefat" /></p>';
        echo '<p><label for="gm2_focus_keyword_limit">' . esc_html__( 'Focus Keyword Limit', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="number" id="gm2_focus_keyword_limit" name="gm2_focus_keyword_limit" value="' . esc_attr($focus_limit) . '" class="small-text" min="1" /></p>';
        echo '<p><label for="gm2_number_of_words">' . esc_html__( 'Number of Words', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="number" id="gm2_number_of_words" name="gm2_number_of_words" value="' . esc_attr($number_of_words) . '" class="small-text" min="0" /></p>';
        echo '<p><label><input type="checkbox" id="gm2_improve_readability" name="gm2_improve_readability" value="1" ' . checked($improve_readability, '1', false) . '> ' . esc_html__( 'Improve Readability', 'gm2-wordpress-suite' ) . '</label></p>';
        echo '<p><label for="gm2_search_intent">' . esc_html__( 'Search Intent', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_search_intent" name="gm2_search_intent" value="' . esc_attr($search_intent) . '" class="widefat" /></p>';
        echo '<p><label for="gm2_focus_keyword_limit">' . esc_html__( 'Focus Keyword Limit', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="number" id="gm2_focus_keyword_limit" name="gm2_focus_keyword_limit" value="' . esc_attr($focus_limit) . '" class="small-text" min="1" /></p>';
        echo '<p><label for="gm2_number_of_words">' . esc_html__( 'Number of Words', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="number" id="gm2_number_of_words" name="gm2_number_of_words" value="' . esc_attr($number_of_words) . '" class="small-text" min="0" /></p>';
        echo '<p><label><input type="checkbox" id="gm2_improve_readability" name="gm2_improve_readability" value="1" ' . checked($improve_readability, '1', false) . '> ' . esc_html__( 'Improve Readability', 'gm2-wordpress-suite' ) . '</label></p>';
        echo '<p><label><input type="checkbox" name="gm2_noindex" value="1" ' . checked($noindex, '1', false) . '> ' . esc_html__( 'noindex', 'gm2-wordpress-suite' ) . '</label></p>';
        echo '<p><label><input type="checkbox" name="gm2_nofollow" value="1" ' . checked($nofollow, '1', false) . '> ' . esc_html__( 'nofollow', 'gm2-wordpress-suite' ) . '</label></p>';
        echo '<p><label for="gm2_canonical_url">' . esc_html__( 'Canonical URL', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="url" id="gm2_canonical_url" name="gm2_canonical_url" value="' . esc_attr($canonical) . '" placeholder="https://example.com/original-page" class="widefat" /> <span class="dashicons dashicons-info" title="' . esc_attr__( 'Point to the preferred URL', 'gm2-wordpress-suite' ) . '"></span></p>';

        echo '<p><label for="gm2_max_snippet">' . esc_html__( 'Max Snippet', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_max_snippet" name="gm2_max_snippet" value="' . esc_attr($max_snippet) . '" class="small-text" /></p>';
        echo '<p><label for="gm2_max_image_preview">' . esc_html__( 'Max Image Preview', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_max_image_preview" name="gm2_max_image_preview" value="' . esc_attr($max_image_preview) . '" class="small-text" /></p>';
        echo '<p><label for="gm2_max_video_preview">' . esc_html__( 'Max Video Preview', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_max_video_preview" name="gm2_max_video_preview" value="' . esc_attr($max_video_preview) . '" class="small-text" /></p>';



        $og_image = is_object($term) ? get_term_meta($term->term_id, '_gm2_og_image', true) : '';
        $og_image_url = $og_image ? wp_get_attachment_url($og_image) : '';
        echo '<p><label for="gm2_og_image">' . esc_html__( 'OG Image', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="hidden" id="gm2_og_image" name="gm2_og_image" value="' . esc_attr($og_image) . '" />';
        echo '<input type="button" class="button gm2-upload-image" data-target="gm2_og_image" value="' . esc_attr__( 'Select Image', 'gm2-wordpress-suite' ) . '" />';
        echo '<span class="gm2-image-preview">' . ($og_image_url ? '<img src="' . esc_url($og_image_url) . '" style="max-width:100%;height:auto;" />' : '') . '</span></p>';
        echo '</div>';

        echo '<div id="gm2-content-analysis" class="gm2-tab-panel" role="tabpanel">';
        if ($desc_warning) {
            echo '<p class="gm2-warning" style="color:#d63638;">' . esc_html($desc_warning) . '</p>';
        }
        echo '<ul class="gm2-analysis-rules">';
        $min_int = (int) get_option('gm2_min_internal_links', 1);
        $min_ext = (int) get_option('gm2_min_external_links', 1);
        foreach ($rule_lines as $text) {
            $key = sanitize_title($text);
            $disp = preg_replace('/Minimum X internal links/i', 'Minimum ' . $min_int . ' internal links', $text);
            $disp = preg_replace('/Minimum X external links/i', 'Minimum ' . $min_ext . ' external links', $disp);
            echo '<li data-key="' . esc_attr($key) . '"><span class="dashicons dashicons-no"></span> ' . esc_html($disp) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '<div id="gm2-schema" class="gm2-tab-panel" role="tabpanel">';
        echo '<p><label for="gm2_schema_type">' . esc_html__( 'Primary Schema Type', 'gm2-wordpress-suite' ) . '</label>';
        echo '<select id="gm2_schema_type" name="gm2_schema_type">';
        $opts = [
            ''         => __( 'Default', 'gm2-wordpress-suite' ),
            'article'  => __( 'Article', 'gm2-wordpress-suite' ),
            'product'  => __( 'Product', 'gm2-wordpress-suite' ),
            'webpage'  => __( 'Web Page', 'gm2-wordpress-suite' ),
            'brand'    => __( 'Brand', 'gm2-wordpress-suite' ),
        ];
        $custom = get_option('gm2_custom_schema', []);
        if (is_array($custom)) {
            foreach ($custom as $id => $tpl) {
                $label = is_array($tpl) && isset($tpl['label']) ? $tpl['label'] : $id;
                $opts[$id] = $label;
            }
        }
        foreach ($opts as $val => $label) {
            echo '<option value="' . esc_attr($val) . '"' . selected($schema_type, $val, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';
        echo '<p><label for="gm2_schema_brand">' . esc_html__( 'Brand Name', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_schema_brand" name="gm2_schema_brand" value="' . esc_attr($schema_brand) . '" class="widefat" /></p>';
        echo '<p><label for="gm2_schema_rating">' . esc_html__( 'Review Rating', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="number" step="0.1" min="0" max="5" id="gm2_schema_rating" name="gm2_schema_rating" value="' . esc_attr($schema_rating) . '" class="small-text" /></p>';
        if (is_object($term)) {
            $link = get_term_link($term);
            if (!is_wp_error($link)) {
                $rich_url = 'https://search.google.com/test/rich-results?url=' . rawurlencode($link);
            }
        }
        echo '<div id="gm2-schema-preview"></div>';
        if (!empty($rich_url)) {
            echo '<p><a id="gm2-rich-results-preview" class="button button-secondary" href="' . esc_url($rich_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Test in Google', 'gm2-wordpress-suite' ) . '</a></p>';
        }
        echo '<script type="text/html" id="tmpl-gm2-schema-card">';
        echo '<div class="gm2-schema-card">';
        echo '<# var title = data.name || data.headline; if ( title ) { #><div class="gm2-schema-card__title">{{ title }}</div><# } #>';
        echo '<# if ( data.description ) { #><div class="gm2-schema-card__desc">{{ data.description }}</div><# } #>';
        echo '<# if ( data.offers && data.offers.price ) { #><div class="gm2-schema-card__price">{{ data.offers.price }}</div><# } #>';
        echo '</div>';
        echo '</script>';
        echo '</div>';
        echo '<div id="gm2-ai-seo" class="gm2-tab-panel" role="tabpanel">';
        echo '<p><button type="button" class="button gm2-ai-research">' . esc_html__( 'AI Research', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '<div id="gm2-ai-results"></div>';
        echo '<p><button type="button" class="button gm2-ai-implement">' . esc_html__( 'Implement Selected', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '</div>';

        echo '</div>';
        echo $wrapper_end;
    }

    public function save_post_meta($post_id, $post = null) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST['gm2_seo_nonce']) || !wp_verify_nonce($_POST['gm2_seo_nonce'], 'gm2_save_seo_meta')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (!$post) {
            $post = get_post($post_id);
        }
        $title       = isset($_POST['gm2_seo_title']) ? sanitize_text_field($_POST['gm2_seo_title']) : '';
        $description = isset($_POST['gm2_seo_description']) ? sanitize_textarea_field($_POST['gm2_seo_description']) : '';
        if ($description === '' && $post) {
            $sanitized_content = wp_strip_all_tags($post->post_content);
            $snippet_content   = $this->safe_truncate($sanitized_content, 400);
            $prompt  = "Write a short SEO description for the following content:\n\n" . $snippet_content;
            $context = gm2_get_business_context_prompt();
            if ($context !== '') {
                $prompt = $context . "\n\n" . $prompt;
            }
            $resp = gm2_ai_send_prompt($prompt);
            if (!is_wp_error($resp) && $resp !== '') {
                $description = sanitize_textarea_field($resp);
            } else {
                $msg = is_wp_error($resp) ? $resp->get_error_message() : __( 'Empty response from AI', 'gm2-wordpress-suite' );
                self::add_notice(sprintf( __( 'AI description error: %s', 'gm2-wordpress-suite' ), $msg ));
            }
        }
        $noindex     = isset($_POST['gm2_noindex']) ? '1' : '0';
        $nofollow    = isset($_POST['gm2_nofollow']) ? '1' : '0';
        $canonical      = isset($_POST['gm2_canonical_url']) ? esc_url_raw($_POST['gm2_canonical_url']) : '';
        $focus_keywords   = isset($_POST['gm2_focus_keywords']) ? sanitize_text_field($_POST['gm2_focus_keywords']) : '';
        $long_tail_keywords = isset($_POST['gm2_long_tail_keywords']) ? sanitize_text_field($_POST['gm2_long_tail_keywords']) : '';
        $search_intent    = isset($_POST['gm2_search_intent']) ? sanitize_text_field($_POST['gm2_search_intent']) : '';
        $focus_limit      = isset($_POST['gm2_focus_keyword_limit']) ? absint($_POST['gm2_focus_keyword_limit']) : 0;
        $number_of_words  = isset($_POST['gm2_number_of_words']) ? absint($_POST['gm2_number_of_words']) : 0;
        $improve_readability = isset($_POST['gm2_improve_readability']) ? '1' : '0';
        $max_snippet      = isset($_POST['gm2_max_snippet']) ? sanitize_text_field($_POST['gm2_max_snippet']) : '';
        $max_image_preview = isset($_POST['gm2_max_image_preview']) ? sanitize_text_field($_POST['gm2_max_image_preview']) : '';
        $max_video_preview = isset($_POST['gm2_max_video_preview']) ? sanitize_text_field($_POST['gm2_max_video_preview']) : '';
        $og_image         = isset($_POST['gm2_og_image']) ? absint($_POST['gm2_og_image']) : 0;
        $schema_type      = isset($_POST['gm2_schema_type']) ? sanitize_text_field($_POST['gm2_schema_type']) : '';
        $schema_brand     = isset($_POST['gm2_schema_brand']) ? sanitize_text_field($_POST['gm2_schema_brand']) : '';
        if ($schema_brand === '') {
            $schema_brand = $this->infer_brand_name($post_id);
        }
        $schema_rating    = isset($_POST['gm2_schema_rating']) ? sanitize_text_field($_POST['gm2_schema_rating']) : '';
        $link_rel_data    = isset($_POST['gm2_link_rel']) ? wp_unslash($_POST['gm2_link_rel']) : '';
        if (!is_array(json_decode($link_rel_data, true)) && $link_rel_data !== '') {
            $link_rel_data = '';
        }
        if ($schema_type === '' && $post) {
            if ($post->post_type === 'product') {
                $schema_type = 'product';
            } elseif ($post->post_type === 'post') {
                $schema_type = 'article';
            } elseif ($post->post_type === 'page') {
                $schema_type = 'webpage';
            }
        }
        update_post_meta($post_id, '_gm2_title', $title);
        update_post_meta($post_id, '_gm2_description', $description);
        update_post_meta($post_id, '_gm2_noindex', $noindex);
        update_post_meta($post_id, '_gm2_nofollow', $nofollow);
        update_post_meta($post_id, '_gm2_canonical', $canonical);
        update_post_meta($post_id, '_gm2_focus_keywords', $focus_keywords);
        update_post_meta($post_id, '_gm2_long_tail_keywords', $long_tail_keywords);
        update_post_meta($post_id, '_gm2_search_intent', $search_intent);
        update_post_meta($post_id, '_gm2_focus_keyword_limit', $focus_limit);
        update_post_meta($post_id, '_gm2_number_of_words', $number_of_words);
        update_post_meta($post_id, '_gm2_improve_readability', $improve_readability);
        update_post_meta($post_id, '_gm2_max_snippet', $max_snippet);
        update_post_meta($post_id, '_gm2_max_image_preview', $max_image_preview);
        update_post_meta($post_id, '_gm2_max_video_preview', $max_video_preview);
        update_post_meta($post_id, '_gm2_og_image', $og_image);
        update_post_meta($post_id, '_gm2_link_rel', $link_rel_data);
        update_post_meta($post_id, '_gm2_schema_type', $schema_type);
        update_post_meta($post_id, '_gm2_schema_brand', $schema_brand);
        update_post_meta($post_id, '_gm2_schema_rating', $schema_rating);

        if (isset($_POST['aeseo_lcp_meta_nonce']) && wp_verify_nonce($_POST['aeseo_lcp_meta_nonce'], 'aeseo_lcp_meta')) {
            $raw_override = $_POST['aeseo_lcp_override'] ?? '';
            $override     = is_numeric($raw_override) ? absint($raw_override) : esc_url_raw($raw_override);
            update_post_meta($post_id, '_aeseo_lcp_override', $override);
            update_post_meta($post_id, '_aeseo_lcp_disable', isset($_POST['aeseo_lcp_disable']) ? '1' : '0');
            if ($override !== '') {
                wp_cache_delete('aeseo_lcp_candidate_' . $post_id, 'aeseo');
                wp_cache_delete('aeseo_lcp_override_' . $post_id, 'aeseo');
            }
        }
    }

    public function save_taxonomy_meta($term_id) {
        if (!isset($_POST['gm2_seo_nonce']) || !wp_verify_nonce($_POST['gm2_seo_nonce'], 'gm2_save_seo_meta')) {
            return;
        }
        if (!current_user_can('edit_term', $term_id)) {
            return;
        }
        $title       = isset($_POST['gm2_seo_title']) ? sanitize_text_field($_POST['gm2_seo_title']) : '';
        $description = isset($_POST['gm2_seo_description']) ? sanitize_textarea_field($_POST['gm2_seo_description']) : '';
        $noindex     = isset($_POST['gm2_noindex']) ? '1' : '0';
        $nofollow    = isset($_POST['gm2_nofollow']) ? '1' : '0';
        $canonical      = isset($_POST['gm2_canonical_url']) ? esc_url_raw($_POST['gm2_canonical_url']) : '';
        $focus_keywords   = isset($_POST['gm2_focus_keywords']) ? sanitize_text_field($_POST['gm2_focus_keywords']) : '';
        $long_tail_keywords = isset($_POST['gm2_long_tail_keywords']) ? sanitize_text_field($_POST['gm2_long_tail_keywords']) : '';
        $search_intent    = isset($_POST['gm2_search_intent']) ? sanitize_text_field($_POST['gm2_search_intent']) : '';
        $focus_limit      = isset($_POST['gm2_focus_keyword_limit']) ? absint($_POST['gm2_focus_keyword_limit']) : 0;
        $number_of_words  = isset($_POST['gm2_number_of_words']) ? absint($_POST['gm2_number_of_words']) : 0;
        $improve_readability = isset($_POST['gm2_improve_readability']) ? '1' : '0';
        $max_snippet      = isset($_POST['gm2_max_snippet']) ? sanitize_text_field($_POST['gm2_max_snippet']) : '';
        $max_image_preview = isset($_POST['gm2_max_image_preview']) ? sanitize_text_field($_POST['gm2_max_image_preview']) : '';
        $max_video_preview = isset($_POST['gm2_max_video_preview']) ? sanitize_text_field($_POST['gm2_max_video_preview']) : '';
        $og_image         = isset($_POST['gm2_og_image']) ? absint($_POST['gm2_og_image']) : 0;
        $schema_type   = isset($_POST['gm2_schema_type']) ? sanitize_text_field($_POST['gm2_schema_type']) : '';
        $schema_brand  = isset($_POST['gm2_schema_brand']) ? sanitize_text_field($_POST['gm2_schema_brand']) : '';
        $schema_rating = isset($_POST['gm2_schema_rating']) ? sanitize_text_field($_POST['gm2_schema_rating']) : '';
        $taxonomy      = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';
        $term          = null;
        if ($taxonomy === '') {
            $term = get_term($term_id);
            if ($term && !is_wp_error($term)) {
                $taxonomy = $term->taxonomy;
            }
        }
        if ($schema_brand === '' && in_array($taxonomy, ['brand', 'product_brand'], true)) {
            if (!$term) {
                $term = get_term($term_id, $taxonomy);
            }
            if ($term && !is_wp_error($term)) {
                $schema_brand = sanitize_text_field($term->name);
            }
        }
        if ($schema_type === '') {
            if (in_array($taxonomy, ['brand', 'product_brand'], true)) {
                $schema_type = 'brand';
            }
        }

        $min_len = (int) get_option('gm2_tax_min_length', 0);
        if ($min_len > 0 && isset($_POST['description'])) {
            $word_count = str_word_count( wp_strip_all_tags( wp_unslash( $_POST['description'] ) ) );
            if ($word_count < $min_len) {
                self::add_notice( sprintf( __( 'Description has %d words; minimum is %d.', 'gm2-wordpress-suite' ), $word_count, $min_len ) );
            }
        }
        update_term_meta($term_id, '_gm2_title', $title);
        update_term_meta($term_id, '_gm2_description', $description);
        update_term_meta($term_id, '_gm2_noindex', $noindex);
        update_term_meta($term_id, '_gm2_nofollow', $nofollow);
        update_term_meta($term_id, '_gm2_canonical', $canonical);
        update_term_meta($term_id, '_gm2_focus_keywords', $focus_keywords);
        update_term_meta($term_id, '_gm2_long_tail_keywords', $long_tail_keywords);
        update_term_meta($term_id, '_gm2_search_intent', $search_intent);
        update_term_meta($term_id, '_gm2_focus_keyword_limit', $focus_limit);
        update_term_meta($term_id, '_gm2_number_of_words', $number_of_words);
        update_term_meta($term_id, '_gm2_improve_readability', $improve_readability);
        update_term_meta($term_id, '_gm2_max_snippet', $max_snippet);
        update_term_meta($term_id, '_gm2_max_image_preview', $max_image_preview);
        update_term_meta($term_id, '_gm2_max_video_preview', $max_video_preview);
        update_term_meta($term_id, '_gm2_og_image', $og_image);
        update_term_meta($term_id, '_gm2_schema_type', $schema_type);
        update_term_meta($term_id, '_gm2_schema_brand', $schema_brand);
        update_term_meta($term_id, '_gm2_schema_rating', $schema_rating);
    }

    public function handle_sitemap_form() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        if (!isset($_POST['gm2_sitemap_nonce']) || !wp_verify_nonce($_POST['gm2_sitemap_nonce'], 'gm2_sitemap_save')) {
            wp_die( esc_html__( 'Invalid nonce', 'gm2-wordpress-suite' ) );
        }

        $enabled = isset($_POST['gm2_sitemap_enabled']) ? '1' : '0';
        update_option('gm2_sitemap_enabled', $enabled);

        $frequency = isset($_POST['gm2_sitemap_frequency']) ? sanitize_text_field($_POST['gm2_sitemap_frequency']) : 'daily';
        update_option('gm2_sitemap_frequency', $frequency);

        $path     = isset($_POST['gm2_sitemap_path']) ? sanitize_text_field($_POST['gm2_sitemap_path']) : ABSPATH . 'sitemap.xml';
        $old_path = get_option('gm2_sitemap_path', ABSPATH . 'sitemap.xml');
        $dir      = trailingslashit(dirname($path));
        if ($path === $old_path || wp_is_writable($dir)) {
            update_option('gm2_sitemap_path', $path);
        } else {
            self::add_notice( __( 'Sitemap directory is not writable.', 'gm2-wordpress-suite' ) );
        }

        $max_urls = isset($_POST['gm2_sitemap_max_urls']) ? intval($_POST['gm2_sitemap_max_urls']) : 1000;
        update_option('gm2_sitemap_max_urls', $max_urls);

        if (isset($_POST['gm2_regenerate'])) {
            $result = gm2_generate_sitemap();
            if (is_wp_error($result)) {
                self::add_notice($result->get_error_message());
            } else {
                self::add_notice( __( 'Sitemap generated', 'gm2-wordpress-suite' ), 'success' );
            }
        }

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=sitemap&updated=1'));
        exit;
    }

    public function handle_meta_tags_form() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        if (!isset($_POST['gm2_meta_tags_nonce']) || !wp_verify_nonce($_POST['gm2_meta_tags_nonce'], 'gm2_meta_tags_save')) {
            wp_die( esc_html__( 'Invalid nonce', 'gm2-wordpress-suite' ) );
        }

        $variants = isset($_POST['gm2_noindex_variants']) ? '1' : '0';
        update_option('gm2_noindex_variants', $variants);

        $oos = isset($_POST['gm2_noindex_oos']) ? '1' : '0';
        update_option('gm2_noindex_oos', $oos);

        $canon_parent = isset($_POST['gm2_variation_canonical_parent']) ? '1' : '0';
        update_option('gm2_variation_canonical_parent', $canon_parent);

        $meta_keywords = isset($_POST['gm2_meta_keywords_enabled']) ? '1' : '0';
        update_option('gm2_meta_keywords_enabled', $meta_keywords);

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=meta&updated=1'));
        exit;
    }

    public function handle_schema_form() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        if (!isset($_POST['gm2_schema_nonce']) || !wp_verify_nonce($_POST['gm2_schema_nonce'], 'gm2_schema_save')) {
            wp_die( esc_html__( 'Invalid nonce', 'gm2-wordpress-suite' ) );
        }

        $product    = isset($_POST['gm2_schema_product']) ? '1' : '0';
        update_option('gm2_schema_product', $product);

        $brand      = isset($_POST['gm2_schema_brand']) ? '1' : '0';
        update_option('gm2_schema_brand', $brand);

        $breadcrumbs   = isset($_POST['gm2_schema_breadcrumbs']) ? '1' : '0';
        update_option('gm2_schema_breadcrumbs', $breadcrumbs);

        $taxonomy_list = isset($_POST['gm2_schema_taxonomy']) ? '1' : '0';
        update_option('gm2_schema_taxonomy', $taxonomy_list);

        $article = isset($_POST['gm2_schema_article']) ? '1' : '0';
        update_option('gm2_schema_article', $article);

        $footer_bc = isset($_POST['gm2_show_footer_breadcrumbs']) ? '1' : '0';
        update_option('gm2_show_footer_breadcrumbs', $footer_bc);

        $review     = isset($_POST['gm2_schema_review']) ? '1' : '0';
        update_option('gm2_schema_review', $review);

        $templates = ['product', 'brand', 'breadcrumb', 'taxonomy', 'article', 'review'];
        foreach ($templates as $tpl) {
            $field = 'gm2_schema_template_' . $tpl;
            if (isset($_POST[$field])) {
                update_option($field, wp_unslash($_POST[$field]));
            }
        }

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=schema&updated=1'));
        exit;
    }

    public function handle_custom_schema_save() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied', 'gm2-wordpress-suite'));
        }
        if (!isset($_POST['gm2_custom_schema_nonce']) || !wp_verify_nonce($_POST['gm2_custom_schema_nonce'], 'gm2_save_custom_schema')) {
            wp_die(esc_html__('Invalid nonce', 'gm2-wordpress-suite'));
        }

        $label = isset($_POST['label']) ? sanitize_text_field($_POST['label']) : '';
        $desc  = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : '';
        $json  = isset($_POST['json']) ? wp_unslash($_POST['json']) : '';
        json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_redirect(admin_url('admin.php?page=gm2-seo&tab=schema&error=invalid_json'));
            exit;
        }

        $templates = get_option('gm2_custom_schema', []);
        if (!is_array($templates)) {
            $templates = [];
        }
        $id = !empty($_POST['id']) ? sanitize_text_field($_POST['id']) : uniqid('tpl_');
        $templates[$id] = [
            'label'       => $label,
            'description' => $desc,
            'json'        => $json,
        ];
        update_option('gm2_custom_schema', $templates);
        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=schema&updated=1'));
        exit;
    }

    public function handle_custom_schema_delete() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied', 'gm2-wordpress-suite'));
        }
        $id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
        if (!$id || !isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'gm2_delete_custom_schema_' . $id)) {
            wp_die(esc_html__('Invalid nonce', 'gm2-wordpress-suite'));
        }
        $templates = get_option('gm2_custom_schema', []);
        if (isset($templates[$id])) {
            unset($templates[$id]);
            update_option('gm2_custom_schema', $templates);
        }
        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=schema&deleted=1'));
        exit;
    }

    private function render_custom_schema_admin() {
        $templates = get_option('gm2_custom_schema', []);
        if (!is_array($templates)) {
            $templates = [];
        }
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        if ($action === 'add_custom_schema' || ($action === 'edit_custom_schema' && isset($_GET['id']))) {
            $editing = $action === 'edit_custom_schema';
            $id = $editing ? sanitize_text_field($_GET['id']) : '';
            $current = $editing && isset($templates[$id]) ? $templates[$id] : ['label' => '', 'description' => '', 'json' => ''];
            echo '<h2>' . ($editing ? esc_html__('Edit Custom Schema', 'gm2-wordpress-suite') : esc_html__('Add Custom Schema', 'gm2-wordpress-suite')) . '</h2>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="gm2_save_custom_schema" />';
            wp_nonce_field('gm2_save_custom_schema', 'gm2_custom_schema_nonce');
            if ($editing) {
                echo '<input type="hidden" name="id" value="' . esc_attr($id) . '" />';
            }
            echo '<p><label for="gm2_custom_schema_label">' . esc_html__('Label', 'gm2-wordpress-suite') . '</label>';
            echo '<input type="text" id="gm2_custom_schema_label" name="label" value="' . esc_attr($current['label']) . '" class="regular-text" /></p>';
            echo '<p><label for="gm2_custom_schema_description">' . esc_html__('Description', 'gm2-wordpress-suite') . '</label>';
            echo '<textarea id="gm2_custom_schema_description" name="description" rows="2" class="large-text">' . esc_textarea($current['description']) . '</textarea></p>';
            echo '<p><label for="gm2_custom_schema_json">' . esc_html__('JSON-LD', 'gm2-wordpress-suite') . '</label>';
            echo '<textarea id="gm2_custom_schema_json" name="json" rows="8" class="large-text code">' . esc_textarea($current['json']) . '</textarea></p>';
            echo '<div id="gm2-custom-schema-preview"></div>';
            submit_button($editing ? esc_html__('Update', 'gm2-wordpress-suite') : esc_html__('Add'));
            echo '</form>';
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=gm2-seo&tab=schema')) . '">&larr; ' . esc_html__('Back', 'gm2-wordpress-suite') . '</a></p>';
            echo '<script type="text/html" id="tmpl-gm2-schema-card">';
            echo '<div class="gm2-schema-card"><# var title = data.name || data.headline; if ( title ) { #><div class="gm2-schema-card__title">{{ title }}</div><# } #><# if ( data.description ) { #><div class="gm2-schema-card__desc">{{ data.description }}</div><# } #><# if ( data.offers && data.offers.price ) { #><div class="gm2-schema-card__price">{{ data.offers.price }}</div><# } #></div>';
            echo '</script>';
            return;
        }
        echo '<h2>' . esc_html__('Custom Schema Templates', 'gm2-wordpress-suite') . '</h2>';
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=gm2-seo&tab=schema&action=add_custom_schema')) . '">' . esc_html__('Add New', 'gm2-wordpress-suite') . '</a></p>';
        if (empty($templates)) {
            echo '<p>' . esc_html__('No custom templates found.', 'gm2-wordpress-suite') . '</p>';
            return;
        }
        echo '<table class="widefat fixed striped"><thead><tr><th>' . esc_html__('Label', 'gm2-wordpress-suite') . '</th><th>' . esc_html__('Description', 'gm2-wordpress-suite') . '</th><th>' . esc_html__('Actions', 'gm2-wordpress-suite') . '</th></tr></thead><tbody>';
        foreach ($templates as $tid => $tpl) {
            $label = isset($tpl['label']) ? $tpl['label'] : '';
            $desc  = isset($tpl['description']) ? $tpl['description'] : '';
            $edit  = admin_url('admin.php?page=gm2-seo&tab=schema&action=edit_custom_schema&id=' . urlencode($tid));
            $del   = wp_nonce_url(admin_url('admin-post.php?action=gm2_delete_custom_schema&id=' . urlencode($tid)), 'gm2_delete_custom_schema_' . $tid);
            echo '<tr><td>' . esc_html($label) . '</td><td>' . esc_html($desc) . '</td><td><a href="' . esc_url($edit) . '">' . esc_html__('Edit', 'gm2-wordpress-suite') . '</a> | <a href="' . esc_url($del) . '" onclick="return confirm(\'' . esc_js(__('Delete this template?', 'gm2-wordpress-suite')) . '\');">' . esc_html__('Delete', 'gm2-wordpress-suite') . '</a></td></tr>';
        }
        echo '</tbody></table>';
    }

    public function handle_render_optimizer_form() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        if (empty($_POST['gm2_render_optimizer_nonce']) || !wp_verify_nonce($_POST['gm2_render_optimizer_nonce'], 'gm2_render_optimizer_save')) {
            wp_die( esc_html__( 'Invalid nonce', 'gm2-wordpress-suite' ) );
        }
        $critical = isset($_POST['ae_seo_ro_enable_critical_css']) ? '1' : '0';
        update_option('ae_seo_ro_enable_critical_css', $critical);

        $strategy = isset($_POST['ae_seo_ro_critical_strategy']) ? sanitize_key($_POST['ae_seo_ro_critical_strategy']) : 'per_home_archive_single';
        update_option('ae_seo_ro_critical_strategy', $strategy);

        $async = isset($_POST['ae_seo_ro_async_css_method']) ? sanitize_key($_POST['ae_seo_ro_async_css_method']) : 'preload_onload';
        update_option('ae_seo_ro_async_css_method', $async);

        $map = $_POST['ae_seo_ro_critical_css_map'] ?? [];
        $map = $this->sanitize_css_map($map);
        update_option('ae_seo_ro_critical_css_map', $map);

        $exclusions = isset($_POST['ae_seo_ro_critical_css_exclusions']) ? sanitize_text_field($_POST['ae_seo_ro_critical_css_exclusions']) : '';
        update_option('ae_seo_ro_critical_css_exclusions', $exclusions);

        $defer_js = isset($_POST['ae_seo_ro_enable_defer_js']) ? '1' : '0';
        update_option('ae_seo_ro_enable_defer_js', $defer_js);
        update_option('ae_seo_defer_js', $defer_js);

        $allow_handles = isset($_POST['gm2_defer_js_allowlist']) ? sanitize_text_field($_POST['gm2_defer_js_allowlist']) : '';
        update_option('gm2_defer_js_allowlist', $allow_handles);

        $deny_handles = isset($_POST['gm2_defer_js_denylist']) ? sanitize_text_field($_POST['gm2_defer_js_denylist']) : '';
        update_option('gm2_defer_js_denylist', $deny_handles);

        $allow_domains = isset($_POST['ae_seo_ro_defer_allow_domains']) ? sanitize_text_field($_POST['ae_seo_ro_defer_allow_domains']) : '';
        update_option('ae_seo_ro_defer_allow_domains', $allow_domains);

        $deny_domains = isset($_POST['ae_seo_ro_defer_deny_domains']) ? sanitize_text_field($_POST['ae_seo_ro_defer_deny_domains']) : '';
        update_option('ae_seo_ro_defer_deny_domains', $deny_domains);

        $respect = isset($_POST['ae_seo_ro_defer_respect_in_footer']) ? '1' : '0';
        update_option('ae_seo_ro_defer_respect_in_footer', $respect);

        $preserve = isset($_POST['ae_seo_ro_defer_preserve_jquery']) ? '1' : '0';
        update_option('ae_seo_ro_defer_preserve_jquery', $preserve);

        $combine_css = isset($_POST['ae_seo_ro_enable_combine_css']) ? '1' : '0';
        update_option('ae_seo_ro_enable_combine_css', $combine_css);

        $combine_js = isset($_POST['ae_seo_ro_enable_combine_js']) ? '1' : '0';
        update_option('ae_seo_ro_enable_combine_js', $combine_js);

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=performance&subtab=render-optimizer&updated=1'));
        exit;
    }

    public function handle_js_optimizer_form() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        if (empty($_POST['gm2_js_optimizer_nonce']) || !wp_verify_nonce($_POST['gm2_js_optimizer_nonce'], 'gm2_js_optimizer_save')) {
            wp_die( esc_html__( 'Invalid nonce', 'gm2-wordpress-suite' ) );
        }

        $enable = isset($_POST['ae_js_enable_manager']) ? '1' : '0';
        update_option('ae_js_enable_manager', $enable);

        $lazy = isset($_POST['ae_js_lazy_load']) ? '1' : '0';
        update_option('ae_js_lazy_load', $lazy);

        $lazy_recaptcha = isset($_POST['ae_js_lazy_recaptcha']) ? '1' : '0';
        update_option('ae_js_lazy_recaptcha', $lazy_recaptcha);

        $lazy_analytics = isset($_POST['ae_js_lazy_analytics']) ? '1' : '0';
        update_option('ae_js_lazy_analytics', $lazy_analytics);

        $analytics_id = isset($_POST['ae_js_analytics_id']) ? sanitize_text_field($_POST['ae_js_analytics_id']) : '';
        update_option('ae_js_analytics_id', $analytics_id);

        $gtm_id = isset($_POST['ae_js_gtm_id']) ? sanitize_text_field($_POST['ae_js_gtm_id']) : '';
        update_option('ae_js_gtm_id', $gtm_id);

        $fb_id = isset($_POST['ae_js_fb_id']) ? sanitize_text_field($_POST['ae_js_fb_id']) : '';
        update_option('ae_js_fb_id', $fb_id);

        $recaptcha_key = isset($_POST['ae_recaptcha_site_key']) ? sanitize_text_field($_POST['ae_recaptcha_site_key']) : '';
        update_option('ae_recaptcha_site_key', $recaptcha_key);

        $hcaptcha_key = isset($_POST['ae_hcaptcha_site_key']) ? sanitize_text_field($_POST['ae_hcaptcha_site_key']) : '';
        update_option('ae_hcaptcha_site_key', $hcaptcha_key);

        $consent_key = isset($_POST['ae_js_consent_key']) ? sanitize_text_field($_POST['ae_js_consent_key']) : 'aeConsent';
        update_option('ae_js_consent_key', $consent_key);

        $consent_value = isset($_POST['ae_js_consent_value']) ? sanitize_text_field($_POST['ae_js_consent_value']) : 'allow_analytics';
        update_option('ae_js_consent_value', $consent_value);

        $replacements = isset($_POST['ae_js_replacements']) ? '1' : '0';
        update_option('ae_js_replacements', $replacements);

        $debug = isset($_POST['ae_js_debug_log']) ? '1' : '0';
        update_option('ae_js_debug_log', $debug);

        $console = isset($_POST['ae_js_console_log']) ? '1' : '0';
        update_option('ae_js_console_log', $console);

        $auto = isset($_POST['ae_js_auto_dequeue']) ? '1' : '0';
        update_option('ae_js_auto_dequeue', $auto);

        $safe = isset($_POST['ae_js_respect_safe_mode']) ? '1' : '0';
        update_option('ae_js_respect_safe_mode', $safe);

        $nomodule = isset($_POST['ae_js_nomodule_legacy']) ? '1' : '0';
        update_option('ae_js_nomodule_legacy', $nomodule);

        $jquery_demand = isset($_POST['ae_js_jquery_on_demand']) ? '1' : '0';
        update_option('ae_js_jquery_on_demand', $jquery_demand);

        $jquery_allow = isset($_POST['ae_js_jquery_url_allow']) ? trim(sanitize_textarea_field($_POST['ae_js_jquery_url_allow'])) : '';
        update_option('ae_js_jquery_url_allow', $jquery_allow);

        $allow = isset($_POST['ae_js_dequeue_allowlist']) ? $this->sanitize_handle_array((array) $_POST['ae_js_dequeue_allowlist']) : [];
        update_option('ae_js_dequeue_allowlist', $allow);

        $deny = isset($_POST['ae_js_dequeue_denylist']) ? $this->sanitize_handle_array((array) $_POST['ae_js_dequeue_denylist']) : [];
        update_option('ae_js_dequeue_denylist', $deny);

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=performance&subtab=javascript&updated=1'));
        exit;
    }

    public function handle_js_compatibility_form() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }
        if (empty($_POST['gm2_js_compatibility_nonce']) || !wp_verify_nonce($_POST['gm2_js_compatibility_nonce'], 'gm2_js_compatibility_save')) {
            wp_die( esc_html__( 'Invalid nonce', 'gm2-wordpress-suite' ) );
        }
        $selected = isset($_POST['ae_js_compat_plugins']) ? array_map('sanitize_key', (array) $_POST['ae_js_compat_plugins']) : [];
        $file = GM2_PLUGIN_DIR . 'config/compat-defaults.php';
        $map = [];
        if (file_exists($file)) {
            $map = include $file;
        }
        if (!is_array($map)) {
            $map = [];
        }
        $overrides = [];
        foreach ($map as $plugin => $handles) {
            if (!in_array($plugin, $selected, true)) {
                foreach ((array) $handles as $handle) {
                    $overrides[] = sanitize_text_field($handle);
                }
            }
        }
        update_option('ae_js_compat_overrides', array_values(array_unique($overrides)));
        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=performance&subtab=javascript&js-tab=compatibility&updated=1'));
        exit;
    }

    public function handle_performance_form() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        if (!isset($_POST['gm2_performance_nonce']) || !wp_verify_nonce($_POST['gm2_performance_nonce'], 'gm2_performance_save')) {
            wp_die( esc_html__( 'Invalid nonce', 'gm2-wordpress-suite' ) );
        }

        $auto_fill = isset($_POST['gm2_auto_fill_alt']) ? '1' : '0';
        update_option('gm2_auto_fill_alt', $auto_fill);

        $clean_files = isset($_POST['gm2_clean_image_filenames']) ? '1' : '0';
        update_option('gm2_clean_image_filenames', $clean_files);

        $enable_comp = isset($_POST['gm2_enable_compression']) ? '1' : '0';
        update_option('gm2_enable_compression', $enable_comp);

        $api_key = isset($_POST['gm2_compression_api_key']) ? sanitize_text_field($_POST['gm2_compression_api_key']) : '';
        update_option('gm2_compression_api_key', $api_key);

        $api_url = isset($_POST['gm2_compression_api_url']) ? esc_url_raw($_POST['gm2_compression_api_url']) : 'https://api.example.com/compress';
        update_option('gm2_compression_api_url', $api_url);

        $min_html = isset($_POST['gm2_minify_html']) ? '1' : '0';
        update_option('gm2_minify_html', $min_html);

        $min_css = isset($_POST['gm2_minify_css']) ? '1' : '0';
        update_option('gm2_minify_css', $min_css);

        $min_js = isset($_POST['gm2_minify_js']) ? '1' : '0';
        update_option('gm2_minify_js', $min_js);

        $pretty_versions = isset($_POST['gm2_pretty_versioned_urls']) ? '1' : '0';
        update_option('gm2_pretty_versioned_urls', $pretty_versions);
        if ($pretty_versions === '1') {
            Gm2_Version_Route_Apache::maybe_apply();
        }

        $perf_worker = isset($_POST['ae_perf_worker']) ? '1' : '0';
        update_option('ae_perf_worker', $perf_worker);
        $perf_long = isset($_POST['ae_perf_long_tasks']) ? '1' : '0';
        update_option('ae_perf_long_tasks', $perf_long);
        $perf_layout = isset($_POST['ae_perf_layout_thrash']) ? '1' : '0';
        update_option('ae_perf_layout_thrash', $perf_layout);
        $perf_no_thrash = isset($_POST['ae_perf_no_thrash']) ? '1' : '0';
        update_option('ae_perf_no_thrash', $perf_no_thrash);
        $perf_passive = isset($_POST['ae_perf_passive_listeners']) ? '1' : '0';
        update_option('ae_perf_passive_listeners', $perf_passive);
        $perf_dom = isset($_POST['ae_perf_dom_audit']) ? '1' : '0';
        update_option('ae_perf_dom_audit', $perf_dom);

        $map = get_option('ae_seo_ro_critical_css_map', []);
        if (!is_array($map)) {
            $map = [];
        }
        $map['manual'] = isset($_POST['ae_seo_ro_manual_css']) ? wp_strip_all_tags($_POST['ae_seo_ro_manual_css']) : '';
        update_option('ae_seo_ro_critical_css_map', $map);

        $exclusions = isset($_POST['ae_seo_ro_critical_css_exclusions']) ? sanitize_text_field($_POST['ae_seo_ro_critical_css_exclusions']) : '';
        update_option('ae_seo_ro_critical_css_exclusions', $exclusions);

        $vendor_opts = [
            'facebook' => isset($_POST['gm2_remote_mirror_vendors']['facebook']) ? '1' : '0',
            'google'   => isset($_POST['gm2_remote_mirror_vendors']['google']) ? '1' : '0',
        ];
        update_option('gm2_remote_mirror_vendors', $vendor_opts);

        $custom_urls = [];
        if (!empty($_POST['gm2_remote_mirror_custom_urls'])) {
            $lines = explode("\n", (string) $_POST['gm2_remote_mirror_custom_urls']);
            foreach ($lines as $line) {
                $url = esc_url_raw(trim($line));
                if ($url !== '') {
                    $custom_urls[] = $url;
                }
            }
        }
        update_option('gm2_remote_mirror_custom_urls', $custom_urls);
        Gm2_Remote_Mirror::init()->refresh_all();

        $attributes = [];
        if (isset($_POST['gm2_script_attr_reset'])) {
            $attributes = [];
        } elseif (isset($_POST['gm2_script_attr_apply_preset']) && !empty($_POST['gm2_script_attr_preset'])) {
            $preset = sanitize_text_field($_POST['gm2_script_attr_preset']);
            $scripts = wp_scripts();
            $core   = [];
            foreach ($scripts->registered as $handle => $dep) {
                $src = $dep->src ?? '';
                if ($src === '' || strpos($src, includes_url()) === 0 || strpos($src, '/wp-includes/') === 0) {
                    $core[] = $handle;
                }
            }
            if ($preset === 'defer_third') {
                foreach ($scripts->registered as $handle => $dep) {
                    if (in_array($handle, $core, true)) {
                        $attributes[$handle] = 'blocking';
                    } else {
                        $attributes[$handle] = 'defer';
                    }
                }
            } elseif ($preset === 'conservative') {
                foreach ($core as $handle) {
                    $attributes[$handle] = 'blocking';
                }
            }
        } else {
            $handles = $_POST['gm2_script_attr_handles'] ?? [];
            $values  = $_POST['gm2_script_attr_values'] ?? [];
            foreach ($handles as $i => $handle) {
                $handle = sanitize_key($handle);
                $val    = $values[$i] ?? '';
                if ($handle === '' || !in_array($val, ['blocking', 'defer', 'async'], true)) {
                    continue;
                }
                $attributes[$handle] = $val;
            }
        }
        update_option('gm2_script_attributes', $attributes);

        $ps_key = isset($_POST['gm2_pagespeed_api_key']) ? sanitize_text_field($_POST['gm2_pagespeed_api_key']) : '';
        update_option('gm2_pagespeed_api_key', $ps_key);

        if (isset($_POST['gm2_test_pagespeed'])) {
            $helper = new Gm2_PageSpeed($ps_key);
            $scores = $helper->get_scores(home_url('/'));
            if (!is_wp_error($scores)) {
                $scores['time'] = time();
                update_option('gm2_pagespeed_scores', $scores);
            } else {
                self::add_notice($scores->get_error_message());
            }
        }

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=performance&updated=1'));
        exit;
    }

    public function handle_purge_critical_css() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }
        check_admin_referer('gm2_purge_critical_css');
        delete_option('ae_seo_ro_critical_css_map');
        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=performance&critical_css_purged=1'));
        exit;
    }

    public function handle_purge_js_map() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }
        check_admin_referer('gm2_purge_js_map');
        delete_transient('gm2_defer_js_map');
        delete_transient('gm2_defer_js_dependencies');
        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=performance&js_map_purged=1'));
        exit;
    }

    public function handle_purge_optimizer_cache() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }
        check_admin_referer('gm2_purge_optimizer_cache');
        if (class_exists('\\AE_SEO_Combine_Minify')) {
            \AE_SEO_Combine_Minify::purge_cache();
        }
        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=performance&optimizer_cache_purged=1'));
        exit;
    }

    public function ajax_purge_critical_css() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__( 'Permission denied', 'gm2-wordpress-suite' )], 403);
        }
        check_ajax_referer('gm2_purge_critical_css', 'nonce');
        delete_option('ae_seo_ro_critical_css_map');
        delete_transient('gm2_defer_js_map');
        delete_transient('gm2_defer_js_dependencies');
        if (class_exists('\\AE_SEO_Combine_Minify')) {
            \AE_SEO_Combine_Minify::purge_cache();
        }
        wp_send_json_success(['message' => esc_html__( 'Critical CSS purged.', 'gm2-wordpress-suite' )]);
    }

    public function ajax_purge_js_map() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__( 'Permission denied', 'gm2-wordpress-suite' )], 403);
        }
        check_ajax_referer('gm2_purge_js_map', 'nonce');
        delete_transient('gm2_defer_js_map');
        delete_transient('gm2_defer_js_dependencies');
        if (class_exists('\\AE_SEO_Combine_Minify')) {
            \AE_SEO_Combine_Minify::purge_cache();
        }
        wp_send_json_success(['message' => esc_html__( 'JS map purged.', 'gm2-wordpress-suite' )]);
    }

    public function ajax_purge_optimizer_cache() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__( 'Permission denied', 'gm2-wordpress-suite' )], 403);
        }
        check_ajax_referer('gm2_purge_optimizer_cache', 'nonce');
        delete_transient('gm2_defer_js_map');
        delete_transient('gm2_defer_js_dependencies');
        if (class_exists('\\AE_SEO_Combine_Minify')) {
            \AE_SEO_Combine_Minify::purge_cache();
        }
        wp_send_json_success(['message' => esc_html__( 'Optimizer cache purged.', 'gm2-wordpress-suite' )]);
    }

    public function ajax_clear_optimizer_diagnostics() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__( 'Permission denied', 'gm2-wordpress-suite' )], 403);
        }
        check_ajax_referer('gm2_clear_optimizer_diagnostics', 'nonce');
        if (class_exists('\\AE_SEO_Optimizer_Diagnostics')) {
            \AE_SEO_Optimizer_Diagnostics::clear();
        }
        wp_send_json_success(['message' => esc_html__( 'Diagnostics cleared.', 'gm2-wordpress-suite' )]);
    }

    public function handle_insert_cache_rules() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }
        check_admin_referer('gm2_insert_cache_rules');
        Gm2_Cache_Headers_Apache::maybe_apply();
        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=performance'));
        exit;
    }

    public function handle_remove_cache_rules() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }
        check_admin_referer('gm2_remove_cache_rules');
        Gm2_Cache_Headers_Apache::remove_rules();
        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=performance'));
        exit;
    }


    public function handle_generate_nginx_cache() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }
        check_admin_referer('gm2_generate_nginx_cache');
        Gm2_Cache_Headers_Nginx::write_rules();
        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=performance&nginx_cache_written=1'));
        exit;
    }

    public function handle_verify_nginx_cache() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }
        check_admin_referer('gm2_verify_nginx_cache');
        $verified = Gm2_Cache_Headers_Nginx::verify();
        $flag = $verified ? '1' : '0';
        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=performance&nginx_cache_verified=' . $flag));
        exit;
    }
    public function handle_general_settings_form() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        if (!isset($_POST['gm2_general_settings_nonce']) || !wp_verify_nonce($_POST['gm2_general_settings_nonce'], 'gm2_general_settings_save')) {
            wp_die( esc_html__( 'Invalid nonce', 'gm2-wordpress-suite' ) );
        }

        $ga_id  = isset($_POST['gm2_ga_measurement_id']) ? sanitize_text_field($_POST['gm2_ga_measurement_id']) : '';
        $sc_ver = isset($_POST['gm2_search_console_verification']) ? sanitize_text_field($_POST['gm2_search_console_verification']) : '';
        $tw_site = isset($_POST['gm2_twitter_site']) ? sanitize_text_field($_POST['gm2_twitter_site']) : '';
        $tw_creator = isset($_POST['gm2_twitter_creator']) ? sanitize_text_field($_POST['gm2_twitter_creator']) : '';
        $org_name = isset($_POST['gm2_org_name']) ? sanitize_text_field($_POST['gm2_org_name']) : '';
        $org_logo = isset($_POST['gm2_org_logo']) ? esc_url_raw($_POST['gm2_org_logo']) : '';
        $search_url = isset($_POST['gm2_site_search_url']) ? esc_url_raw($_POST['gm2_site_search_url']) : '';
        $token  = isset($_POST['gm2_gads_developer_token']) ? sanitize_text_field($_POST['gm2_gads_developer_token']) : '';
        $cust   = isset($_POST['gm2_gads_customer_id']) ? $this->sanitize_customer_id($_POST['gm2_gads_customer_id']) : '';
        $clean  = isset($_POST['gm2_clean_slugs']) ? '1' : '0';
        $words  = isset($_POST['gm2_slug_stopwords']) ? sanitize_textarea_field($_POST['gm2_slug_stopwords']) : '';
        $prompt = isset($_POST['gm2_tax_desc_prompt']) ? sanitize_textarea_field($_POST['gm2_tax_desc_prompt']) : '';

        update_option('gm2_ga_measurement_id', $ga_id);
        update_option('gm2_search_console_verification', $sc_ver);
        update_option('gm2_twitter_site', $tw_site);
        update_option('gm2_twitter_creator', $tw_creator);
        update_option('gm2_org_name', $org_name);
        update_option('gm2_org_logo', $org_logo);
        update_option('gm2_site_search_url', $search_url);
        update_option('gm2_gads_developer_token', $token);
        update_option('gm2_gads_customer_id', $cust);
        update_option('gm2_clean_slugs', $clean);
        update_option('gm2_slug_stopwords', $words);
        update_option('gm2_tax_desc_prompt', $prompt);

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=general&updated=1'));
        exit;
    }

    public function handle_redirects_form() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        if (!isset($_POST['gm2_redirects_nonce']) || !wp_verify_nonce($_POST['gm2_redirects_nonce'], 'gm2_redirects_save')) {
            wp_die( esc_html__( 'Invalid nonce', 'gm2-wordpress-suite' ) );
        }

        $source = isset($_POST['gm2_redirect_source']) ? sanitize_text_field($_POST['gm2_redirect_source']) : '';
        $target = isset($_POST['gm2_redirect_target']) ? esc_url_raw($_POST['gm2_redirect_target']) : '';
        $type   = ($_POST['gm2_redirect_type'] ?? '301') === '302' ? '302' : '301';

        if ($source && $target) {
            $redirects   = get_option('gm2_redirects', []);
            $redirects[] = [
                'source' => untrailingslashit(parse_url($source, PHP_URL_PATH)),
                'target' => $target,
                'type'   => $type,
            ];
            update_option('gm2_redirects', $redirects);

            $logs = get_option('gm2_404_logs', []);
            $path = untrailingslashit(parse_url($source, PHP_URL_PATH));
            $index = array_search($path, $logs, true);
            if ($index !== false) {
                unset($logs[$index]);
                update_option('gm2_404_logs', array_values($logs));
            }
        }

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=redirects&updated=1'));
        exit;
    }

    public function handle_clear_404_logs() {
        if (!current_user_can('edit_posts')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        check_admin_referer('gm2_clear_404_logs');

        delete_option('gm2_404_logs');

        self::add_notice( __( '404 logs cleared.', 'gm2-wordpress-suite' ), 'success' );

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=redirects&logs_cleared=1'));
        exit;
    }

    public function handle_reset_seo() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        check_admin_referer('gm2_reset_seo');

        global $wpdb;
        $names = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('gm2_') . '%'
        ));

        foreach ($names as $name) {
            delete_option($name);
        }

        wp_redirect(admin_url('admin.php?page=gm2-seo&reset=1'));
        exit;
    }

    public function handle_export_settings() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        check_admin_referer('gm2_export_settings');

        global $wpdb;
        $names = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('gm2_') . '%'
        ));

        $data = [];
        foreach ($names as $name) {
            $data[$name] = get_option($name);
        }

        $json = wp_json_encode($data, JSON_PRETTY_PRINT);

        nocache_headers();
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="gm2-seo-settings.json"');
        echo $json;

        if (defined('GM2_TESTING') && GM2_TESTING) {
            return;
        }
        exit;
    }

    public function handle_import_settings() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        check_admin_referer('gm2_import_settings');

        if (!isset($_FILES['gm2_settings_file']) || !is_uploaded_file($_FILES['gm2_settings_file']['tmp_name'])) {
            wp_die( esc_html__( 'No file uploaded', 'gm2-wordpress-suite' ) );
        }

        $raw  = file_get_contents($_FILES['gm2_settings_file']['tmp_name']);
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            wp_die( esc_html__( 'Invalid JSON file', 'gm2-wordpress-suite' ) );
        }

        foreach ($data as $name => $value) {
            if (strpos($name, 'gm2_') !== 0) {
                continue;
            }
            update_option($name, $value);
        }

        wp_redirect(admin_url('admin.php?page=gm2-seo&import=1'));
        exit;
    }

    public function handle_content_rules_form() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        if (!isset($_POST['gm2_content_rules_nonce']) || !wp_verify_nonce($_POST['gm2_content_rules_nonce'], 'gm2_content_rules_save')) {
            wp_die( esc_html__( 'Invalid nonce', 'gm2-wordpress-suite' ) );
        }

        $rules = [];
        if (isset($_POST['gm2_content_rules']) && is_array($_POST['gm2_content_rules'])) {
            foreach ($_POST['gm2_content_rules'] as $k => $v) {
                $rules[$k] = [];
                if (is_array($v)) {
                    foreach ($v as $cat => $val) {
                        $rules[$k][$cat] = sanitize_textarea_field(
                            wp_unslash($this->flatten_rule_value($val))
                        );
                    }
                } else {
                    // Support legacy or single-category submissions.
                    $rules[$k]['general'] = sanitize_textarea_field(
                        wp_unslash($this->flatten_rule_value($v))
                    );
                }
            }
        }
        update_option('gm2_content_rules', $rules);
        $min_int = isset($_POST['gm2_min_internal_links']) ? absint($_POST['gm2_min_internal_links']) : 1;
        $min_ext = isset($_POST['gm2_min_external_links']) ? absint($_POST['gm2_min_external_links']) : 1;
        update_option('gm2_min_internal_links', $min_int);
        update_option('gm2_min_external_links', $min_ext);

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=rules&updated=1'));
        exit;
    }

    public function handle_guideline_rules_form() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        if (!isset($_POST['gm2_guideline_rules_nonce']) || !wp_verify_nonce($_POST['gm2_guideline_rules_nonce'], 'gm2_guideline_rules_save')) {
            wp_die( esc_html__( 'Invalid nonce', 'gm2-wordpress-suite' ) );
        }

        $rules = [];
        if (isset($_POST['gm2_guideline_rules']) && is_array($_POST['gm2_guideline_rules'])) {
            foreach ($_POST['gm2_guideline_rules'] as $k => $v) {
                $rules[$k] = [];
                if (is_array($v)) {
                    foreach ($v as $cat => $val) {
                        $rules[$k][$cat] = sanitize_textarea_field(
                            wp_unslash($this->flatten_rule_value($val))
                        );
                    }
                } else {
                    $rules[$k]['general'] = sanitize_textarea_field(
                        wp_unslash($this->flatten_rule_value($v))
                    );
                }
            }
        }
        update_option('gm2_guideline_rules', $rules);

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=guidelines&updated=1'));
        exit;
    }

    public function handle_keyword_settings_form() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        if (!isset($_POST['gm2_keyword_settings_nonce']) || !wp_verify_nonce($_POST['gm2_keyword_settings_nonce'], 'gm2_keyword_settings_save')) {
            wp_die( esc_html__( 'Invalid nonce', 'gm2-wordpress-suite' ) );
        }

        $lang  = isset($_POST['gm2_gads_language']) ? sanitize_text_field($_POST['gm2_gads_language']) : '';
        $geo   = isset($_POST['gm2_gads_geo_target']) ? sanitize_text_field($_POST['gm2_gads_geo_target']) : '';
        $login = isset($_POST['gm2_gads_login_customer_id']) ? $this->sanitize_customer_id($_POST['gm2_gads_login_customer_id']) : '';
        $sc_limit  = isset($_POST['gm2_sc_query_limit']) ? absint($_POST['gm2_sc_query_limit']) : 0;
        $days      = isset($_POST['gm2_analytics_days']) ? absint($_POST['gm2_analytics_days']) : 0;
        $retention = isset($_POST['gm2_analytics_retention_days']) ? absint($_POST['gm2_analytics_retention_days']) : 0;

        update_option('gm2_gads_language', $lang);
        update_option('gm2_gads_geo_target', $geo);
        update_option('gm2_gads_login_customer_id', $login);
        update_option('gm2_sc_query_limit', $sc_limit);
        update_option('gm2_analytics_days', $days);
        update_option('gm2_analytics_retention_days', $retention);

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=keywords&updated=1'));
        exit;
    }

    public function handle_google_test_connection() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        if (!isset($_POST['gm2_google_test_nonce']) || !wp_verify_nonce($_POST['gm2_google_test_nonce'], 'gm2_google_test')) {
            wp_die( esc_html__( 'Invalid nonce', 'gm2-wordpress-suite' ) );
        }

        $oauth = apply_filters('gm2_google_oauth_instance', new Gm2_Google_OAuth());

        if (!$oauth->is_connected()) {
            $msg = __( 'Google account not connected.', 'gm2-wordpress-suite' );
            $url = add_query_arg([
                'gm2_google_test' => rawurlencode($msg),
                'gm2_google_test_error' => 1,
            ], admin_url('admin.php?page=gm2-google-connect'));
            wp_redirect($url);
            if (defined('GM2_TESTING') && GM2_TESTING) {
                return;
            }
            exit;
        }

        $result = $oauth->list_analytics_properties();
        if (is_wp_error($result)) {
            $msg = $result->get_error_message();
            $url = add_query_arg([
                'gm2_google_test' => rawurlencode($msg),
                'gm2_google_test_error' => 1,
            ], admin_url('admin.php?page=gm2-google-connect'));
        } else {
            $msg = __( 'Connection successful.', 'gm2-wordpress-suite' );
            $url = add_query_arg([
                'gm2_google_test' => rawurlencode($msg),
                'gm2_google_test_error' => 0,
            ], admin_url('admin.php?page=gm2-google-connect'));
        }

        wp_redirect($url);
        if (defined('GM2_TESTING') && GM2_TESTING) {
            return;
        }
        exit;
    }
    public function auto_fill_alt_on_upload($attachment_id, $keyword = '') {
        if (get_option('gm2_clean_image_filenames', '0') === '1') {
            $file = get_attached_file($attachment_id);
            if ($file && file_exists($file)) {
                $info = pathinfo($file);
                $dir  = $info['dirname'];
                $ext  = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
                $source = $keyword !== '' ? $keyword : get_post($attachment_id)->post_title;
                $name   = sanitize_file_name(sanitize_title($source)) . $ext;
                $name   = wp_unique_filename($dir, $name);
                $new    = $dir . '/' . $name;
                if ($new !== $file && @rename($file, $new)) {
                    update_attached_file($attachment_id, $new);
                    \gm2_queue_thumbnail_regeneration($attachment_id);
                }
            }
        }

        if (get_option('gm2_auto_fill_alt', '0') !== '1') {
            return;
        }

        $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if ($alt === '') {
            $title = get_post($attachment_id)->post_title;
            $alt   = sanitize_text_field($title);
            $prompt = "Provide a short descriptive alt text for an image titled: {$title}";
            $context = gm2_get_business_context_prompt();
            if ($context !== '') {
                $prompt = $context . "\n\n" . $prompt;
            }
            $resp = gm2_ai_send_prompt($prompt);
            if (!is_wp_error($resp) && $resp !== '') {
                $alt = sanitize_text_field($resp);
            } else {
                $msg = is_wp_error($resp) ? $resp->get_error_message() : __( 'Empty response from AI', 'gm2-wordpress-suite' );
                self::add_notice( sprintf( __( 'AI alt text error: %s', 'gm2-wordpress-suite' ), $msg ) );
            }
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        }
    }

    public function compress_image_on_upload($attachment_id) {
        if (get_option('gm2_enable_compression', '0') !== '1') {
            return;
        }

        $api_key = get_option('gm2_compression_api_key', '');
        if ($api_key === '') {
            return;
        }

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return;
        }

        $api_url = apply_filters(
            'gm2_compression_api_url',
            get_option('gm2_compression_api_url', 'https://api.example.com/compress')
        );

        $response = wp_remote_post(
            $api_url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/octet-stream',
                ],
                'body'    => file_get_contents($file),
                'timeout' => 60,
            ]
        );

        if (is_wp_error($response)) {
            return;
        }

        if (wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            if ($body !== '') {
                file_put_contents($file, $body);
                \gm2_queue_thumbnail_regeneration($attachment_id);
            }
        }
    }

    public function auto_fill_product_alt($post_id, $post, $update) {
        if (get_option('gm2_auto_fill_alt', '0') !== '1') {
            return;
        }

        if ($post->post_type !== 'product') {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $title = get_the_title($post_id);
        $thumb_id = get_post_thumbnail_id($post_id);
        if ($thumb_id) {
            $alt = get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
            if ($alt === '') {
                update_post_meta($thumb_id, '_wp_attachment_image_alt', sanitize_text_field($title));
            }
        }

        $gallery = get_post_meta($post_id, '_product_image_gallery', true);
        if ($gallery) {
            $ids = array_filter(array_map('trim', explode(',', $gallery)));
            foreach ($ids as $id) {
                $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
                if ($alt === '') {
                    update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($title));
                }
            }
        }
    }

    private function get_rendered_html($post_id, $term_id, $taxonomy) {
        if ($post_id) {
            $post_obj = get_post($post_id);
            if ($post_obj) {
                global $post;
                $prev_post = $post;
                $post      = $post_obj;
                setup_postdata($post);
                $content = $post_obj->post_content;
                if ($post_obj->post_excerpt !== '') {
                    $content = $post_obj->post_excerpt . "\n\n" . $content;
                }
                $html = apply_filters('the_content', $content);
                $html = $this->sanitize_snippet_html($html);
                wp_reset_postdata();
                $post = $prev_post;
                return $html;
            }
        } elseif ($term_id && $taxonomy) {
            $desc = term_description($term_id, $taxonomy);
            $stub = new \stdClass();
            $stub->ID = 0;
            // Provide a more complete context object for shortcodes and filters.
            $stub->post_title  = '';
            $stub->post_type   = 'post';
            $stub->post_status = 'publish';
            $post_obj = new \WP_Post($stub);
            global $post;
            $prev_post = $post;
            $post      = $post_obj;
            setup_postdata($post);
            $html = apply_filters('the_content', $desc);
            $html = $this->sanitize_snippet_html($html);
            wp_reset_postdata();
            $post = $prev_post;

            return $html;
        }
        return '';
    }

    private function detect_html_issues($html, $canonical, $focus_main = '') {
        $issues = [];
        if ($canonical === '') {
            $issues[] = 'Missing canonical link tag';
        }
        if (trim($html) === '') {
            return $issues;
        }

        if (!class_exists('\DOMDocument') || !function_exists('libxml_use_internal_errors')) {
            return $issues;
        }

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $h1s = $doc->getElementsByTagName('h1');
        if ($h1s->length > 1) {
            $issues[] = __( 'Multiple <h1> tags found', 'gm2-wordpress-suite' );
        }

        foreach ($doc->getElementsByTagName('img') as $img) {
            if (!$img->hasAttribute('alt') || trim($img->getAttribute('alt')) === '') {
                $issues[] = __( 'Image missing alt attribute', 'gm2-wordpress-suite' );
                break;
            }
            if ($focus_main !== '' && stripos($img->getAttribute('alt'), $focus_main) === false) {
                $issues[] = __( 'Image alt text missing focus keyword', 'gm2-wordpress-suite' );
                break;
            }
        }

        return $issues;
    }

    private function safe_truncate($text, $length) {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $length, 'UTF-8');
        }
        if (preg_match('/^.{0,' . (int) $length . '}/us', $text, $m)) {
            return $m[0];
        }
        return substr($text, 0, $length);
    }

    /**
     * Extract and clean a JSON object from a raw AI response.
     *
     * @param string $response Raw response text.
     * @return string Clean JSON string ready for decoding.
     */
    private function sanitize_ai_json($response) {
        $original = $response;
        $start = strpos($response, '{');
        $end   = strrpos($response, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $json    = substr($response, $start, $end - $start + 1);
            $trimmed = $json;

            while ($trimmed !== '') {
                json_decode($trimmed);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $json = $trimmed;
                    break;
                }
                $pos = strrpos($trimmed, '}');
                if ($pos === false) {
                    break;
                }
                $trimmed = substr($trimmed, 0, $pos);
            }
        } else {
            $json = $response;
        }

        // Normalize curly double quotes which often appear in AI output.
        $json = str_replace(["\xE2\x80\x9C", "\xE2\x80\x9D"], '"', $json);

        // Convert single-quoted keys and values to standard double quotes.
        $before = $json;
        $json = preg_replace_callback(
            "#'(?:\\\\.|[^'\\\\])*'#s",
            function($m) {
                $inner = substr($m[0], 1, -1);
                $inner = str_replace("\\'", "'", $inner);
                $inner = str_replace('"', '\\"', $inner);
                return '"' . $inner . '"';
            },
            $before
        );
        if ($json === null) {
            $this->debug_log('sanitize_ai_json regex failure: "#\'(?:\\\\.|[^\'\\\\])*\'#s" on ' . $this->safe_truncate($before, 200));
            return $original;
        }

        // Strip JavaScript-style comments before further processing.
        $before = $json;
        $json = preg_replace('#/\*.*?\*/#s', '', $before);
        if ($json === null) {
            $this->debug_log('sanitize_ai_json regex failure: "#/\*.*?\*/#s" on ' . $this->safe_truncate($before, 200));
            return $original;
        }
        $before = $json;
        $json = preg_replace('#//.*$#m', '', $before);
        if ($json === null) {
            $this->debug_log('sanitize_ai_json regex failure: "#//.*$#m" on ' . $this->safe_truncate($before, 200));
            return $original;
        }

        $before = $json;
        $json = preg_replace_callback('/"(?:\\\\.|[^"\\\\])*"/s', function($matches) {
            $str = str_replace("\n", "\\n", $matches[0]);

            $inner = substr($str, 1, -1);
            $inner = preg_replace('/(?<!\\\\)(\d+(?:\.\d+)?(?:-inch)?)"/', '$1\\"', $inner);
            $inner = preg_replace('/(?<!\\)"/', '\\"', $inner);

            return '"' . $inner . '"';
        }, $before);
        if ($json === null) {
            $this->debug_log('sanitize_ai_json regex failure: "/\"(?:\\\\.|[^\"\\\\])*\"/s" on ' . $this->safe_truncate($before, 200));
            return $original;
        }

        // Remove ellipses or stray characters after JSON strings.
        $before = $json;
        $json = preg_replace('/("(?:\\.|[^"\\])*")\s*[^,:}\]]+(?=\s*[}\]])/u', '$1', $before);
        if ($json === null) {
            $this->debug_log('sanitize_ai_json regex failure: "(/\"(?:\\.|[^\"\\])*\")\\s*[^,:}\\]]+(?=\\s*[}\\]])/u" on ' . $this->safe_truncate($before, 200));
            return $original;
        }

        // Remove trailing commas before closing braces or brackets.
        $before = $json;
        $json = preg_replace_callback(
            '/"(?:\\.|[^"\\])*"|,(?=\s*[}\]])/s',
            function($m) {
                return ($m[0][0] === '"') ? $m[0] : '';
            },
            $before
        );
        if ($json === null) {
            $this->debug_log('sanitize_ai_json regex failure: "/\"(?:\\.|[^\"\\])*\"|,(?=\\s*[}\\]])/s" on ' . $this->safe_truncate($before, 200));
            return $original;
        }

        $before = $json;
        $json = preg_replace_callback(
            '/:\s*\{\s*("(?:\\\\.|[^"\\])*"\s*(?:,\s*"(?:\\\\.|[^"\\])*"\s*)*)\}/s',
            function($m) {
                if (strpos($m[1], ':') !== false) {
                    return $m[0];
                }
                return ':[' . $m[1] . ']';
            },
            $before
        );
        if ($json === null) {
            $this->debug_log('sanitize_ai_json regex failure: "/:\\s*\\{\\s*(\"(?:\\\\.|[^\"\\])*\"\\s*(?:,\\s*\"(?:\\\\.|[^\"\\])*\"\\s*)*)\\}/s" on ' . $this->safe_truncate($before, 200));
            return $original;
        }


        $end = strrpos($json, '}');
        if ($end !== false) {
            $json = substr($json, 0, $end + 1);
        }

        return trim($json);
    }

    /**
     * Remove page builder wrapper markup leaving only semantic content tags.
     *
     * @param string $html Raw HTML output from the_content filter.
     * @return string Sanitized snippet HTML.
     */
    private function sanitize_snippet_html($html) {
        $allowed = [
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'h5' => [],
            'h6' => [],
            'p'  => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'strong' => [],
            'em' => [],
            'a' => [ 'href' => true, 'title' => true ],
            'img' => [ 'src' => true, 'alt' => true ],
            'blockquote' => [],
            'code' => [],
            'pre' => [],
            'br' => [],
        ];

        if (class_exists('\\DOMDocument') && function_exists('libxml_use_internal_errors')) {
            $doc = new \DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            // Remove style and script tags.
            foreach (['style', 'script'] as $tag) {
                $nodes = $doc->getElementsByTagName($tag);
                for ($i = $nodes->length - 1; $i >= 0; $i--) {
                    $node = $nodes->item($i);
                    $node->parentNode->removeChild($node);
                }
            }

            // Remove HTML comments.
            $xpath = new \DOMXPath($doc);
            foreach ($xpath->query('//comment()') as $comment) {
                $comment->parentNode->removeChild($comment);
            }

            // Convert <br> tags to newline text nodes.
            $brs = $doc->getElementsByTagName('br');
            for ($i = $brs->length - 1; $i >= 0; $i--) {
                $br = $brs->item($i);
                $br->parentNode->replaceChild($doc->createTextNode("\n"), $br);
            }

            // Trim whitespace and add line breaks after block elements for readability.
            $blocks    = ['p', 'ul', 'ol', 'li', 'blockquote', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
            $blockList = [];
            foreach ($blocks as $tag) {
                $list = $doc->getElementsByTagName($tag);
                for ($i = 0; $i < $list->length; $i++) {
                    $blockList[] = $list->item($i);
                }
            }

            foreach ($blockList as $node) {
                foreach ($node->childNodes as $child) {
                    if ($child->nodeType === XML_TEXT_NODE) {
                        $child->nodeValue = trim($child->nodeValue);
                    }
                }
                $next = $node->nextSibling;
                $line = $doc->createTextNode("\n");
                if ($next) {
                    if ($next->nodeType !== XML_TEXT_NODE || strpos($next->nodeValue, "\n") !== 0) {
                        $node->parentNode->insertBefore($line, $next);
                    }
                } else {
                    $node->parentNode->appendChild($line);
                }
            }

            $body = $doc->getElementsByTagName('body')->item(0);
            $html = '';
            if ($body) {
                foreach ($body->childNodes as $child) {
                    $html .= $doc->saveHTML($child);
                }
            } else {
                $html = $doc->saveHTML();
            }

            // Collapse consecutive newlines.
            $html = preg_replace("/\n{2,}/", "\n", $html);
        }

        $html = wp_kses($html, $allowed);

        // Replace any remaining <br> tags in case DOMDocument is unavailable.
        $html = preg_replace('/(<br\s*\/?\s*>\s*)+/i', "\n", $html);
        $html = preg_replace('/\n{2,}/', "\n", $html);

        // Decode entities and normalize whitespace.
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $html);
        $html = preg_replace('/ {2,}/', ' ', $html);

        return trim($html);
    }

    /**
     * Convert a rule value to a string for display.
     *
     * @param mixed $value Rule value which may be array or string.
     * @return string Flattened rule string.
     */
    private function flatten_rule_value($value) {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $v) {
                $flat = $this->flatten_rule_value($v);
                if ($flat !== '') {
                    $parts[] = $flat;
                }
            }
            return implode("\n", $parts);
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            $value = (array) $value;
            return $this->flatten_rule_value($value);
        }

        return (string) $value;
    }

    /**
     * Build a newline-separated guidelines string from stored rules.
     *
     * @param string $base Option base like post_{type} or tax_{taxonomy}.
     * @return string
     */
    private function build_guidelines_text($base) {
        $rules = get_option('gm2_guideline_rules', []);
        if (!isset($rules[$base]) || !is_array($rules[$base])) {
            return '';
        }
        $parts = [];
        foreach ($rules[$base] as $text) {
            $flat = $this->flatten_rule_value($text);
            if ($flat !== '') {
                $parts[] = $flat;
            }
        }
        return implode("\n", $parts);
    }

    /**
     * Build a newline-separated content rules string from stored rules.
     *
     * @param string $base Option base like post_{type} or tax_{taxonomy}.
     * @return string
     */
    private function build_content_rules_text($base) {
        $rules = get_option('gm2_content_rules', []);
        if (!isset($rules[$base]) || !is_array($rules[$base])) {
            return '';
        }
        $parts = [];
        foreach ($rules[$base] as $text) {
            $flat = $this->flatten_rule_value($text);
            if ($flat !== '') {
                $parts[] = $flat;
            }
        }
        return implode("\n", $parts);
    }

    /**
     * Choose the best focus and long-tail keywords from Keyword Planner ideas.
     *
     * @param array $ideas Raw ideas array from Keyword Planner.
     * @return array{focus:string,long_tail:array}
     */
    private function select_best_keywords(array $ideas) {
        $filtered = [];
        foreach ($ideas as $idea) {
            if (empty($idea['metrics']) || !is_array($idea['metrics'])) {
                continue;
            }
            $comp = $idea['metrics']['competition'] ?? '';
            if ($comp !== 'LOW' && $comp !== 'MEDIUM') {
                continue;
            }
            $avg = (int) ($idea['metrics']['avg_monthly_searches'] ?? 0);
            $trend3 = 0;
            $trend12 = 0;
            if (!empty($idea['metrics']['monthly_search_volumes']) && is_array($idea['metrics']['monthly_search_volumes'])) {
                $vols = $idea['metrics']['monthly_search_volumes'];
                usort($vols, function ($a, $b) {
                    $ta = ($a['year'] ?? 0) * 12 + ($a['month'] ?? 0);
                    $tb = ($b['year'] ?? 0) * 12 + ($b['month'] ?? 0);
                    return $ta <=> $tb;
                });
                $n = count($vols);
                if ($n >= 3) {
                    $trend3 = $vols[$n - 1]['monthly_searches'] - $vols[$n - 3]['monthly_searches'];
                }
                if ($n >= 13) {
                    $trend12 = $vols[$n - 1]['monthly_searches'] - $vols[$n - 13]['monthly_searches'];
                }
            }
            $filtered[] = [
                'text'    => $idea['text'],
                'avg'     => $avg,
                'trend3'  => $trend3,
                'trend12' => $trend12,
            ];
        }

        usort($filtered, function ($a, $b) {
            if ($b['avg'] !== $a['avg']) {
                return $b['avg'] <=> $a['avg'];
            }
            $scoreA = $a['trend3'] + $a['trend12'];
            $scoreB = $b['trend3'] + $b['trend12'];
            return $scoreB <=> $scoreA;
        });

        if (empty($filtered)) {
            return [ 'focus' => '', 'long_tail' => [] ];
        }

        $focus = array_shift($filtered);
        $long  = array_column($filtered, 'text');

        return [ 'focus' => $focus['text'], 'long_tail' => array_slice($long, 0, 5) ];
    }

    /**
     * Fallback keyword selection using the original order when metrics are missing.
     *
     * @param array $ideas Raw ideas array from Keyword Planner.
     * @return array{focus:string,long_tail:array}
     */
    private function select_top_keywords(array $ideas) {
        $keywords = [];
        foreach ($ideas as $idea) {
            if (empty($idea['text'])) {
                continue;
            }
            $keywords[] = $idea['text'];
        }
        if (empty($keywords)) {
            return [ 'focus' => '', 'long_tail' => [] ];
        }
        $focus = array_shift($keywords);
        return [ 'focus' => $focus, 'long_tail' => array_slice($keywords, 0, 5) ];
    }

    /**
     * Generate keyword ideas using the configured AI provider when Keyword Planner is unavailable.
     *
     * @param string $query Seed keyword or phrase.
     * @return array|\WP_Error
     */
    private function ai_keyword_ideas($query) {
        $prompt = sprintf(
            'Provide a comma-separated list of short keyword ideas related to: %s',
            $query
        );
        $context = gm2_get_business_context_prompt();
        $used_keywords = gm2_get_used_focus_keywords();
        if ($context !== '') {
            $prompt = $context . "\n\n" . $prompt;
        }
        try {
            $resp = gm2_ai_send_prompt($prompt);
        } catch (\Throwable $e) {
            error_log('AI keyword ideas failed: ' . $e->getMessage());
            return new \WP_Error('ai_error', __('AI request failed', 'gm2-wordpress-suite'));
        }
        if (is_wp_error($resp)) {
            error_log('AI keyword ideas error: ' . $resp->get_error_message());
            return $resp;
        }
        $ideas = [];
        foreach (preg_split('/,\s*/', $resp) as $kw) {
            $kw = trim($kw);
            if ($kw !== '') {
                $ideas[] = ['text' => $kw];
            }
        }
        if (!$ideas) {
            return new \WP_Error('no_results', __('No keyword ideas found.', 'gm2-wordpress-suite'));
        }
        return $ideas;
    }

    public function ajax_check_rules() {
        check_ajax_referer('gm2_check_rules');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
        }

        $strlen = function ($str) {
            return function_exists('mb_strlen') ? mb_strlen($str) : strlen($str);
        };

        $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : 'post';
        $taxonomy  = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';

        $title       = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        $focus       = isset($_POST['focus']) ? sanitize_text_field(wp_unslash($_POST['focus'])) : '';
        $content     = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';

        $text       = wp_strip_all_tags($content);
        $word_count = str_word_count($text);

        $rules_option = get_option('gm2_content_rules', []);
        $rule_lines = [];
        if ($taxonomy && isset($rules_option['tax_' . $taxonomy]) && is_array($rules_option['tax_' . $taxonomy])) {
            foreach ($rules_option['tax_' . $taxonomy] as $txt) {
                $txt = $this->flatten_rule_value($txt);
                $rule_lines = array_merge($rule_lines, array_filter(array_map('trim', explode("\n", $txt))));
            }
        } elseif (isset($rules_option['post_' . $post_type]) && is_array($rules_option['post_' . $post_type])) {
            foreach ($rules_option['post_' . $post_type] as $txt) {
                $txt = $this->flatten_rule_value($txt);
                $rule_lines = array_merge($rule_lines, array_filter(array_map('trim', explode("\n", $txt))));
            }
        }
        if (!$rule_lines) {
            if ($taxonomy) {
                $rule_lines = [
                    __( 'Title length between 30 and 60 characters', 'gm2-wordpress-suite' ),
                    __( 'Description length between 50 and 160 characters', 'gm2-wordpress-suite' ),
                    __( 'Description has at least 150 words', 'gm2-wordpress-suite' ),
                    __( 'SEO title is unique', 'gm2-wordpress-suite' ),
                    __( 'Meta description is unique', 'gm2-wordpress-suite' ),
                ];
            } else {
                $rule_lines = [
                    __( 'Title length between 30 and 60 characters', 'gm2-wordpress-suite' ),
                    __( 'Description length between 50 and 160 characters', 'gm2-wordpress-suite' ),
                    __( 'At least one focus keyword', 'gm2-wordpress-suite' ),
                    __( 'Content has at least 300 words', 'gm2-wordpress-suite' ),
                    __( 'Focus keyword appears in first paragraph', 'gm2-wordpress-suite' ),
                    __( 'Only one H1 tag present', 'gm2-wordpress-suite' ),
                    __( 'Image alt text contains focus keyword', 'gm2-wordpress-suite' ),
                    __( 'At least one internal link', 'gm2-wordpress-suite' ),
                    __( 'At least one external link', 'gm2-wordpress-suite' ),
                    __( 'Focus keyword included in meta description', 'gm2-wordpress-suite' ),
                    __( 'Focus keyword is unique', 'gm2-wordpress-suite' ),
                    __( 'SEO title is unique', 'gm2-wordpress-suite' ),
                    __( 'Meta description is unique', 'gm2-wordpress-suite' ),
                ];
            }
        }

        $home_host = parse_url(home_url(), PHP_URL_HOST);
        $focus_main = trim(explode(',', $focus)[0]);
        $first_para = '';
        if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $content, $pm)) {
            $first_para = wp_strip_all_tags($pm[1]);
        }
        $h1_count = preg_match_all('/<h1\b[^>]*>/i', $content, $h1m);
        $internal = false;
        $external = false;
        $internal_count = 0;
        $external_count = 0;
        if (preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $content, $am)) {
            foreach ($am[1] as $href) {
                $host = parse_url($href, PHP_URL_HOST);
                if (!$host || $host === $home_host) {
                    $internal = true;
                    $internal_count++;
                } else {
                    $external = true;
                    $external_count++;
                }
            }
        }

        $img_focus = false;
        if ($focus_main !== '') {
            if (preg_match_all('/<img[^>]+alt=["\']([^"\']*)["\']/i', $content, $im)) {
                foreach ($im[1] as $alt) {
                    if (stripos($alt, $focus_main) !== false) {
                        $img_focus = true;
                        break;
                    }
                }
            }
        }

        $dup_title = false;
        $dup_desc  = false;
        $dup_focus = false;
        if ($title !== '') {
            $dup_title = !empty(get_posts([
                'post_type'      => $this->get_supported_post_types(),
                'post_status'    => 'any',
                'meta_key'       => '_gm2_title',
                'meta_value'     => $title,
                'fields'         => 'ids',
                'posts_per_page' => 1,
            ]));
            $t = get_terms([
                'taxonomy'   => $this->get_supported_taxonomies(),
                'hide_empty' => false,
                'meta_query' => [ [ 'key' => '_gm2_title', 'value' => $title ] ],
                'fields'     => 'ids',
                'number'     => 1,
            ]);
            if (!is_wp_error($t) && !empty($t)) {
                $dup_title = true;
            }
        }
        if ($description !== '') {
            $dup_desc = !empty(get_posts([
                'post_type'      => $this->get_supported_post_types(),
                'post_status'    => 'any',
                'meta_key'       => '_gm2_description',
                'meta_value'     => $description,
                'fields'         => 'ids',
                'posts_per_page' => 1,
            ]));
            $t = get_terms([
                'taxonomy'   => $this->get_supported_taxonomies(),
                'hide_empty' => false,
                'meta_query' => [ [ 'key' => '_gm2_description', 'value' => $description ] ],
                'fields'     => 'ids',
                'number'     => 1,
            ]);
            if (!is_wp_error($t) && !empty($t)) {
                $dup_desc = true;
            }
        }

        if ($focus !== '') {
            $dup_focus = !empty(get_posts([
                'post_type'      => $this->get_supported_post_types(),
                'post_status'    => 'any',
                'meta_key'       => '_gm2_focus_keywords',
                'meta_value'     => $focus,
                'meta_compare'   => 'LIKE',
                'fields'         => 'ids',
                'posts_per_page' => 1,
            ]));
            $t = get_terms([
                'taxonomy'   => $this->get_supported_taxonomies(),
                'hide_empty' => false,
                'meta_query' => [ [ 'key' => '_gm2_focus_keywords', 'value' => $focus, 'compare' => 'LIKE' ] ],
                'fields'     => 'ids',
                'number'     => 1,
            ]);
            if (!is_wp_error($t) && !empty($t)) {
                $dup_focus = true;
            }
        }

        $results = [];
        $min_internal = (int) get_option('gm2_min_internal_links', 1);
        $min_external = (int) get_option('gm2_min_external_links', 1);
        foreach ($rule_lines as $line) {
            $key  = sanitize_title($line);
            $pass = false;
            if (preg_match('/title.*?(\d+).*?(\d+)/i', $line, $m)) {
                $min  = (int) $m[1];
                $max  = (int) $m[2];
                $pass = $strlen($title) >= $min && $strlen($title) <= $max;
            } elseif (preg_match('/description.*?(\d+).*?(\d+)/i', $line, $m)) {
                $min  = (int) $m[1];
                $max  = (int) $m[2];
                $pass = $strlen($description) >= $min && $strlen($description) <= $max;
            } elseif (stripos($line, 'first paragraph') !== false) {
                $pass = $focus_main !== '' && stripos($first_para, $focus_main) !== false;
            } elseif (stripos($line, 'one h1') !== false) {
                $pass = $h1_count === 1;
            } elseif (preg_match('/minimum\s*(\d+)\s*internal/i', $line, $m)) {
                $pass = $internal_count >= (int) $m[1];
            } elseif (preg_match('/minimum\s*(\d+)\s*external/i', $line, $m)) {
                $pass = $external_count >= (int) $m[1];
            } elseif (stripos($line, 'minimum x internal') !== false) {
                $pass = $internal_count >= $min_internal;
            } elseif (stripos($line, 'minimum x external') !== false) {
                $pass = $external_count >= $min_external;
            } elseif (stripos($line, 'internal link') !== false) {
                $pass = $internal;
            } elseif (stripos($line, 'external link') !== false) {
                $pass = $external;
            } elseif (stripos($line, 'alt text') !== false) {
                $pass = $img_focus;
            } elseif (stripos($line, 'meta description') !== false && stripos($line, 'focus keyword') !== false) {
                $pass = $focus_main !== '' && stripos($description, $focus_main) !== false;
            } elseif (stripos($line, 'title') !== false && stripos($line, 'unique') !== false) {
                $pass = !$dup_title;
            } elseif (stripos($line, 'description') !== false && stripos($line, 'unique') !== false) {
                $pass = !$dup_desc;
            } elseif (stripos($line, 'focus keyword') !== false && stripos($line, 'unique') !== false) {
                $pass = !$dup_focus;
            } elseif (stripos($line, 'focus keyword') !== false) {
                $pass = trim($focus) !== '';
            } elseif (preg_match('/(\d+).*words/i', $line, $m)) {
                $min  = (int) $m[1];
                $pass = $word_count >= $min;
            }
            $results[$key] = $pass;
        }

        wp_send_json_success($results);
    }

    public function ajax_keyword_ideas() {
        check_ajax_referer('gm2_keyword_ideas');
        if (!current_user_can('manage_options')) {
            wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
        }

        $creds_ok = trim(get_option('gm2_gads_developer_token', '')) !== '' &&
            trim(get_option('gm2_gads_customer_id', '')) !== '' &&
            get_option('gm2_google_refresh_token', '') !== '';

        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        if ($query === '') {
            wp_send_json_error( __( 'empty query', 'gm2-wordpress-suite' ) );
        }

        $fallback = false;
        if ($creds_ok) {
            $planner = new Gm2_Keyword_Planner();
            $ideas   = $planner->generate_keyword_ideas($query);
            if (is_wp_error($ideas)) {
                error_log('Keyword Planner error: ' . $ideas->get_error_message());
                $ideas = $this->ai_keyword_ideas($query);
                $fallback = true;
            }
        } else {
            $ideas = $this->ai_keyword_ideas($query);
            $fallback = true;
        }

        if (is_wp_error($ideas)) {
            error_log('Keyword ideas error: ' . $ideas->get_error_message());
            wp_send_json_error( __( 'Keyword ideas request failed', 'gm2-wordpress-suite' ) );
        }

        wp_send_json_success([
            'ideas'  => $ideas,
            'ai_only'=> $fallback,
        ]);
    }


    public function ajax_research_content_rules() {
        check_ajax_referer('gm2_research_content_rules');
        if (!current_user_can('manage_options')) {
            wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
        }

        $cats   = isset($_POST['categories']) ? sanitize_text_field(wp_unslash($_POST['categories'])) : '';
        $target = isset($_POST['target']) ? sanitize_key($_POST['target']) : '';

        if ($cats === '' || $target === '') {
            wp_send_json_error( __( 'missing parameters', 'gm2-wordpress-suite' ) );
        }

        $allowed = [];
        foreach ($this->get_supported_post_types() as $pt) {
            $allowed[] = 'post_' . $pt;
        }
        foreach ($this->get_supported_taxonomies() as $tax) {
            $allowed[] = 'tax_' . $tax;
        }
        if (!in_array($target, $allowed, true)) {
            wp_send_json_error( __( 'invalid target', 'gm2-wordpress-suite' ) );
        }

        if (strpos($target, 'post_') === 0) {
            $prompt_target = sprintf('for the %s post type', gm2_substr($target, 5));
        } else {
            $prompt_target = sprintf('for the %s taxonomy', gm2_substr($target, 4));
        }

        $context = gm2_get_business_context_prompt();
        $prompt  = '';
        if ($context !== '') {
            $prompt .= $context . "\n\n";
        }
        $prompt .= sprintf(
                "You are an SEO strategist. Use the business context above to generate a strict SEO ruleset %1\$s in WordPress. " .
                "These rules will be reused and prepended to future SEO prompts targeting %1\$s—so they must be reusable, highly relevant to the business context, and reduce the need for restating SEO principles in future tasks.\n\n" .
            
                "All rules must align tightly with the business’s brand tone, content strategy, target audience, SEO objectives, and product/service niche. Do not return generic best practices—each rule must be tailored to the context.\n\n" .
            
                "Each ruleset must include exactly 5 clear, strict, and verifiable rules per category below:\n" .
                "* SEO Title – 5 measurable rules for SEO titles %1\$s (e.g., 'must be under 60 characters', 'must include focus keyword in first 5 words').\n" .
                "* SEO Description – 5 rules for meta descriptions %1\$s (e.g., 'must include focus keyword', 'must end with a CTA').\n" .
                "* Focus Keywords – 5 strict rules for selecting one primary keyword per %1\$s (focused on uniqueness, search intent, and alignment with offerings).\n" .
                "* Long-Tail Keywords – 5 precise rules for identifying and incorporating long-tail phrases %1\$s (e.g., phrase length, placement in H2s or body, search relevance).\n" .
                "* Canonical URL – 5 rules for properly setting canonical URLs %1\$s (e.g., avoid pointing to paginated pages, must match preferred slug structure).\n" .
                "* Content – 5 actionable rules for content structure and SEO optimization %1\$s (e.g., heading use, media inclusion, internal linking, minimum word count).\n" .
                "* General Cohesive SEO Rules – 5 strict rules to ensure all above elements work together and align with business context (e.g., 'focus keyword must appear in title, description, and at least one H2').\n\n" .
            
                "Avoid soft language like 'should', 'consider', or 'try to'. Use direct, testable phrasing such as 'must', 'must not', 'only if'.\n\n" .
                "Return ONLY a JSON object using these keys: %2\$s. Each key maps to an array of 5 short, strict SEO rules (strings)."
            ,
                $prompt_target,
                $cats
            );
        
        $resp   = gm2_ai_send_prompt($prompt);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Content rules response: ' . $resp);
        }

        if (is_wp_error($resp)) {
            error_log('Content rules request failed: ' . $resp->get_error_message());
            wp_send_json_error( __( 'AI request failed', 'gm2-wordpress-suite' ) );
        }

        $clean = $this->sanitize_ai_json($resp);
        $data  = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            error_log('Content rules JSON decode error: ' . json_last_error_msg());
            wp_send_json_error( __( 'Invalid AI response', 'gm2-wordpress-suite' ) );
        }

        $rules = get_option('gm2_content_rules', []);
        if (!isset($rules[$target]) || !is_array($rules[$target])) {
            $rules[$target] = [];
        }

        $valid_slugs = [
            'seo_title', 'seo_description', 'focus_keywords',
            'long_tail_keywords', 'canonical_url', 'content', 'general'
        ];

        $alias_map = [
            'content_in_post'        => 'content',
            'content_in_page'        => 'content',
            'content_in_custom_post' => 'content',
            'content_in_product'     => 'content',
        ];

        $requested_slugs = [];
        foreach (array_filter(array_map('trim', explode(',', strtolower($cats)))) as $req) {
            $r = str_replace([' ', '-'], '_', $req);
            $r = preg_replace('/[^a-z0-9_]/', '', $r);
            if (isset($alias_map[$r])) {
                $r = $alias_map[$r];
            }
            if (in_array($r, $valid_slugs, true)) {
                $requested_slugs[] = $r;
            }
        }
        $requested_slugs = array_unique($requested_slugs);

        $formatted = [];
        foreach ($data as $cat => $text) {
            $key = strtolower(str_replace([' ', '-'], '_', $cat));
            $key = preg_replace('/[^a-z0-9_]/', '', $key);
            if (isset($alias_map[$key])) {
                $key = $alias_map[$key];
            }

            if (!in_array($key, $valid_slugs, true) || !in_array($key, $requested_slugs, true)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Discarded content rule category: ' . $key);
                }
                continue;
            }

            $text = sanitize_textarea_field(
                $this->flatten_rule_value($text)
            );
            $rules[$target][$key] = $text;
            $formatted[$key]     = $text;
        }

        if (empty($formatted)) {
            wp_send_json_error( __( 'Unrecognized categories', 'gm2-wordpress-suite' ) );
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Content rules formatted: ' . print_r($formatted, true));
        }

        update_option('gm2_content_rules', $rules);

        wp_send_json_success($formatted);
    }

    public function ajax_research_guideline_rules() {
        check_ajax_referer('gm2_research_guideline_rules');
        if (!current_user_can('manage_options')) {
            wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
        }

        $cats   = isset($_POST['categories']) ? sanitize_text_field(wp_unslash($_POST['categories'])) : '';
        $target = isset($_POST['target']) ? sanitize_key($_POST['target']) : '';

        if ($cats === '' || $target === '') {
            wp_send_json_error( __( 'missing parameters', 'gm2-wordpress-suite' ) );
        }

        $allowed = [];
        foreach ($this->get_supported_post_types() as $pt) {
            $allowed[] = 'post_' . $pt;
        }
        foreach ($this->get_supported_taxonomies() as $tax) {
            $allowed[] = 'tax_' . $tax;
        }
        if (!in_array($target, $allowed, true)) {
            wp_send_json_error( __( 'invalid target', 'gm2-wordpress-suite' ) );
        }

        if (strpos($target, 'post_') === 0) {
            $prompt_target = sprintf('for the %s post type', gm2_substr($target, 5));
        } else {
            $prompt_target = sprintf('for the %s taxonomy', gm2_substr($target, 4));
        }

        $context = gm2_get_business_context_prompt();
        $prompt  = '';
        if ($context !== '') {
            $prompt .= $context . "\n\n";
        }
        $prompt .= sprintf(
            "You are an SEO content strategist. Use the business context provided above as the foundation for every suggestion and recommendation in this task. " .
            "All guidelines must be tailored to the business’s niche, audience, tone, product focus, and SEO objectives. Avoid general or one-size-fits-all advice—every suggestion must reflect the specific context of this business.\n\n" .
        
            "This set of SEO guidelines and suggestions will be reused and prepended to future AI prompts %1\$s. " .
            "Therefore, ensure that every recommendation is reusable, highly relevant to the business context, and reduces the need to restate SEO strategy in future prompts targeting %1\$s.\n\n" .
        
            "Generate actionable SEO content guidelines and helpful best practices %1\$s in WordPress. Do not return strict rules—provide flexible, business-relevant suggestions that writers, strategists, and SEO tools can interpret and apply.\n\n" .
        
            "* SEO Title – Suggest 5 useful tips or best practices for creating optimized SEO titles %1\$s. Tailor them to the brand's voice, target audience, and keyword focus.\n" .
            "* SEO Description – Suggest 5 concise recommendations for writing SEO meta descriptions for %1\$s. Match tone, search intent, and page purpose.\n" .
            "* Focus Keywords – Provide 5 contextual guidelines for choosing a primary focus keyword for each %1\$s. Align with the company’s offerings and SEO goals.\n" .
            "* Long-Tail Keywords – Provide 5 suggestions for identifying and using long-tail keyword phrases %1\$s. Emphasize relevance and conversion potential.\n" .
            "* Canonical URL – Offer 5 best practices for setting canonical URLs properly %1\$s. Address indexation consistency and duplicate content prevention.\n" .
            "* Content – Offer 5 tips for writing and structuring content %1\$s. Consider format, tone, internal linking, keyword usage, and user experience.\n" .
            "* General Cohesive SEO Guidelines – Suggest 5 principles to ensure all the above elements work cohesively across %1\$s. Reinforce synergy, intent match, and business context continuity.\n\n" .
        
            "Return ONLY a JSON object. Use the following keys: %2\$s. Each key must contain an array of 5 concise, business-specific suggestions or best practices."
        ,
            $prompt_target,
            $cats
        );

        $resp   = gm2_ai_send_prompt($prompt);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Guideline rules response: ' . $resp);
        }

        if (is_wp_error($resp)) {
            error_log('Guideline rules request failed: ' . $resp->get_error_message());
            wp_send_json_error( __( 'AI request failed', 'gm2-wordpress-suite' ) );
        }

        $clean = $this->sanitize_ai_json($resp);
        $data  = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            error_log('Guideline rules JSON decode error: ' . json_last_error_msg());
            wp_send_json_error( __( 'Invalid AI response', 'gm2-wordpress-suite' ) );
        }

        $rules = get_option('gm2_guideline_rules', []);
        if (!isset($rules[$target]) || !is_array($rules[$target])) {
            $rules[$target] = [];
        }

        $valid_slugs = [
            'seo_title', 'seo_description', 'focus_keywords',
            'long_tail_keywords', 'canonical_url', 'content', 'general'
        ];

        $alias_map = [
            'content_in_post'        => 'content',
            'content_in_page'        => 'content',
            'content_in_custom_post' => 'content',
            'content_in_product'     => 'content',
        ];

        $requested_slugs = [];
        foreach (array_filter(array_map('trim', explode(',', strtolower($cats)))) as $req) {
            $r = str_replace([' ', '-'], '_', $req);
            $r = preg_replace('/[^a-z0-9_]/', '', $r);
            if (isset($alias_map[$r])) {
                $r = $alias_map[$r];
            }
            if (in_array($r, $valid_slugs, true)) {
                $requested_slugs[] = $r;
            }
        }
        $requested_slugs = array_unique($requested_slugs);

        $formatted = [];
        foreach ($data as $cat => $text) {
            $key = strtolower(str_replace([' ', '-'], '_', $cat));
            $key = preg_replace('/[^a-z0-9_]/', '', $key);
            if (isset($alias_map[$key])) {
                $key = $alias_map[$key];
            }

            if (!in_array($key, $valid_slugs, true) || !in_array($key, $requested_slugs, true)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Discarded guideline rule category: ' . $key);
                }
                continue;
            }

            $text = sanitize_textarea_field(
                $this->flatten_rule_value($text)
            );
            $rules[$target][$key] = $text;
            $formatted[$key]     = $text;
        }

        if (empty($formatted)) {
            wp_send_json_error( __( 'Unrecognized categories', 'gm2-wordpress-suite' ) );
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Guideline rules formatted: ' . print_r($formatted, true));
        }

        update_option('gm2_guideline_rules', $rules);

        wp_send_json_success($formatted);
    }

    public function ajax_ai_research() {
        check_ajax_referer('gm2_ai_research');

        $post_id  = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $term_id  = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';

        if ($term_id) {
            if (!current_user_can('edit_term', $term_id)) {
                $this->debug_log('AI Research: permission denied for term ' . $term_id);
                wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
            }
        } else {
            if (!current_user_can('edit_posts')) {
                $this->debug_log('AI Research: permission denied for post');
                wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
            }
        }

        $refresh = !empty($_POST['refresh']);

        if (!$refresh) {
            $stored = '';
            if ($post_id) {
                $stored = get_post_meta($post_id, '_gm2_ai_research', true);
            } elseif ($term_id && $taxonomy) {
                $stored = get_term_meta($term_id, '_gm2_ai_research', true);
            }
            if ($stored) {
                $data = json_decode($stored, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    wp_send_json_success($data);
                }
            }
        }

        $title = $url = '';
        $seo_title = $seo_description = $focus = $canonical = '';

        if ($post_id) {
            $post = get_post($post_id);
            if (!$post) {
                $this->debug_log('AI Research: invalid post ID ' . $post_id);
                wp_send_json_error( __( 'invalid post', 'gm2-wordpress-suite' ) );
            }
            $title = get_the_title($post);
            $url   = get_permalink($post);
            $seo_title       = get_post_meta($post_id, '_gm2_title', true);
            $seo_description = get_post_meta($post_id, '_gm2_description', true);
            $focus           = get_post_meta($post_id, '_gm2_focus_keywords', true);
            $canonical       = get_post_meta($post_id, '_gm2_canonical', true);
        } elseif ($term_id && $taxonomy) {
            $term = get_term($term_id, $taxonomy);
            if (!$term || is_wp_error($term)) {
                $this->debug_log('AI Research: invalid term ' . $term_id . ' for taxonomy ' . $taxonomy);
                wp_send_json_error( __( 'invalid term', 'gm2-wordpress-suite' ) );
            }
            $title = $term->name;
            $url   = get_term_link($term, $taxonomy);
            if (is_wp_error($url)) {
                $url = '';
            }
            $seo_title       = get_term_meta($term_id, '_gm2_title', true);
            $seo_description = get_term_meta($term_id, '_gm2_description', true);
            $focus           = get_term_meta($term_id, '_gm2_focus_keywords', true);
            $canonical       = get_term_meta($term_id, '_gm2_canonical', true);
        } else {
            $this->debug_log('AI Research: invalid parameters');
            wp_send_json_error( __( 'invalid parameters', 'gm2-wordpress-suite' ) );
        }

        // override with submitted values if provided
        if (isset($_POST['seo_title'])) {
            $seo_title = sanitize_text_field(wp_unslash($_POST['seo_title']));
        }
        if (isset($_POST['seo_description'])) {
            $seo_description = sanitize_textarea_field(wp_unslash($_POST['seo_description']));
        }
        if (isset($_POST['focus_keywords'])) {
            $focus = sanitize_text_field(wp_unslash($_POST['focus_keywords']));
        }
        if (isset($_POST['canonical'])) {
            $canonical = esc_url_raw(wp_unslash($_POST['canonical']));
        }

        $extra_context = '';
        if (isset($_POST['extra_context'])) {
            $extra_context = sanitize_textarea_field(wp_unslash($_POST['extra_context']));
        }

        $html        = $this->get_rendered_html($post_id, $term_id, $taxonomy);
        $html_issues = [];
        $snippet     = trim($html);

        $guidelines = '';
        $content_rules = '';
        if ($post_id && !empty($post)) {
            $guidelines    = $this->build_guidelines_text('post_' . $post->post_type);
            $content_rules = $this->build_content_rules_text('post_' . $post->post_type);
        } elseif ($term_id && $taxonomy) {
            $guidelines    = $this->build_guidelines_text('tax_' . $taxonomy);
            $content_rules = $this->build_content_rules_text('tax_' . $taxonomy);
        }
        $guidelines    = trim($guidelines);
        $content_rules = trim($content_rules);

        $context = gm2_get_business_context_prompt();
        $used_keywords = gm2_get_used_focus_keywords();

            $prompt  = '';
            if ($context !== '') {
                $prompt .= "[BUSINESS CONTEXT]\n" . $context . "\n\n";
            }
            
            $prompt .= "[PAGE DETAILS]\n";
            $prompt .= "Title: {$title}\nURL: {$url}\n";
            $prompt .= "Existing SEO Title: {$seo_title}\nSEO Description: {$seo_description}\n";
            $prompt .= "Focus Keywords: {$focus}\nCanonical: {$canonical}\n";
            if (!empty($used_keywords)) {
                $prompt .= "Existing focus keywords: " . implode(', ', $used_keywords) . "\n";
            }
            
            if ($extra_context !== '') {
                $prompt .= "Extra context: {$extra_context}\n";
            }
            if ($snippet !== '') {
                $prompt .= "\n[PAGE HTML]\n" . $snippet . "\n";
            }
            
            $prompt .= "\n[SEO TASK]\n";
            $prompt .= <<<TEXT
            Act as a senior SEO strategist. Based on the provided metadata, business context, and page HTML, generate a concise list of high-quality **seed keywords** that can be expanded into long-tail keywords later. 
            
            Requirements:
            - Keywords must be closely tied to the page’s main topic, content structure, and business audience.
            - Avoid overly broad keywords; focus on high-intent, mid-difficulty search phrases.
            - Output only relevant keywords that will serve as strong input to keyword planner tools.
            
            Return ONLY a flat JSON array named `seed_keywords`, like this:
            
            {
              "seed_keywords": ["keyword one", "keyword two", "keyword three"]
            }
            
            Do NOT include explanation or formatting outside the JSON.
            TEXT;

        try {
            $resp = gm2_ai_send_prompt($prompt);
        } catch (\Throwable $e) {
            error_log('AI Research query failed: ' . $e->getMessage());
            wp_send_json_error(__('AI request failed', 'gm2-wordpress-suite'));
        }

        if (is_wp_error($resp)) {
            error_log('AI Research error: ' . $resp->get_error_message());
            wp_send_json_error(__('AI request failed', 'gm2-wordpress-suite'));
        }

        $clean = $this->sanitize_ai_json($resp);
        $data  = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            error_log('AI Research JSON decode error: ' . json_last_error_msg());
            wp_send_json_error(__('Invalid AI response', 'gm2-wordpress-suite'));
        }


        $seed_value = [];
        if (isset($data['seed_keywords'])) {
            $seed_value = $data['seed_keywords'];
        } elseif (isset($data['focus_keywords'])) {
            $seed_value = $data['focus_keywords'];
        }

        if (is_array($seed_value)) {
            $seeds = array_filter(array_map('trim', $seed_value));
        } else {
            $seeds = array_filter(array_map('trim', explode(',', (string) $seed_value)));
        }

        if ($seeds) {
            $used_lower = array_map('strtolower', $used_keywords);
            $seeds = array_values(array_filter($seeds, function($kw) use ($used_lower) {
                return !in_array(strtolower($kw), $used_lower, true);
            }));
        }

        $final_focus = '';
        $final_long  = [];
        $kwp_notice  = '';

        if (!$seeds) {
            $query = $seo_title !== '' ? $seo_title : ($seo_description !== '' ? $seo_description : $title);
            $ideas = $this->ai_keyword_ideas($query);
            if (!is_wp_error($ideas)) {
                $seeds = array_map(function($i) { return $i['text']; }, $ideas);
                $chosen = $this->select_top_keywords($ideas);
                $final_focus = $chosen['focus'];
                $final_long  = $chosen['long_tail'];
                $kwp_notice  = __('AI response contained no seed keywords—using generated suggestions.', 'gm2-wordpress-suite');
            }
        }

        if ($seeds) {
            $seed_ideas = array_map(function($kw) { return ['text' => $kw]; }, $seeds);

            $creds_ok = trim(get_option('gm2_gads_developer_token', '')) !== '' &&
                trim(get_option('gm2_gads_customer_id', '')) !== '' &&
                get_option('gm2_google_refresh_token', '') !== '';

            if ($creds_ok) {
                $planner = new Gm2_Keyword_Planner();
                $ideas = [];
                $ideas_error = null;
                try {
                    foreach ($seeds as $kw) {
                        $res = $planner->generate_keyword_ideas($kw);
                        if (is_wp_error($res)) {
                            $ideas_error = $res;
                            break;
                        }
                        $ideas = array_merge($ideas, $res);
                    }
                } catch (\Throwable $e) {
                    error_log('Keyword Planner request failed: ' . $e->getMessage());
                    $ideas_error = new \WP_Error('kwp_error', $e->getMessage());
                }

                if ($ideas_error || empty($ideas)) {
                    if ($ideas_error) {
                        error_log('Keyword Planner error: ' . $ideas_error->get_error_message());
                    }
                    $kwp_notice = __('Google Ads keyword research unavailable—using AI suggestions only.', 'gm2-wordpress-suite');
                    $chosen = $this->select_top_keywords($seed_ideas);
                } else {
                    $chosen = $this->select_best_keywords($ideas);
                    if ($chosen['focus'] === '' && empty($chosen['long_tail'])) {
                        $kwp_notice = __('Google Ads API did not return keyword metrics.', 'gm2-wordpress-suite');
                        $raw = $planner->get_last_response_body();
                        error_log('Keyword Planner returned no metrics: ' . $raw);
                        $chosen = $this->select_top_keywords($ideas);
                    }
                }
            } else {
                $kwp_notice = __('Google Ads keyword research unavailable—using AI suggestions only.', 'gm2-wordpress-suite');
                $chosen = $this->select_top_keywords($seed_ideas);
            }

            $final_focus = $chosen['focus'] ?: $seeds[0];
            $final_long  = $chosen['long_tail'];
        }

        $prompt2 = '';
            if ($context !== '') {
                $prompt2 .= "[BUSINESS CONTEXT]\n" . $context . "\n\n";
            }
            if ($guidelines !== '') {
                $prompt2 .= "SEO guidelines:\n" . $guidelines . "\n\n";
            }
            if ($content_rules !== '') {
                $prompt2 .= "Content Rules:\n" . $content_rules . "\n\n";
            }
            
            $prompt2 .= "Page title: {$title}\nURL: {$url}\n";
            $prompt2 .= "Focus Keyword: {$final_focus}\n";
            if (!empty($used_keywords)) {
                $prompt2 .= "Existing focus keywords: " . implode(', ', $used_keywords) . "\n";
            }
            if ($final_long) {
                $prompt2 .= "Long-tail Keywords: " . implode(', ', $final_long) . "\n";
            }
            if ($extra_context !== '') {
                $prompt2 .= "Extra context: {$extra_context}\n";
            }
            if ($snippet !== '') {
                $prompt2 .= "Page HTML: {$snippet}\n";
            }
            
            $prompt2 .= "\n[FINAL SEO TASK]\n";
            $prompt2 .= <<<TEXT
            Act as a senior SEO expert. Using the provided business context, focus and long-tail keywords, content rules, and HTML, optimize this page for maximum search engine visibility and user engagement. 
            Your task is to return final SEO metadata and improvement instructions that align with the goal of ranking this page in the **top 3 Google results** for the target keyword.
            
            Ensure:
            - All outputs follow the guidelines and are contextual to the business.
            - Preserve meaningful content and tone, but update metadata and structure where needed.
            - Include long-tail support and highlight content opportunities.
            
            Return only a JSON object with the following keys:
            {
              "seo_title": "...",
              "description": "...",
              "focus_keywords": ["...", "..."],
              "long_tail_keywords": ["...", "..."],
              "seed_keywords": ["...", "..."],
              "canonical": "...",
              "page_name": "...",
              "slug": "...",
              "updated_html": "...",  // edited HTML version with suggested on-page changes
              "content_suggestions": ["...", "..."],
              "html_issues": ["...", "..."]
            }
            
            Only output this JSON. Do not include any commentary, labels, or extra formatting.
            TEXT;


        try {
            $resp2 = gm2_ai_send_prompt($prompt2);
        } catch (\Throwable $e) {
            error_log('AI Research query failed: ' . $e->getMessage());
            wp_send_json_error(__('AI request failed', 'gm2-wordpress-suite'));
        }

        if (is_wp_error($resp2)) {
            error_log('AI Research error: ' . $resp2->get_error_message());
            wp_send_json_error(__('AI request failed', 'gm2-wordpress-suite'));
        }

        $resp2_clean = $this->sanitize_ai_json($resp2);
        try {
            $data2 = json_decode($resp2_clean, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Research JSON decode failed: ' . $resp2);
                error_log('AI Research JSON error: ' . json_last_error_msg());
            }

            $trimmed = rtrim($resp2_clean);
            $data2   = null;
            while ($trimmed !== '') {
                $result = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
                    $data2 = $result;
                    break;
                }
                $pos = strrpos($trimmed, '}');
                if ($pos === false) {
                    $trimmed = substr($trimmed, 0, -1);
                } else {
                    $trimmed = substr($trimmed, 0, $pos);
                }
            }

            if ($data2 === null) {
                wp_send_json_error(__('Invalid AI response', 'gm2-wordpress-suite'));
            }
        }

        if (!is_array($data2)) {
            wp_send_json_error( __( 'Invalid AI response', 'gm2-wordpress-suite' ) );
        }

        if (!isset($data2['html_issues'])) {
            $data2['html_issues'] = [];
        }
        $data2['html_issues'] = array_merge($data2['html_issues'], $html_issues);
        $data2['focus_keywords'] = $final_focus;
        $data2['long_tail_keywords'] = $final_long;
        $data2['seed_keywords'] = implode(', ', $seeds);
        if ($kwp_notice !== '') {
            $data2['kwp_notice'] = $kwp_notice;
        }
        $slug = isset($data2['slug']) ? sanitize_title($data2['slug']) : '';
        if ($slug !== '') {
            $data2['slug'] = $slug;
        }

        $used_lower = array_map('strtolower', $used_keywords);
        if (isset($data2['focus_keywords'])) {
            if (is_array($data2['focus_keywords'])) {
                $data2['focus_keywords'] = array_values(array_filter($data2['focus_keywords'], function($kw) use ($used_lower) {
                    return !in_array(strtolower($kw), $used_lower, true);
                }));
                if (count($data2['focus_keywords']) === 1) {
                    $data2['focus_keywords'] = $data2['focus_keywords'][0];
                }
            } else {
                if (in_array(strtolower($data2['focus_keywords']), $used_lower, true)) {
                    $data2['focus_keywords'] = '';
                }
            }
        }
        if (isset($data2['long_tail_keywords'])) {
            if (!is_array($data2['long_tail_keywords'])) {
                $data2['long_tail_keywords'] = array_filter(array_map('trim', explode(',', (string) $data2['long_tail_keywords'])));
            }
            $data2['long_tail_keywords'] = array_values(array_filter($data2['long_tail_keywords'], function($kw) use ($used_lower) {
                return !in_array(strtolower($kw), $used_lower, true);
            }));
        }

        if ($post_id) {
            update_post_meta($post_id, '_gm2_ai_research', wp_json_encode($data2));
            $data2['undo'] = (bool) (
                get_post_meta($post_id, '_gm2_prev_title', true) !== '' ||
                get_post_meta($post_id, '_gm2_prev_description', true) !== '' ||
                get_post_meta($post_id, '_gm2_prev_slug', true) !== '' ||
                get_post_meta($post_id, '_gm2_prev_post_title', true) !== ''
            );
        } elseif ($term_id) {
            update_term_meta($term_id, '_gm2_ai_research', wp_json_encode($data2));
            $data2['undo'] = (
                get_term_meta($term_id, '_gm2_prev_title', true) !== '' ||
                get_term_meta($term_id, '_gm2_prev_description', true) !== ''
            );
        }

        wp_send_json_success($data2);
    }

    public function ajax_generate_tax_description() {
        check_ajax_referer('gm2_ai_generate_tax_description');

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';
        $term_id  = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $name     = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

        if (!$taxonomy || !taxonomy_exists($taxonomy)) {
            wp_send_json_error( __( 'invalid taxonomy', 'gm2-wordpress-suite' ) );
        }

        if ($term_id) {
            if (!current_user_can('edit_term', $term_id)) {
                wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
            }
        } else {
            $cap = apply_filters('gm2_bulk_ai_tax_capability', 'manage_categories');
            if (!current_user_can($cap)) {
                wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
            }
        }

        $template = get_option('gm2_tax_desc_prompt', 'Generate a unique, SEO-optimized description for the term "{name}". The goal is to help this taxonomy page rank in the **top 3 Google search results** for its target keyword. The description must be relevant, engaging, and built on the business context.');

        $prompt = strtr($template, [
            '{name}'     => $name,
            '{taxonomy}' => $taxonomy,
        ]);
        
        $seo_title       = '';
        $seo_description = '';
        $focus_keywords  = '';
        $canonical       = '';
        
        if ($term_id) {
            $seo_title       = sanitize_text_field(get_term_meta($term_id, '_gm2_title', true));
            $seo_description = sanitize_text_field(get_term_meta($term_id, '_gm2_description', true));
            $focus_keywords  = sanitize_text_field(get_term_meta($term_id, '_gm2_focus_keywords', true));
            $canonical       = esc_url_raw(get_term_meta($term_id, '_gm2_canonical', true));
        }
        
        $prompt .= "\n\n[SEO METADATA]\n";
        if ($seo_title !== '') {
            $prompt .= "Existing SEO Title: {$seo_title}\n";
        }
        if ($seo_description !== '') {
            $prompt .= "SEO Description: {$seo_description}\n";
        }
        if ($focus_keywords !== '') {
            $prompt .= "Focus Keywords: {$focus_keywords}\n";
        }
        if ($canonical !== '') {
            $prompt .= "Canonical: {$canonical}\n";
        }
        
        $tax_type = $this->describe_taxonomy_type($taxonomy);
        if ($tax_type !== '') {
            $prompt .= "Taxonomy Type: {$tax_type}\n";
        }
        
        $context = gm2_get_business_context_prompt();
        if ($context !== '') {
            $prompt = "[BUSINESS CONTEXT]\n" . $context . "\n\n" . $prompt;
        }
        
        $prompt .= <<<TEXT
        
        [SEO TASK]\n
        Generate a compelling and SEO-optimized taxonomy description for the term "{$name}". This is for a {$tax_type} page.
        
        Requirements:
        - Integrate the focus keyword naturally within the first 160 characters
        - Match user search intent and improve search engine ranking performance
        - Incorporate semantically relevant variations if applicable
        - Use brand tone and voice as reflected in the business context
        - Be informative, unique, and engaging — avoid generic fluff
        - Keep the length between 100–250 words, optimized for Google snippet display
        - Include a subtle call-to-action or forward-navigation cue where possible
        
        Output ONLY the description text. Do not include labels, JSON, or commentary.
        TEXT;

        $resp = gm2_ai_send_prompt($prompt);

        if (is_wp_error($resp)) {
            error_log('Tax description request failed: ' . $resp->get_error_message());
            wp_send_json_error( __( 'AI request failed', 'gm2-wordpress-suite' ) );
        }

        if ($term_id) {
            wp_update_term($term_id, $taxonomy, ['description' => $resp]);
        }

        wp_send_json_success($resp);
    }

    public function ajax_bulk_ai_apply() {
        check_ajax_referer('gm2_bulk_ai_apply');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
        }

        $data = ['ID' => $post_id];
        if (isset($_POST['title'])) {
            update_post_meta($post_id, '_gm2_prev_post_title', get_post_field('post_title', $post_id));
            $data['post_title'] = sanitize_text_field(wp_unslash($_POST['title']));
        }
        if (isset($_POST['slug'])) {
            update_post_meta($post_id, '_gm2_prev_slug', get_post_field('post_name', $post_id));
            $data['post_name'] = sanitize_title(wp_unslash($_POST['slug']));
        }
        if (count($data) > 1) {
            wp_update_post($data);
        }
        if (isset($_POST['seo_title'])) {
            update_post_meta($post_id, '_gm2_prev_title', get_post_meta($post_id, '_gm2_title', true));
            update_post_meta($post_id, '_gm2_title', sanitize_text_field(wp_unslash($_POST['seo_title'])));
        }
        if (isset($_POST['seo_description'])) {
            update_post_meta($post_id, '_gm2_prev_description', get_post_meta($post_id, '_gm2_description', true));
            update_post_meta($post_id, '_gm2_description', sanitize_textarea_field(wp_unslash($_POST['seo_description'])));
        }
        if (isset($_POST['focus_keywords'])) {
            update_post_meta($post_id, '_gm2_prev_focus_keywords', get_post_meta($post_id, '_gm2_focus_keywords', true));
            update_post_meta($post_id, '_gm2_focus_keywords', sanitize_text_field(wp_unslash($_POST['focus_keywords'])));
        }
        if (isset($_POST['long_tail_keywords'])) {
            update_post_meta($post_id, '_gm2_prev_long_tail_keywords', get_post_meta($post_id, '_gm2_long_tail_keywords', true));
            update_post_meta($post_id, '_gm2_long_tail_keywords', sanitize_text_field(wp_unslash($_POST['long_tail_keywords'])));
        }

        $response = [
            'title'            => get_post_field('post_title', $post_id),
            'seo_title'        => get_post_meta($post_id, '_gm2_title', true),
            'description'      => get_post_meta($post_id, '_gm2_description', true),
            'focus_keywords'   => get_post_meta($post_id, '_gm2_focus_keywords', true),
            'long_tail_keywords' => get_post_meta($post_id, '_gm2_long_tail_keywords', true),
        ];

        wp_send_json_success($response);
    }

    public function ajax_bulk_ai_apply_batch() {
        check_ajax_referer('gm2_bulk_ai_apply');

        $posts = isset($_POST['posts']) ? json_decode(wp_unslash($_POST['posts']), true) : null;
        if (!is_array($posts)) {
            wp_send_json_error(__('invalid data', 'gm2-wordpress-suite'));
        }

        $updated = [];

        foreach ($posts as $post_id => $fields) {
            $post_id = absint($post_id);
            if (!$post_id || !current_user_can('edit_post', $post_id)) {
                continue;
            }

            $data = ['ID' => $post_id];
            if (isset($fields['title'])) {
                update_post_meta($post_id, '_gm2_prev_post_title', get_post_field('post_title', $post_id));
                $data['post_title'] = sanitize_text_field($fields['title']);
            }
            if (isset($fields['slug'])) {
                update_post_meta($post_id, '_gm2_prev_slug', get_post_field('post_name', $post_id));
                $data['post_name'] = sanitize_title($fields['slug']);
            }
            if (count($data) > 1) {
                wp_update_post($data);
            }
            if (isset($fields['seo_title'])) {
                update_post_meta($post_id, '_gm2_prev_title', get_post_meta($post_id, '_gm2_title', true));
                update_post_meta($post_id, '_gm2_title', sanitize_text_field($fields['seo_title']));
            }
            if (isset($fields['seo_description'])) {
                update_post_meta($post_id, '_gm2_prev_description', get_post_meta($post_id, '_gm2_description', true));
                update_post_meta($post_id, '_gm2_description', sanitize_textarea_field($fields['seo_description']));
            }
            if (isset($fields['focus_keywords'])) {
                update_post_meta($post_id, '_gm2_prev_focus_keywords', get_post_meta($post_id, '_gm2_focus_keywords', true));
                update_post_meta($post_id, '_gm2_focus_keywords', sanitize_text_field($fields['focus_keywords']));
            }
            if (isset($fields['long_tail_keywords'])) {
                update_post_meta($post_id, '_gm2_prev_long_tail_keywords', get_post_meta($post_id, '_gm2_long_tail_keywords', true));
                update_post_meta($post_id, '_gm2_long_tail_keywords', sanitize_text_field($fields['long_tail_keywords']));
            }

            $updated[$post_id] = [
                'title'            => get_post_field('post_title', $post_id),
                'seo_title'        => get_post_meta($post_id, '_gm2_title', true),
                'description'      => get_post_meta($post_id, '_gm2_description', true),
                'focus_keywords'   => get_post_meta($post_id, '_gm2_focus_keywords', true),
                'long_tail_keywords' => get_post_meta($post_id, '_gm2_long_tail_keywords', true),
            ];
        }

        wp_send_json_success(['updated' => $updated]);
    }

    public function ajax_bulk_ai_undo() {
        check_ajax_referer('gm2_bulk_ai_apply');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
        }

        $data     = ['ID' => $post_id];
        $changed  = false;

        $prev = get_post_meta($post_id, '_gm2_prev_post_title', true);
        if ($prev !== '') {
            $data['post_title'] = $prev;
            delete_post_meta($post_id, '_gm2_prev_post_title');
            $changed = true;
        }

        $prev = get_post_meta($post_id, '_gm2_prev_slug', true);
        if ($prev !== '') {
            $data['post_name'] = $prev;
            delete_post_meta($post_id, '_gm2_prev_slug');
            $changed = true;
        }

        if ($changed) {
            wp_update_post($data);
        }

        $seo_title = get_post_meta($post_id, '_gm2_prev_title', true);
        if ($seo_title !== '') {
            update_post_meta($post_id, '_gm2_title', $seo_title);
            delete_post_meta($post_id, '_gm2_prev_title');
        }

        $seo_desc = get_post_meta($post_id, '_gm2_prev_description', true);
        if ($seo_desc !== '') {
            update_post_meta($post_id, '_gm2_description', $seo_desc);
            delete_post_meta($post_id, '_gm2_prev_description');
        }

        $focus_prev = get_post_meta($post_id, '_gm2_prev_focus_keywords', true);
        if ($focus_prev !== '') {
            update_post_meta($post_id, '_gm2_focus_keywords', $focus_prev);
            delete_post_meta($post_id, '_gm2_prev_focus_keywords');
        }

        $long_prev = get_post_meta($post_id, '_gm2_prev_long_tail_keywords', true);
        if ($long_prev !== '') {
            update_post_meta($post_id, '_gm2_long_tail_keywords', $long_prev);
            delete_post_meta($post_id, '_gm2_prev_long_tail_keywords');
        }

        $response = [
            'title'            => get_the_title($post_id),
            'seo_title'        => get_post_meta($post_id, '_gm2_title', true),
            'description'      => get_post_meta($post_id, '_gm2_description', true),
            'focus_keywords'   => get_post_meta($post_id, '_gm2_focus_keywords', true),
            'long_tail_keywords' => get_post_meta($post_id, '_gm2_long_tail_keywords', true),
        ];

        wp_send_json_success($response);
    }

    public function ajax_bulk_ai_fetch_ids() {
        check_ajax_referer('gm2_bulk_ai_fetch_ids');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
        }

        $status        = sanitize_key($_POST['status'] ?? 'publish');
        $post_type     = sanitize_key($_POST['post_type'] ?? 'all');
        $terms         = isset($_POST['terms']) ? (array) $_POST['terms'] : [];
        $terms         = array_map('sanitize_text_field', $terms);
        $seo_status    = sanitize_key($_POST['seo_status'] ?? 'all');
        $seo_status    = in_array($seo_status, ['all', 'complete', 'incomplete', 'has_ai'], true) ? $seo_status : 'all';
        $missing_title = isset($_POST['missing_title']) && $_POST['missing_title'] === '1' ? '1' : '0';
        $missing_desc  = isset($_POST['missing_desc']) && $_POST['missing_desc'] === '1' ? '1' : '0';
        $search        = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $types = $this->get_supported_post_types();
        if ($post_type !== 'all' && in_array($post_type, $types, true)) {
            $types = [ $post_type ];
        }

        $args = [
            'post_type'      => $types,
            'post_status'    => $status,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];

        if ($search !== '') {
            $args['s'] = $search;
        }

        if ($terms) {
            $taxonomies = $this->get_supported_taxonomies();
            $tax_query  = [ 'relation' => 'OR' ];
            foreach ($terms as $t) {
                if (strpos($t, ':') === false) {
                    continue;
                }
                list($tax, $id) = explode(':', $t);
                if (in_array($tax, $taxonomies, true)) {
                    $tax_query[] = [
                        'taxonomy' => $tax,
                        'field'    => 'term_id',
                        'terms'    => absint($id),
                    ];
                }
            }
            if (count($tax_query) > 1) {
                $args['tax_query'] = $tax_query;
            }
        }

        $meta_query = [];
        if ($missing_title === '1') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
            ];
        }
        if ($missing_desc === '1') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
            ];
        }
        if ($seo_status === 'complete') {
            $meta_query[] = [ 'key' => '_gm2_title', 'value' => '', 'compare' => '!=' ];
            $meta_query[] = [ 'key' => '_gm2_description', 'value' => '', 'compare' => '!=' ];
        } elseif ($seo_status === 'incomplete') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
                [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
            ];
        } elseif ($seo_status === 'has_ai') {
            $meta_query[] = [ 'key' => '_gm2_ai_research', 'value' => '', 'compare' => '!=' ];
        }
        if ($meta_query) {
            $args['meta_query'] = array_merge([ 'relation' => 'AND' ], $meta_query);
        }

        $ids = get_posts($args);

        wp_send_json_success( [ 'ids' => array_map('absint', $ids) ] );
    }

    public function ajax_bulk_ai_tax_fetch_ids() {
        check_ajax_referer('gm2_bulk_ai_tax_fetch_ids');

        $cap = apply_filters('gm2_bulk_ai_tax_capability', 'manage_categories');
        if (!current_user_can($cap)) {
            wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
        }

        $taxonomy      = sanitize_key($_POST['taxonomy'] ?? 'all');
        $status        = sanitize_key($_POST['status'] ?? 'publish');
        $search        = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $seo_status    = sanitize_key($_POST['seo_status'] ?? 'all');
        $seo_status    = in_array($seo_status, ['all', 'complete', 'incomplete', 'has_ai'], true) ? $seo_status : 'all';
        $missing_title = isset($_POST['missing_title']) && $_POST['missing_title'] === '1' ? '1' : '0';
        $missing_desc  = isset($_POST['missing_desc']) && $_POST['missing_desc'] === '1' ? '1' : '0';

        $tax_list = $this->get_supported_taxonomies();
        $tax_arg  = ($taxonomy === 'all') ? $tax_list : $taxonomy;

        $args = [
            'taxonomy'   => $tax_arg,
            'hide_empty' => false,
            'status'     => $status,
        ];
        if ($search !== '') {
            $args['search'] = $search;
        }

        $meta_query = [];
        if ($seo_status === 'complete') {
            $meta_query[] = [ 'key' => '_gm2_title', 'value' => '', 'compare' => '!=' ];
            $meta_query[] = [ 'key' => '_gm2_description', 'value' => '', 'compare' => '!=' ];
        } elseif ($seo_status === 'incomplete') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
                [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
            ];
        } elseif ($seo_status === 'has_ai') {
            $meta_query[] = [ 'key' => '_gm2_ai_research', 'value' => '', 'compare' => '!=' ];
        }
        if ($missing_title === '1') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
            ];
        }
        if ($missing_desc === '1') {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
            ];
        }
        if ($meta_query) {
            $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_query);
        }

        $query = new \WP_Term_Query($args);
        $ids   = [];
        if (!empty($query->terms)) {
            foreach ($query->terms as $term) {
                $ids[] = $term->taxonomy . ':' . $term->term_id;
            }
        }

        wp_send_json_success( [ 'ids' => $ids ] );
    }

    public function ajax_bulk_ai_reset() {
        check_ajax_referer('gm2_bulk_ai_reset');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
        }

        $all   = isset($_POST['all']) && $_POST['all'] === '1';
        $ids      = [];
        $count    = 0;
        $cleared  = 0;

        if ($all) {
            $status        = sanitize_key($_POST['status'] ?? 'publish');
            $post_type     = sanitize_key($_POST['post_type'] ?? 'all');
            $terms         = isset($_POST['terms']) ? (array) $_POST['terms'] : [];
            $terms         = array_map('sanitize_text_field', $terms);
            $seo_status    = sanitize_key($_POST['seo_status'] ?? 'all');
            $seo_status    = in_array($seo_status, ['all', 'complete', 'incomplete', 'has_ai'], true) ? $seo_status : 'all';
            $missing_title = isset($_POST['missing_title']) && $_POST['missing_title'] === '1' ? '1' : '0';
            $missing_desc  = isset($_POST['missing_desc']) && $_POST['missing_desc'] === '1' ? '1' : '0';
            $search        = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

            $types = $this->get_supported_post_types();
            if ($post_type !== 'all' && in_array($post_type, $types, true)) {
                $types = [ $post_type ];
            }

            $args = [
                'post_type'      => $types,
                'post_status'    => $status,
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ];
            if ($search !== '') {
                $args['s'] = $search;
            }

            if ($terms) {
                $taxonomies = $this->get_supported_taxonomies();
                $tax_query  = [ 'relation' => 'OR' ];
                foreach ($terms as $t) {
                    if (strpos($t, ':') === false) {
                        continue;
                    }
                    list($tax, $id) = explode(':', $t);
                    if (in_array($tax, $taxonomies, true)) {
                        $tax_query[] = [
                            'taxonomy' => $tax,
                            'field'    => 'term_id',
                            'terms'    => absint($id),
                        ];
                    }
                }
                if (count($tax_query) > 1) {
                    $args['tax_query'] = $tax_query;
                }
            }

            $meta_query = [];
            if ($missing_title === '1') {
                $meta_query[] = [
                    'relation' => 'OR',
                    [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
                ];
            }
            if ($missing_desc === '1') {
                $meta_query[] = [
                    'relation' => 'OR',
                    [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
                ];
            }
            if ($seo_status === 'complete') {
                $meta_query[] = [ 'key' => '_gm2_title', 'value' => '', 'compare' => '!=' ];
                $meta_query[] = [ 'key' => '_gm2_description', 'value' => '', 'compare' => '!=' ];
            } elseif ($seo_status === 'incomplete') {
                $meta_query[] = [
                    'relation' => 'OR',
                    [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
                    [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
                ];
            } elseif ($seo_status === 'has_ai') {
                $meta_query[] = [ 'key' => '_gm2_ai_research', 'value' => '', 'compare' => '!=' ];
            }
            if ($meta_query) {
                $args['meta_query'] = array_merge([ 'relation' => 'AND' ], $meta_query);
            }

            $ids = get_posts($args);
        } else {
            $ids = isset($_POST['ids']) ? json_decode(wp_unslash($_POST['ids']), true) : [];
            if (!is_array($ids)) {
                wp_send_json_error( __( 'invalid data', 'gm2-wordpress-suite' ) );
            }
        }

        foreach ($ids as $post_id) {
            $post_id = absint($post_id);
            if (!$post_id || !current_user_can('edit_post', $post_id)) {
                continue;
            }
            delete_post_meta($post_id, '_gm2_title');
            delete_post_meta($post_id, '_gm2_description');
            delete_post_meta($post_id, '_gm2_prev_title');
            delete_post_meta($post_id, '_gm2_prev_description');
            delete_post_meta($post_id, '_gm2_prev_slug');
            delete_post_meta($post_id, '_gm2_prev_post_title');
            delete_post_meta($post_id, '_gm2_focus_keywords');
            delete_post_meta($post_id, '_gm2_long_tail_keywords');
            delete_post_meta($post_id, '_gm2_prev_focus_keywords');
            delete_post_meta($post_id, '_gm2_prev_long_tail_keywords');
            if (delete_post_meta($post_id, '_gm2_ai_research')) {
                $cleared++;
            }
            $count++;
        }

        wp_send_json_success( [ 'reset' => $count, 'cleared' => $cleared ] );
    }

    public function ajax_bulk_ai_clear() {
        check_ajax_referer('gm2_bulk_ai_clear');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
        }

        $ids = isset($_POST['ids']) ? json_decode(wp_unslash($_POST['ids']), true) : [];
        if (!is_array($ids)) {
            wp_send_json_error( __( 'invalid data', 'gm2-wordpress-suite' ) );
        }

        $count = 0;
        foreach ($ids as $post_id) {
            $post_id = absint($post_id);
            if (!$post_id || !current_user_can('edit_post', $post_id)) {
                continue;
            }
            delete_post_meta($post_id, '_gm2_ai_research');
            delete_post_meta($post_id, '_gm2_prev_focus_keywords');
            delete_post_meta($post_id, '_gm2_prev_long_tail_keywords');
            $count++;
        }

        wp_send_json_success( [ 'cleared' => $count ] );
    }

    public function ajax_ai_research_clear() {
        check_ajax_referer('gm2_ai_research');

        $post_id  = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $term_id  = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';

        if ($post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
            }
            delete_post_meta($post_id, '_gm2_ai_research');
            delete_post_meta($post_id, '_gm2_prev_focus_keywords');
            delete_post_meta($post_id, '_gm2_prev_long_tail_keywords');
            wp_send_json_success();
        } elseif ($term_id && $taxonomy) {
            if (!current_user_can('edit_term', $term_id)) {
                wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
            }
            delete_term_meta($term_id, '_gm2_ai_research');
            delete_term_meta($term_id, '_gm2_prev_focus_keywords');
            delete_term_meta($term_id, '_gm2_prev_long_tail_keywords');
            wp_send_json_success();
        }

        wp_send_json_error( __( 'invalid parameters', 'gm2-wordpress-suite' ) );
    }

    public function ajax_bulk_ai_tax_apply() {
        check_ajax_referer('gm2_bulk_ai_apply');

        $term_id  = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';
        if (!$term_id || !$taxonomy || !current_user_can('edit_term', $term_id)) {
            wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
        }

        if (isset($_POST['seo_title'])) {
            update_term_meta($term_id, '_gm2_prev_title', get_term_meta($term_id, '_gm2_title', true));
            update_term_meta($term_id, '_gm2_title', sanitize_text_field(wp_unslash($_POST['seo_title'])));
        }
        if (isset($_POST['seo_description'])) {
            update_term_meta($term_id, '_gm2_prev_description', get_term_meta($term_id, '_gm2_description', true));
            update_term_meta($term_id, '_gm2_description', sanitize_textarea_field(wp_unslash($_POST['seo_description'])));
        }
        if (isset($_POST['focus_keywords'])) {
            update_term_meta($term_id, '_gm2_prev_focus_keywords', get_term_meta($term_id, '_gm2_focus_keywords', true));
            update_term_meta($term_id, '_gm2_focus_keywords', sanitize_text_field(wp_unslash($_POST['focus_keywords'])));
        }
        if (isset($_POST['long_tail_keywords'])) {
            update_term_meta($term_id, '_gm2_prev_long_tail_keywords', get_term_meta($term_id, '_gm2_long_tail_keywords', true));
            update_term_meta($term_id, '_gm2_long_tail_keywords', sanitize_text_field(wp_unslash($_POST['long_tail_keywords'])));
        }

        $response = [
            'seo_title'       => get_term_meta($term_id, '_gm2_title', true),
            'seo_description' => get_term_meta($term_id, '_gm2_description', true),
            'focus_keywords'  => get_term_meta($term_id, '_gm2_focus_keywords', true),
            'long_tail_keywords' => get_term_meta($term_id, '_gm2_long_tail_keywords', true),
        ];

        wp_send_json_success($response);
    }

    public function ajax_bulk_ai_tax_undo() {
        check_ajax_referer('gm2_bulk_ai_apply');

        $term_id  = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';
        if (!$term_id || !$taxonomy || !current_user_can('edit_term', $term_id)) {
            wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
        }

        $prev = get_term_meta($term_id, '_gm2_prev_title', true);
        if ($prev !== '') {
            update_term_meta($term_id, '_gm2_title', $prev);
            delete_term_meta($term_id, '_gm2_prev_title');
        }

        $prev = get_term_meta($term_id, '_gm2_prev_description', true);
        if ($prev !== '') {
            update_term_meta($term_id, '_gm2_description', $prev);
            delete_term_meta($term_id, '_gm2_prev_description');
        }
        $prev = get_term_meta($term_id, '_gm2_prev_focus_keywords', true);
        if ($prev !== '') {
            update_term_meta($term_id, '_gm2_focus_keywords', $prev);
            delete_term_meta($term_id, '_gm2_prev_focus_keywords');
        }
        $prev = get_term_meta($term_id, '_gm2_prev_long_tail_keywords', true);
        if ($prev !== '') {
            update_term_meta($term_id, '_gm2_long_tail_keywords', $prev);
            delete_term_meta($term_id, '_gm2_prev_long_tail_keywords');
        }

        $response = [
            'seo_title'       => get_term_meta($term_id, '_gm2_title', true),
            'seo_description' => get_term_meta($term_id, '_gm2_description', true),
            'focus_keywords'  => get_term_meta($term_id, '_gm2_focus_keywords', true),
            'long_tail_keywords' => get_term_meta($term_id, '_gm2_long_tail_keywords', true),
        ];

        wp_send_json_success($response);
    }

    public function ajax_bulk_ai_tax_apply_batch() {
        check_ajax_referer('gm2_bulk_ai_apply');

        $terms = isset($_POST['terms']) ? json_decode(wp_unslash($_POST['terms']), true) : null;
        if (!is_array($terms)) {
            wp_send_json_error( __( 'invalid data', 'gm2-wordpress-suite' ) );
        }

        foreach ($terms as $key => $fields) {
            if (strpos($key, ':') === false) {
                continue;
            }
            list($taxonomy, $id) = explode(':', $key);
            $term_id = absint($id);
            if (!$term_id || !taxonomy_exists($taxonomy) || !current_user_can('edit_term', $term_id)) {
                continue;
            }
            if (isset($fields['seo_title'])) {
                update_term_meta($term_id, '_gm2_prev_title', get_term_meta($term_id, '_gm2_title', true));
                update_term_meta($term_id, '_gm2_title', sanitize_text_field($fields['seo_title']));
            }
            if (isset($fields['seo_description'])) {
                update_term_meta($term_id, '_gm2_prev_description', get_term_meta($term_id, '_gm2_description', true));
                update_term_meta($term_id, '_gm2_description', sanitize_textarea_field($fields['seo_description']));
            }
            if (isset($fields['focus_keywords'])) {
                update_term_meta($term_id, '_gm2_prev_focus_keywords', get_term_meta($term_id, '_gm2_focus_keywords', true));
                update_term_meta($term_id, '_gm2_focus_keywords', sanitize_text_field($fields['focus_keywords']));
            }
            if (isset($fields['long_tail_keywords'])) {
                update_term_meta($term_id, '_gm2_prev_long_tail_keywords', get_term_meta($term_id, '_gm2_long_tail_keywords', true));
                update_term_meta($term_id, '_gm2_long_tail_keywords', sanitize_text_field($fields['long_tail_keywords']));
            }
            $updated[$key] = [
                'seo_title'       => get_term_meta($term_id, '_gm2_title', true),
                'seo_description' => get_term_meta($term_id, '_gm2_description', true),
                'focus_keywords'  => get_term_meta($term_id, '_gm2_focus_keywords', true),
                'long_tail_keywords' => get_term_meta($term_id, '_gm2_long_tail_keywords', true),
            ];
        }

        wp_send_json_success(['updated' => $updated ?? []]);
    }

    public function ajax_bulk_ai_tax_reset() {
        check_ajax_referer('gm2_bulk_ai_tax_reset');

        $cap = apply_filters('gm2_bulk_ai_tax_capability', 'manage_categories');
        if (!current_user_can($cap)) {
            wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
        }

        $all   = isset($_POST['all']) && $_POST['all'] === '1';
        $ids   = [];
        $count = 0;

        if ($all) {
            $taxonomy      = sanitize_key($_POST['taxonomy'] ?? 'all');
            $status        = sanitize_key($_POST['status'] ?? 'publish');
            $search        = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            $seo_status    = sanitize_key($_POST['seo_status'] ?? 'all');
            $seo_status    = in_array($seo_status, ['all','complete','incomplete','has_ai'], true) ? $seo_status : 'all';
            $missing_title = isset($_POST['missing_title']) && $_POST['missing_title'] === '1' ? '1' : '0';
            $missing_desc  = isset($_POST['missing_desc']) && $_POST['missing_desc'] === '1' ? '1' : '0';

            $tax_arg = ($taxonomy === 'all') ? $this->get_supported_taxonomies() : $taxonomy;
            $args    = [
                'taxonomy'   => $tax_arg,
                'hide_empty' => false,
                'status'     => $status,
                'fields'     => 'ids',
                'number'     => 0,
            ];
            if ($search !== '') {
                $args['search'] = $search;
            }
            $meta_query = [];
            if ($seo_status === 'complete') {
                $meta_query[] = [ 'key' => '_gm2_title', 'value' => '', 'compare' => '!=' ];
                $meta_query[] = [ 'key' => '_gm2_description', 'value' => '', 'compare' => '!=' ];
            } elseif ($seo_status === 'incomplete') {
                $meta_query[] = [
                    'relation' => 'OR',
                    [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
                    [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
                ];
            } elseif ($seo_status === 'has_ai') {
                $meta_query[] = [ 'key' => '_gm2_ai_research', 'value' => '', 'compare' => '!=' ];
            }
            if ($missing_title === '1') {
                $meta_query[] = [
                    'relation' => 'OR',
                    [ 'key' => '_gm2_title', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => '_gm2_title', 'value' => '', 'compare' => '=' ],
                ];
            }
            if ($missing_desc === '1') {
                $meta_query[] = [
                    'relation' => 'OR',
                    [ 'key' => '_gm2_description', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => '_gm2_description', 'value' => '', 'compare' => '=' ],
                ];
            }
            if ($meta_query) {
                $args['meta_query'] = array_merge([ 'relation' => 'AND' ], $meta_query);
            }

            $ids = get_terms($args);
            if (is_wp_error($ids)) {
                wp_send_json_error($ids->get_error_message());
            }
        } else {
            $ids = isset($_POST['ids']) ? json_decode(wp_unslash($_POST['ids']), true) : [];
            if (!is_array($ids)) {
                wp_send_json_error( __( 'invalid data', 'gm2-wordpress-suite' ) );
            }
        }

        foreach ($ids as $key) {
            $term_id = 0;
            if (is_string($key) && strpos($key, ':') !== false) {
                list($tax, $id) = explode(':', $key);
                $term_id = absint($id);
            } else {
                $term_id = absint($key);
            }
            if (!$term_id || !current_user_can('edit_term', $term_id)) {
                continue;
            }
            delete_term_meta($term_id, '_gm2_title');
            delete_term_meta($term_id, '_gm2_description');
            delete_term_meta($term_id, '_gm2_prev_title');
            delete_term_meta($term_id, '_gm2_prev_description');
            delete_term_meta($term_id, '_gm2_focus_keywords');
            delete_term_meta($term_id, '_gm2_long_tail_keywords');
            delete_term_meta($term_id, '_gm2_prev_focus_keywords');
            delete_term_meta($term_id, '_gm2_prev_long_tail_keywords');
            delete_term_meta($term_id, '_gm2_ai_research');
            $count++;
        }

        wp_send_json_success( [ 'reset' => $count ] );
    }

    public function ajax_bulk_ai_tax_clear() {
        check_ajax_referer('gm2_bulk_ai_tax_clear');

        $cap = apply_filters('gm2_bulk_ai_tax_capability', 'manage_categories');
        if (!current_user_can($cap)) {
            wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
        }

        $ids = isset($_POST['ids']) ? json_decode(wp_unslash($_POST['ids']), true) : [];
        if (!is_array($ids)) {
            wp_send_json_error( __( 'invalid data', 'gm2-wordpress-suite' ) );
        }

        $count = 0;
        foreach ($ids as $key) {
            if (is_string($key) && strpos($key, ':') !== false) {
                list($tax, $id) = explode(':', $key);
                $term_id = absint($id);
            } else {
                $term_id = absint($key);
            }
            if (!$term_id || !current_user_can('edit_term', $term_id)) {
                continue;
            }
            delete_term_meta($term_id, '_gm2_ai_research');
            $count++;
        }

        wp_send_json_success( [ 'cleared' => $count ] );
    }

    public function ajax_schema_preview() {
        check_ajax_referer('gm2_schema_preview', 'nonce');

        $post_id  = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $term_id  = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field(wp_unslash($_POST['taxonomy'])) : '';
        if (!$post_id && !($term_id && $taxonomy)) {
            wp_send_json_error( __( 'invalid object', 'gm2-wordpress-suite' ) );
        }

        $overrides = [
            'schema_type'  => isset($_POST['schema_type']) ? sanitize_text_field(wp_unslash($_POST['schema_type'])) : '',
            'schema_brand' => isset($_POST['schema_brand']) ? sanitize_text_field(wp_unslash($_POST['schema_brand'])) : '',
            'schema_rating'=> isset($_POST['schema_rating']) ? sanitize_text_field(wp_unslash($_POST['schema_rating'])) : '',
        ];

        $public = new Gm2_SEO_Public();
        if ($post_id) {
            $schema = $public->generate_schema_data($post_id, $overrides);
        } else {
            $schema = $public->generate_term_schema_data($term_id, $taxonomy, $overrides);
        }

        wp_send_json_success($schema);
    }

    public function enqueue_editor_scripts($hook = null) {

        /*
         * $pagenow is not always reliable inside the block editor iframe.
         * Determine the post type directly so the tab assets load in both
         * classic and block editors even when Elementor adjusts the screen.
         */

        $typenow = '';
        $screen  = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && !empty($screen->post_type)) {
            $typenow = $screen->post_type;
        } elseif (isset($_GET['post_type'])) {
            $typenow = sanitize_key($_GET['post_type']);
        } elseif (!empty($_GET['post'])) {
            $typenow = get_post_type(absint($_GET['post']));
        } else {
            global $typenow;
        }
        if (!$typenow || !in_array($typenow, $this->get_supported_post_types(), true)) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_script(
            'gm2-content-analysis',
            GM2_PLUGIN_URL . 'admin/js/gm2-content-analysis.js',
            ['jquery', 'wp-data'],
            GM2_VERSION,
            true
        );

        wp_enqueue_script(
            'gm2-seo-tabs',
            GM2_PLUGIN_URL . 'admin/js/gm2-seo.js',
            ['jquery'],
            GM2_VERSION,
            true
        );
        wp_localize_script(
            'gm2-seo-tabs',
            'gm2Seo',
            [
                'i18n' => [
                    'selectImage' => __( 'Select Image', 'gm2-wordpress-suite' ),
                    'useImage'    => __( 'Use image', 'gm2-wordpress-suite' ),
                ],
            ]
        );

        wp_enqueue_script(
            'gm2-ai-seo',
            GM2_PLUGIN_URL . 'admin/js/gm2-ai-seo.js',
            ['jquery'],
            GM2_VERSION,
            true
        );

        wp_enqueue_script(
            'gm2-snippet-preview',
            GM2_PLUGIN_URL . 'admin/js/gm2-snippet-preview.js',
            ['jquery'],
            GM2_VERSION,
            true
        );

        wp_enqueue_script(
            'gm2-rich-preview',
            GM2_PLUGIN_URL . 'admin/js/gm2-rich-preview.js',
            ['jquery', 'wp-data'],
            GM2_VERSION,
            true
        );

        wp_enqueue_script(
            'gm2-schema-preview',
            GM2_PLUGIN_URL . 'admin/js/gm2-schema-preview.js',
            ['jquery', 'wp-util'],
            GM2_VERSION,
            true
        );

        wp_localize_script(
            'gm2-schema-preview',
            'gm2SchemaPreview',
            [
                'nonce'    => wp_create_nonce('gm2_schema_preview'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'post_id'  => isset($_GET['post']) ? absint($_GET['post']) : 0,
                'term_id'  => isset($_GET['tag_ID']) ? absint($_GET['tag_ID']) : 0,
                'taxonomy' => isset($_GET['taxonomy']) ? sanitize_key($_GET['taxonomy']) : '',
            ]
        );

        wp_localize_script(
            'gm2-ai-seo',
            'gm2AiSeo',
            [
                'nonce'    => wp_create_nonce('gm2_ai_research'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'post_id'  => isset($_GET['post']) ? absint($_GET['post']) : 0,
                'results'  => (function(){
                    $id = isset($_GET['post']) ? absint($_GET['post']) : 0;
                    if($id){
                        $stored = get_post_meta($id, '_gm2_ai_research', true);
                        $data = $stored ? json_decode($stored, true) : null;
                        if(json_last_error() === JSON_ERROR_NONE){
                            return $data;
                        }
                    }
                    return null;
                })(),
                'context_exists' => (function(){
                    foreach (gm2_get_seo_context() as $val) {
                        if (trim($val) !== '') {
                            return true;
                        }
                    }
                    return false;
                })(),
                'i18n'     => [
                    'researching' => __( 'Researching...', 'gm2-wordpress-suite' ),
                    'useExisting' => __( 'Use existing SEO values for AI research?', 'gm2-wordpress-suite' ),
                    'promptExtra' => __( 'Describe the page or its target audience:', 'gm2-wordpress-suite' ),
                    'selectAll'   => __( 'Select all', 'gm2-wordpress-suite' ),
                    'parseError'  => __( 'Unable to parse AI response—please try again', 'gm2-wordpress-suite' ),
                    'longTailKeywords'  => __( 'Long Tail Keywords', 'gm2-wordpress-suite' ),
                    'contentSuggestions' => __( 'Content Suggestions', 'gm2-wordpress-suite' ),
                    'htmlIssues'  => __( 'HTML Issues', 'gm2-wordpress-suite' ),
                    'applyFix'    => __( 'Apply fix', 'gm2-wordpress-suite' ),
                    'labels' => [
                        'seoTitle'       => __( 'SEO Title', 'gm2-wordpress-suite' ),
                        'description'    => __( 'SEO Description', 'gm2-wordpress-suite' ),
                        'focusKeywords'  => __( 'Focus Keywords', 'gm2-wordpress-suite' ),
                        'canonical'      => __( 'Canonical URL', 'gm2-wordpress-suite' ),
                        'pageName'       => __( 'Page Name', 'gm2-wordpress-suite' ),
                        'slug'           => __( 'Slug', 'gm2-wordpress-suite' ),
                    ],
                ],
            ]
        );

        wp_enqueue_style(
            'gm2-seo-style',
            GM2_PLUGIN_URL . 'admin/css/gm2-seo.css',
            [],
            GM2_VERSION
        );

        $current = isset($_GET['post']) ? absint($_GET['post']) : 0;
        $posts    = get_posts([
            'post_type'   => $this->get_supported_post_types(),
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids',
        ]);
        $list = [];
        foreach ($posts as $id) {
            if ($id === $current) {
                continue;
            }
            $list[] = [
                'title' => get_the_title($id),
                'link'  => get_permalink($id),
            ];
        }
        $all_rules    = get_option('gm2_content_rules', []);
        $current_rules = [];
        if (isset($all_rules['post_' . $typenow]) && is_array($all_rules['post_' . $typenow])) {
            foreach ($all_rules['post_' . $typenow] as $txt) {
                $txt          = $this->flatten_rule_value($txt);
                $current_rules = array_merge($current_rules, array_filter(array_map('trim', explode("\n", $txt))));
            }
        }
        wp_localize_script(
            'gm2-content-analysis',
            'gm2ContentAnalysisData',
            [
                'posts' => $list,
                'rules' => $current_rules,
                'postType' => $typenow,
                'nonce' => wp_create_nonce('gm2_check_rules'),
            ]
        );
    }


    public function enqueue_taxonomy_scripts($hook) {
        if ($hook !== 'edit-tags.php' && $hook !== 'term.php') {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || empty($screen->taxonomy)) {
            return;
        }
        if (!in_array($screen->taxonomy, $this->get_supported_taxonomies(), true)) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_script(
            'gm2-seo-tabs',
            GM2_PLUGIN_URL . 'admin/js/gm2-seo.js',
            ['jquery'],
            GM2_VERSION,
            true
        );
        wp_localize_script(
            'gm2-seo-tabs',
            'gm2Seo',
            [
                'i18n' => [
                    'selectImage' => __( 'Select Image', 'gm2-wordpress-suite' ),
                    'useImage'    => __( 'Use image', 'gm2-wordpress-suite' ),
                ],
            ]
        );

        wp_enqueue_script(
            'gm2-ai-seo',
            GM2_PLUGIN_URL . 'admin/js/gm2-ai-seo.js',
            ['jquery'],
            GM2_VERSION,
            true
        );

        wp_enqueue_script(
            'gm2-snippet-preview',
            GM2_PLUGIN_URL . 'admin/js/gm2-snippet-preview.js',
            ['jquery'],
            GM2_VERSION,
            true
        );

        wp_enqueue_script(
            'gm2-rich-preview',
            GM2_PLUGIN_URL . 'admin/js/gm2-rich-preview.js',
            ['jquery', 'wp-data'],
            GM2_VERSION,
            true
        );
        wp_localize_script(
            'gm2-ai-seo',
            'gm2AiSeo',
            [
                'nonce'    => wp_create_nonce('gm2_ai_research'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'term_id'  => isset($_GET['tag_ID']) ? absint($_GET['tag_ID']) : 0,
                'taxonomy' => $screen->taxonomy,
                'results'  => (function() use ($screen){
                    $id = isset($_GET['tag_ID']) ? absint($_GET['tag_ID']) : 0;
                    if($id){
                        $stored = get_term_meta($id, '_gm2_ai_research', true);
                        $data = $stored ? json_decode($stored, true) : null;
                        if(json_last_error() === JSON_ERROR_NONE){
                            return $data;
                        }
                    }
                    return null;
                })(),
                'context_exists' => (function(){
                    foreach (gm2_get_seo_context() as $val) {
                        if (trim($val) !== '') {
                            return true;
                        }
                    }
                    return false;
                })(),
                'i18n'     => [
                    'researching' => __( 'Researching...', 'gm2-wordpress-suite' ),
                    'useExisting' => __( 'Use existing SEO values for AI research?', 'gm2-wordpress-suite' ),
                    'promptExtra' => __( 'Describe the page or its target audience:', 'gm2-wordpress-suite' ),
                    'selectAll'   => __( 'Select all', 'gm2-wordpress-suite' ),
                    'parseError'  => __( 'Unable to parse AI response—please try again', 'gm2-wordpress-suite' ),
                    'longTailKeywords'  => __( 'Long Tail Keywords', 'gm2-wordpress-suite' ),
                    'contentSuggestions' => __( 'Content Suggestions', 'gm2-wordpress-suite' ),
                    'htmlIssues'  => __( 'HTML Issues', 'gm2-wordpress-suite' ),
                    'applyFix'    => __( 'Apply fix', 'gm2-wordpress-suite' ),
                    'labels' => [
                        'seoTitle'       => __( 'SEO Title', 'gm2-wordpress-suite' ),
                        'description'    => __( 'SEO Description', 'gm2-wordpress-suite' ),
                        'focusKeywords'  => __( 'Focus Keywords', 'gm2-wordpress-suite' ),
                        'canonical'      => __( 'Canonical URL', 'gm2-wordpress-suite' ),
                        'pageName'       => __( 'Page Name', 'gm2-wordpress-suite' ),
                        'slug'           => __( 'Slug', 'gm2-wordpress-suite' ),
                    ],
                ],
            ]
        );

        wp_enqueue_script(
            'gm2-tax-desc',
            GM2_PLUGIN_URL . 'admin/js/gm2-tax-desc.js',
            ['jquery'],
            GM2_VERSION,
            true
        );
        wp_localize_script(
            'gm2-tax-desc',
            'gm2TaxDesc',
            [
                'nonce'    => wp_create_nonce('gm2_ai_generate_tax_description'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'term_id'  => isset($_GET['tag_ID']) ? absint($_GET['tag_ID']) : 0,
                'taxonomy' => $screen->taxonomy,
                'loading'  => __( 'Researching...', 'gm2-wordpress-suite' ),
            ]
        );
        wp_enqueue_style(
            'gm2-seo-style',
            GM2_PLUGIN_URL . 'admin/css/gm2-seo.css',
            [],
            GM2_VERSION
        );
    }

    public function render_seo_tabs_meta_box($post) {
        $title          = get_post_meta($post->ID, '_gm2_title', true);
        $description    = get_post_meta($post->ID, '_gm2_description', true);
        $noindex        = get_post_meta($post->ID, '_gm2_noindex', true);
        $nofollow       = get_post_meta($post->ID, '_gm2_nofollow', true);
        $canonical      = get_post_meta($post->ID, '_gm2_canonical', true);
        $focus_keywords      = get_post_meta($post->ID, '_gm2_focus_keywords', true);
        $long_tail_keywords  = get_post_meta($post->ID, '_gm2_long_tail_keywords', true);
        $search_intent       = get_post_meta($post->ID, '_gm2_search_intent', true);
        $focus_limit         = get_post_meta($post->ID, '_gm2_focus_keyword_limit', true);
        $number_of_words     = get_post_meta($post->ID, '_gm2_number_of_words', true);
        $improve_readability = get_post_meta($post->ID, '_gm2_improve_readability', true);
        $max_snippet         = get_post_meta($post->ID, '_gm2_max_snippet', true);
        $max_image_preview   = get_post_meta($post->ID, '_gm2_max_image_preview', true);
        $max_video_preview   = get_post_meta($post->ID, '_gm2_max_video_preview', true);
        $schema_type         = get_post_meta($post->ID, '_gm2_schema_type', true);
        $schema_brand        = get_post_meta($post->ID, '_gm2_schema_brand', true);
        if ($schema_brand === '') {
            $schema_brand = $this->infer_brand_name($post->ID);
        }
        $schema_rating       = get_post_meta($post->ID, '_gm2_schema_rating', true);

        if ($schema_type === '') {
            if ($post->post_type === 'product') {
                $schema_type = 'product';
            } elseif ($post->post_type === 'post') {
                $schema_type = 'article';
            } elseif ($post->post_type === 'page') {
                $schema_type = 'webpage';
            }
        }

        wp_nonce_field('gm2_save_seo_meta', 'gm2_seo_nonce');

        echo '<div class="gm2-seo-tabs">';
        echo '<nav class="gm2-nav-tabs" role="tablist">';
        echo '<a href="#" class="gm2-nav-tab active" role="tab" aria-controls="gm2-seo-settings" aria-selected="true" data-tab="gm2-seo-settings">SEO Settings</a>';
        echo '<a href="#" class="gm2-nav-tab" role="tab" aria-controls="gm2-content-analysis" aria-selected="false" data-tab="gm2-content-analysis">Content Analysis</a>';
        echo '<a href="#" class="gm2-nav-tab" role="tab" aria-controls="gm2-schema" aria-selected="false" data-tab="gm2-schema">Schema</a>';
        echo '<a href="#" class="gm2-nav-tab" role="tab" aria-controls="gm2-ai-seo" aria-selected="false" data-tab="gm2-ai-seo">AI SEO</a>';
        echo '</nav>';

        echo '<div id="gm2-seo-settings" class="gm2-tab-panel active" role="tabpanel">';
        echo '<p><label for="gm2_seo_title">SEO Title</label>';
        echo '<input type="text" id="gm2_seo_title" name="gm2_seo_title" value="' . esc_attr($title) . '" placeholder="' . esc_attr__( 'Best Product Ever | My Brand', 'gm2-wordpress-suite' ) . '" class="widefat" /> <span class="dashicons dashicons-info" title="' . esc_attr__( 'Include main keyword and brand', 'gm2-wordpress-suite' ) . '"></span></p>';
        echo '<p><label for="gm2_seo_description">SEO Description</label>';
        echo '<textarea id="gm2_seo_description" name="gm2_seo_description" class="widefat" rows="3" placeholder="' . esc_attr__( 'One sentence summary shown in search results', 'gm2-wordpress-suite' ) . '">' . esc_textarea($description) . '</textarea> <span class="dashicons dashicons-info" title="' . esc_attr__( 'Keep under 160 characters', 'gm2-wordpress-suite' ) . '"></span></p>';

        echo '<p><label for="gm2_focus_keywords">' . esc_html__( 'Focus Keywords (comma separated)', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_focus_keywords" name="gm2_focus_keywords" value="' . esc_attr($focus_keywords) . '" placeholder="' . esc_attr__( 'keyword1, keyword2', 'gm2-wordpress-suite' ) . '" class="widefat" /> <span class="dashicons dashicons-info" title="' . esc_attr__( 'Separate with commas', 'gm2-wordpress-suite' ) . '"></span></p>';
        echo '<p><label for="gm2_long_tail_keywords">' . esc_html__( 'Long Tail Keywords (comma separated)', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_long_tail_keywords" name="gm2_long_tail_keywords" value="' . esc_attr($long_tail_keywords) . '" placeholder="' . esc_attr__( 'longer keyword phrase', 'gm2-wordpress-suite' ) . '" class="widefat" /> <span class="dashicons dashicons-info" title="' . esc_attr__( 'Lower volume phrases', 'gm2-wordpress-suite' ) . '"></span></p>';
        echo '<p><label><input type="checkbox" name="gm2_noindex" value="1" ' . checked($noindex, '1', false) . '> ' . esc_html__( 'noindex', 'gm2-wordpress-suite' ) . '</label></p>';
        echo '<p><label><input type="checkbox" name="gm2_nofollow" value="1" ' . checked($nofollow, '1', false) . '> ' . esc_html__( 'nofollow', 'gm2-wordpress-suite' ) . '</label></p>';
        echo '<p><label for="gm2_canonical_url">' . esc_html__( 'Canonical URL', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="url" id="gm2_canonical_url" name="gm2_canonical_url" value="' . esc_attr($canonical) . '" placeholder="https://example.com/original-page" class="widefat" /> <span class="dashicons dashicons-info" title="' . esc_attr__( 'Point to the preferred URL', 'gm2-wordpress-suite' ) . '"></span></p>';

        echo '<p><label for="gm2_max_snippet">' . esc_html__( 'Max Snippet', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_max_snippet" name="gm2_max_snippet" value="' . esc_attr($max_snippet) . '" class="small-text" /></p>';
        echo '<p><label for="gm2_max_image_preview">' . esc_html__( 'Max Image Preview', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_max_image_preview" name="gm2_max_image_preview" value="' . esc_attr($max_image_preview) . '" class="small-text" /></p>';
        echo '<p><label for="gm2_max_video_preview">' . esc_html__( 'Max Video Preview', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_max_video_preview" name="gm2_max_video_preview" value="' . esc_attr($max_video_preview) . '" class="small-text" /></p>';

        $og_image = get_post_meta($post->ID, '_gm2_og_image', true);
        $og_image_url = $og_image ? wp_get_attachment_url($og_image) : '';
        echo '<p><label for="gm2_og_image">' . esc_html__( 'OG Image', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="hidden" id="gm2_og_image" name="gm2_og_image" value="' . esc_attr($og_image) . '" />';
        echo '<input type="button" class="button gm2-upload-image" data-target="gm2_og_image" value="' . esc_attr__( 'Select Image', 'gm2-wordpress-suite' ) . '" />';
        echo '<span class="gm2-image-preview">' . ($og_image_url ? '<img src="' . esc_url($og_image_url) . '" style="max-width:100%;height:auto;" />' : '') . '</span></p>';

        $link_rel = get_post_meta($post->ID, '_gm2_link_rel', true);
        echo '<input type="hidden" id="gm2_link_rel_data" name="gm2_link_rel" value="' . esc_attr($link_rel) . '" />';
        echo '<p class="description">' . esc_html__( 'Use the link dialog to mark external links as', 'gm2-wordpress-suite' ) . ' <code>nofollow</code> ' . esc_html__( 'or', 'gm2-wordpress-suite' ) . ' <code>sponsored</code>.</p>';
        echo '</div>';

        echo '<div id="gm2-content-analysis" class="gm2-tab-panel" role="tabpanel">';
        echo '<ul class="gm2-analysis-rules">';
        $rules_option = get_option('gm2_content_rules', []);
        $rule_lines = [];
        if (isset($rules_option['post_' . $post->post_type]) && is_array($rules_option['post_' . $post->post_type])) {
            foreach ($rules_option['post_' . $post->post_type] as $txt) {
                $txt        = $this->flatten_rule_value($txt);
                $rule_lines = array_merge($rule_lines, array_filter(array_map('trim', explode("\n", $txt))));
            }
        }
        if (!$rule_lines) {
            $rule_lines = [
                __( 'Title length between 30 and 60 characters', 'gm2-wordpress-suite' ),
                __( 'Description length between 50 and 160 characters', 'gm2-wordpress-suite' ),
                __( 'At least one focus keyword', 'gm2-wordpress-suite' ),
                __( 'Content has at least 300 words', 'gm2-wordpress-suite' ),
                __( 'Image alt text contains focus keyword', 'gm2-wordpress-suite' ),
            ];
        }
        $min_int = (int) get_option('gm2_min_internal_links', 1);
        $min_ext = (int) get_option('gm2_min_external_links', 1);
        foreach ($rule_lines as $idx => $text) {
            $key = sanitize_title($text);
            $disp = preg_replace('/Minimum X internal links/i', 'Minimum ' . $min_int . ' internal links', $text);
            $disp = preg_replace('/Minimum X external links/i', 'Minimum ' . $min_ext . ' external links', $disp);
            echo '<li data-key="' . esc_attr($key) . '"><span class="dashicons dashicons-no"></span> ' . esc_html($disp) . '</li>';
        }
        echo '</ul>';
        echo '<div id="gm2-content-analysis-data">';
        echo '<p>' . esc_html__( 'Word Count', 'gm2-wordpress-suite' ) . ': <span id="gm2-content-analysis-word-count">0</span></p>';
        echo '<p>' . esc_html__( 'Top Keyword', 'gm2-wordpress-suite' ) . ': <span id="gm2-content-analysis-keyword"></span></p>';
        echo '<p>' . esc_html__( 'Keyword Density', 'gm2-wordpress-suite' ) . ': <span id="gm2-content-analysis-density">0</span>%</p>';
        echo '<p>' . esc_html__( 'Focus Keyword Density', 'gm2-wordpress-suite' ) . ':</p><ul id="gm2-focus-keyword-density"></ul>';
        echo '<p>' . esc_html__( 'Readability', 'gm2-wordpress-suite' ) . ': <span id="gm2-content-analysis-readability">0</span></p>';
        echo '<p>' . esc_html__( 'Internal Links', 'gm2-wordpress-suite' ) . ': <span id="gm2-content-analysis-internal-links">0</span></p>';
        echo '<p>' . esc_html__( 'External Links', 'gm2-wordpress-suite' ) . ': <span id="gm2-content-analysis-external-links">0</span></p>';
        echo '<p>' . esc_html__( 'Suggested Links', 'gm2-wordpress-suite' ) . ':</p><ul id="gm2-content-analysis-links"></ul>';
        echo '</div>';
        echo '</div>';
        echo '<div id="gm2-schema" class="gm2-tab-panel" role="tabpanel">';
        echo '<p><label for="gm2_schema_type">' . esc_html__( 'Primary Schema Type', 'gm2-wordpress-suite' ) . '</label>';
        echo '<select id="gm2_schema_type" name="gm2_schema_type">';
        $opts = [
            ''         => __( 'Default', 'gm2-wordpress-suite' ),
            'article'  => __( 'Article', 'gm2-wordpress-suite' ),
            'product'  => __( 'Product', 'gm2-wordpress-suite' ),
            'webpage'  => __( 'Web Page', 'gm2-wordpress-suite' ),
        ];
        $custom = get_option('gm2_custom_schema', []);
        if (is_array($custom)) {
            foreach ($custom as $id => $tpl) {
                $label = is_array($tpl) && isset($tpl['label']) ? $tpl['label'] : $id;
                $opts[$id] = $label;
            }
        }
        foreach ($opts as $val => $label) {
            echo '<option value="' . esc_attr($val) . '"' . selected($schema_type, $val, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';
        echo '<p><label for="gm2_schema_brand">' . esc_html__( 'Brand Name', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_schema_brand" name="gm2_schema_brand" value="' . esc_attr($schema_brand) . '" class="widefat" /></p>';
        echo '<p><label for="gm2_schema_rating">' . esc_html__( 'Review Rating', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="number" step="0.1" min="0" max="5" id="gm2_schema_rating" name="gm2_schema_rating" value="' . esc_attr($schema_rating) . '" class="small-text" /></p>';
        $permalink = get_permalink($post);
        $rich_url  = 'https://search.google.com/test/rich-results?url=' . rawurlencode($permalink);
        echo '<div id="gm2-schema-preview"></div>';
        echo '<p><a id="gm2-rich-results-preview" class="button button-secondary" href="' . esc_url($rich_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Test in Google', 'gm2-wordpress-suite' ) . '</a></p>';
        echo '<script type="text/html" id="tmpl-gm2-schema-card">';
        echo '<div class="gm2-schema-card">';
        echo '<# var title = data.name || data.headline; if ( title ) { #><div class="gm2-schema-card__title">{{ title }}</div><# } #>';
        echo '<# if ( data.description ) { #><div class="gm2-schema-card__desc">{{ data.description }}</div><# } #>';
        echo '<# if ( data.offers && data.offers.price ) { #><div class="gm2-schema-card__price">{{ data.offers.price }}</div><# } #>';
        echo '</div>';
        echo '</script>';
        echo '</div>';
        echo '<div id="gm2-ai-seo" class="gm2-tab-panel" role="tabpanel">';
        echo '<p><button type="button" class="button gm2-ai-research">' . esc_html__( 'AI Research', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '<div id="gm2-ai-results"></div>';
        echo '<p><button type="button" class="button gm2-ai-implement">' . esc_html__( 'Implement Selected', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '</div>';
        echo '</div>';
    }

    public static function add_notice($msg, $type = 'error') {
        self::$notices[] = [ 'message' => $msg, 'type' => $type ];
    }

    public function admin_notices() {
        foreach (self::$notices as $n) {
            echo '<div class="notice notice-' . esc_attr($n['type']) . '"><p>' . esc_html($n['message']) . '</p></div>';
        }
        self::$notices = [];
    }

    public function dom_extension_warning() {
        if (!class_exists('\\DOMDocument')) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'PHP DOM/LibXML extension not installed—HTML analysis and AI features are unavailable.', 'gm2-wordpress-suite' ) . '</p></div>';
        }
    }

    public function openssl_extension_warning() {
        if (!function_exists('openssl_sign')) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'PHP OpenSSL extension not installed—Google OAuth features are unavailable.', 'gm2-wordpress-suite' ) . '</p></div>';
        }
    }
    public function enqueue_elementor_scripts() {
        $this->enqueue_editor_scripts();
    }

    public function output_elementor_panel() {
        global $post;
        if ($post) {
            echo '<div id="gm2-elementor-seo-panel">';
            $this->render_seo_tabs_meta_box($post);
            echo '</div>';
        }
    }

    public static function cron_die_handler($message = '', $title = '', $args = []) {
        echo $message;
    }

    public function run_ai_research_cron($post_id) {
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
        add_filter('wp_die_ajax_handler', [__CLASS__, 'cron_die_handler']);
        add_filter('wp_die_handler', [__CLASS__, 'cron_die_handler']);
        $_POST = [
            'post_id' => $post_id,
            '_ajax_nonce' => wp_create_nonce('gm2_ai_research'),
        ];
        $_REQUEST = $_POST;
        ob_start();
        $this->ajax_ai_research();
        $json = ob_get_clean();
        remove_filter('wp_die_ajax_handler', [__CLASS__, 'cron_die_handler']);
        remove_filter('wp_die_handler', [__CLASS__, 'cron_die_handler']);
        $data = json_decode($json, true);
        if ($data && isset($data['success']) && $data['success']) {
            return $data['data'];
        }
        return new \WP_Error('gm2_ai_error', is_array($data) && isset($data['data']) ? $data['data'] : 'error');
    }

    private function queue_ai_posts(array $ids) {
        $queue = get_option(self::AI_QUEUE_OPTION, []);
        $queue = array_unique(array_merge($queue, array_map('absint', $ids)));
        update_option(self::AI_QUEUE_OPTION, $queue);
        if (!wp_next_scheduled(self::AI_CRON_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::AI_CRON_HOOK);
        }
    }

    private function clear_ai_queue() {
        delete_option(self::AI_QUEUE_OPTION);
        $ts = wp_next_scheduled(self::AI_CRON_HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::AI_CRON_HOOK);
        }
    }

    public function cron_process_ai_queue() {
        $queue = get_option(self::AI_QUEUE_OPTION, []);
        if (empty($queue)) {
            $this->clear_ai_queue();
            return;
        }
        $limit = apply_filters('gm2_ai_batch_limit', 5);
        $processed = 0;
        $remaining = [];
        foreach ($queue as $id) {
            if ($processed >= $limit) {
                $remaining[] = $id;
                continue;
            }
            $this->run_ai_research_cron($id);
            $processed++;
        }
        update_option(self::AI_QUEUE_OPTION, $remaining);
        if (empty($remaining)) {
            $this->clear_ai_queue();
        }
    }

    public function ajax_ai_batch_schedule() {
        check_ajax_referer('gm2_ai_batch');
        $ids = isset($_POST['ids']) ? json_decode(wp_unslash($_POST['ids']), true) : [];
        if (!is_array($ids)) {
            wp_send_json_error(__( 'invalid data', 'gm2-wordpress-suite' ));
        }
        $this->queue_ai_posts($ids);
        wp_send_json_success();
    }

    public function ajax_ai_batch_cancel() {
        check_ajax_referer('gm2_ai_batch');
        $this->clear_ai_queue();
        wp_send_json_success();
    }

    public function ajax_ai_tax_batch_schedule() {
        check_ajax_referer('gm2_ai_batch');
        $ids = isset($_POST['ids']) ? json_decode(wp_unslash($_POST['ids']), true) : [];
        if (!is_array($ids)) {
            wp_send_json_error(__( 'invalid data', 'gm2-wordpress-suite' ));
        }
        $this->queue_ai_terms($ids);
        wp_send_json_success();
    }

    public function ajax_ai_tax_batch_cancel() {
        check_ajax_referer('gm2_ai_batch');
        $this->clear_ai_tax_queue();
        wp_send_json_success();
    }

    private function queue_ai_terms(array $keys) {
        $queue = get_option(self::AI_TAX_QUEUE_OPTION, []);
        $items = [];
        foreach ($keys as $key) {
            if (strpos($key, ':') === false) {
                continue;
            }
            list($tax, $id) = explode(':', $key);
            $taxonomy = sanitize_key($tax);
            $term_id = absint($id);
            if ($taxonomy && $term_id) {
                $items[] = $taxonomy . ':' . $term_id;
            }
        }
        $queue = array_unique(array_merge($queue, $items));
        update_option(self::AI_TAX_QUEUE_OPTION, $queue);
        if (!wp_next_scheduled(self::AI_TAX_CRON_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::AI_TAX_CRON_HOOK);
        }
    }

    private function clear_ai_tax_queue() {
        delete_option(self::AI_TAX_QUEUE_OPTION);
        $ts = wp_next_scheduled(self::AI_TAX_CRON_HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::AI_TAX_CRON_HOOK);
        }
    }

    public function run_ai_tax_research_cron($term_id, $taxonomy) {
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
        add_filter('wp_die_ajax_handler', [__CLASS__, 'cron_die_handler']);
        add_filter('wp_die_handler', [__CLASS__, 'cron_die_handler']);
        $_POST = [
            'term_id'   => $term_id,
            'taxonomy'  => $taxonomy,
            '_ajax_nonce' => wp_create_nonce('gm2_ai_research'),
        ];
        $_REQUEST = $_POST;
        ob_start();
        $this->ajax_ai_research();
        $json = ob_get_clean();
        remove_filter('wp_die_ajax_handler', [__CLASS__, 'cron_die_handler']);
        remove_filter('wp_die_handler', [__CLASS__, 'cron_die_handler']);
        $data = json_decode($json, true);
        if ($data && isset($data['success']) && $data['success']) {
            return $data['data'];
        }
        return new \WP_Error('gm2_ai_error', is_array($data) && isset($data['data']) ? $data['data'] : 'error');
    }

    public function display_search_console_page() {
        $notice = '';
        if (isset($_POST['gm2_sc_nonce']) && wp_verify_nonce($_POST['gm2_sc_nonce'], 'gm2_sc_save')) {
            $cid    = sanitize_text_field($_POST['gm2_sc_client_id'] ?? '');
            $secret = sanitize_text_field($_POST['gm2_sc_client_secret'] ?? '');
            $token  = sanitize_text_field($_POST['gm2_sc_refresh_token'] ?? '');
            $json   = sanitize_text_field($_POST['gm2_sc_service_account_json'] ?? '');
            $auto   = isset($_POST['gm2_sc_auto']) ? '1' : '0';
            update_option('gm2_sc_client_id', $cid);
            update_option('gm2_sc_client_secret', $secret);
            update_option('gm2_sc_refresh_token', $token);
            update_option('gm2_sc_service_account_json', $json);
            update_option('gm2_sc_auto', $auto);
            $notice = '<div class="updated notice"><p>' . esc_html__('Settings saved.', 'gm2-wordpress-suite') . '</p></div>';
        }
        echo '<div class="wrap"><h1>' . esc_html__('Search Console Settings', 'gm2-wordpress-suite') . '</h1>';
        echo $notice;
        echo '<form method="post">';
        wp_nonce_field('gm2_sc_save', 'gm2_sc_nonce');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row">' . esc_html__('Client ID', 'gm2-wordpress-suite') . '</th><td><input type="text" name="gm2_sc_client_id" value="' . esc_attr(get_option('gm2_sc_client_id', '')) . '" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Client Secret', 'gm2-wordpress-suite') . '</th><td><input type="text" name="gm2_sc_client_secret" value="' . esc_attr(get_option('gm2_sc_client_secret', '')) . '" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Refresh Token', 'gm2-wordpress-suite') . '</th><td><input type="text" name="gm2_sc_refresh_token" value="' . esc_attr(get_option('gm2_sc_refresh_token', '')) . '" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Service Account JSON', 'gm2-wordpress-suite') . '</th><td><input type="text" name="gm2_sc_service_account_json" value="' . esc_attr(get_option('gm2_sc_service_account_json', '')) . '" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Automatic Product Submissions', 'gm2-wordpress-suite') . '</th><td><label><input type="checkbox" name="gm2_sc_auto" value="1" ' . checked(get_option('gm2_sc_auto', '0'), '1', false) . '> ' . esc_html__('Enable', 'gm2-wordpress-suite') . '</label></td></tr>';
        echo '</tbody></table>';
        submit_button();
        echo '</form></div>';
    }

    public function display_script_audit_page() {
        require GM2_PLUGIN_DIR . 'admin/views/third-party-audit.php';
    }

    public function display_lcp_settings_page() {
        require GM2_PLUGIN_DIR . 'admin/views/settings-lcp.php';
    }

    public function cron_process_ai_tax_queue() {
        $queue = get_option(self::AI_TAX_QUEUE_OPTION, []);
        if (empty($queue)) {
            $this->clear_ai_tax_queue();
            return;
        }
        $limit = apply_filters('gm2_ai_batch_limit', 5);
        $processed = 0;
        $remaining = [];
        foreach ($queue as $key) {
            if ($processed >= $limit) {
                $remaining[] = $key;
                continue;
            }
            if (strpos($key, ':') === false) {
                continue;
            }
            list($tax, $id) = explode(':', $key);
            $taxonomy = sanitize_key($tax);
            $term_id = absint($id);
            if (!$taxonomy || !$term_id || !taxonomy_exists($taxonomy)) {
                continue;
            }
            $this->run_ai_tax_research_cron($term_id, $taxonomy);
            $processed++;
        }
        update_option(self::AI_TAX_QUEUE_OPTION, $remaining);
        if (empty($remaining)) {
            $this->clear_ai_tax_queue();
        }
    }

    public function add_settings_help() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }
        $readme = plugins_url( 'readme.txt', GM2_PLUGIN_DIR . 'gm2-wordpress-suite.php' );
        $screen->add_help_tab(
            [
                'id'      => 'gm2-wp-debugging',
                'title'   => __( 'WP Debugging', 'gm2-wordpress-suite' ),
                'content' => '<p>' . sprintf(
                    __( 'See the <a href="%s#wp-debugging" target="_blank">WP Debugging</a> section of the readme for instructions. Errors will appear in <code>wp-content/debug.log</code>.', 'gm2-wordpress-suite' ),
                    esc_url( $readme )
                ) . '</p>',
            ]
        );

        $screen->add_help_tab(
            [
                'id'      => 'gm2-seo-context',
                'title'   => __( 'SEO Context', 'gm2-wordpress-suite' ),
                'content' => '<p>' . __( 'Use the Context tab to describe your business model, industry, audience, unique selling points and more. Saved answers are automatically included in ChatGPT prompts for AI SEO. ChatGPT must be enabled and configured.', 'gm2-wordpress-suite' ) . '</p>',
            ]
        );
    }
}

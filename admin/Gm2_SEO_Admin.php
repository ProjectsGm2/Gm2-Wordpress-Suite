<?php

namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_SEO_Admin {
    private $elementor_initialized = false;
    private static $notices = [];

    private function debug_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message);
        }
    }
    public function run() {
        add_action('admin_menu', [$this, 'add_settings_pages']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post', [$this, 'save_post_meta']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_editor_scripts']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_taxonomy_scripts']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_gm2_sitemap_settings', [$this, 'handle_sitemap_form']);
        add_action('admin_post_gm2_meta_tags_settings', [$this, 'handle_meta_tags_form']);
        add_action('admin_post_gm2_schema_settings', [$this, 'handle_schema_form']);
        add_action('admin_post_gm2_performance_settings', [$this, 'handle_performance_form']);
        add_action('admin_post_gm2_redirects', [$this, 'handle_redirects_form']);
        add_action('admin_post_gm2_content_rules', [$this, 'handle_content_rules_form']);
        add_action('admin_post_gm2_general_settings', [$this, 'handle_general_settings_form']);
        add_action('admin_post_gm2_keyword_settings', [$this, 'handle_keyword_settings_form']);

        add_action('wp_ajax_gm2_check_rules', [$this, 'ajax_check_rules']);
        add_action('wp_ajax_gm2_keyword_ideas', [$this, 'ajax_keyword_ideas']);
        add_action('wp_ajax_gm2_research_guidelines', [$this, 'ajax_research_guidelines']);
        add_action('wp_ajax_gm2_research_content_rules', [$this, 'ajax_research_content_rules']);
        add_action('wp_ajax_gm2_ai_research', [$this, 'ajax_ai_research']);
        add_action('wp_ajax_gm2_ai_generate_tax_description', [$this, 'ajax_generate_tax_description']);
        add_action('wp_ajax_gm2_bulk_ai_apply', [$this, 'ajax_bulk_ai_apply']);

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
    }

    public function maybe_generate_sitemap($new_status = null, $old_status = null, $post = null) {
        if (is_null($new_status) && is_null($old_status)) {
            gm2_generate_sitemap();
            return;
        }

        if ($new_status === 'publish' || $old_status === 'publish') {
            gm2_generate_sitemap();
        }
    }

    private function get_supported_post_types() {
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

    private function get_supported_taxonomies() {
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

    public function add_settings_pages() {
        $hook = add_submenu_page(
            'gm2',
            esc_html__( 'SEO', 'gm2-wordpress-suite' ),
            esc_html__( 'SEO', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-seo',
            [$this, 'display_dashboard']
        );
        if ( $hook ) {
            add_action( 'load-' . $hook, [ $this, 'add_settings_help' ] );
        }

        add_submenu_page(
            'gm2',
            esc_html__( 'Connect Google Account', 'gm2-wordpress-suite' ),
            esc_html__( 'Connect Google Account', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-google-connect',
            [$this, 'display_google_connect_page']
        );

        add_submenu_page(
            'gm2',
            esc_html__( 'Robots.txt', 'gm2-wordpress-suite' ),
            esc_html__( 'Robots.txt', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-robots',
            [$this, 'display_robots_page']
        );

        add_submenu_page(
            'gm2',
            esc_html__( 'Bulk AI Review', 'gm2-wordpress-suite' ),
            esc_html__( 'Bulk AI Review', 'gm2-wordpress-suite' ),
            'edit_posts',
            'gm2-bulk-ai-review',
            [$this, 'display_bulk_ai_page']
        );
    }

    public function register_settings() {
        register_setting('gm2_seo_options', 'gm2_ga_measurement_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_search_console_verification', [
            'sanitize_callback' => 'sanitize_text_field',
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
        register_setting('gm2_seo_options', 'gm2_analytics_days', [
            'sanitize_callback' => 'absint',
        ]);
        register_setting('gm2_seo_options', 'gm2_clean_slugs', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_slug_stopwords', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_tax_desc_prompt', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_bulk_ai_page_size', [
            'sanitize_callback' => 'absint',
        ]);
        register_setting('gm2_seo_options', 'gm2_bulk_ai_status', [
            'sanitize_callback' => 'sanitize_key',
        ]);
        register_setting('gm2_seo_options', 'gm2_bulk_ai_post_type', [
            'sanitize_callback' => 'sanitize_key',
        ]);
        register_setting('gm2_seo_options', 'gm2_bulk_ai_term', [
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
        foreach ($this->get_supported_post_types() as $pt) {
            register_setting('gm2_seo_options', 'gm2_seo_guidelines_post_' . $pt, [
                'sanitize_callback' => 'sanitize_textarea_field',
            ]);
        }
        foreach ($this->get_supported_taxonomies() as $tax) {
            register_setting('gm2_seo_options', 'gm2_seo_guidelines_tax_' . $tax, [
                'sanitize_callback' => 'sanitize_textarea_field',
            ]);
        }

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
                $value = get_option('gm2_tax_desc_prompt', __( 'Write a short SEO description for the term "{name}". {guidelines}', 'gm2-wordpress-suite' ) );
                echo '<textarea name="gm2_tax_desc_prompt" rows="3" class="large-text">' . esc_textarea($value) . '</textarea>';
                echo '<p class="description">' . esc_html__( 'Available tags: {name}, {taxonomy}, {guidelines}', 'gm2-wordpress-suite' ) . '</p>';
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
            'rules'       => esc_html__( 'Content Rules', 'gm2-wordpress-suite' ),
            'guidelines'  => esc_html__( 'SEO Guidelines', 'gm2-wordpress-suite' ),
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

        if ($active === 'meta') {
            $variants       = get_option('gm2_noindex_variants', '0');
            $oos            = get_option('gm2_noindex_oos', '0');
            $canon_parent   = get_option('gm2_variation_canonical_parent', '0');
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
            echo '</tbody></table>';
            submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
            echo '</form>';
        } elseif ($active === 'sitemap') {
            $enabled   = get_option('gm2_sitemap_enabled', '1');
            $frequency = get_option('gm2_sitemap_frequency', 'daily');
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
            echo '</tbody></table>';
            submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
            echo '<input type="submit" name="gm2_regenerate" class="button" value="' . esc_attr__( 'Regenerate Sitemap', 'gm2-wordpress-suite' ) . '" />';
            echo '</form>';
        } elseif ($active === 'schema') {
            $product     = get_option('gm2_schema_product', '1');
            $brand       = get_option('gm2_schema_brand', '1');
            $breadcrumbs = get_option('gm2_schema_breadcrumbs', '1');
            $article     = get_option('gm2_schema_article', '1');
            $review      = get_option('gm2_schema_review', '1');
            $footer_bc   = get_option('gm2_show_footer_breadcrumbs', '1');
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
            echo '<tr><th scope="row">' . esc_html__( 'Article Schema', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_schema_article" value="1" ' . checked($article, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Show Breadcrumbs in Footer', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_show_footer_breadcrumbs" value="1" ' . checked($footer_bc, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Review Schema', 'gm2-wordpress-suite' ) . '</th><td><input type="checkbox" name="gm2_schema_review" value="1" ' . checked($review, '1', false) . '></td></tr>';
            echo '</tbody></table>';
            submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
            echo '</form>';
        } elseif ($active === 'redirects') {
            $redirects = get_option('gm2_redirects', []);
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
        } elseif ($active === 'performance') {
            $auto_fill = get_option('gm2_auto_fill_alt', '0');
            $clean_files = get_option('gm2_clean_image_filenames', '0');
            $enable_comp = get_option('gm2_enable_compression', '0');
            $api_key    = get_option('gm2_compression_api_key', '');
            $api_url   = get_option('gm2_compression_api_url', 'https://api.example.com/compress');
            $min_html  = get_option('gm2_minify_html', '0');
            $min_css   = get_option('gm2_minify_css', '0');
            $min_js    = get_option('gm2_minify_js', '0');
            $ps_key    = get_option('gm2_pagespeed_api_key', '');
            $ps_scores = get_option('gm2_pagespeed_scores', []);
            if (!empty($_GET['updated'])) {
                echo '<div class="updated notice"><p>' . esc_html__('Settings saved.', 'gm2-wordpress-suite') . '</p></div>';
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
            echo '<tr><th scope="row">' . esc_html__( 'PageSpeed API Key', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="gm2_pagespeed_api_key" value="' . esc_attr($ps_key) . '" class="regular-text" />';
            if (!empty($ps_scores['mobile']) || !empty($ps_scores['desktop'])) {
                $time = !empty($ps_scores['time']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $ps_scores['time']) : '';
                echo '<p>Mobile: ' . esc_html($ps_scores['mobile'] ?? '') . ' Desktop: ' . esc_html($ps_scores['desktop'] ?? '') . ' ' . esc_html($time) . '</p>';
            }
            echo '</td></tr>';
            echo '</tbody></table>';
            submit_button( esc_html__( 'Save Settings', 'gm2-wordpress-suite' ) );
            echo ' <input type="submit" name="gm2_test_pagespeed" class="button" value="' . esc_attr__( 'Test Page Speed', 'gm2-wordpress-suite' ) . '" />';
            echo '</form>';
        } elseif ($active === 'keywords') {
            $enabled = trim(get_option('gm2_gads_developer_token', '')) !== '' &&
                trim(get_option('gm2_gads_customer_id', '')) !== '' &&
                get_option('gm2_google_refresh_token', '') !== '';

            $lang   = get_option('gm2_gads_language', 'languageConstants/1000');
            $geo    = get_option('gm2_gads_geo_target', 'geoTargetConstants/2840');
            $login  = get_option('gm2_gads_login_customer_id', '');
            $limit  = get_option('gm2_sc_query_limit', 10);
            $days   = get_option('gm2_analytics_days', 30);

            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_keyword_settings_save', 'gm2_keyword_settings_nonce');
            echo '<input type="hidden" name="action" value="gm2_keyword_settings" />';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">' . esc_html__( 'Language Constant', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="gm2_gads_language" id="gm2_gads_language" value="' . esc_attr($lang) . '" class="regular-text" /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Geo Target Constant', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="gm2_gads_geo_target" id="gm2_gads_geo_target" value="' . esc_attr($geo) . '" class="regular-text" /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Login Customer ID', 'gm2-wordpress-suite' ) . '</th><td><input type="text" name="gm2_gads_login_customer_id" id="gm2_gads_login_customer_id" value="' . esc_attr($login) . '" class="regular-text" /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Search Console Query Limit', 'gm2-wordpress-suite' ) . '</th><td><input type="number" name="gm2_sc_query_limit" id="gm2_sc_query_limit" value="' . esc_attr($limit) . '" class="small-text" /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Analytics Days', 'gm2-wordpress-suite' ) . '</th><td><input type="number" name="gm2_analytics_days" id="gm2_analytics_days" value="' . esc_attr($days) . '" class="small-text" /></td></tr>';
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
                    echo '<textarea id="gm2_rule_post_' . esc_attr($pt . '_' . $c) . '" name="gm2_content_rules[post_' . esc_attr($pt) . '][' . esc_attr($c) . ']" rows="3" class="large-text">' . esc_textarea($val) . '</textarea>';
                    echo ' <button type="button" class="button gm2-research-rules" data-base="post_' . esc_attr($pt) . '" data-category="' . esc_attr($c) . '">' . esc_html__( 'AI Research Content Rules', 'gm2-wordpress-suite' ) . '</button></p>';
                }
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
                    echo '<textarea id="gm2_rule_tax_' . esc_attr($tax . '_' . $c) . '" name="gm2_content_rules[tax_' . esc_attr($tax) . '][' . esc_attr($c) . ']" rows="3" class="large-text">' . esc_textarea($val) . '</textarea>';
                    echo ' <button type="button" class="button gm2-research-rules" data-base="tax_' . esc_attr($tax) . '" data-category="' . esc_attr($c) . '">' . esc_html__( 'AI Research Content Rules', 'gm2-wordpress-suite' ) . '</button></p>';
                }
                echo '</td></tr>';
            }
            $min_int = (int) get_option('gm2_min_internal_links', 1);
            $min_ext = (int) get_option('gm2_min_external_links', 1);
            echo '<tr><th scope="row">' . esc_html__( 'Minimum Internal Links', 'gm2-wordpress-suite' ) . '</th><td><input type="number" name="gm2_min_internal_links" value="' . esc_attr($min_int) . '" class="small-text" /></td></tr>';
            echo '<tr><th scope="row">' . esc_html__( 'Minimum External Links', 'gm2-wordpress-suite' ) . '</th><td><input type="number" name="gm2_min_external_links" value="' . esc_attr($min_ext) . '" class="small-text" /></td></tr>';
            echo '</tbody></table>';
            submit_button( esc_html__( 'Save Rules', 'gm2-wordpress-suite' ) );
            echo '</form>';
        } elseif ($active === 'context') {
            echo '<form method="post" action="options.php">';
            settings_fields('gm2_seo_options');

            echo '<table class="form-table"><tbody>';
            $fields = [
                'gm2_context_business_model'        => [ 'label' => __( 'Business Model', 'gm2-wordpress-suite' ), 'type' => 'textarea' ],
                'gm2_context_industry_category'     => [ 'label' => __( 'Industry Category', 'gm2-wordpress-suite' ), 'type' => 'text' ],
                'gm2_context_target_audience'       => [ 'label' => __( 'Target Audience', 'gm2-wordpress-suite' ), 'type' => 'textarea' ],
                'gm2_context_unique_selling_points' => [ 'label' => __( 'Unique Selling Points', 'gm2-wordpress-suite' ), 'type' => 'textarea' ],
                'gm2_context_revenue_streams'       => [ 'label' => __( 'Revenue Streams', 'gm2-wordpress-suite' ), 'type' => 'textarea' ],
                'gm2_context_primary_goal'          => [ 'label' => __( 'Primary Goal', 'gm2-wordpress-suite' ), 'type' => 'textarea' ],
                'gm2_context_brand_voice'           => [ 'label' => __( 'Brand Voice', 'gm2-wordpress-suite' ), 'type' => 'textarea' ],
                'gm2_context_competitors'           => [ 'label' => __( 'Competitors', 'gm2-wordpress-suite' ), 'type' => 'textarea' ],
            ];
            foreach ( $fields as $opt => $data ) {
                $val = get_option( $opt, '' );
                echo '<tr><th scope="row"><label for="' . esc_attr( $opt ) . '">' . esc_html( $data['label'] ) . '</label></th><td>';
                if ( $data['type'] === 'text' ) {
                    echo '<input type="text" id="' . esc_attr( $opt ) . '" name="' . esc_attr( $opt ) . '" value="' . esc_attr( $val ) . '" class="regular-text" />';
                } else {
                    echo '<textarea id="' . esc_attr( $opt ) . '" name="' . esc_attr( $opt ) . '" rows="3" class="large-text">' . esc_textarea( $val ) . '</textarea>';
                }
                echo '</td></tr>';
            }
            echo '</tbody></table>';
            submit_button( esc_html__( 'Save Context', 'gm2-wordpress-suite' ) );
            echo '</form>';
        } elseif ($active === 'guidelines') {
            echo '<form method="post" action="options.php">';
            settings_fields('gm2_seo_options');

            foreach ($this->get_supported_post_types() as $pt) {
                $opt   = 'gm2_seo_guidelines_post_' . $pt;
                $label = get_post_type_object($pt)->labels->singular_name ?? ucfirst($pt);
                $val   = get_option($opt, '');
                echo '<h3>' . esc_html($label) . '</h3>';
                echo '<textarea name="' . esc_attr($opt) . '" rows="6" class="large-text">' . esc_textarea($val) . '</textarea>';
                echo '<p><button class="button gm2-research-guidelines" data-target="' . esc_attr($opt) . '">' . esc_html__( 'Research SEO Guidelines', 'gm2-wordpress-suite' ) . '</button></p>';
            }

            foreach ($this->get_supported_taxonomies() as $tax) {
                $opt   = 'gm2_seo_guidelines_tax_' . $tax;
                $tax_obj = get_taxonomy($tax);
                if ($tax === 'category') {
                    $label = __('Post Category', 'gm2-wordpress-suite');
                } elseif ($tax === 'product_cat') {
                    $label = __('Product Category', 'gm2-wordpress-suite');
                } else {
                    $label = $tax_obj ? $tax_obj->labels->singular_name : ucfirst($tax);
                }
                $val   = get_option($opt, '');
                echo '<h3>' . esc_html($label) . '</h3>';
                echo '<textarea name="' . esc_attr($opt) . '" rows="6" class="large-text">' . esc_textarea($val) . '</textarea>';
                echo '<p><button class="button gm2-research-guidelines" data-target="' . esc_attr($opt) . '">' . esc_html__( 'Research SEO Guidelines', 'gm2-wordpress-suite' ) . '</button></p>';
            }

            submit_button( esc_html__( 'Save Guidelines', 'gm2-wordpress-suite' ) );
            echo '</form>';
        } else {
            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_general_settings_save', 'gm2_general_settings_nonce');
            echo '<input type="hidden" name="action" value="gm2_general_settings" />';
            do_settings_sections('gm2_seo');
            submit_button();
            echo '</form>';
        }

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

        $page_size = max(1, absint(get_option('gm2_bulk_ai_page_size', 10)));
        $status    = get_option('gm2_bulk_ai_status', 'publish');
        $post_type = get_option('gm2_bulk_ai_post_type', 'all');
        $term      = get_option('gm2_bulk_ai_term', '');
        $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

        if (isset($_POST['gm2_bulk_ai_save']) && check_admin_referer('gm2_bulk_ai_settings')) {
            $page_size = max(1, absint($_POST['page_size'] ?? 10));
            $status    = sanitize_key($_POST['status'] ?? 'publish');
            $post_type = sanitize_key($_POST['gm2_post_type'] ?? 'all');
            $term      = sanitize_text_field($_POST['term'] ?? '');
            update_option('gm2_bulk_ai_page_size', $page_size);
            update_option('gm2_bulk_ai_status', $status);
            update_option('gm2_bulk_ai_post_type', $post_type);
            update_option('gm2_bulk_ai_term', $term);
        }

        $types = $this->get_supported_post_types();
        if ($post_type !== 'all' && in_array($post_type, $types, true)) {
            $types = [$post_type];
        }
        $args = [
            'post_type'      => $types,
            'post_status'    => $status,
            'posts_per_page' => $page_size,
            'paged'          => $current_page,
        ];
        if ($term && strpos($term, ':') !== false) {
            list($tax, $id) = explode(':', $term);
            $taxonomies = $this->get_supported_taxonomies();
            if (in_array($tax, $taxonomies, true)) {
                $args['tax_query'] = [[
                    'taxonomy' => $tax,
                    'field'    => 'term_id',
                    'terms'    => absint($id),
                ]];
            }
        }
        $query = new \WP_Query($args);

        echo '<div class="wrap" id="gm2-bulk-ai">';
        echo '<h1>' . esc_html__( 'Bulk AI Review', 'gm2-wordpress-suite' ) . '</h1>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=gm2-bulk-ai-review' ) ) . '">';
        wp_nonce_field('gm2_bulk_ai_settings');
        echo '<p><label>' . esc_html__( 'Posts per page', 'gm2-wordpress-suite' ) . ' <input type="number" name="page_size" value="' . esc_attr($page_size) . '" min="1"></label> ';
        echo '<label>' . esc_html__( 'Status', 'gm2-wordpress-suite' ) . ' <select name="status">';
        echo '<option value="publish"' . selected($status, 'publish', false) . '>' . esc_html__( 'Published', 'gm2-wordpress-suite' ) . '</option>';
        echo '<option value="draft"' . selected($status, 'draft', false) . '>' . esc_html__( 'Draft', 'gm2-wordpress-suite' ) . '</option>';
        echo '</select></label> ';
        echo '<label>' . esc_html__( 'Post Type', 'gm2-wordpress-suite' ) . ' <select name="gm2_post_type">';
        echo '<option value="all"' . selected($post_type, 'all', false) . '>' . esc_html__( 'All', 'gm2-wordpress-suite' ) . '</option>';
        foreach ($this->get_supported_post_types() as $pt) {
            $obj = get_post_type_object($pt);
            $name = $obj ? $obj->labels->singular_name : $pt;
            echo '<option value="' . esc_attr($pt) . '"' . selected($post_type, $pt, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select></label> ';
        echo '<label>' . esc_html__( 'Category', 'gm2-wordpress-suite' ) . ' <select name="term">';
        echo '<option value=""' . selected($term, '', false) . '>' . esc_html__( 'All', 'gm2-wordpress-suite' ) . '</option>';
        $dropdown_terms = get_terms([
            'taxonomy'   => $this->get_supported_taxonomies(),
            'hide_empty' => false,
        ]);
        foreach ($dropdown_terms as $t) {
            $tax_obj = get_taxonomy($t->taxonomy);
            $label = ($tax_obj ? $tax_obj->labels->singular_name : $t->taxonomy) . ': ' . $t->name;
            $value = $t->taxonomy . ':' . $t->term_id;
            echo '<option value="' . esc_attr($value) . '"' . selected($term, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label> ';
        submit_button( esc_html__( 'Save', 'gm2-wordpress-suite' ), 'secondary', 'gm2_bulk_ai_save', false );
        echo '</p></form>';

        echo '<table class="widefat" id="gm2-bulk-list"><thead><tr><th class="check-column"><input type="checkbox" id="gm2-bulk-select-all"></th><th>' . esc_html__( 'Title', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'SEO Title', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Description', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'Slug', 'gm2-wordpress-suite' ) . '</th><th>' . esc_html__( 'AI Suggestions', 'gm2-wordpress-suite' ) . '</th></tr></thead><tbody>';
        foreach ($query->posts as $post) {
            $seo_title   = get_post_meta($post->ID, '_gm2_title', true);
            $description = get_post_meta($post->ID, '_gm2_description', true);
            echo '<tr id="gm2-row-' . intval($post->ID) . '">';
            echo '<th scope="row" class="check-column"><input type="checkbox" class="gm2-select" value="' . intval($post->ID) . '"></th>';
            echo '<td>' . esc_html($post->post_title) . '</td>';
            echo '<td>' . esc_html($seo_title) . '</td>';
            echo '<td>' . esc_html($description) . '</td>';
            echo '<td>' . esc_html($post->post_name) . '</td>';
            echo '<td class="gm2-result"></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        $links = paginate_links([
            'base'    => add_query_arg('paged', '%#%', admin_url('admin.php?page=gm2-bulk-ai-review')),
            'format'  => '',
            'current' => $current_page,
            'total'   => max(1, $query->max_num_pages),
        ]);
        if ($links) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . $links . '</div></div>';
        }
        echo '<p><button type="button" class="button" id="gm2-bulk-analyze">' . esc_html__( 'Analyze Selected', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '</div>';
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
        }
    }


    public function render_taxonomy_meta_box($term) {
        $title               = '';
        $description         = '';
        $noindex             = '';
        $nofollow            = '';
        $canonical           = '';
        $focus_keywords      = '';
        $long_tail_keywords  = '';
        $max_snippet         = '';
        $max_image_preview   = '';
        $max_video_preview   = '';
        $taxonomy       = is_object($term) ? $term->taxonomy : (string) $term;

        if (is_object($term)) {
            $title          = get_term_meta($term->term_id, '_gm2_title', true);
            $description    = get_term_meta($term->term_id, '_gm2_description', true);
            $noindex        = get_term_meta($term->term_id, '_gm2_noindex', true);
            $nofollow       = get_term_meta($term->term_id, '_gm2_nofollow', true);
            $canonical        = get_term_meta($term->term_id, '_gm2_canonical', true);
            $focus_keywords   = get_term_meta($term->term_id, '_gm2_focus_keywords', true);
            $long_tail_keywords = get_term_meta($term->term_id, '_gm2_long_tail_keywords', true);
            $max_snippet      = get_term_meta($term->term_id, '_gm2_max_snippet', true);
            $max_image_preview = get_term_meta($term->term_id, '_gm2_max_image_preview', true);
            $max_video_preview = get_term_meta($term->term_id, '_gm2_max_video_preview', true);
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
        echo '<nav class="gm2-nav-tabs">';
        echo '<a href="#" class="gm2-nav-tab active" data-tab="gm2-seo-settings">' . esc_html__( 'SEO Settings', 'gm2-wordpress-suite' ) . '</a>';
        echo '<a href="#" class="gm2-nav-tab" data-tab="gm2-content-analysis">' . esc_html__( 'Content Analysis', 'gm2-wordpress-suite' ) . '</a>';
        echo '<a href="#" class="gm2-nav-tab" data-tab="gm2-ai-seo">' . esc_html__( 'AI SEO', 'gm2-wordpress-suite' ) . '</a>';
        echo '</nav>';

        echo '<div id="gm2-seo-settings" class="gm2-tab-panel active">';
        echo '<p><label for="gm2_seo_title">' . esc_html__( 'SEO Title', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_seo_title" name="gm2_seo_title" value="' . esc_attr($title) . '" class="widefat" /></p>';
        echo '<p><label for="gm2_seo_description">' . esc_html__( 'SEO Description', 'gm2-wordpress-suite' ) . '</label>';
        echo '<textarea id="gm2_seo_description" name="gm2_seo_description" class="widefat" rows="3">' . esc_textarea($description) . '</textarea></p>';
        echo '<p><label for="gm2_focus_keywords">' . esc_html__( 'Focus Keywords (comma separated)', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_focus_keywords" name="gm2_focus_keywords" value="' . esc_attr($focus_keywords) . '" class="widefat" /></p>';
        echo '<p><label for="gm2_long_tail_keywords">' . esc_html__( 'Long Tail Keywords (comma separated)', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_long_tail_keywords" name="gm2_long_tail_keywords" value="' . esc_attr($long_tail_keywords) . '" class="widefat" /></p>';
        echo '<p><label><input type="checkbox" name="gm2_noindex" value="1" ' . checked($noindex, '1', false) . '> ' . esc_html__( 'noindex', 'gm2-wordpress-suite' ) . '</label></p>';
        echo '<p><label><input type="checkbox" name="gm2_nofollow" value="1" ' . checked($nofollow, '1', false) . '> ' . esc_html__( 'nofollow', 'gm2-wordpress-suite' ) . '</label></p>';
        echo '<p><label for="gm2_canonical_url">' . esc_html__( 'Canonical URL', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="url" id="gm2_canonical_url" name="gm2_canonical_url" value="' . esc_attr($canonical) . '" class="widefat" /></p>';

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

        echo '<div id="gm2-content-analysis" class="gm2-tab-panel">';
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
        echo '<div id="gm2-ai-seo" class="gm2-tab-panel">';
        echo '<p><button type="button" class="button gm2-ai-research">' . esc_html__( 'AI Research', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '<div id="gm2-ai-results"></div>';
        echo '<p><button type="button" class="button gm2-ai-implement">' . esc_html__( 'Implement Selected', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '</div>';

        echo '</div>';
        echo $wrapper_end;
    }

    public function save_post_meta($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST['gm2_seo_nonce']) || !wp_verify_nonce($_POST['gm2_seo_nonce'], 'gm2_save_seo_meta')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $title       = isset($_POST['gm2_seo_title']) ? sanitize_text_field($_POST['gm2_seo_title']) : '';
        $description = isset($_POST['gm2_seo_description']) ? sanitize_textarea_field($_POST['gm2_seo_description']) : '';
        if ($description === '' && trim(get_option('gm2_chatgpt_api_key', '')) !== '') {
            $post = get_post($post_id);
            if ($post) {
                $sanitized_content = wp_strip_all_tags($post->post_content);
                $snippet_content   = $this->safe_truncate($sanitized_content, 400);
                $prompt  = "Write a short SEO description for the following content:\n\n" . $snippet_content;
                $chat    = new Gm2_ChatGPT();
                $resp    = $chat->query($prompt);
                if (!is_wp_error($resp) && $resp !== '') {
                    $description = sanitize_textarea_field($resp);
                } else {
                    $msg = is_wp_error($resp) ? $resp->get_error_message() : __( 'Empty response from ChatGPT', 'gm2-wordpress-suite' );
                    self::add_notice(sprintf( __( 'ChatGPT description error: %s', 'gm2-wordpress-suite' ), $msg ));
                }
            }
        }
        $noindex     = isset($_POST['gm2_noindex']) ? '1' : '0';
        $nofollow    = isset($_POST['gm2_nofollow']) ? '1' : '0';
        $canonical      = isset($_POST['gm2_canonical_url']) ? esc_url_raw($_POST['gm2_canonical_url']) : '';
        $focus_keywords   = isset($_POST['gm2_focus_keywords']) ? sanitize_text_field($_POST['gm2_focus_keywords']) : '';
        $long_tail_keywords = isset($_POST['gm2_long_tail_keywords']) ? sanitize_text_field($_POST['gm2_long_tail_keywords']) : '';
        $max_snippet      = isset($_POST['gm2_max_snippet']) ? sanitize_text_field($_POST['gm2_max_snippet']) : '';
        $max_image_preview = isset($_POST['gm2_max_image_preview']) ? sanitize_text_field($_POST['gm2_max_image_preview']) : '';
        $max_video_preview = isset($_POST['gm2_max_video_preview']) ? sanitize_text_field($_POST['gm2_max_video_preview']) : '';
        $og_image         = isset($_POST['gm2_og_image']) ? absint($_POST['gm2_og_image']) : 0;
        $link_rel_data    = isset($_POST['gm2_link_rel']) ? wp_unslash($_POST['gm2_link_rel']) : '';
        if (!is_array(json_decode($link_rel_data, true)) && $link_rel_data !== '') {
            $link_rel_data = '';
        }
        update_post_meta($post_id, '_gm2_title', $title);
        update_post_meta($post_id, '_gm2_description', $description);
        update_post_meta($post_id, '_gm2_noindex', $noindex);
        update_post_meta($post_id, '_gm2_nofollow', $nofollow);
        update_post_meta($post_id, '_gm2_canonical', $canonical);
        update_post_meta($post_id, '_gm2_focus_keywords', $focus_keywords);
        update_post_meta($post_id, '_gm2_long_tail_keywords', $long_tail_keywords);
        update_post_meta($post_id, '_gm2_max_snippet', $max_snippet);
        update_post_meta($post_id, '_gm2_max_image_preview', $max_image_preview);
        update_post_meta($post_id, '_gm2_max_video_preview', $max_video_preview);
        update_post_meta($post_id, '_gm2_og_image', $og_image);
        update_post_meta($post_id, '_gm2_link_rel', $link_rel_data);
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
        $max_snippet      = isset($_POST['gm2_max_snippet']) ? sanitize_text_field($_POST['gm2_max_snippet']) : '';
        $max_image_preview = isset($_POST['gm2_max_image_preview']) ? sanitize_text_field($_POST['gm2_max_image_preview']) : '';
        $max_video_preview = isset($_POST['gm2_max_video_preview']) ? sanitize_text_field($_POST['gm2_max_video_preview']) : '';
        $og_image         = isset($_POST['gm2_og_image']) ? absint($_POST['gm2_og_image']) : 0;
        update_term_meta($term_id, '_gm2_title', $title);
        update_term_meta($term_id, '_gm2_description', $description);
        update_term_meta($term_id, '_gm2_noindex', $noindex);
        update_term_meta($term_id, '_gm2_nofollow', $nofollow);
        update_term_meta($term_id, '_gm2_canonical', $canonical);
        update_term_meta($term_id, '_gm2_focus_keywords', $focus_keywords);
        update_term_meta($term_id, '_gm2_long_tail_keywords', $long_tail_keywords);
        update_term_meta($term_id, '_gm2_max_snippet', $max_snippet);
        update_term_meta($term_id, '_gm2_max_image_preview', $max_image_preview);
        update_term_meta($term_id, '_gm2_max_video_preview', $max_video_preview);
        update_term_meta($term_id, '_gm2_og_image', $og_image);
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

        if (isset($_POST['gm2_regenerate'])) {
            gm2_generate_sitemap();
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

        $breadcrumbs = isset($_POST['gm2_schema_breadcrumbs']) ? '1' : '0';
        update_option('gm2_schema_breadcrumbs', $breadcrumbs);

        $article = isset($_POST['gm2_schema_article']) ? '1' : '0';
        update_option('gm2_schema_article', $article);

        $footer_bc = isset($_POST['gm2_show_footer_breadcrumbs']) ? '1' : '0';
        update_option('gm2_show_footer_breadcrumbs', $footer_bc);

        $review     = isset($_POST['gm2_schema_review']) ? '1' : '0';
        update_option('gm2_schema_review', $review);

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=schema&updated=1'));
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

    public function handle_general_settings_form() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }

        if (!isset($_POST['gm2_general_settings_nonce']) || !wp_verify_nonce($_POST['gm2_general_settings_nonce'], 'gm2_general_settings_save')) {
            wp_die( esc_html__( 'Invalid nonce', 'gm2-wordpress-suite' ) );
        }

        $ga_id  = isset($_POST['gm2_ga_measurement_id']) ? sanitize_text_field($_POST['gm2_ga_measurement_id']) : '';
        $sc_ver = isset($_POST['gm2_search_console_verification']) ? sanitize_text_field($_POST['gm2_search_console_verification']) : '';
        $token  = isset($_POST['gm2_gads_developer_token']) ? sanitize_text_field($_POST['gm2_gads_developer_token']) : '';
        $cust   = isset($_POST['gm2_gads_customer_id']) ? $this->sanitize_customer_id($_POST['gm2_gads_customer_id']) : '';
        $clean  = isset($_POST['gm2_clean_slugs']) ? '1' : '0';
        $words  = isset($_POST['gm2_slug_stopwords']) ? sanitize_textarea_field($_POST['gm2_slug_stopwords']) : '';
        $prompt = isset($_POST['gm2_tax_desc_prompt']) ? sanitize_textarea_field($_POST['gm2_tax_desc_prompt']) : '';

        update_option('gm2_ga_measurement_id', $ga_id);
        update_option('gm2_search_console_verification', $sc_ver);
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
        $sc_limit = isset($_POST['gm2_sc_query_limit']) ? absint($_POST['gm2_sc_query_limit']) : 0;
        $days     = isset($_POST['gm2_analytics_days']) ? absint($_POST['gm2_analytics_days']) : 0;

        update_option('gm2_gads_language', $lang);
        update_option('gm2_gads_geo_target', $geo);
        update_option('gm2_gads_login_customer_id', $login);
        update_option('gm2_sc_query_limit', $sc_limit);
        update_option('gm2_analytics_days', $days);

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=keywords&updated=1'));
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
                    $meta = wp_generate_attachment_metadata($attachment_id, $new);
                    wp_update_attachment_metadata($attachment_id, $meta);
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
            if (trim(get_option('gm2_chatgpt_api_key', '')) !== '') {
                $prompt = "Provide a short descriptive alt text for an image titled: {$title}";
                $chat   = new Gm2_ChatGPT();
                $resp   = $chat->query($prompt);
                if (!is_wp_error($resp) && $resp !== '') {
                    $alt = sanitize_text_field($resp);
                } else {
                    $msg = is_wp_error($resp) ? $resp->get_error_message() : __( 'Empty response from ChatGPT', 'gm2-wordpress-suite' );
                    self::add_notice( sprintf( __( 'ChatGPT alt text error: %s', 'gm2-wordpress-suite' ), $msg ) );
                }
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
                $metadata = wp_generate_attachment_metadata($attachment_id, $file);
                wp_update_attachment_metadata($attachment_id, $metadata);
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
                $html = apply_filters('the_content', $post_obj->post_content);
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
     * Generate keyword ideas using ChatGPT when Keyword Planner is unavailable.
     *
     * @param string $query Seed keyword or phrase.
     * @return array|\WP_Error
     */
    private function chatgpt_keyword_ideas($query) {
        $prompt = sprintf(
            'Provide a comma-separated list of short keyword ideas related to: %s',
            $query
        );
        $chat = new Gm2_ChatGPT();
        try {
            $resp = $chat->query($prompt);
        } catch (\Throwable $e) {
            error_log('ChatGPT keyword ideas failed: ' . $e->getMessage());
            return new \WP_Error('chatgpt_error', __('AI request failed', 'gm2-wordpress-suite'));
        }
        if (is_wp_error($resp)) {
            error_log('ChatGPT keyword ideas error: ' . $resp->get_error_message());
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
                $ideas = $this->chatgpt_keyword_ideas($query);
                $fallback = true;
            }
        } else {
            $ideas = $this->chatgpt_keyword_ideas($query);
            $fallback = true;
        }

        if (is_wp_error($ideas)) {
            wp_send_json_error( $ideas->get_error_message() );
        }

        wp_send_json_success([
            'ideas'  => $ideas,
            'ai_only'=> $fallback,
        ]);
    }

    public function ajax_research_guidelines() {
        check_ajax_referer('gm2_research_guidelines');
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
            $allowed[] = 'gm2_seo_guidelines_post_' . $pt;
        }
        foreach ($this->get_supported_taxonomies() as $tax) {
            $allowed[] = 'gm2_seo_guidelines_tax_' . $tax;
        }
        if (!in_array($target, $allowed, true)) {
            wp_send_json_error( __( 'invalid target', 'gm2-wordpress-suite' ) );
        }

        $prompt = 'Provide SEO best practice guidelines for the following categories: ' . $cats;
        $chat   = new Gm2_ChatGPT();
        $resp   = $chat->query($prompt);

        if (is_wp_error($resp)) {
            wp_send_json_error($resp->get_error_message());
        }

        $decoded = json_decode($resp, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $resp = $decoded;
        }

        $flat = $this->flatten_rule_value($resp);

        update_option($target, $flat);
        wp_send_json_success($flat);
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
            $prompt_target = sprintf('for the %s post type', substr($target, 5));
        } else {
            $prompt_target = sprintf('for the %s taxonomy', substr($target, 4));
        }

        $prompt = sprintf(
            'Provide an array of short, measurable rules %s. Use these categories: %s. ' .
            'Respond ONLY with JSON where each key matches the provided slugs and each value is an array of rules.',
            $prompt_target,
            $cats
        );
        $chat   = new Gm2_ChatGPT();
        $resp   = $chat->query($prompt);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Content rules response: ' . $resp);
        }

        if (is_wp_error($resp)) {
            wp_send_json_error($resp->get_error_message());
        }

        $data = json_decode($resp, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (preg_match('/\{.*\}/s', $resp, $m)) {
                $data = json_decode($m[0], true);
            }
        }
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
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

        $formatted = [];
        foreach ($data as $cat => $text) {
            $key = strtolower(str_replace([' ', '-'], '_', $cat));
            $key = preg_replace('/[^a-z0-9_]/', '', $key);
            if (isset($alias_map[$key])) {
                $key = $alias_map[$key];
            }

            if (!in_array($key, $valid_slugs, true)) {
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
        $focus_main  = trim(explode(',', $focus)[0]);
        $html_issues = $this->detect_html_issues($html, $canonical, $focus_main);
        $clean_html  = wp_strip_all_tags($html);
        $snippet     = $this->safe_truncate($clean_html, 400);

        $guidelines = '';
        if ($post_id && !empty($post)) {
            $guidelines = get_option('gm2_seo_guidelines_post_' . $post->post_type, '');
        } elseif ($term_id && $taxonomy) {
            $guidelines = get_option('gm2_seo_guidelines_tax_' . $taxonomy, '');
        }
        $guidelines = trim($guidelines);

        $context_parts = array_filter(array_map('trim', gm2_get_seo_context()));
        $context_block = '';
        if ($context_parts) {
            $pairs = [];
            foreach ($context_parts as $k => $v) {
                $pairs[] = ucfirst(str_replace('_', ' ', $k)) . ': ' . $v;
            }
            $context_block = 'Site context: ' . implode('; ', $pairs);
        }

        $prompt  = '';
        if ($guidelines !== '') {
            $prompt .= "SEO guidelines:\n" . $guidelines . "\n\n";
        }
        $prompt .= "Page title: {$title}\nURL: {$url}\n";
        $prompt .= "Existing SEO Title: {$seo_title}\nSEO Description: {$seo_description}\n";
        $prompt .= "Focus Keywords: {$focus}\nCanonical: {$canonical}\n";
        if ($context_block !== '') {
            $prompt .= $context_block . "\n";
        }
        if ($extra_context !== '') {
            $prompt .= "Extra context: {$extra_context}\n";
        }
        if ($snippet !== '') {
            $prompt .= "Content snippet: {$snippet}\n";
        }
        $prompt .= "Provide JSON with keys seo_title, description, focus_keywords, long_tail_keywords, seed_keywords, canonical, page_name, slug, content_suggestions, html_issues.";

        $chat = new Gm2_ChatGPT();
        try {
            $resp = $chat->query($prompt);
        } catch (\Throwable $e) {
            error_log('AI Research ChatGPT query failed: ' . $e->getMessage());
            wp_send_json_error(__('AI request failed', 'gm2-wordpress-suite'));
        }

        if (is_wp_error($resp)) {
            error_log('AI Research ChatGPT error: ' . $resp->get_error_message());
            wp_send_json_error(__('AI request failed', 'gm2-wordpress-suite'));
        }

        try {
            $data = json_decode($resp, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            error_log('AI Research JSON decode failed: ' . $resp);
            if (preg_match('/\{.*\}/s', $resp, $m)) {
                try {
                    $data = json_decode($m[0], true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable $e2) {
                    error_log('AI Research JSON decode failed after extraction: ' . $m[0]);
                    wp_send_json_error(__('Invalid AI response', 'gm2-wordpress-suite'));
                }
            } else {
                wp_send_json_error(__('Invalid AI response', 'gm2-wordpress-suite'));
            }
        }

        if (!is_array($data)) {
            wp_send_json_error( __( 'Invalid AI response', 'gm2-wordpress-suite' ) );
        }


        $seed_string = '';
        if (!empty($data['seed_keywords'])) {
            $seed_string = $data['seed_keywords'];
        } elseif (!empty($data['focus_keywords'])) {
            $seed_string = $data['focus_keywords'];
        }

        $seeds = array_filter(array_map('trim', explode(',', $seed_string)));

        $final_focus = '';
        $final_long  = [];
        $kwp_notice  = '';

        if (!$seeds) {
            $query = $seo_title !== '' ? $seo_title : ($seo_description !== '' ? $seo_description : $title);
            $ideas = $this->chatgpt_keyword_ideas($query);
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
        if ($guidelines !== '') {
            $prompt2 .= "SEO guidelines:\n" . $guidelines . "\n\n";
        }
        $prompt2 .= "Page title: {$title}\nURL: {$url}\n";
        $prompt2 .= "Focus Keyword: {$final_focus}\n";
        if ($final_long) {
            $prompt2 .= "Long-tail Keywords: " . implode(', ', $final_long) . "\n";
        }
        if ($context_block !== '') {
            $prompt2 .= $context_block . "\n";
        }
        if ($extra_context !== '') {
            $prompt2 .= "Extra context: {$extra_context}\n";
        }
        if ($snippet !== '') {
            $prompt2 .= "Content snippet: {$snippet}\n";
        }
        $prompt2 .= "Provide JSON with keys seo_title, description, focus_keywords, long_tail_keywords, canonical, page_name, slug, content_suggestions, html_issues.";

        try {
            $resp2 = $chat->query($prompt2);
        } catch (\Throwable $e) {
            error_log('AI Research ChatGPT query failed: ' . $e->getMessage());
            wp_send_json_error(__('AI request failed', 'gm2-wordpress-suite'));
        }

        if (is_wp_error($resp2)) {
            error_log('AI Research ChatGPT error: ' . $resp2->get_error_message());
            wp_send_json_error(__('AI request failed', 'gm2-wordpress-suite'));
        }

        try {
            $data2 = json_decode($resp2, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            error_log('AI Research JSON decode failed: ' . $resp2);
            if (preg_match('/\{.*\}/s', $resp2, $m)) {
                try {
                    $data2 = json_decode($m[0], true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable $e2) {
                    error_log('AI Research JSON decode failed after extraction: ' . $m[0]);
                    wp_send_json_error(__('Invalid AI response', 'gm2-wordpress-suite'));
                }
            } else {
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

        if ($post_id) {
            update_post_meta($post_id, '_gm2_ai_research', wp_json_encode($data2));
        } elseif ($term_id) {
            update_term_meta($term_id, '_gm2_ai_research', wp_json_encode($data2));
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
            if (!current_user_can('edit_terms')) {
                wp_send_json_error( __( 'permission denied', 'gm2-wordpress-suite' ), 403 );
            }
        }

        $guidelines = trim(get_option('gm2_seo_guidelines_tax_' . $taxonomy, ''));
        $template   = get_option('gm2_tax_desc_prompt', 'Write a short SEO description for the term "{name}". {guidelines}');

        $prompt = strtr($template, [
            '{name}'       => $name,
            '{taxonomy}'   => $taxonomy,
            '{guidelines}' => $guidelines,
        ]);

        $context_parts = array_filter(array_map('trim', gm2_get_seo_context()));
        if ($context_parts) {
            $pairs = [];
            foreach ($context_parts as $k => $v) {
                $pairs[] = ucfirst(str_replace('_', ' ', $k)) . ': ' . $v;
            }
            $prompt .= "\nSite context: " . implode('; ', $pairs);
        }

        $chat = new Gm2_ChatGPT();
        $resp = $chat->query($prompt);

        if (is_wp_error($resp)) {
            wp_send_json_error($resp->get_error_message());
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
            $data['post_title'] = sanitize_text_field(wp_unslash($_POST['title']));
        }
        if (isset($_POST['slug'])) {
            $data['post_name'] = sanitize_title(wp_unslash($_POST['slug']));
        }
        if (count($data) > 1) {
            wp_update_post($data);
        }
        if (isset($_POST['seo_title'])) {
            update_post_meta($post_id, '_gm2_title', sanitize_text_field(wp_unslash($_POST['seo_title'])));
        }
        if (isset($_POST['seo_description'])) {
            update_post_meta($post_id, '_gm2_description', sanitize_textarea_field(wp_unslash($_POST['seo_description'])));
        }

        wp_send_json_success();
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
        $max_snippet         = get_post_meta($post->ID, '_gm2_max_snippet', true);
        $max_image_preview   = get_post_meta($post->ID, '_gm2_max_image_preview', true);
        $max_video_preview   = get_post_meta($post->ID, '_gm2_max_video_preview', true);

        wp_nonce_field('gm2_save_seo_meta', 'gm2_seo_nonce');

        echo '<div class="gm2-seo-tabs">';
        echo '<nav class="gm2-nav-tabs">';
        echo '<a href="#" class="gm2-nav-tab active" data-tab="gm2-seo-settings">SEO Settings</a>';
        echo '<a href="#" class="gm2-nav-tab" data-tab="gm2-content-analysis">Content Analysis</a>';
        echo '<a href="#" class="gm2-nav-tab" data-tab="gm2-ai-seo">AI SEO</a>';
        echo '</nav>';

        echo '<div id="gm2-seo-settings" class="gm2-tab-panel active">';
        echo '<p><label for="gm2_seo_title">SEO Title</label>';
        echo '<input type="text" id="gm2_seo_title" name="gm2_seo_title" value="' . esc_attr($title) . '" class="widefat" /></p>';
        echo '<p><label for="gm2_seo_description">SEO Description</label>';
        echo '<textarea id="gm2_seo_description" name="gm2_seo_description" class="widefat" rows="3">' . esc_textarea($description) . '</textarea></p>';
        echo '<p><label for="gm2_focus_keywords">' . esc_html__( 'Focus Keywords (comma separated)', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_focus_keywords" name="gm2_focus_keywords" value="' . esc_attr($focus_keywords) . '" class="widefat" /></p>';
        echo '<p><label for="gm2_long_tail_keywords">' . esc_html__( 'Long Tail Keywords (comma separated)', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="text" id="gm2_long_tail_keywords" name="gm2_long_tail_keywords" value="' . esc_attr($long_tail_keywords) . '" class="widefat" /></p>';
        echo '<p><label><input type="checkbox" name="gm2_noindex" value="1" ' . checked($noindex, '1', false) . '> ' . esc_html__( 'noindex', 'gm2-wordpress-suite' ) . '</label></p>';
        echo '<p><label><input type="checkbox" name="gm2_nofollow" value="1" ' . checked($nofollow, '1', false) . '> ' . esc_html__( 'nofollow', 'gm2-wordpress-suite' ) . '</label></p>';
        echo '<p><label for="gm2_canonical_url">' . esc_html__( 'Canonical URL', 'gm2-wordpress-suite' ) . '</label>';
        echo '<input type="url" id="gm2_canonical_url" name="gm2_canonical_url" value="' . esc_attr($canonical) . '" class="widefat" /></p>';

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

        echo '<div id="gm2-content-analysis" class="gm2-tab-panel">';
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
        echo '<div id="gm2-ai-seo" class="gm2-tab-panel">';
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
                'content' => '<p>' . __( 'Use the Context tab to describe your business model, industry, audience, unique selling points and more. Saved answers are automatically included in ChatGPT prompts for AI SEO.', 'gm2-wordpress-suite' ) . '</p>',
            ]
        );
    }
}

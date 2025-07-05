<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gm2_SEO_Admin {
    public function run() {
        add_action('admin_menu', [$this, 'add_settings_pages']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post', [$this, 'save_post_meta']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_editor_scripts']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_gm2_sitemap_settings', [$this, 'handle_sitemap_form']);
        add_action('admin_post_gm2_meta_tags_settings', [$this, 'handle_meta_tags_form']);
        add_action('admin_post_gm2_schema_settings', [$this, 'handle_schema_form']);
        add_action('admin_post_gm2_performance_settings', [$this, 'handle_performance_form']);
        add_action('admin_post_gm2_redirects', [$this, 'handle_redirects_form']);
        add_action('admin_post_gm2_content_rules', [$this, 'handle_content_rules_form']);

        add_action('wp_ajax_gm2_check_rules', [$this, 'ajax_check_rules']);

        add_action('add_attachment', [$this, 'auto_fill_alt_on_upload']);
        add_action('add_attachment', [$this, 'compress_image_on_upload'], 20);
        add_action('save_post', [$this, 'auto_fill_product_alt'], 20, 3);

        add_action('transition_post_status', [$this, 'maybe_generate_sitemap'], 10, 3);

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
        $types = ['post', 'page'];
        if (post_type_exists('product')) {
            $types[] = 'product';
        }
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

    public function add_settings_pages() {
        add_submenu_page(
            'gm2',
            'SEO',
            'SEO',
            'manage_options',
            'gm2-seo',
            [$this, 'display_dashboard']
        );
    }

    public function register_settings() {
        register_setting('gm2_seo_options', 'gm2_ga_measurement_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('gm2_seo_options', 'gm2_search_console_verification', [
            'sanitize_callback' => 'sanitize_text_field',
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
            },
            'gm2_seo',
            'gm2_seo_main'
        );
    }

    public function display_dashboard() {
        $tabs = [
            'general'     => 'General',
            'meta'        => 'Meta Tags',
            'sitemap'     => 'Sitemap',
            'redirects'   => 'Redirects',
            'schema'      => 'Structured Data',
            'performance' => 'Performance',
            'rules'       => 'Content Rules',
        ];

        $active = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        if (!isset($tabs[$active])) {
            $active = 'general';
        }

        echo '<div class="wrap">';
        echo '<h1>SEO Settings</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $slug => $label) {
            $class = $active === $slug ? ' nav-tab-active' : '';
            $url   = admin_url('admin.php?page=gm2-seo&tab=' . $slug);
            echo '<a href="' . esc_url($url) . '" class="nav-tab' . $class . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';

        if ($active === 'meta') {
            $variants = get_option('gm2_noindex_variants', '0');
            $oos      = get_option('gm2_noindex_oos', '0');
            if (!empty($_GET['updated'])) {
                echo '<div class="updated notice"><p>Settings saved.</p></div>';
            }
            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_meta_tags_save', 'gm2_meta_tags_nonce');
            echo '<input type="hidden" name="action" value="gm2_meta_tags_settings" />';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">Noindex product variants</th><td><input type="checkbox" name="gm2_noindex_variants" value="1" ' . checked($variants, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">Noindex out-of-stock products</th><td><input type="checkbox" name="gm2_noindex_oos" value="1" ' . checked($oos, '1', false) . '></td></tr>';
            echo '</tbody></table>';
            submit_button('Save Settings');
            echo '</form>';
        } elseif ($active === 'sitemap') {
            $enabled   = get_option('gm2_sitemap_enabled', '1');
            $frequency = get_option('gm2_sitemap_frequency', 'daily');
            if (!empty($_GET['updated'])) {
                echo '<div class="updated notice"><p>Settings saved.</p></div>';
            }
            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_sitemap_save', 'gm2_sitemap_nonce');
            echo '<input type="hidden" name="action" value="gm2_sitemap_settings" />';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">Enable Sitemap</th><td><input type="checkbox" name="gm2_sitemap_enabled" value="1" ' . checked($enabled, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">Update Frequency</th><td><select name="gm2_sitemap_frequency">';
            $options = ["daily", "weekly", "monthly"];
            foreach ($options as $opt) {
                echo '<option value="' . esc_attr($opt) . '" ' . selected($frequency, $opt, false) . '>' . esc_html(ucfirst($opt)) . '</option>';
            }
            echo '</select></td></tr>';
            echo '</tbody></table>';
            submit_button('Save Settings');
            echo '<input type="submit" name="gm2_regenerate" class="button" value="Regenerate Sitemap" />';
            echo '</form>';
        } elseif ($active === 'schema') {
            $product     = get_option('gm2_schema_product', '1');
            $brand       = get_option('gm2_schema_brand', '1');
            $breadcrumbs = get_option('gm2_schema_breadcrumbs', '1');
            $review      = get_option('gm2_schema_review', '1');
            $footer_bc   = get_option('gm2_show_footer_breadcrumbs', '1');
            if (!empty($_GET['updated'])) {
                echo '<div class="updated notice"><p>Settings saved.</p></div>';
            }
            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_schema_save', 'gm2_schema_nonce');
            echo '<input type="hidden" name="action" value="gm2_schema_settings" />';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">Product Schema</th><td><input type="checkbox" name="gm2_schema_product" value="1" ' . checked($product, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">Brand Schema</th><td><input type="checkbox" name="gm2_schema_brand" value="1" ' . checked($brand, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">Breadcrumb Schema</th><td><input type="checkbox" name="gm2_schema_breadcrumbs" value="1" ' . checked($breadcrumbs, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">Show Breadcrumbs in Footer</th><td><input type="checkbox" name="gm2_show_footer_breadcrumbs" value="1" ' . checked($footer_bc, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">Review Schema</th><td><input type="checkbox" name="gm2_schema_review" value="1" ' . checked($review, '1', false) . '></td></tr>';
            echo '</tbody></table>';
            submit_button('Save Settings');
            echo '</form>';
        } elseif ($active === 'redirects') {
            $redirects = get_option('gm2_redirects', []);
            if (!empty($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
                $id = absint($_GET['id']);
                check_admin_referer('gm2_delete_redirect_' . $id);
                if (isset($redirects[$id])) {
                    unset($redirects[$id]);
                    update_option('gm2_redirects', array_values($redirects));
                    echo '<div class="updated"><p>Redirect deleted.</p></div>';
                    $redirects = array_values($redirects);
                }
            }

            $source_prefill = isset($_GET['source']) ? esc_url_raw($_GET['source']) : '';
            if (!empty($_GET['updated'])) {
                echo '<div class="updated notice"><p>Redirect saved.</p></div>';
            }
            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_redirects_save', 'gm2_redirects_nonce');
            echo '<input type="hidden" name="action" value="gm2_redirects" />';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row"><label for="gm2_redirect_source">Source URL</label></th><td><input name="gm2_redirect_source" type="text" id="gm2_redirect_source" value="' . esc_attr($source_prefill) . '" class="regular-text" required></td></tr>';
            echo '<tr><th scope="row"><label for="gm2_redirect_target">Target URL</label></th><td><input name="gm2_redirect_target" type="url" id="gm2_redirect_target" class="regular-text" required></td></tr>';
            echo '<tr><th scope="row"><label for="gm2_redirect_type">Type</label></th><td><select name="gm2_redirect_type" id="gm2_redirect_type"><option value="301">301</option><option value="302">302</option></select></td></tr>';
            echo '</tbody></table>';
            submit_button('Add Redirect');
            echo '</form>';

            echo '<h2>Existing Redirects</h2>';
            echo '<table class="widefat"><thead><tr><th>Source</th><th>Target</th><th>Type</th><th>Actions</th></tr></thead><tbody>';
            if ($redirects) {
                foreach ($redirects as $index => $r) {
                    $delete_url = wp_nonce_url(admin_url('admin.php?page=gm2-seo&tab=redirects&action=delete&id=' . $index), 'gm2_delete_redirect_' . $index);
                    echo '<tr>';
                    echo '<td>' . esc_html($r['source']) . '</td>';
                    echo '<td>' . esc_html($r['target']) . '</td>';
                    echo '<td>' . esc_html($r['type']) . '</td>';
                    echo '<td><a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Are you sure?\');">Delete</a></td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="4">No redirects found.</td></tr>';
            }
            echo '</tbody></table>';

            $logs = get_option('gm2_404_logs', []);
            if ($logs) {
                echo '<h2>404 Logs</h2>';
                echo '<table class="widefat"><thead><tr><th>URL</th></tr></thead><tbody>';
                foreach ($logs as $log) {
                    $link = admin_url('admin.php?page=gm2-seo&tab=redirects&source=' . urlencode($log));
                    echo '<tr><td><a href="' . esc_url($link) . '">' . esc_html($log) . '</a></td></tr>';
                }
                echo '</tbody></table>';
            }
        } elseif ($active === 'performance') {
            $auto_fill = get_option('gm2_auto_fill_alt', '0');
            $enable_comp = get_option('gm2_enable_compression', '0');
            $api_key    = get_option('gm2_compression_api_key', '');
            $api_url   = get_option('gm2_compression_api_url', 'https://api.example.com/compress');
            $min_html  = get_option('gm2_minify_html', '0');
            $min_css   = get_option('gm2_minify_css', '0');
            $min_js    = get_option('gm2_minify_js', '0');
            if (!empty($_GET['updated'])) {
                echo '<div class="updated notice"><p>Settings saved.</p></div>';
            }
            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_performance_save', 'gm2_performance_nonce');
            echo '<input type="hidden" name="action" value="gm2_performance_settings" />';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row">Auto-fill missing alt text</th><td><label><input type="checkbox" name="gm2_auto_fill_alt" value="1" ' . checked($auto_fill, '1', false) . '> Use product title</label></td></tr>';
            echo '<tr><th scope="row">Enable Image Compression</th><td><input type="checkbox" name="gm2_enable_compression" value="1" ' . checked($enable_comp, '1', false) . '></td></tr>';
            echo '<tr><th scope="row">Compression API Key</th><td><input type="text" name="gm2_compression_api_key" value="' . esc_attr($api_key) . '" class="regular-text" /></td></tr>';
            echo '<tr><th scope="row">Compression API URL</th><td><input type="text" name="gm2_compression_api_url" value="' . esc_attr($api_url) . '" class="regular-text" /></td></tr>';
            echo '<tr><th scope="row">Minify HTML</th><td><label><input type="checkbox" name="gm2_minify_html" value="1" ' . checked($min_html, '1', false) . '></label></td></tr>';
            echo '<tr><th scope="row">Minify CSS</th><td><label><input type="checkbox" name="gm2_minify_css" value="1" ' . checked($min_css, '1', false) . '></label></td></tr>';
            echo '<tr><th scope="row">Minify JS</th><td><label><input type="checkbox" name="gm2_minify_js" value="1" ' . checked($min_js, '1', false) . '></label></td></tr>';
            echo '</tbody></table>';
            submit_button('Save Settings');
            echo '</form>';
        } elseif ($active === 'rules') {
            $all_rules = get_option('gm2_content_rules', []);
            if (!empty($_GET['updated'])) {
                echo '<div class="updated notice"><p>Rules saved.</p></div>';
            }
            echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
            wp_nonce_field('gm2_content_rules_save', 'gm2_content_rules_nonce');
            echo '<input type="hidden" name="action" value="gm2_content_rules" />';
            echo '<table class="form-table"><tbody>';
            foreach ($this->get_supported_post_types() as $pt) {
                $label = get_post_type_object($pt)->labels->singular_name ?? ucfirst($pt);
                $val   = $all_rules['post_' . $pt] ?? '';
                echo '<tr><th scope="row"><label for="gm2_rule_post_' . esc_attr($pt) . '">' . esc_html($label) . '</label></th>';
                echo '<td><textarea id="gm2_rule_post_' . esc_attr($pt) . '" name="gm2_content_rules[post_' . esc_attr($pt) . ']" rows="3" class="large-text">' . esc_textarea($val) . '</textarea></td></tr>';
            }
            foreach ($this->get_supported_taxonomies() as $tax) {
                $tax_obj = get_taxonomy($tax);
                $label   = $tax_obj ? $tax_obj->labels->singular_name : $tax;
                $val     = $all_rules['tax_' . $tax] ?? '';
                echo '<tr><th scope="row"><label for="gm2_rule_tax_' . esc_attr($tax) . '">' . esc_html($label) . '</label></th>';
                echo '<td><textarea id="gm2_rule_tax_' . esc_attr($tax) . '" name="gm2_content_rules[tax_' . esc_attr($tax) . ']" rows="3" class="large-text">' . esc_textarea($val) . '</textarea></td></tr>';
            }
            echo '</tbody></table>';
            submit_button('Save Rules');
            echo '</form>';
        } else {
            echo '<form method="post" action="options.php">';
            settings_fields('gm2_seo_options');
            do_settings_sections('gm2_seo');
            submit_button();
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
        $title = '';
        $description = '';
        $noindex = '';
        $nofollow = '';
        $canonical      = '';
        $focus_keywords = '';
        if (is_object($term)) {
            $title       = get_term_meta($term->term_id, '_gm2_title', true);
            $description = get_term_meta($term->term_id, '_gm2_description', true);
            $noindex     = get_term_meta($term->term_id, '_gm2_noindex', true);
            $nofollow    = get_term_meta($term->term_id, '_gm2_nofollow', true);
            $canonical   = get_term_meta($term->term_id, '_gm2_canonical', true);
            $focus_keywords = get_term_meta($term->term_id, '_gm2_focus_keywords', true);
        }
        wp_nonce_field('gm2_save_seo_meta', 'gm2_seo_nonce');

        if (is_object($term)) {
            echo '<tr class="form-field"><th scope="row"><label for="gm2_seo_title">SEO Title</label></th><td><input name="gm2_seo_title" id="gm2_seo_title" type="text" value="' . esc_attr($title) . '" class="regular-text" /></td></tr>';
            echo '<tr class="form-field"><th scope="row"><label for="gm2_seo_description">SEO Description</label></th><td><textarea name="gm2_seo_description" id="gm2_seo_description" rows="5" class="large-text">' . esc_textarea($description) . '</textarea></td></tr>';
            echo '<tr class="form-field"><th scope="row">Robots</th><td><label><input type="checkbox" name="gm2_noindex" value="1" ' . checked($noindex, '1', false) . '> noindex</label><br/><label><input type="checkbox" name="gm2_nofollow" value="1" ' . checked($nofollow, '1', false) . '> nofollow</label></td></tr>';
            echo '<tr class="form-field"><th scope="row"><label for="gm2_canonical_url">Canonical URL</label></th><td><input name="gm2_canonical_url" id="gm2_canonical_url" type="url" value="' . esc_attr($canonical) . '" class="regular-text" /></td></tr>';
            echo '<tr class="form-field"><th scope="row"><label for="gm2_focus_keywords">Focus Keywords (comma separated)</label></th><td><input name="gm2_focus_keywords" id="gm2_focus_keywords" type="text" value="' . esc_attr($focus_keywords) . '" class="regular-text" /></td></tr>';
        } else {
            echo '<div class="form-field"><label for="gm2_seo_title">SEO Title</label><input type="text" name="gm2_seo_title" id="gm2_seo_title" value="" /></div>';
            echo '<div class="form-field"><label for="gm2_seo_description">SEO Description</label><textarea name="gm2_seo_description" id="gm2_seo_description" rows="5"></textarea></div>';
            echo '<div class="form-field"><label><input type="checkbox" name="gm2_noindex" value="1"> noindex</label></div>';
            echo '<div class="form-field"><label><input type="checkbox" name="gm2_nofollow" value="1"> nofollow</label></div>';
            echo '<div class="form-field"><label for="gm2_canonical_url">Canonical URL</label><input type="url" name="gm2_canonical_url" id="gm2_canonical_url" /></div>';
            echo '<div class="form-field"><label for="gm2_focus_keywords">Focus Keywords (comma separated)</label><input type="text" name="gm2_focus_keywords" id="gm2_focus_keywords" /></div>';
        }
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
        $noindex     = isset($_POST['gm2_noindex']) ? '1' : '0';
        $nofollow    = isset($_POST['gm2_nofollow']) ? '1' : '0';
        $canonical      = isset($_POST['gm2_canonical_url']) ? esc_url_raw($_POST['gm2_canonical_url']) : '';
        $focus_keywords = isset($_POST['gm2_focus_keywords']) ? sanitize_text_field($_POST['gm2_focus_keywords']) : '';
        update_post_meta($post_id, '_gm2_title', $title);
        update_post_meta($post_id, '_gm2_description', $description);
        update_post_meta($post_id, '_gm2_noindex', $noindex);
        update_post_meta($post_id, '_gm2_nofollow', $nofollow);
        update_post_meta($post_id, '_gm2_canonical', $canonical);
        update_post_meta($post_id, '_gm2_focus_keywords', $focus_keywords);
    }

    public function save_taxonomy_meta($term_id) {
        if (!isset($_POST['gm2_seo_nonce']) || !wp_verify_nonce($_POST['gm2_seo_nonce'], 'gm2_save_seo_meta')) {
            return;
        }
        $title       = isset($_POST['gm2_seo_title']) ? sanitize_text_field($_POST['gm2_seo_title']) : '';
        $description = isset($_POST['gm2_seo_description']) ? sanitize_textarea_field($_POST['gm2_seo_description']) : '';
        $noindex     = isset($_POST['gm2_noindex']) ? '1' : '0';
        $nofollow    = isset($_POST['gm2_nofollow']) ? '1' : '0';
        $canonical      = isset($_POST['gm2_canonical_url']) ? esc_url_raw($_POST['gm2_canonical_url']) : '';
        $focus_keywords = isset($_POST['gm2_focus_keywords']) ? sanitize_text_field($_POST['gm2_focus_keywords']) : '';
        update_term_meta($term_id, '_gm2_title', $title);
        update_term_meta($term_id, '_gm2_description', $description);
        update_term_meta($term_id, '_gm2_noindex', $noindex);
        update_term_meta($term_id, '_gm2_nofollow', $nofollow);
        update_term_meta($term_id, '_gm2_canonical', $canonical);
        update_term_meta($term_id, '_gm2_focus_keywords', $focus_keywords);
    }

    public function handle_sitemap_form() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        if (!isset($_POST['gm2_sitemap_nonce']) || !wp_verify_nonce($_POST['gm2_sitemap_nonce'], 'gm2_sitemap_save')) {
            wp_die('Invalid nonce');
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
            wp_die('Permission denied');
        }

        if (!isset($_POST['gm2_meta_tags_nonce']) || !wp_verify_nonce($_POST['gm2_meta_tags_nonce'], 'gm2_meta_tags_save')) {
            wp_die('Invalid nonce');
        }

        $variants = isset($_POST['gm2_noindex_variants']) ? '1' : '0';
        update_option('gm2_noindex_variants', $variants);

        $oos = isset($_POST['gm2_noindex_oos']) ? '1' : '0';
        update_option('gm2_noindex_oos', $oos);

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=meta&updated=1'));
        exit;
    }

    public function handle_schema_form() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        if (!isset($_POST['gm2_schema_nonce']) || !wp_verify_nonce($_POST['gm2_schema_nonce'], 'gm2_schema_save')) {
            wp_die('Invalid nonce');
        }

        $product    = isset($_POST['gm2_schema_product']) ? '1' : '0';
        update_option('gm2_schema_product', $product);

        $brand      = isset($_POST['gm2_schema_brand']) ? '1' : '0';
        update_option('gm2_schema_brand', $brand);

        $breadcrumbs = isset($_POST['gm2_schema_breadcrumbs']) ? '1' : '0';
        update_option('gm2_schema_breadcrumbs', $breadcrumbs);

        $footer_bc = isset($_POST['gm2_show_footer_breadcrumbs']) ? '1' : '0';
        update_option('gm2_show_footer_breadcrumbs', $footer_bc);

        $review     = isset($_POST['gm2_schema_review']) ? '1' : '0';
        update_option('gm2_schema_review', $review);

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=schema&updated=1'));
        exit;
    }

    public function handle_performance_form() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        if (!isset($_POST['gm2_performance_nonce']) || !wp_verify_nonce($_POST['gm2_performance_nonce'], 'gm2_performance_save')) {
            wp_die('Invalid nonce');
        }

        $auto_fill = isset($_POST['gm2_auto_fill_alt']) ? '1' : '0';
        update_option('gm2_auto_fill_alt', $auto_fill);

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

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=performance&updated=1'));
        exit;
    }

    public function handle_redirects_form() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        if (!isset($_POST['gm2_redirects_nonce']) || !wp_verify_nonce($_POST['gm2_redirects_nonce'], 'gm2_redirects_save')) {
            wp_die('Invalid nonce');
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
            wp_die('Permission denied');
        }

        if (!isset($_POST['gm2_content_rules_nonce']) || !wp_verify_nonce($_POST['gm2_content_rules_nonce'], 'gm2_content_rules_save')) {
            wp_die('Invalid nonce');
        }

        $rules = [];
        if (isset($_POST['gm2_content_rules']) && is_array($_POST['gm2_content_rules'])) {
            foreach ($_POST['gm2_content_rules'] as $k => $v) {
                $rules[$k] = sanitize_textarea_field($v);
            }
        }
        update_option('gm2_content_rules', $rules);

        wp_redirect(admin_url('admin.php?page=gm2-seo&tab=rules&updated=1'));
        exit;
    }

    public function auto_fill_alt_on_upload($attachment_id) {
        if (get_option('gm2_auto_fill_alt', '0') !== '1') {
            return;
        }

        $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if ($alt === '') {
            $title = get_post($attachment_id)->post_title;
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($title));
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

    public function ajax_check_rules() {
        check_ajax_referer('gm2_check_rules');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('permission denied', 403);
        }

        $strlen = function ($str) {
            return function_exists('mb_strlen') ? mb_strlen($str) : strlen($str);
        };

        $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : 'post';

        $title       = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        $focus       = isset($_POST['focus']) ? sanitize_text_field(wp_unslash($_POST['focus'])) : '';
        $content     = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';

        $text       = wp_strip_all_tags($content);
        $word_count = str_word_count($text);

        $rules_option = get_option('gm2_content_rules', []);
        $rule_lines = [];
        if (isset($rules_option['post_' . $post_type])) {
            $rule_lines = array_filter(array_map('trim', explode("\n", $rules_option['post_' . $post_type])));
        }
        if (!$rule_lines) {
            $rule_lines = [
                'Title length between 30 and 60 characters',
                'Description length between 50 and 160 characters',
                'At least one focus keyword',
                'Content has at least 300 words',
            ];
        }

        $results = [];
        foreach ($rule_lines as $line) {
            $key = sanitize_title($line);
            $pass = false;
            if (preg_match('/title.*?(\d+).*?(\d+)/i', $line, $m)) {
                $min = (int) $m[1];
                $max = (int) $m[2];
                $pass = $strlen($title) >= $min && $strlen($title) <= $max;
            } elseif (preg_match('/description.*?(\d+).*?(\d+)/i', $line, $m)) {
                $min = (int) $m[1];
                $max = (int) $m[2];
                $pass = $strlen($description) >= $min && $strlen($description) <= $max;
            } elseif (stripos($line, 'focus keyword') !== false) {
                $pass = trim($focus) !== '';
            } elseif (preg_match('/(\d+).*words/i', $line, $m)) {
                $min = (int) $m[1];
                $pass = $word_count >= $min;
            }
            $results[$key] = $pass;
        }

        wp_send_json_success($results);
    }

    public function enqueue_editor_scripts($hook) {
        global $typenow;
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        if (!in_array($typenow, $this->get_supported_post_types(), true)) {
            return;
        }

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
        if (isset($all_rules['post_' . $typenow])) {
            $current_rules = array_filter(array_map('trim', explode("\n", $all_rules['post_' . $typenow])));
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

    public function render_seo_tabs_meta_box($post) {
        $title          = get_post_meta($post->ID, '_gm2_title', true);
        $description    = get_post_meta($post->ID, '_gm2_description', true);
        $noindex        = get_post_meta($post->ID, '_gm2_noindex', true);
        $nofollow       = get_post_meta($post->ID, '_gm2_nofollow', true);
        $canonical      = get_post_meta($post->ID, '_gm2_canonical', true);
        $focus_keywords = get_post_meta($post->ID, '_gm2_focus_keywords', true);

        wp_nonce_field('gm2_save_seo_meta', 'gm2_seo_nonce');

        echo '<div class="gm2-seo-tabs">';
        echo '<nav class="gm2-nav-tabs">';
        echo '<a href="#" class="gm2-nav-tab active" data-tab="gm2-seo-settings">SEO Settings</a>';
        echo '<a href="#" class="gm2-nav-tab" data-tab="gm2-content-analysis">Content Analysis</a>';
        echo '</nav>';

        echo '<div id="gm2-seo-settings" class="gm2-tab-panel active">';
        echo '<p><label for="gm2_seo_title">SEO Title</label>';
        echo '<input type="text" id="gm2_seo_title" name="gm2_seo_title" value="' . esc_attr($title) . '" class="widefat" /></p>';
        echo '<p><label for="gm2_seo_description">SEO Description</label>';
        echo '<textarea id="gm2_seo_description" name="gm2_seo_description" class="widefat" rows="3">' . esc_textarea($description) . '</textarea></p>';
        echo '<p><label for="gm2_focus_keywords">Focus Keywords (comma separated)</label>';
        echo '<input type="text" id="gm2_focus_keywords" name="gm2_focus_keywords" value="' . esc_attr($focus_keywords) . '" class="widefat" /></p>';
        echo '<p><label><input type="checkbox" name="gm2_noindex" value="1" ' . checked($noindex, '1', false) . '> noindex</label></p>';
        echo '<p><label><input type="checkbox" name="gm2_nofollow" value="1" ' . checked($nofollow, '1', false) . '> nofollow</label></p>';
        echo '<p><label for="gm2_canonical_url">Canonical URL</label>';
        echo '<input type="url" id="gm2_canonical_url" name="gm2_canonical_url" value="' . esc_attr($canonical) . '" class="widefat" /></p>';
        echo '</div>';

        echo '<div id="gm2-content-analysis" class="gm2-tab-panel">';
        echo '<ul class="gm2-analysis-rules">';
        $rules_option = get_option('gm2_content_rules', []);
        $rule_lines = [];
        if (isset($rules_option['post_' . $post->post_type])) {
            $rule_lines = array_filter(array_map('trim', explode("\n", $rules_option['post_' . $post->post_type])));
        }
        if (!$rule_lines) {
            $rule_lines = [
                'Title length between 30 and 60 characters',
                'Description length between 50 and 160 characters',
                'At least one focus keyword',
                'Content has at least 300 words',
            ];
        }
        foreach ($rule_lines as $idx => $text) {
            $key = sanitize_title($text);
            echo '<li data-rule="' . esc_attr($key) . '"><span class="dashicons dashicons-no"></span> ' . esc_html($text) . '</li>';
        }
        echo '</ul>';
        echo '<div id="gm2-content-analysis-data">';
        echo '<p>Word Count: <span id="gm2-content-analysis-word-count">0</span></p>';
        echo '<p>Top Keyword: <span id="gm2-content-analysis-keyword"></span></p>';
        echo '<p>Keyword Density: <span id="gm2-content-analysis-density">0</span>%</p>';
        echo '<p>Focus Keyword Density:</p><ul id="gm2-focus-keyword-density"></ul>';
        echo '<p>Readability: <span id="gm2-content-analysis-readability">0</span></p>';
        echo '<p>Suggested Links:</p><ul id="gm2-content-analysis-links"></ul>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}

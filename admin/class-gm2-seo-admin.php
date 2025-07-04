<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gm2_SEO_Admin {
    public function run() {
        add_action('admin_menu', [$this, 'add_settings_pages']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post', [$this, 'save_post_meta']);

        $taxonomies = $this->get_supported_taxonomies();
        foreach ($taxonomies as $tax) {
            add_action("{$tax}_add_form_fields", [$this, 'render_taxonomy_meta_box']);
            add_action("{$tax}_edit_form_fields", [$this, 'render_taxonomy_meta_box']);
            add_action("create_{$tax}", [$this, 'save_taxonomy_meta']);
            add_action("edited_{$tax}", [$this, 'save_taxonomy_meta']);
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
        add_menu_page(
            'SEO',
            'SEO',
            'manage_options',
            'gm2-seo',
            [$this, 'display_dashboard'],
            'dashicons-chart-line'
        );

        add_submenu_page(
            'gm2-seo',
            'Meta Tags',
            'Meta Tags',
            'manage_options',
            'gm2-meta-tags',
            [$this, 'display_meta_tags_page']
        );

        add_submenu_page(
            'gm2-seo',
            'Sitemap',
            'Sitemap',
            'manage_options',
            'gm2-sitemap',
            [$this, 'display_sitemap_page']
        );

        add_submenu_page(
            'gm2-seo',
            'Redirects',
            'Redirects',
            'manage_options',
            'gm2-redirects',
            [$this, 'display_redirects_page']
        );

        add_submenu_page(
            'gm2-seo',
            'Performance',
            'Performance',
            'manage_options',
            'gm2-performance',
            [$this, 'display_performance_page']
        );
    }

    public function display_dashboard() {
        echo '<div class="wrap"><h1>SEO Settings</h1></div>';
    }

    public function display_meta_tags_page() {
        echo '<div class="wrap"><h1>Meta Tags</h1><p>Manage meta tags here.</p></div>';
    }

    public function display_sitemap_page() {
        echo '<div class="wrap"><h1>Sitemap</h1><p>Configure sitemap settings.</p></div>';
    }

    public function display_redirects_page() {
        echo '<div class="wrap"><h1>Redirects</h1><p>Manage URL redirects.</p></div>';
    }

    public function display_performance_page() {
        echo '<div class="wrap"><h1>Performance</h1><p>Performance settings.</p></div>';
    }

    public function register_meta_boxes() {
        foreach ($this->get_supported_post_types() as $type) {
            add_meta_box(
                'gm2_seo_' . $type . '_meta',
                'SEO Settings',
                [$this, 'render_post_meta_box'],
                $type,
                'normal',
                'high'
            );
        }
    }

    public function render_post_meta_box($post) {
        $title       = get_post_meta($post->ID, '_gm2_title', true);
        $description = get_post_meta($post->ID, '_gm2_description', true);
        wp_nonce_field('gm2_save_seo_meta', 'gm2_seo_nonce');
        echo '<p><label for="gm2_seo_title">SEO Title</label>';
        echo '<input type="text" id="gm2_seo_title" name="gm2_seo_title" value="' . esc_attr($title) . '" class="widefat" /></p>';
        echo '<p><label for="gm2_seo_description">SEO Description</label>';
        echo '<textarea id="gm2_seo_description" name="gm2_seo_description" class="widefat" rows="3">' . esc_textarea($description) . '</textarea></p>';
    }

    public function render_taxonomy_meta_box($term) {
        $title = '';
        $description = '';
        if (is_object($term)) {
            $title       = get_term_meta($term->term_id, '_gm2_title', true);
            $description = get_term_meta($term->term_id, '_gm2_description', true);
        }
        wp_nonce_field('gm2_save_seo_meta', 'gm2_seo_nonce');

        if (is_object($term)) {
            echo '<tr class="form-field"><th scope="row"><label for="gm2_seo_title">SEO Title</label></th><td><input name="gm2_seo_title" id="gm2_seo_title" type="text" value="' . esc_attr($title) . '" class="regular-text" /></td></tr>';
            echo '<tr class="form-field"><th scope="row"><label for="gm2_seo_description">SEO Description</label></th><td><textarea name="gm2_seo_description" id="gm2_seo_description" rows="5" class="large-text">' . esc_textarea($description) . '</textarea></td></tr>';
        } else {
            echo '<div class="form-field"><label for="gm2_seo_title">SEO Title</label><input type="text" name="gm2_seo_title" id="gm2_seo_title" value="" /></div>';
            echo '<div class="form-field"><label for="gm2_seo_description">SEO Description</label><textarea name="gm2_seo_description" id="gm2_seo_description" rows="5"></textarea></div>';
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
        update_post_meta($post_id, '_gm2_title', $title);
        update_post_meta($post_id, '_gm2_description', $description);
    }

    public function save_taxonomy_meta($term_id) {
        if (!isset($_POST['gm2_seo_nonce']) || !wp_verify_nonce($_POST['gm2_seo_nonce'], 'gm2_save_seo_meta')) {
            return;
        }
        $title       = isset($_POST['gm2_seo_title']) ? sanitize_text_field($_POST['gm2_seo_title']) : '';
        $description = isset($_POST['gm2_seo_description']) ? sanitize_textarea_field($_POST['gm2_seo_description']) : '';
        update_term_meta($term_id, '_gm2_title', $title);
        update_term_meta($term_id, '_gm2_description', $description);
    }
}

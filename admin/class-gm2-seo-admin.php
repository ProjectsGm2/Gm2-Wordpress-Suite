<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gm2_SEO_Admin {
    public function run() {
        add_action('admin_menu', [$this, 'add_settings_pages']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
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
        add_meta_box(
            'gm2_seo_post_meta',
            'SEO Settings',
            [$this, 'render_post_meta_box'],
            'post',
            'normal',
            'high'
        );

        if (post_type_exists('product')) {
            add_meta_box(
                'gm2_seo_product_meta',
                'SEO Settings',
                [$this, 'render_product_meta_box'],
                'product',
                'normal',
                'high'
            );
        }

        add_action('category_add_form_fields', [$this, 'render_taxonomy_meta_box']);
        add_action('category_edit_form_fields', [$this, 'render_taxonomy_meta_box']);
    }

    public function render_post_meta_box($post) {
        echo '<p>Post SEO options go here.</p>';
    }

    public function render_product_meta_box($post) {
        echo '<p>Product SEO options go here.</p>';
    }

    public function render_taxonomy_meta_box($term) {
        echo '<div class="form-field"><label>SEO Title</label><input type="text" name="gm2_seo_title" value="" /></div>';
    }
}

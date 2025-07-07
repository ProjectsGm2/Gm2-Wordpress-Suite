<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Elementor {
    private $seo_admin;

    public function __construct($seo_admin) {
        $this->seo_admin = $seo_admin;
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_editor_assets']);
        add_action('elementor/editor/footer', [$this, 'render_meta_box']);
        add_action('elementor/document/after_save', [$this, 'save_meta'], 10, 2);
    }

    public function enqueue_editor_assets() {
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
    }

    public function render_meta_box() {
        global $post;
        if ($post) {
            $this->seo_admin->render_seo_tabs_meta_box($post);
        }
    }

    public function save_meta($document) {
        if (is_object($document) && method_exists($document, 'get_main_id')) {
            $post_id = $document->get_main_id();
        } else {
            $post_id = intval($document);
        }
        $this->seo_admin->save_post_meta($post_id);
    }
}

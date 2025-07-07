<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Elementor_SEO {
    public function __construct() {
        add_action('init', [$this, 'init']);
    }

    public function init() {
        if (!class_exists('Elementor\\Plugin')) {
            return;
        }
        add_action('elementor/documents/register_controls', [$this, 'register_controls']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_editor_assets']);
        add_action('elementor/document/save', [$this, 'save_document']);
    }

    public function register_controls($document) {
        if (!method_exists($document, 'get_main_id')) {
            return;
        }
        $post_id = $document->get_main_id();
        $document->start_controls_section(
            'gm2_seo_section',
            [
                'label' => __('GM2 SEO', 'gm2-wordpress-suite'),
                'tab'   => \Elementor\Controls_Manager::TAB_SETTINGS,
            ]
        );
        $document->add_control(
            'gm2_seo_title',
            [
                'label' => __('SEO Title', 'gm2-wordpress-suite'),
                'type'  => \Elementor\Controls_Manager::TEXT,
                'default' => get_post_meta($post_id, '_gm2_title', true),
            ]
        );
        $document->add_control(
            'gm2_seo_description',
            [
                'label' => __('SEO Description', 'gm2-wordpress-suite'),
                'type'  => \Elementor\Controls_Manager::TEXTAREA,
                'default' => get_post_meta($post_id, '_gm2_description', true),
            ]
        );
        $document->add_control(
            'gm2_focus_keywords',
            [
                'label' => __('Focus Keywords', 'gm2-wordpress-suite'),
                'type'  => \Elementor\Controls_Manager::TEXT,
                'default' => get_post_meta($post_id, '_gm2_focus_keywords', true),
            ]
        );
        $document->add_control(
            'gm2_noindex',
            [
                'label' => __('noindex', 'gm2-wordpress-suite'),
                'type'  => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => '1',
                'default' => get_post_meta($post_id, '_gm2_noindex', true) === '1' ? '1' : '',
            ]
        );
        $document->add_control(
            'gm2_nofollow',
            [
                'label' => __('nofollow', 'gm2-wordpress-suite'),
                'type'  => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => '1',
                'default' => get_post_meta($post_id, '_gm2_nofollow', true) === '1' ? '1' : '',
            ]
        );
        $document->add_control(
            'gm2_canonical_url',
            [
                'label' => __('Canonical URL', 'gm2-wordpress-suite'),
                'type'  => \Elementor\Controls_Manager::URL,
                'default' => [ 'url' => get_post_meta($post_id, '_gm2_canonical', true) ],
            ]
        );
        $document->end_controls_section();
    }

    public function enqueue_editor_assets() {
        $admin = new Gm2_SEO_Admin();
        $admin->enqueue_editor_scripts();
    }

    public function save_document($document) {
        if (!method_exists($document, 'get_main_id')) {
            return;
        }
        $post_id = $document->get_main_id();
        $data = $document->get_settings();
        $this->save_post_meta($post_id, $data);
    }

    private function save_post_meta($post_id, $data) {
        $title       = isset($data['gm2_seo_title']) ? sanitize_text_field($data['gm2_seo_title']) : '';
        $description = isset($data['gm2_seo_description']) ? sanitize_textarea_field($data['gm2_seo_description']) : '';
        $noindex     = empty($data['gm2_noindex']) ? '0' : '1';
        $nofollow    = empty($data['gm2_nofollow']) ? '0' : '1';
        $canonical_val = $data['gm2_canonical_url'] ?? '';
        if (is_array($canonical_val)) {
            $canonical_val = $canonical_val['url'] ?? '';
        }
        $canonical   = esc_url_raw($canonical_val);
        $focus       = isset($data['gm2_focus_keywords']) ? sanitize_text_field($data['gm2_focus_keywords']) : '';
        update_post_meta($post_id, '_gm2_title', $title);
        update_post_meta($post_id, '_gm2_description', $description);
        update_post_meta($post_id, '_gm2_noindex', $noindex);
        update_post_meta($post_id, '_gm2_nofollow', $nofollow);
        update_post_meta($post_id, '_gm2_canonical', $canonical);
        update_post_meta($post_id, '_gm2_focus_keywords', $focus);
    }
}

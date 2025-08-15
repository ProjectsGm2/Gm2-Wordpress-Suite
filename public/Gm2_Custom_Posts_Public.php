<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Custom_Posts_Public {
    public function run() {
        add_action('init', [ $this, 'register_from_config' ]);
        add_shortcode('gm2_custom_post_fields', [ $this, 'shortcode' ]);
        add_action('wp_enqueue_scripts', [ $this, 'enqueue_assets' ]);
    }

    public function register_from_config() {
        $config = get_option('gm2_custom_posts_config', []);
        if (!is_array($config)) {
            return;
        }
        if (!empty($config['post_types'])) {
            foreach ($config['post_types'] as $slug => $pt) {
                $args = [];
                foreach (($pt['args'] ?? []) as $a_key => $a_val) {
                    $args[$a_key] = is_array($a_val) && array_key_exists('value', $a_val) ? $a_val['value'] : $a_val;
                }
                $label = $pt['label'] ?? ucfirst($slug);
                $args['labels']['name'] = $label;
                $args['labels']['singular_name'] = $label;
                $args = array_merge(['public' => true, 'show_in_rest' => true], $args);
                register_post_type($slug, $args);
            }
        }
        if (!empty($config['taxonomies'])) {
            foreach ($config['taxonomies'] as $slug => $tax) {
                $args = [];
                foreach (($tax['args'] ?? []) as $a_key => $a_val) {
                    $args[$a_key] = is_array($a_val) && array_key_exists('value', $a_val) ? $a_val['value'] : $a_val;
                }
                $label = $tax['label'] ?? ucfirst($slug);
                $args['labels']['name'] = $label;
                $args['labels']['singular_name'] = $label;
                $args = array_merge(['public' => true, 'show_in_rest' => true], $args);
                $post_types = $tax['post_types'] ?? [];
                register_taxonomy($slug, $post_types, $args);
            }
        }
    }

    public function enqueue_assets() {
        $css = GM2_PLUGIN_DIR . 'public/css/gm2-custom-posts.css';
        if (file_exists($css)) {
            wp_enqueue_style(
                'gm2-custom-posts',
                GM2_PLUGIN_URL . 'public/css/gm2-custom-posts.css',
                [],
                filemtime($css)
            );
        }

        $js = GM2_PLUGIN_DIR . 'public/js/gm2-custom-posts.js';
        if (file_exists($js)) {
            wp_enqueue_script(
                'gm2-custom-posts',
                GM2_PLUGIN_URL . 'public/js/gm2-custom-posts.js',
                ['jquery'],
                filemtime($js),
                true
            );
        }
    }

    public function shortcode($atts) {
        return gm2_render_custom_post_fields(get_post());
    }
}

function gm2_render_custom_post_fields($post = null) {
    $post = get_post($post);
    if (!$post) {
        return '';
    }
    $config = get_option('gm2_custom_posts_config', []);
    $ptype = $post->post_type;
    if (empty($config['post_types'][$ptype]['fields'])) {
        return '';
    }
    $fields = $config['post_types'][$ptype]['fields'];
    $out = '<div class="gm2-custom-fields">';
    foreach ($fields as $key => $field) {
        $label = $field['label'] ?? $key;
        $display = gm2_get_custom_post_field($key, $post);
        if ($display === '') {
            continue;
        }
        $out .= '<div class="gm2-field gm2-field-' . esc_attr($key) . '"><strong>' . esc_html($label) . ':</strong> ' . esc_html($display) . '</div>';
    }
    $out .= '</div>';
    return $out;
}

function gm2_get_custom_post_field($key, $post = null) {
    $post = get_post($post);
    if (!$post) {
        return '';
    }
    $config = get_option('gm2_custom_posts_config', []);
    $ptype = $post->post_type;
    if (empty($config['post_types'][$ptype]['fields'][$key])) {
        return '';
    }
    $field = $config['post_types'][$ptype]['fields'][$key];
    $value = get_post_meta($post->ID, $key, true);
    if ($value === '' || $value === null) {
        return '';
    }
    if (isset($field['options'][$value])) {
        return $field['options'][$value];
    }
    if (($field['type'] ?? '') === 'checkbox') {
        return $value ? __('Yes', 'gm2-wordpress-suite') : __('No', 'gm2-wordpress-suite');
    }
    return $value;
}

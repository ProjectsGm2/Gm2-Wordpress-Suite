<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Custom_Posts_Public {
    public function run() {
        add_action('init', [ $this, 'register_from_config' ]);
        add_shortcode('gm2_custom_post_fields', [ $this, 'shortcode' ]);
    }

    public function register_from_config() {
        $config = get_option('gm2_custom_posts_config', []);
        if (!is_array($config)) {
            return;
        }
        if (!empty($config['post_types'])) {
            foreach ($config['post_types'] as $slug => $pt) {
                $args = $pt['args'] ?? [];
                $label = $pt['label'] ?? ucfirst($slug);
                $args['labels']['name'] = $label;
                $args['labels']['singular_name'] = $label;
                $args = array_merge(['public' => true, 'show_in_rest' => true], $args);
                register_post_type($slug, $args);
            }
        }
        if (!empty($config['taxonomies'])) {
            foreach ($config['taxonomies'] as $slug => $tax) {
                $args = $tax['args'] ?? [];
                $label = $tax['label'] ?? ucfirst($slug);
                $args['labels']['name'] = $label;
                $args['labels']['singular_name'] = $label;
                $args = array_merge(['public' => true, 'show_in_rest' => true], $args);
                $post_types = $tax['post_types'] ?? [];
                register_taxonomy($slug, $post_types, $args);
            }
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
        $value = get_post_meta($post->ID, $key, true);
        if ($value === '' || $value === null) {
            continue;
        }
        if (isset($field['options'][$value])) {
            $display = $field['options'][$value];
        } elseif (($field['type'] ?? '') === 'checkbox') {
            $display = $value ? __('Yes', 'gm2-wordpress-suite') : __('No', 'gm2-wordpress-suite');
        } else {
            $display = $value;
        }
        $out .= '<div class="gm2-field gm2-field-' . esc_attr($key) . '"><strong>' . esc_html($label) . ':</strong> ' . esc_html($display) . '</div>';
    }
    $out .= '</div>';
    return $out;
}

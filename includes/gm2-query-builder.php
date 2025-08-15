<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Query_Manager {
    public static function get_queries() {
        $queries = get_option('gm2_saved_queries', []);
        return is_array($queries) ? $queries : [];
    }

    public static function get_query($id) {
        $queries = self::get_queries();
        return $queries[$id] ?? null;
    }

    public static function save_query($id, $args) {
        $queries = self::get_queries();
        $queries[$id] = $args;
        update_option('gm2_saved_queries', $queries);
    }
}

interface Query_Adapter_Interface {
    public function query($args);
}

class WP_Query_Adapter implements Query_Adapter_Interface {
    public function query($args) {
        return new \WP_Query($args);
    }
}

class Elasticsearch_Adapter implements Query_Adapter_Interface {
    public function query($args) {
        return apply_filters('gm2_elastic_query', [], $args);
    }
}

class OpenSearch_Adapter extends Elasticsearch_Adapter {}

function gm2_get_query_adapter() {
    $adapter = apply_filters('gm2_query_adapter', null);
    if ($adapter instanceof Query_Adapter_Interface) {
        return $adapter;
    }
    return new WP_Query_Adapter();
}

function gm2_run_query($args) {
    $adapter = gm2_get_query_adapter();
    return $adapter->query($args);
}

function gm2_render_posts($query) {
    if ($query instanceof \WP_Query) {
        $output = '<ul class="gm2-query-results">';
        while ($query->have_posts()) {
            $query->the_post();
            $output .= '<li><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></li>';
        }
        wp_reset_postdata();
        $output .= '</ul>';
        return $output;
    }
    return '';
}

function gm2_query_shortcode($atts = []) {
    $atts = shortcode_atts(['id' => ''], $atts, 'gm2_query');
    $id = sanitize_key($atts['id']);
    if (!$id) {
        return '';
    }
    $args = Query_Manager::get_query($id);
    if (!$args) {
        return '';
    }
    $results = gm2_run_query($args);
    return gm2_render_posts($results);
}
add_shortcode('gm2_query', __NAMESPACE__ . '\\gm2_query_shortcode');

function gm2_register_query_block() {
    register_block_type('gm2/query', [
        'attributes' => [
            'id' => [ 'type' => 'string' ],
        ],
        'render_callback' => __NAMESPACE__ . '\\gm2_query_shortcode',
    ]);
}
add_action('init', __NAMESPACE__ . '\\gm2_register_query_block');

add_filter('rest_post_query', function($args, $request) {
    $id = sanitize_key($request->get_param('gm2_query'));
    if ($id) {
        $saved = Query_Manager::get_query($id);
        if ($saved) {
            $args = array_merge($args, $saved);
        }
    }
    return $args;
}, 10, 2);

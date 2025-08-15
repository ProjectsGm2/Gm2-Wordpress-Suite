<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_REST_Visibility {
    const OPTION = 'gm2_rest_visibility';

    public static function init() : void {
        add_action('init', [ __CLASS__, 'apply_visibility' ], 20);
        add_action('rest_api_init', [ __CLASS__, 'register_routes' ]);
        if (class_exists('WPGraphQL')) {
            add_action('graphql_register_types', [ __CLASS__, 'register_graphql' ]);
        }
    }

    protected static function defaults() : array {
        return [
            'post_types' => [],
            'taxonomies' => [],
            'fields' => [],
        ];
    }

    public static function get_visibility() : array {
        $vis = get_option(self::OPTION, []);
        return wp_parse_args(is_array($vis) ? $vis : [], self::defaults());
    }

    public static function apply_visibility() : void {
        $vis = self::get_visibility();
        if (!empty($vis['post_types'])) {
            foreach ($vis['post_types'] as $type => $show) {
                if (post_type_exists($type)) {
                    global $wp_post_types;
                    if (isset($wp_post_types[$type])) {
                        $wp_post_types[$type]->show_in_rest = (bool) $show;
                    }
                }
            }
        }
        if (!empty($vis['taxonomies'])) {
            foreach ($vis['taxonomies'] as $tax => $show) {
                if (taxonomy_exists($tax)) {
                    global $wp_taxonomies;
                    if (isset($wp_taxonomies[$tax])) {
                        $wp_taxonomies[$tax]->show_in_rest = (bool) $show;
                    }
                }
            }
        }
    }

    public static function register_routes() : void {
        register_rest_route('gm2/v1', '/visibility', [
            [
                'methods'  => \WP_REST_Server::READABLE,
                'callback' => [ __CLASS__, 'rest_get' ],
                'permission_callback' => '__return_true',
                'args'     => [],
                'schema'   => [ __CLASS__, 'get_schema' ],
            ],
            [
                'methods'  => \WP_REST_Server::EDITABLE,
                'callback' => [ __CLASS__, 'rest_update' ],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
                'args' => [
                    'post_types' => [
                        'type' => 'object',
                        'sanitize_callback' => [ __CLASS__, 'sanitize_bool_map' ],
                        'validate_callback' => [ __CLASS__, 'validate_bool_map' ],
                    ],
                    'taxonomies' => [
                        'type' => 'object',
                        'sanitize_callback' => [ __CLASS__, 'sanitize_bool_map' ],
                        'validate_callback' => [ __CLASS__, 'validate_bool_map' ],
                    ],
                    'fields'     => [
                        'type' => 'object',
                        'sanitize_callback' => [ __CLASS__, 'sanitize_bool_map' ],
                        'validate_callback' => [ __CLASS__, 'validate_bool_map' ],
                    ],
                ],
                'schema'   => [ __CLASS__, 'get_schema' ],
            ],
        ]);
    }

    public static function rest_get(\WP_REST_Request $req) {
        return rest_ensure_response(self::get_visibility());
    }

    public static function rest_update(\WP_REST_Request $req) {
        $data = [
            'post_types' => (array) $req->get_param('post_types'),
            'taxonomies' => (array) $req->get_param('taxonomies'),
            'fields'     => (array) $req->get_param('fields'),
        ];
        update_option(self::OPTION, $data);
        self::apply_visibility();
        return rest_ensure_response($data);
    }

    public static function sanitize_bool_map($value) {
        $value = (array) $value;
        return array_map('rest_sanitize_boolean', $value);
    }

    public static function validate_bool_map($value) {
        return is_array($value);
    }

    public static function get_schema() : array {
        return [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title'   => 'gm2_rest_visibility',
            'type'    => 'object',
            'properties' => [
                'post_types' => [
                    'type' => 'object',
                    'additionalProperties' => [ 'type' => 'boolean' ],
                ],
                'taxonomies' => [
                    'type' => 'object',
                    'additionalProperties' => [ 'type' => 'boolean' ],
                ],
                'fields' => [
                    'type' => 'object',
                    'additionalProperties' => [ 'type' => 'boolean' ],
                ],
            ],
        ];
    }

    public static function register_graphql() : void {
        if (!function_exists('register_graphql_field')) {
            return;
        }
        register_graphql_field('RootQuery', 'gm2Visibility', [
            'type'        => 'JSON',
            'description' => __('GM2 REST visibility settings', 'gm2-wordpress-suite'),
            'resolve'     => function () {
                return self::get_visibility();
            },
        ]);
    }
}

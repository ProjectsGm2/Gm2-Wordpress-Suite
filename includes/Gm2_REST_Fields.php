<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_REST_Fields {
    public static function init(): void {
        add_action('rest_api_init', [ __CLASS__, 'register_routes' ]);
        if (class_exists('WPGraphQL')) {
            add_action('graphql_register_types', [ __CLASS__, 'register_graphql' ]);
        }
    }

    public static function register_routes(): void {
        register_rest_route('gm2/v1', '/fields/(?P<id>\\d+)', [
            'methods'  => \WP_REST_Server::READABLE,
            'callback' => [ __CLASS__, 'rest_get' ],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
                'format' => [
                    'type' => 'string',
                    'enum' => [ 'raw', 'rendered', 'media' ],
                ],
                'context' => [
                    'type' => 'string',
                    'enum' => [ 'raw', 'rendered', 'media' ],
                ],
            ],
            'schema' => [ __CLASS__, 'get_schema' ],
        ]);
    }

    public static function rest_get(\WP_REST_Request $req) {
        $id = (int) $req->get_param('id');
        $post = get_post($id);
        if (!$id || !$post) {
            return new \WP_Error('gm2_invalid_id', __('Invalid object ID.', 'gm2-wordpress-suite'), [ 'status' => 404 ]);
        }
        $format = $req->get_param('format');
        if (!$format) {
            $format = $req->get_param('context');
        }
        if (!in_array($format, [ 'raw', 'rendered', 'media' ], true)) {
            $format = null;
        }

        $visibility = Gm2_REST_Visibility::get_visibility();
        $fields = array_keys(array_filter($visibility['fields'] ?? []));
        $config = get_option('gm2_custom_posts_config', []);
        $defs = $config['post_types'][$post->post_type]['fields'] ?? [];
        $data = [];
        foreach ($fields as $field) {
            if (!Gm2_Capability_Manager::can_read_field($field, $id)) {
                continue;
            }
            $value = get_post_meta($id, $field, true);
            $mode = $format ?: ($defs[$field]['serialize'] ?? 'raw');
            if ($mode === 'rendered') {
                $data[$field] = is_scalar($value) ? apply_filters('the_content', $value) : $value;
            } elseif ($mode === 'media') {
                if (is_numeric($value) && ($attachment = get_post((int) $value)) && $attachment->post_type === 'attachment') {
                    $data[$field] = wp_prepare_attachment_for_js((int) $value);
                } else {
                    $data[$field] = $value;
                }
            } else {
                $data[$field] = $value;
            }
        }
        return rest_ensure_response($data);
    }

    public static function get_schema(): array {
        return [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title'   => 'gm2_fields',
            'type'    => 'object',
            'properties' => [
                'id' => [ 'type' => 'integer' ],
                'format' => [ 'type' => 'string' ],
                'context' => [ 'type' => 'string' ],
            ],
            'required' => [ 'id' ],
        ];
    }

    public static function register_graphql(): void {
        if (!function_exists('register_graphql_field') || !function_exists('register_graphql_object_type')) {
            return;
        }
        $visibility = Gm2_REST_Visibility::get_visibility();
        foreach (array_keys(array_filter($visibility['post_types'] ?? [])) as $type) {
            if (!post_type_exists($type)) {
                continue;
            }
            $graphql_type = self::graphql_type_name($type);
            register_graphql_object_type($graphql_type, [
                'fields' => [
                    'id' => [ 'type' => 'ID' ],
                ],
            ]);
        }
        foreach (array_keys(array_filter($visibility['taxonomies'] ?? [])) as $tax) {
            if (!taxonomy_exists($tax)) {
                continue;
            }
            $graphql_type = self::graphql_type_name($tax);
            register_graphql_object_type($graphql_type, [
                'fields' => [
                    'id' => [ 'type' => 'ID' ],
                ],
            ]);
        }
        foreach (array_keys(array_filter($visibility['fields'] ?? [])) as $field) {
            register_graphql_field('ContentNode', $field, [
                'type'        => 'String',
                'description' => sprintf(__('GM2 field %s', 'gm2-wordpress-suite'), $field),
                'resolve'     => function ($post) use ($field) {
                    if (!isset($post->ID) || !Gm2_Capability_Manager::can_read_field($field, (int) $post->ID)) {
                        return null;
                    }
                    $value = get_post_meta((int) $post->ID, $field, true);
                    return is_scalar($value) ? (string) $value : $value;
                },
            ]);
        }
    }

    protected static function graphql_type_name(string $name): string {
        $parts = preg_split('/[_-]/', $name);
        $parts = array_map('ucfirst', array_filter($parts));
        return implode('', $parts);
    }
}

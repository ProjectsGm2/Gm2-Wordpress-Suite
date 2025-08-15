<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_REST_Fields {
    public static function init(): void {
        add_action('rest_api_init', [ __CLASS__, 'register_routes' ]);
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
        if (!$id || !get_post($id)) {
            return new \WP_Error('gm2_invalid_id', __('Invalid object ID.', 'gm2-wordpress-suite'), [ 'status' => 404 ]);
        }
        $format = $req->get_param('format');
        if (!$format) {
            $format = $req->get_param('context');
        }
        if (!in_array($format, [ 'rendered', 'media' ], true)) {
            $format = 'raw';
        }

        $visibility = Gm2_REST_Visibility::get_visibility();
        $fields = array_keys(array_filter($visibility['fields'] ?? []));
        $data = [];
        foreach ($fields as $field) {
            $value = get_post_meta($id, $field, true);
            if ($format === 'rendered') {
                $data[$field] = is_scalar($value) ? apply_filters('the_content', $value) : $value;
            } elseif ($format === 'media') {
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
}

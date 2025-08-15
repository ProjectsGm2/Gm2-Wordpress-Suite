<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_REST_Media {
    public static function init(): void {
        add_action('rest_api_init', [ __CLASS__, 'register_routes' ]);
        add_action('gm2_generate_thumbnails', [ __CLASS__, 'generate_thumbnails' ]);
    }

    public static function register_routes(): void {
        register_rest_route('gm2/v1', '/media/(?P<id>\d+)/thumbnails', [
            'methods'  => \WP_REST_Server::EDITABLE,
            'callback' => [ __CLASS__, 'schedule' ],
            'permission_callback' => function () {
                return current_user_can('upload_files');
            },
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
            ],
            'schema' => [ __CLASS__, 'get_schema' ],
        ]);
    }

    public static function schedule(\WP_REST_Request $req) {
        $id = (int) $req->get_param('id');
        if (!wp_attachment_is_image($id)) {
            return new \WP_Error('gm2_invalid_attachment', __('Attachment must be an image.', 'gm2-wordpress-suite'), [ 'status' => 400 ]);
        }
        wp_schedule_single_event(time(), 'gm2_generate_thumbnails', [ $id ]);
        return rest_ensure_response([ 'scheduled' => true ]);
    }

    public static function generate_thumbnails($id): void {
        $file = get_attached_file($id);
        if (!$file) {
            return;
        }
        $metadata = wp_generate_attachment_metadata($id, $file);
        if (!is_wp_error($metadata)) {
            wp_update_attachment_metadata($id, $metadata);
        }
    }

    public static function get_schema(): array {
        return [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title'   => 'gm2_media',
            'type'    => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                ],
            ],
            'required' => [ 'id' ],
        ];
    }
}

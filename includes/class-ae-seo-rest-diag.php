<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists(__NAMESPACE__ . '\\AE_SEO_REST_Diag')) {
    return;
}

class AE_SEO_REST_Diag {
    public static function init(): void {
        add_action('rest_api_init', [ __CLASS__, 'register_routes' ]);
    }

    public static function register_routes(): void {
        register_rest_route('ae-seo/v1', '/diag/headers', [
            'methods'  => \WP_REST_Server::READABLE,
            'callback' => [ __CLASS__, 'get_headers' ],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args' => [
                'url' => [
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'esc_url_raw',
                    'validate_callback' => [ __CLASS__, 'validate_url' ],
                ],
            ],
        ]);
    }

    public static function validate_url($value, $request, $param) {
        return (bool) filter_var($value, FILTER_VALIDATE_URL);
    }

    public static function get_headers(\WP_REST_Request $request) {
        $url = $request->get_param('url');
        $response = wp_remote_head($url);
        if (is_wp_error($response)) {
            return new \WP_Error('ae_seo_diag_http_error', $response->get_error_message(), [ 'status' => 500 ]);
        }
        $headers = wp_remote_retrieve_headers($response);
        if ($headers instanceof \Requests_Headers) {
            $headers = $headers->getAll();
        }
        if (!is_array($headers)) {
            $headers = [];
        }
        $headers = array_change_key_case($headers, CASE_LOWER);
        $wanted = [ 'content-encoding', 'cache-control', 'content-type', 'expires', 'last-modified' ];
        $out = [];
        foreach ($wanted as $key) {
            if (isset($headers[$key])) {
                $out[$key] = $headers[$key];
            }
        }
        return rest_ensure_response([ 'headers' => $out ]);
    }
}

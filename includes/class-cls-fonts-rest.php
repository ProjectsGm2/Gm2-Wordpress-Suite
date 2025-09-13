<?php
namespace Plugin\CLS;

if (!defined('ABSPATH')) {
    exit;
}

class Fonts_REST {
    public static function init(): void {
        add_action('rest_api_init', [ __CLASS__, 'register' ]);
    }

    public static function register(): void {
        register_rest_route('gm2/v1', '/above-fold-fonts', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'handle' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
            'args'                => [
                'fonts' => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'object' ],
                ],
            ],
        ]);
    }

    public static function check_permission(\WP_REST_Request $req): bool {
        $nonce = $req->get_header('X-WP-Nonce');
        return (bool) wp_verify_nonce($nonce, 'wp_rest');
    }

    public static function handle(\WP_REST_Request $req) {
        $fonts = $req->get_param('fonts');
        if (!is_array($fonts)) {
            return rest_ensure_response([ 'saved' => false ]);
        }
        $available = \Plugin\CLS\Fonts\get_discovered_fonts();
        $matched   = [];
        foreach ($fonts as $font) {
            $family = sanitize_text_field($font['family'] ?? '');
            $weight = sanitize_text_field($font['weight'] ?? '');
            $style  = sanitize_text_field($font['style'] ?? '');
            if ($family === '') {
                continue;
            }
            foreach ($available as $af) {
                $af_family = $af['family'] ?? '';
                if ($af_family !== '' && strcasecmp($af_family, $family) === 0 && strcasecmp((string) ($af['weight'] ?? ''), (string) $weight) === 0 && strcasecmp((string) ($af['style'] ?? ''), (string) $style) === 0) {
                    $matched[$af['url']] = $af;
                    break;
                }
            }
        }
        if (!$matched) {
            return rest_ensure_response([ 'saved' => false ]);
        }
        $stored = get_option('plugin_cls_critical_fonts', []);
        if (!is_array($stored)) {
            $stored = [];
        }
        $combined = $stored;
        foreach ($matched as $url => $font) {
            $exists = false;
            foreach ($combined as $existing) {
                if (is_array($existing) && ($existing['url'] ?? '') === $url) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $combined[] = $font;
            }
        }
        if (count($combined) > 3) {
            $combined = array_slice($combined, 0, 3);
        }
        update_option('plugin_cls_critical_fonts', $combined);
        return rest_ensure_response([ 'saved' => true ]);
    }
}

<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Search_Console {
    public static function init() {
        add_action('save_post_product', [__CLASS__, 'maybe_request_indexing'], 10, 1);
    }

    public static function maybe_request_indexing($post_id) {
        if (get_option('gm2_sc_auto', '0') !== '1') {
            return;
        }
        self::request_indexing($post_id);
    }

    public static function request_indexing($post_id) {
        if (wp_is_post_revision($post_id)) {
            return;
        }
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }
        $url = get_permalink($post);
        if (!$url) {
            return;
        }
        $token = self::get_access_token();
        if (!$token) {
            return;
        }
        $body = wp_json_encode([
            'url'  => $url,
            'type' => 'URL_UPDATED',
        ]);
        wp_remote_post('https://indexing.googleapis.com/v3/urlNotifications:publish', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body'    => $body,
            'timeout' => 20,
        ]);
    }

    private static function get_access_token() {
        $refresh = get_option('gm2_sc_refresh_token', '');
        $client  = get_option('gm2_sc_client_id', '');
        $secret  = get_option('gm2_sc_client_secret', '');
        if ($refresh && $client && $secret) {
            $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
                'body' => [
                    'client_id'     => $client,
                    'client_secret' => $secret,
                    'refresh_token' => $refresh,
                    'grant_type'    => 'refresh_token',
                ],
                'timeout' => 20,
            ]);
            if (!is_wp_error($resp)) {
                $data = json_decode(wp_remote_retrieve_body($resp), true);
                if (isset($data['access_token'])) {
                    return $data['access_token'];
                }
            }
        }
        $json = get_option('gm2_sc_service_account_json', '');
        if ($json && file_exists($json)) {
            $creds = json_decode(file_get_contents($json), true);
            if ($creds && !empty($creds['client_email']) && !empty($creds['private_key'])) {
                $now = time();
                $hdr = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
                $clm = rtrim(strtr(base64_encode(json_encode([
                    'iss'   => $creds['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/indexing',
                    'aud'   => 'https://oauth2.googleapis.com/token',
                    'exp'   => $now + 3600,
                    'iat'   => $now,
                ])), '+/', '-_'), '=');
                $sig_data = $hdr . '.' . $clm;
                if (!function_exists('openssl_sign')) {
                    return '';
                }
                openssl_sign($sig_data, $signature, $creds['private_key'], 'sha256');
                $sig = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
                $jwt = $sig_data . '.' . $sig;
                $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
                    'body' => [
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion'  => $jwt,
                    ],
                    'timeout' => 20,
                ]);
                if (!is_wp_error($resp)) {
                    $data = json_decode(wp_remote_retrieve_body($resp), true);
                    return $data['access_token'] ?? '';
                }
            }
        }
        return '';
    }
}

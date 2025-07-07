<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Keyword_Planner {
    private function get_credentials() {
        return [
            'developer_token' => trim(get_option('gm2_gads_developer_token', '')),
            'client_id'       => trim(get_option('gm2_gads_client_id', '')),
            'client_secret'   => trim(get_option('gm2_gads_client_secret', '')),
            'refresh_token'   => trim(get_option('gm2_gads_refresh_token', '')),
            'customer_id'     => trim(get_option('gm2_gads_customer_id', '')),
        ];
    }

    private function refresh_access_token($client_id, $client_secret, $refresh_token) {
        $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token',
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($resp)) {
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return $body['access_token'] ?? '';
    }

    public function generate_keyword_ideas($keyword) {
        $creds = $this->get_credentials();
        foreach ($creds as $v) {
            if ($v === '') {
                return new WP_Error('missing_creds', 'Keyword Planner credentials not set');
            }
        }

        $token = $this->refresh_access_token($creds['client_id'], $creds['client_secret'], $creds['refresh_token']);
        if (!$token) {
            return new WP_Error('no_token', 'Unable to obtain access token');
        }

        $url = sprintf('https://googleads.googleapis.com/v15/customers/%s:generateKeywordIdeas', $creds['customer_id']);

        $body = [
            'customerId' => $creds['customer_id'],
            'keywordSeed' => [
                'keywords' => [$keyword],
            ],
        ];

        $resp = wp_remote_post($url, [
            'headers' => [
                'Authorization'   => 'Bearer ' . $token,
                'developer-token' => $creds['developer_token'],
                'Content-Type'    => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 20,
        ]);

        if (is_wp_error($resp)) {
            return $resp;
        }

        $data = json_decode(wp_remote_retrieve_body($resp), true);
        $ideas = [];
        if (!empty($data['results'])) {
            foreach ($data['results'] as $res) {
                if (!empty($res['text'])) {
                    $ideas[] = $res['text'];
                }
            }
        }
        return $ideas;
    }
}

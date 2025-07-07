<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Keyword_Planner {
    private function get_credentials() {
        return [
            'developer_token' => trim(get_option('gm2_gads_developer_token', '')),
            'customer_id'     => trim(get_option('gm2_gads_customer_id', '')),
        ];
    }

    public function generate_keyword_ideas($keyword) {
        $creds = $this->get_credentials();
        foreach ($creds as $v) {
            if ($v === '') {
                return new WP_Error('missing_creds', 'Keyword Planner credentials not set');
            }
        }

        $oauth = new Gm2_Google_OAuth();
        if (!$oauth->is_connected()) {
            return new WP_Error('missing_creds', 'Google account not connected');
        }
        $token = $oauth->get_access_token();
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

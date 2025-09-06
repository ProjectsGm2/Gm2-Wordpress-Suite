<?php

namespace Gm2 {
    if (!defined('ABSPATH')) {
        exit;
    }

    class Gm2_Github_Client {
        private $token;

        public function __construct() {
            $this->token = (string) get_option('gm2_github_token', '');
        }

    private function get($url) {
        $args = [
            'timeout' => 20,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'Gm2-WordPress-Suite',
            ],
        ];
        if ($this->token !== '') {
            $args['headers']['Authorization'] = 'Bearer ' . $this->token;
        }
        $response = wp_safe_remote_get($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new \WP_Error('github_api_error', "HTTP $code response", [
                'code' => $code,
                'body' => wp_remote_retrieve_body($response),
            ]);
        }
        $body = wp_remote_retrieve_body($response);
        return $body === '' ? [] : json_decode($body, true);
    }

    public function get_comments($repo, $pr_number) {
        $url    = sprintf('https://api.github.com/repos/%s/pulls/%d/comments', $repo, $pr_number);
        $result = $this->get($url);
        if (is_wp_error($result)) {
            return $result;
        }
        if (!is_array($result)) {
            return new \WP_Error('github_invalid_response', __('Invalid response from GitHub', 'gm2-wordpress-suite'));
        }
        return $result;
    }
}
}

namespace {
    function gm2_get_github_comments($repo, $pr_number) {
        $client = new \Gm2\Gm2_Github_Client();
        return $client->get_comments($repo, $pr_number);
    }
}


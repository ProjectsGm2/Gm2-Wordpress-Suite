<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_PageSpeed {
    private $api_key;

    public function __construct($api_key = '') {
        $this->api_key = $api_key ?: get_option('gm2_pagespeed_api_key', '');
    }

    private function request($url, $strategy) {
        $api = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
        $query = http_build_query([
            'url' => $url,
            'strategy' => $strategy,
            'key' => $this->api_key,
        ]);
        $resp = wp_remote_get($api . '?' . $query, ['timeout' => 20]);
        if (is_wp_error($resp)) {
            return $resp;
        }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            return new \WP_Error('api_error', "HTTP $code response", [
                'code' => $code,
                'body' => wp_remote_retrieve_body($resp),
            ]);
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($body)) {
            return new \WP_Error('invalid_json', 'Invalid API response');
        }
        return $body;
    }

    public function get_scores($url) {
        $scores = [];
        foreach (['mobile', 'desktop'] as $strategy) {
            $resp = $this->request($url, $strategy);
            if (is_wp_error($resp)) {
                return $resp;
            }
            $val = $resp['lighthouseResult']['categories']['performance']['score'] ?? null;
            if ($val === null) {
                return new \WP_Error('missing_score', 'Score not found');
            }
            $scores[$strategy] = floatval($val) * 100;
        }
        return $scores;
    }
}

<?php

namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Keyword_Planner {
    /**
     * Stores the raw response body from the most recent API request.
     *
     * @var string
     */
    private $last_response_body = '';

    /**
     * Return the raw response body from the last request.
     *
     * @return string
     */
    public function get_last_response_body() {
        return $this->last_response_body;
    }

    private function get_credentials() {
        $id = preg_replace('/\D/', '', get_option('gm2_gads_customer_id', ''));
        return [
            'developer_token' => trim(get_option('gm2_gads_developer_token', '')),
            'customer_id'     => $id,
        ];
    }

    public function generate_keyword_ideas($keyword) {
        $creds = $this->get_credentials();
        foreach ($creds as $v) {
            if ($v === '') {
                return new \WP_Error('missing_creds', 'Keyword Planner credentials not set');
            }
        }

        $oauth = new Gm2_Google_OAuth();
        if (!$oauth->is_connected()) {
            return new \WP_Error('missing_creds', 'Google account not connected');
        }
        $token = $oauth->get_access_token();
        if (!$token) {
            return new \WP_Error('no_token', 'Unable to obtain access token');
        }

        $url = sprintf('https://googleads.googleapis.com/%s/customers/%s:generateKeywordIdeas', \Gm2\Gm2_Google_OAuth::GOOGLE_ADS_API_VERSION, $creds['customer_id']);

        $language    = get_option('gm2_gads_language', 'languageConstants/1000');
        $geo_targets = get_option('gm2_gads_geo_target', 'geoTargetConstants/2840');
        $network     = get_option('gm2_kwp_network', 'GOOGLE_SEARCH');

        if (!is_array($geo_targets)) {
            $geo_targets = array_filter(array_map('trim', explode(',', $geo_targets)));
        }

        $body = [
            'customerId'        => $creds['customer_id'],
            'language'          => $language,
            'geoTargetConstants'=> $geo_targets,
            'keywordPlanNetwork'=> $network,
            'keywordSeed'       => [
                'keywords' => [$keyword],
            ],
        ];

        $headers = [
            'Authorization'   => 'Bearer ' . $token,
            'developer-token' => $creds['developer_token'],
            'Content-Type'    => 'application/json',
        ];

        if ($login = preg_replace('/\D/', '', get_option('gm2_gads_login_customer_id'))) {
            $headers['login-customer-id'] = $login;
        }

        $resp = wp_remote_post($url, [
            'headers' => $headers,
            'body'    => wp_json_encode($body),
            'timeout' => 20,
        ]);

        if (is_wp_error($resp)) {
            return $resp;
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $this->last_response_body = $body;
        $data = $body !== '' ? json_decode($body, true) : null;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('KWP status: ' . $code);
            error_log('KWP body: ' . $body);
        }

        if ($code < 200 || $code >= 300 || (!empty($data['error']['message']))) {
            $msg = $data['error']['message'] ?? "HTTP $code response";
            return new \WP_Error('api_error', $msg);
        }

        $ideas = [];
        if (!empty($data['results'])) {
            foreach ($data['results'] as $res) {
                if (empty($res['text'])) {
                    continue;
                }

                $txt = $res['text'];
                if (is_array($txt) || is_object($txt)) {
                    $txt = $txt['value'] ?? wp_json_encode($txt);
                }
                $idea = ['text' => $txt];

                if (!empty($res['keyword_idea_metrics']) && is_array($res['keyword_idea_metrics'])) {
                    $metrics = [];
                    foreach ($res['keyword_idea_metrics'] as $m_key => $m_val) {
                        if ($m_val !== '' && $m_val !== null) {
                            $metrics[$m_key] = $m_val;
                        }
                    }

                    if (!empty($metrics['monthly_search_volumes']) && is_array($metrics['monthly_search_volumes'])) {
                        $vols = $metrics['monthly_search_volumes'];
                        usort($vols, function ($a, $b) {
                            $ta = ($a['year'] ?? 0) * 12 + ($a['month'] ?? 0);
                            $tb = ($b['year'] ?? 0) * 12 + ($b['month'] ?? 0);
                            return $ta <=> $tb;
                        });
                        $n = count($vols);
                        if ($n >= 3) {
                            $metrics['three_month_change'] = $vols[$n - 1]['monthly_searches'] - $vols[$n - 3]['monthly_searches'];
                        }
                        if ($n >= 13) {
                            $metrics['yoy_change'] = $vols[$n - 1]['monthly_searches'] - $vols[$n - 13]['monthly_searches'];
                        }
                    }

                    if ($metrics) {
                        foreach ($metrics as $m_key => $m_val) {
                            if (is_array($m_val) || is_object($m_val)) {
                                $m_val = $m_val['value'] ?? wp_json_encode($m_val);
                            }
                            $metrics[$m_key] = $m_val;
                        }
                        $idea['metrics'] = $metrics;
                    }
                }

                $ideas[] = $idea;
            }
        }

        if (empty($ideas)) {
            return new \WP_Error('no_results', 'No keyword ideas found.');
        }

        return $ideas;
    }
}

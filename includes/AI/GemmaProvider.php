<?php
namespace Gm2\AI;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class GemmaProvider implements ProviderInterface {
    private $api_key;
    private $model;
    private $temperature;
    private $max_tokens;
    private $endpoint;

    public function __construct() {
        $this->api_key   = get_option('gm2_gemma_api_key', '');
        $this->model     = get_option('gm2_gemma_model', 'gemma-7b-it');
        $temp            = get_option('gm2_gemma_temperature', '');
        $this->temperature = $temp === '' ? 1.0 : floatval($temp);
        $this->max_tokens = intval(get_option('gm2_gemma_max_tokens', ''));
        $default_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent';
        $this->endpoint  = get_option('gm2_gemma_endpoint', $default_endpoint);
    }

    public function query(string $prompt, array $args = []): string|WP_Error {
        if (get_option('gm2_enable_gemma', '1') !== '1') {
            return new WP_Error('gemma_disabled', 'Gemma feature disabled');
        }
        if ($this->api_key === '') {
            return new WP_Error('no_api_key', 'Gemma API key not set');
        }

        $temperature = isset($args['temperature']) ? floatval($args['temperature']) : $this->temperature;
        $max_tokens  = isset($args['number-of-words']) ? intval($args['number-of-words']) : $this->max_tokens;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $temperature,
            ],
        ];
        if ($max_tokens > 0) {
            $payload['generationConfig']['maxOutputTokens'] = $max_tokens;
        }

        $args = [
            'headers' => [
                'Content-Type'   => 'application/json',
                'X-Goog-Api-Key' => $this->api_key,
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 20,
        ];

        $response = wp_remote_post($this->endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);

        if ($status !== 200) {
            $data    = json_decode($body, true);
            $message = $data['error']['message'] ?? 'Non-200 response';
            return new WP_Error('api_error', $message);
        }

        if ($body === '') {
            return '';
        }

        $data = json_decode($body, true);
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }
}

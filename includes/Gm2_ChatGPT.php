<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_ChatGPT {
    private $api_key;
    private $model;
    private $temperature;
    private $max_tokens;
    private $endpoint;

    public function __construct() {
        $this->api_key   = get_option('gm2_chatgpt_api_key', '');
        $this->model     = get_option('gm2_chatgpt_model', 'gpt-3.5-turbo');
        $temp            = get_option('gm2_chatgpt_temperature', '');
        $this->temperature = $temp === '' ? 1.0 : floatval($temp);
        $this->max_tokens = intval(get_option('gm2_chatgpt_max_tokens', ''));
        $this->endpoint  = get_option('gm2_chatgpt_endpoint', 'https://api.openai.com/v1/chat/completions');
    }

    public function query($prompt) {
        if ($this->api_key === '') {
            return new \WP_Error('no_api_key', 'ChatGPT API key not set');
        }
        $payload = [
            'model'     => $this->model,
            'messages'  => [ [ 'role' => 'user', 'content' => $prompt ] ],
            'temperature' => $this->temperature,
        ];
        if ($this->max_tokens > 0) {
            $payload['max_tokens'] = $this->max_tokens;
        }
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
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
            return new \WP_Error('api_error', $message);
        }

        if ($body === '') {
            return '';
        }

        $data = json_decode($body, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }
}

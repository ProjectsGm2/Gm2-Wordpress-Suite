<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_ChatGPT {
    private $api_key;
    private $model;

    public function __construct() {
        $this->api_key = get_option('gm2_chatgpt_api_key', '');
        $this->model   = get_option('gm2_chatgpt_model', 'gpt-3.5-turbo');
    }

    public function query($prompt) {
        if ($this->api_key === '') {
            return new \WP_Error('no_api_key', 'ChatGPT API key not set');
        }
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode([
                'model' => $this->model,
                'messages' => [ [ 'role' => 'user', 'content' => $prompt ] ],
            ]),
            'timeout' => 20,
        ];
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
        if (is_wp_error($response)) {
            return $response;
        }
        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new \WP_Error('api_error', 'Non-200 response');
        }
        $body = wp_remote_retrieve_body($response);
        if ($body === '') {
            return '';
        }
        $data = json_decode($body, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }

    public function get_models() {
        if ($this->api_key === '') {
            return [];
        }
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'timeout' => 20,
        ];
        $resp = wp_remote_get('https://api.openai.com/v1/models', $args);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            return [];
        }
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        $models = [];
        if (!empty($data['data'])) {
            foreach ($data['data'] as $m) {
                if (!empty($m['id'])) {
                    $models[] = $m['id'];
                }
            }
        }
        sort($models);
        return $models;
    }
}

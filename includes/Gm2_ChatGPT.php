<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_ChatGPT {
    private $api_key;

    public function __construct() {
        $this->api_key = get_option('gm2_chatgpt_api_key', '');
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
                'model' => 'gpt-3.5-turbo',
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
}

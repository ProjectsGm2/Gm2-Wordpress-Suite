<?php
namespace Gm2\AI;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class LlamaProvider implements ProviderInterface {
    private $api_key;
    private $model;
    private $temperature;
    private $max_tokens;
    private $endpoint;

    public function __construct() {
        $this->api_key   = get_option('gm2_llama_api_key', '');
        $this->model     = get_option('gm2_llama_model', 'llama2');
        $temp            = get_option('gm2_llama_temperature', '');
        $this->temperature = $temp === '' ? 1.0 : floatval($temp);
        $this->max_tokens = intval(get_option('gm2_llama_max_tokens', ''));
        $this->endpoint  = get_option('gm2_llama_endpoint', 'https://api.llama.com/v1/chat/completions');
    }

    public function query(string $prompt, array $args = []): string|WP_Error {
        if (get_option('gm2_enable_llama', '1') !== '1') {
            return new WP_Error('llama_disabled', 'Llama feature disabled');
        }
        if ($this->api_key === '') {
            return new WP_Error('no_api_key', 'Llama API key not set');
        }
        $model       = $args['language-model'] ?? $this->model;
        $temperature = isset($args['temperature']) ? floatval($args['temperature']) : $this->temperature;
        $max_tokens  = isset($args['number-of-words']) ? intval($args['number-of-words']) : $this->max_tokens;

        $payload = [
            'model'       => $model,
            'messages'    => [ [ 'role' => 'user', 'content' => $prompt ] ],
            'temperature' => $temperature,
        ];
        if ($max_tokens > 0) {
            $payload['max_tokens'] = $max_tokens;
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
            return new WP_Error('api_error', $message);
        }

        if ($body === '') {
            return '';
        }

        $data = json_decode($body, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }
}

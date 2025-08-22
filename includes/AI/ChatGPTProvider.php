<?php
namespace Gm2\AI;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class ChatGPTProvider implements ProviderInterface {
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

    public function query(string $prompt): string|WP_Error {
        if (get_option('gm2_enable_chatgpt', '1') !== '1') {
            return new WP_Error('chatgpt_disabled', 'ChatGPT feature disabled');
        }
        if ($this->api_key === '') {
            return new WP_Error('no_api_key', 'ChatGPT API key not set');
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Gm2_ChatGPT request: ' . wp_json_encode($payload));
        }
        $response = wp_remote_post($this->endpoint, $args);

        $result = null;
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Gm2_ChatGPT error: ' . $response->get_error_message());
            }
            $result = $response;
        } else {
            $status = wp_remote_retrieve_response_code($response);
            $body   = wp_remote_retrieve_body($response);

            if ($status !== 200) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $snippet = gm2_substr($body, 0, 200);
                    error_log(sprintf('Gm2_ChatGPT HTTP %s: %s', $status, $snippet));
                }
                $data    = json_decode($body, true);
                $message = $data['error']['message'] ?? 'Non-200 response';
                $result  = new WP_Error('api_error', $message);
            } else {
                if ($body === '') {
                    $result = '';
                } else {
                    $data = json_decode($body, true);
                    $result = $data['choices'][0]['message']['content'] ?? '';
                }
            }
        }

        if (get_option('gm2_enable_chatgpt_logging', '0') === '1' && defined('GM2_CHATGPT_LOG_FILE')) {
            $log_resp = is_wp_error($result) ? $result->get_error_message() : $result;
            $entry = wp_json_encode([
                'prompt'   => $prompt,
                'response' => $log_resp,
            ]);
            error_log($entry . PHP_EOL, 3, GM2_CHATGPT_LOG_FILE);
        }

        return $result;
    }

    public static function get_available_models() {
        $key = get_option('gm2_chatgpt_api_key', '');
        $defaults = [ 'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo' ];

        if ($key === '') {
            return $defaults;
        }

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
            ],
            'timeout' => 20,
        ];

        $response = wp_safe_remote_get('https://api.openai.com/v1/models', $args);

        if (is_wp_error($response)) {
            return $defaults;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200 || $body === '') {
            return $defaults;
        }

        $data = json_decode($body, true);
        if (!isset($data['data']) || !is_array($data['data'])) {
            return $defaults;
        }

        $models = [];
        foreach ($data['data'] as $model) {
            if (isset($model['id'])) {
                $models[] = $model['id'];
            }
        }

        return $models ?: $defaults;
    }
}

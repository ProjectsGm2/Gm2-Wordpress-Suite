<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gm2_ChatGPT {
    public static function send_prompt($prompt) {
        $api_key = get_option('gm2_chatgpt_api_key');
        if (empty($api_key)) {
            return 'API key not set.';
        }

        $body = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode($body),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }

        return 'Unexpected response from API.';
    }
}
?>


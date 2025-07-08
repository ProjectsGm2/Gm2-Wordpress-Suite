<?php
use Gm2\Gm2_ChatGPT;
use Gm2\Gm2_Admin;

class ChatGPTTest extends WP_UnitTestCase {
    public function test_query_returns_response() {
        update_option('gm2_chatgpt_api_key', 'key');
        $filter = function($pre, $args, $url) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [ ['message' => ['content' => 'hi']] ]
                    ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);
        $chat = new Gm2_ChatGPT();
        $res = $chat->query('hello');
        remove_filter('pre_http_request', $filter, 10);
        $this->assertSame('hi', $res);
    }

    public function test_chatgpt_page_contains_field() {
        $admin = new Gm2_Admin();
        ob_start();
        $admin->display_chatgpt_page();
        $out = ob_get_clean();
        $this->assertStringContainsString('gm2_chatgpt_api_key', $out);
        $this->assertStringContainsString('gm2_chatgpt_model', $out);
    }

    public function test_get_models_returns_list() {
        update_option('gm2_chatgpt_api_key', 'key');
        $filter = function($pre, $args, $url) {
            if ($url === 'https://api.openai.com/v1/models') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['data' => [ ['id' => 'a'], ['id' => 'b'] ]])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);
        $chat = new Gm2_ChatGPT();
        $models = $chat->get_models();
        remove_filter('pre_http_request', $filter, 10);
        $this->assertSame(['a', 'b'], $models);
    }
}

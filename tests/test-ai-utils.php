<?php
use function Gm2\gm2_ai_send_prompt;

class AiUtilsTest extends WP_UnitTestCase {
    public function test_gpt4_turbo_model_selected() {
        update_option('gm2_chatgpt_api_key', 'key');
        $captured = null;
        $filter = function($pre, $args, $url) use (&$captured) {
            $captured = json_decode($args['body'], true)['model'];
            return [
                'response' => ['code' => 200],
                'body'     => json_encode(['choices' => [ ['message' => ['content' => 'ok']] ] ])
            ];
        };
        add_filter('pre_http_request', $filter, 10, 3);
        $result = gm2_ai_send_prompt('hi', ['language-model' => 'gpt-4-turbo']);
        remove_filter('pre_http_request', $filter, 10);
        $this->assertSame('ok', $result);
        $this->assertSame('gpt-4-turbo', $captured);
    }
}

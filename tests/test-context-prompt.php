<?php
class ContextPromptAjaxTest extends WP_Ajax_UnitTestCase {
    public function test_context_prompt_returns_summary() {
        update_option('gm2_chatgpt_api_key', 'key');
        $captured = null;
        $filter = function($pre, $args, $url) use (&$captured) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                $body = json_decode($args['body'], true);
                $captured = $body['messages'][0]['content'];
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [ ['message' => ['content' => 'context summary']] ]
                    ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $this->_setRole('administrator');
        $_POST['prompt'] = "info line 1\ninfo line 2";
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_chatgpt_nonce');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try {
            $this->_handleAjax('gm2_chatgpt_prompt');
        } catch (WPAjaxDieContinueException $e) {
        }
        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('context summary', $resp['data']);
        $this->assertSame("info line 1\ninfo line 2", $captured);
    }
}

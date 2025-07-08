<?php
class ChatGPTTest extends WP_UnitTestCase {
    public function test_send_prompt_without_key() {
        delete_option('gm2_chatgpt_api_key');
        $result = Gm2_ChatGPT::send_prompt('Hello');
        $this->assertEquals('API key not set.', $result);
    }

    public function test_send_prompt_with_mock_response() {
        update_option('gm2_chatgpt_api_key', 'dummy');
        add_filter('pre_http_request', function($preempt, $r, $url) {
            if (false !== strpos($url, 'api.openai.com')) {
                return array(
                    'headers'  => array(),
                    'body'     => json_encode(array(
                        'choices' => array(array('message' => array('content' => 'Hi there')))
                    )),
                    'response' => array('code' => 200),
                );
            }
            return false;
        }, 10, 3);
        $result = Gm2_ChatGPT::send_prompt('Hello');
        $this->assertEquals('Hi there', $result);
    }
}


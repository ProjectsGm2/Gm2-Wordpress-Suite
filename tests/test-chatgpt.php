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

    public function test_query_uses_custom_options() {
        update_option('gm2_chatgpt_api_key', 'key');
        update_option('gm2_chatgpt_model', 'test-model');
        update_option('gm2_chatgpt_temperature', '0.5');
        update_option('gm2_chatgpt_max_tokens', '50');
        update_option('gm2_chatgpt_endpoint', 'https://example.com/api');
        $captured = null;
        $filter = function($pre, $args, $url) use (&$captured) {
            $captured = [ $args, $url ];
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'choices' => [ ['message' => ['content' => 'hi']] ]
                ])
            ];
        };
        add_filter('pre_http_request', $filter, 10, 3);
        $chat = new Gm2_ChatGPT();
        $chat->query('hello');
        remove_filter('pre_http_request', $filter, 10);
        list($args, $url) = $captured;
        $body = json_decode($args['body'], true);
        $this->assertSame('test-model', $body['model']);
        $this->assertSame(0.5, $body['temperature']);
        $this->assertSame(50, $body['max_tokens']);
        $this->assertSame('https://example.com/api', $url);
    }

    public function test_chatgpt_page_contains_field() {
        $admin = new Gm2_Admin();
        ob_start();
        $admin->display_chatgpt_page();
        $out = ob_get_clean();
        $this->assertStringContainsString('gm2_chatgpt_api_key', $out);
        $this->assertStringContainsString('gm2_chatgpt_model', $out);
        $this->assertStringContainsString('gm2_chatgpt_temperature', $out);
        $this->assertStringContainsString('gm2_chatgpt_max_tokens', $out);
        $this->assertStringContainsString('gm2_chatgpt_endpoint', $out);
    }

    public function test_chatgpt_page_model_dropdown() {
        $admin = new Gm2_Admin();
        ob_start();
        $admin->display_chatgpt_page();
        $out = ob_get_clean();
        $this->assertStringContainsString('<select id="gm2_chatgpt_model"', $out);
        $this->assertMatchesRegularExpression('/<select[^>]*id="gm2_chatgpt_model"[^>]*>.*<option/i', $out);
    }

    public function test_chatgpt_page_logging_checkbox() {
        $admin = new Gm2_Admin();
        ob_start();
        $admin->display_chatgpt_page();
        $out = ob_get_clean();
        $this->assertStringContainsString('gm2_enable_chatgpt_logging', $out);
        $this->assertStringContainsString('Enable Logging', $out);
    }

    public function test_logging_option_saved_by_form() {
        $admin = new Gm2_Admin();
        $user = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);

        $_POST['_wpnonce'] = wp_create_nonce('gm2_chatgpt_settings');
        $_POST['gm2_enable_chatgpt_logging'] = '1';
        $_POST['gm2_chatgpt_api_key'] = 'key';

        $admin->handle_chatgpt_form();

        $this->assertSame('1', get_option('gm2_enable_chatgpt_logging'));
    }

    public function test_chatgpt_page_shows_logs_when_file_exists() {
        $admin = new Gm2_Admin();
        update_option('gm2_enable_chatgpt_logging', '1');
        file_put_contents(GM2_CHATGPT_LOG_FILE, 'log entry');
        ob_start();
        $admin->display_chatgpt_page();
        $out = ob_get_clean();
        @unlink(GM2_CHATGPT_LOG_FILE);
        update_option('gm2_enable_chatgpt_logging', '0');
        $this->assertStringContainsString('ChatGPT Logs', $out);
        $this->assertStringContainsString('<textarea', $out);
        $this->assertStringContainsString('log entry', $out);
    }
}

class ChatGPTAjaxTest extends WP_Ajax_UnitTestCase {
    public function test_multiline_prompt_preserved() {
        update_option('gm2_chatgpt_api_key', 'key');
        $captured = null;
        $filter = function($pre, $args, $url) use (&$captured) {
            $body = json_decode($args['body'], true);
            $captured = $body['messages'][0]['content'];
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'choices' => [ ['message' => ['content' => 'hi']] ]
                ])
            ];
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $this->_setRole('administrator');
        $_POST['prompt'] = "line1\nline2";
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_chatgpt_nonce');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];

        try {
            $this->_handleAjax('gm2_chatgpt_prompt');
        } catch (WPAjaxDieContinueException $e) {
            // Expected due to wp_die in wp_send_json_* functions
        }

        remove_filter('pre_http_request', $filter, 10);
        $this->assertSame("line1\nline2", $captured);
    }

    public function test_error_when_chatgpt_disabled() {
        update_option('gm2_enable_chatgpt', '0');

        $this->_setRole('administrator');
        $_POST['prompt'] = 'hi';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_chatgpt_nonce');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];

        try {
            $this->_handleAjax('gm2_chatgpt_prompt');
        } catch (WPAjaxDieContinueException $e) {
        }

        $resp = json_decode($this->_last_response, true);
        $this->assertFalse($resp['success']);
        $this->assertSame('ChatGPT is disabled', $resp['data']);

        update_option('gm2_enable_chatgpt', '1');
    }

    public function test_error_when_api_key_missing() {
        update_option('gm2_chatgpt_api_key', '');

        $this->_setRole('administrator');
        $_POST['prompt'] = 'hi';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_chatgpt_nonce');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];

        try {
            $this->_handleAjax('gm2_chatgpt_prompt');
        } catch (WPAjaxDieContinueException $e) {
        }

        $resp = json_decode($this->_last_response, true);
        $this->assertFalse($resp['success']);
        $this->assertSame('ChatGPT API key not set', $resp['data']);
    }
}

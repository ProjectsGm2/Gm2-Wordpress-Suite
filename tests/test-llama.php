<?php
use Gm2\AI\LlamaProvider as Gm2_Llama;
use Gm2\Gm2_Admin;

class LlamaTest extends WP_UnitTestCase {
    public function test_query_returns_response() {
        update_option('gm2_llama_api_key', 'key');
        $endpoint = 'https://api.llama.com/v1/chat/completions';
        $filter = function($pre, $args, $url) use ($endpoint) {
            if ($url === $endpoint) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [ [ 'message' => [ 'content' => 'hi' ] ] ]
                    ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);
        $llama = new Gm2_Llama();
        $res = $llama->query('hello');
        remove_filter('pre_http_request', $filter, 10);
        $this->assertSame('hi', $res);
    }

    public function test_query_uses_custom_options() {
        update_option('gm2_llama_api_key', 'key');
        update_option('gm2_llama_model', 'test-model');
        update_option('gm2_llama_temperature', '0.3');
        update_option('gm2_llama_max_tokens', '25');
        update_option('gm2_llama_endpoint', 'https://example.com/llama');
        $captured = null;
        $filter = function($pre, $args, $url) use (&$captured) {
            $captured = [$args, $url];
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'choices' => [ [ 'message' => [ 'content' => 'hi' ] ] ]
                ])
            ];
        };
        add_filter('pre_http_request', $filter, 10, 3);
        $llama = new Gm2_Llama();
        $llama->query('hello');
        remove_filter('pre_http_request', $filter, 10);
        list($args, $url) = $captured;
        $body = json_decode($args['body'], true);
        $this->assertSame('https://example.com/llama', $url);
        $this->assertSame('test-model', $body['model']);
        $this->assertSame(0.3, $body['temperature']);
        $this->assertSame(25, $body['max_tokens']);
    }

    public function test_llama_page_contains_field() {
        $admin = new Gm2_Admin();
        ob_start();
        $admin->display_ai_settings_page();
        $out = ob_get_clean();
        $this->assertStringContainsString('gm2_llama_api_key', $out);
        $this->assertStringContainsString('gm2_llama_endpoint', $out);
        $this->assertStringContainsString('gm2_llama_model_path', $out);
        $this->assertStringContainsString('gm2_llama_binary_path', $out);
    }

    public function test_form_saves_llama_fields() {
        $admin = new Gm2_Admin();
        $user = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        $_POST['_wpnonce'] = wp_create_nonce('gm2_ai_settings');
        $_POST['gm2_ai_provider'] = 'llama';
        $_POST['gm2_llama_api_key'] = 'abc';
        $_POST['gm2_llama_endpoint'] = 'https://api.example.com';
        $_POST['gm2_llama_model_path'] = '/path/to/model';
        $_POST['gm2_llama_binary_path'] = '/path/to/bin';
        $admin->handle_ai_settings_form();
        $this->assertSame('abc', get_option('gm2_llama_api_key'));
        $this->assertSame('https://api.example.com', get_option('gm2_llama_endpoint'));
        $this->assertSame('/path/to/model', get_option('gm2_llama_model_path'));
        $this->assertSame('/path/to/bin', get_option('gm2_llama_binary_path'));
    }

    public function test_form_allows_local_paths_without_api_key() {
        $admin = new Gm2_Admin();
        $user = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        $_POST['_wpnonce'] = wp_create_nonce('gm2_ai_settings');
        $_POST['gm2_ai_provider'] = 'llama';
        $_POST['gm2_llama_api_key'] = '';
        $_POST['gm2_llama_endpoint'] = '';
        $_POST['gm2_llama_model_path'] = '/model';
        $_POST['gm2_llama_binary_path'] = '/binary';
        $admin->handle_ai_settings_form();
        $this->assertSame('/model', get_option('gm2_llama_model_path'));
        $this->assertSame('/binary', get_option('gm2_llama_binary_path'));
        $this->assertSame('', get_option('gm2_llama_api_key'));
    }

    public function test_error_when_llama_disabled() {
        update_option('gm2_llama_api_key', 'key');
        update_option('gm2_enable_llama', '0');
        $llama = new Gm2_Llama();
        $res = $llama->query('hi');
        $this->assertInstanceOf('WP_Error', $res);
        $this->assertSame('Llama feature disabled', $res->get_error_message());
        update_option('gm2_enable_llama', '1');
    }

    public function test_error_when_api_key_missing() {
        update_option('gm2_llama_api_key', '');
        $llama = new Gm2_Llama();
        $res = $llama->query('hi');
        $this->assertInstanceOf('WP_Error', $res);
        $this->assertSame('Llama API key not set', $res->get_error_message());
    }
}


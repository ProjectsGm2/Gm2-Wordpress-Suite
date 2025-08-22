<?php
use Gm2\AI\GemmaProvider as Gm2_Gemma;
use Gm2\Gm2_Admin;

class GemmaTest extends WP_UnitTestCase {
    public function test_query_returns_response() {
        update_option('gm2_gemma_api_key', 'key');
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemma-7b-it:generateContent';
        $filter = function($pre, $args, $url) use ($endpoint) {
            if ($url === $endpoint) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'candidates' => [ [ 'content' => [ 'parts' => [ [ 'text' => 'hi' ] ] ] ] ]
                    ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);
        $gemma = new Gm2_Gemma();
        $res = $gemma->query('hello');
        remove_filter('pre_http_request', $filter, 10);
        $this->assertSame('hi', $res);
    }

    public function test_query_uses_custom_options() {
        update_option('gm2_gemma_api_key', 'key');
        update_option('gm2_gemma_temperature', '0.7');
        update_option('gm2_gemma_max_tokens', '40');
        update_option('gm2_gemma_endpoint', 'https://example.com/gemma');
        $captured = null;
        $filter = function($pre, $args, $url) use (&$captured) {
            $captured = [$args, $url];
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'candidates' => [ [ 'content' => [ 'parts' => [ [ 'text' => 'hi' ] ] ] ] ]
                ])
            ];
        };
        add_filter('pre_http_request', $filter, 10, 3);
        $gemma = new Gm2_Gemma();
        $gemma->query('hello');
        remove_filter('pre_http_request', $filter, 10);
        list($args, $url) = $captured;
        $body = json_decode($args['body'], true);
        $this->assertSame('https://example.com/gemma', $url);
        $this->assertSame(0.7, $body['generationConfig']['temperature']);
        $this->assertSame(40, $body['generationConfig']['maxOutputTokens']);
    }

    public function test_gemma_page_contains_field() {
        $admin = new Gm2_Admin();
        ob_start();
        $admin->display_ai_settings_page();
        $out = ob_get_clean();
        $this->assertStringContainsString('gm2_gemma_api_key', $out);
        $this->assertStringContainsString('gm2_gemma_endpoint', $out);
    }

    public function test_form_saves_gemma_fields() {
        $admin = new Gm2_Admin();
        $user = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        $_POST['_wpnonce'] = wp_create_nonce('gm2_ai_settings');
        $_POST['gm2_ai_provider'] = 'gemma';
        $_POST['gm2_gemma_api_key'] = 'abc';
        $_POST['gm2_gemma_endpoint'] = 'https://api.example.com';
        $admin->handle_ai_settings_form();
        $this->assertSame('abc', get_option('gm2_gemma_api_key'));
        $this->assertSame('https://api.example.com', get_option('gm2_gemma_endpoint'));
    }

    public function test_error_when_gemma_disabled() {
        update_option('gm2_gemma_api_key', 'key');
        update_option('gm2_enable_gemma', '0');
        $gemma = new Gm2_Gemma();
        $res = $gemma->query('hi');
        $this->assertInstanceOf('WP_Error', $res);
        $this->assertSame('Gemma feature disabled', $res->get_error_message());
        update_option('gm2_enable_gemma', '1');
    }

    public function test_error_when_api_key_missing() {
        update_option('gm2_gemma_api_key', '');
        $gemma = new Gm2_Gemma();
        $res = $gemma->query('hi');
        $this->assertInstanceOf('WP_Error', $res);
        $this->assertSame('Gemma API key not set', $res->get_error_message());
    }
}


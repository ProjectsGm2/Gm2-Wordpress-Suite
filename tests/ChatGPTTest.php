<?php

use PHPUnit\Framework\TestCase;

if (!class_exists('WP_UnitTestCase')) {
    class WP_UnitTestCase extends TestCase {}
}

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$GLOBALS['gm2_options'] = [];
$GLOBALS['gm2_filters'] = [];

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        $GLOBALS['gm2_options'][$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        unset($GLOBALS['gm2_options'][$option]);
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return array_key_exists($option, $GLOBALS['gm2_options']) ? $GLOBALS['gm2_options'][$option] : $default;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
        if (!isset($GLOBALS['gm2_filters'][$tag][$priority])) {
            $GLOBALS['gm2_filters'][$tag][$priority] = [];
        }
        $GLOBALS['gm2_filters'][$tag][$priority][] = [$callback, $accepted_args];
        return true;
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter($tag, $callback, $priority = 10) {
        if (empty($GLOBALS['gm2_filters'][$tag][$priority])) {
            return false;
        }
        foreach ($GLOBALS['gm2_filters'][$tag][$priority] as $index => $stored) {
            if ($stored[0] === $callback) {
                unset($GLOBALS['gm2_filters'][$tag][$priority][$index]);
            }
        }
        if (empty($GLOBALS['gm2_filters'][$tag][$priority])) {
            unset($GLOBALS['gm2_filters'][$tag][$priority]);
        }
        if (empty($GLOBALS['gm2_filters'][$tag])) {
            unset($GLOBALS['gm2_filters'][$tag]);
        }
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        if (empty($GLOBALS['gm2_filters'][$tag])) {
            return $value;
        }
        ksort($GLOBALS['gm2_filters'][$tag]);
        foreach ($GLOBALS['gm2_filters'][$tag] as $priority => $callbacks) {
            foreach ($callbacks as $stored) {
                [$callback, $accepted_args] = $stored;
                $parameters = array_merge([$value], array_slice($args, 0, max($accepted_args - 1, 0)));
                $value = $callback(...$parameters);
            }
        }
        return $value;
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        $preempt = apply_filters('pre_http_request', false, $args, $url);
        if (false !== $preempt) {
            return $preempt;
        }

        return [
            'headers'  => [],
            'body'     => json_encode(['choices' => []]),
            'response' => ['code' => 200],
        ];
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return isset($response['body']) ? $response['body'] : '';
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return trim((string) $value);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) {
        $value = str_replace("\r", '', (string) $value);

        return trim($value);
    }
}

require_once __DIR__ . '/../includes/class-gm2-chatgpt.php';
require_once __DIR__ . '/../admin/class-gm2-admin.php';

class ChatGPTTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['gm2_options'] = [];
        $GLOBALS['gm2_filters'] = [];
    }

    public function test_send_prompt_without_key() {
        delete_option('gm2_chatgpt_api_key');
        $result = Gm2_ChatGPT::send_prompt('Hello');
        $this->assertEquals('API key not set.', $result);
    }

    public function test_send_prompt_with_mock_response() {
        update_option('gm2_chatgpt_api_key', 'dummy');
        $callback = function($preempt, $r, $url) {
            if (false !== strpos($url, 'api.openai.com')) {
                return [
                    'headers'  => [],
                    'body'     => json_encode([
                        'choices' => [
                            ['message' => ['content' => 'Hi there']],
                        ],
                    ]),
                    'response' => ['code' => 200],
                ];
            }

            return $preempt;
        };
        add_filter('pre_http_request', $callback, 10, 3);
        $result = Gm2_ChatGPT::send_prompt('Hello');
        remove_filter('pre_http_request', $callback, 10);
        $this->assertEquals('Hi there', $result);
    }

    public function test_sanitize_prompt_preserves_newlines() {
        $raw_prompt = "Line one\nLine two";
        $this->assertSame($raw_prompt, Gm2_Admin::sanitize_prompt($raw_prompt));
    }
}

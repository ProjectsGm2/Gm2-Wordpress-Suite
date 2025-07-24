<?php
class ContentRulesAjaxTest extends WP_Ajax_UnitTestCase {
    public function test_check_rules_pass_with_valid_data() {
        // Create a post with enough content
        self::factory()->post->create([
            'post_title'   => 'Sample Post',
            'post_content' => str_repeat('word ', 300),
        ]);
        $this->_setRole('administrator');
        $_POST['title'] = str_repeat('T', 35);
        $_POST['description'] = str_repeat('D', 80);
        $_POST['focus'] = 'keyword';
        $_POST['content'] = '<img src="img.jpg" alt="keyword" /> ' . str_repeat('word ', 300);
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_check_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try {
            $this->_handleAjax('gm2_check_rules');
        } catch (WPAjaxDieContinueException $e) {
            // Expected due to wp_die in wp_send_json_success
        }
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        foreach ($resp['data'] as $value) {
            $this->assertTrue($value);
        }
    }

    public function test_check_rules_fails_with_missing_focus_and_short_content() {
        self::factory()->post->create([
            'post_title'   => 'Short Content Post',
            'post_content' => str_repeat('word ', 50),
        ]);
        $this->_setRole('administrator');
        $_POST['title'] = str_repeat('T', 35);
        $_POST['description'] = str_repeat('D', 80);
        $_POST['focus'] = '';
        $_POST['content'] = str_repeat('word ', 50);
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_check_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try {
            $this->_handleAjax('gm2_check_rules');
        } catch (WPAjaxDieContinueException $e) {
        }
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertTrue($resp['data']['title-length-between-30-and-60-characters']);
        $this->assertTrue($resp['data']['description-length-between-50-and-160-characters']);
        $this->assertFalse($resp['data']['at-least-one-focus-keyword']);
        $this->assertFalse($resp['data']['content-has-at-least-300-words']);
    }

    public function test_duplicate_titles_and_descriptions_fail() {
        $existing = self::factory()->post->create([
            'post_title'   => 'Dup',
            'post_content' => 'Content',
        ]);
        update_post_meta($existing, '_gm2_title', 'Dup Title');
        update_post_meta($existing, '_gm2_description', 'Dup Description');

        $this->_setRole('administrator');
        $_POST['title'] = 'Dup Title';
        $_POST['description'] = 'Dup Description';
        $_POST['focus'] = 'keyword';
        $_POST['content'] = str_repeat('word ', 300);
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_check_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try {
            $this->_handleAjax('gm2_check_rules');
        } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertFalse($resp['data']['seo-title-is-unique']);
        $this->assertFalse($resp['data']['meta-description-is-unique']);
    }

    public function test_duplicate_focus_keywords_fail() {
        $existing = self::factory()->post->create([
            'post_title'   => 'Focus Post',
            'post_content' => 'Content',
        ]);
        update_post_meta($existing, '_gm2_focus_keywords', 'Alpha');

        $this->_setRole('administrator');
        $_POST['title'] = str_repeat('T', 35);
        $_POST['description'] = str_repeat('D', 80);
        $_POST['focus'] = 'Alpha';
        $_POST['content'] = str_repeat('word ', 300);
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_check_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try {
            $this->_handleAjax('gm2_check_rules');
        } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertFalse($resp['data']['focus-keyword-is-unique']);
    }

    public function test_dashboard_handles_legacy_rule_array() {
        $_GET['tab'] = 'rules';
        $legacy = [
            'post_post' => [
                'general' => [
                    'rule-one' => 'Old rule one',
                    'rule-two' => 'Old rule two',
                ],
            ],
        ];
        update_option('gm2_content_rules', $legacy);

        $admin = new \Gm2\Gm2_SEO_Admin();
        ob_start();
        $admin->display_dashboard();
        $out = ob_get_clean();

        $this->assertStringContainsString('Content Rules', $out);
    }
}

class ContentRulesFormTest extends WP_UnitTestCase {
    public function test_form_flattens_arrays() {
        $admin = new \Gm2\Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);

        $_POST['gm2_content_rules_nonce'] = wp_create_nonce('gm2_content_rules_save');
        $_POST['gm2_content_rules'] = [
            'post_post' => [
                'content' => ['First', 'Second']
            ]
        ];

        $admin->handle_content_rules_form();

        $rules = get_option('gm2_content_rules');
        $this->assertIsString($rules['post_post']['content']);
        $this->assertSame("First\nSecond", $rules['post_post']['content']);
    }

    public function test_form_flattens_nested_arrays() {
        $admin = new \Gm2\Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);

        $_POST['gm2_content_rules_nonce'] = wp_create_nonce('gm2_content_rules_save');
        $_POST['gm2_content_rules'] = [
            'post_post' => [
                'canonical_url' => [[ 'Rule A' ], ['Rule B']]
            ]
        ];

        $admin->handle_content_rules_form();

        $rules = get_option('gm2_content_rules');
        $this->assertSame("Rule A\nRule B", $rules['post_post']['canonical_url']);
    }

    public function test_form_flattens_scalar_value() {
        $admin = new \Gm2\Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);

        $_POST['gm2_content_rules_nonce'] = wp_create_nonce('gm2_content_rules_save');
        $_POST['gm2_content_rules'] = [
            'post_post' => 'One\nTwo'
        ];

        $admin->handle_content_rules_form();

        $rules = get_option('gm2_content_rules');
        $this->assertSame('One\nTwo', $rules['post_post']['general']);
    }
}

class ContentRulesNormalizationTest extends WP_Ajax_UnitTestCase {
    public function test_categories_with_spaces_or_hyphens_are_normalized() {
        update_option('gm2_chatgpt_api_key', 'key');
        $resp_data = [ 'SEO Title' => 'Title rule', 'seo-description' => 'Desc rule' ];
        $filter = function($pre, $args, $url) use ($resp_data) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body'     => json_encode([
                        'choices' => [ ['message' => ['content' => json_encode($resp_data)]] ]
                    ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $this->_setRole('administrator');
        $_POST['categories'] = 'SEO Title, seo-description';
        $_POST['target'] = 'post_post';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_research_content_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_research_content_rules'); } catch (WPAjaxDieContinueException $e) {}

        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('Title rule', $resp['data']['seo_title']);
        $this->assertSame('Desc rule', $resp['data']['seo_description']);
        $rules = get_option('gm2_content_rules');
        $this->assertSame('Title rule', $rules['post_post']['seo_title']);
        $this->assertSame('Desc rule', $rules['post_post']['seo_description']);
    }

    public function test_unknown_categories_are_ignored() {
        update_option('gm2_chatgpt_api_key', 'key');
        $resp_data = [ 'unknown' => 'Rule', 'seo_title' => 'Title rule' ];
        $filter = function($pre, $args, $url) use ($resp_data) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body'     => json_encode([
                        'choices' => [ ['message' => ['content' => json_encode($resp_data)]] ]
                    ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $this->_setRole('administrator');
        $_POST['categories'] = 'unknown, seo_title';
        $_POST['target'] = 'post_post';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_research_content_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_research_content_rules'); } catch (WPAjaxDieContinueException $e) {}

        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertArrayHasKey('seo_title', $resp['data']);
        $this->assertArrayNotHasKey('unknown', $resp['data']);
        $rules = get_option('gm2_content_rules');
        $this->assertArrayHasKey('seo_title', $rules['post_post']);
        $this->assertArrayNotHasKey('unknown', $rules['post_post']);
    }

    public function test_nested_arrays_are_flattened_via_ajax() {
        update_option('gm2_chatgpt_api_key', 'key');
        $resp_data = [ 'canonical_url' => [[ 'Rule A' ], ['Rule B' ]] ];
        $filter = function($pre, $args, $url) use ($resp_data) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body'     => json_encode([
                        'choices' => [ ['message' => ['content' => json_encode($resp_data)]] ]
                    ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $this->_setRole('administrator');
        $_POST['categories'] = 'canonical_url';
        $_POST['target'] = 'post_post';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_research_content_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_research_content_rules'); } catch (WPAjaxDieContinueException $e) {}

        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame("Rule A\nRule B", $resp['data']['canonical_url']);
        $rules = get_option('gm2_content_rules');
        $this->assertSame("Rule A\nRule B", $rules['post_post']['canonical_url']);
    }

    public function test_synonym_categories_map_to_content() {
        update_option('gm2_chatgpt_api_key', 'key');
        $resp_data = [ 'content-in-post' => 'Mapped rule' ];
        $filter = function($pre, $args, $url) use ($resp_data) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body'     => json_encode([
                        'choices' => [ ['message' => ['content' => json_encode($resp_data)]] ]
                    ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $this->_setRole('administrator');
        $_POST['categories'] = 'content-in-post';
        $_POST['target'] = 'post_post';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_research_content_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_research_content_rules'); } catch (WPAjaxDieContinueException $e) {}

        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('Mapped rule', $resp['data']['content']);
        $rules = get_option('gm2_content_rules');
        $this->assertSame('Mapped rule', $rules['post_post']['content']);
    }

    public function test_only_unknown_categories_returns_error() {
        update_option('gm2_chatgpt_api_key', 'key');
        $resp_data = [ 'weird' => 'Rule' ];
        $filter = function($pre, $args, $url) use ($resp_data) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body'     => json_encode([
                        'choices' => [ ['message' => ['content' => json_encode($resp_data)]] ]
                    ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $this->_setRole('administrator');
        $_POST['categories'] = 'weird';
        $_POST['target'] = 'post_post';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_research_content_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_research_content_rules'); } catch (WPAjaxDieContinueException $e) {}

        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertFalse($resp['success']);
    }
}
?>

<?php

class GuidelineRulesFormTest extends WP_UnitTestCase {
    public function test_form_flattens_arrays() {
        $admin = new \Gm2\Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);

        $_POST['gm2_guideline_rules_nonce'] = wp_create_nonce('gm2_guideline_rules_save');
        $_POST['gm2_guideline_rules'] = [
            'post_post' => [
                'content' => ['First', 'Second']
            ]
        ];

        $admin->handle_guideline_rules_form();

        $rules = get_option('gm2_guideline_rules');
        $this->assertIsString($rules['post_post']['content']);
        $this->assertSame("First\nSecond", $rules['post_post']['content']);
    }

    public function test_form_flattens_nested_arrays() {
        $admin = new \Gm2\Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);

        $_POST['gm2_guideline_rules_nonce'] = wp_create_nonce('gm2_guideline_rules_save');
        $_POST['gm2_guideline_rules'] = [
            'post_post' => [
                'canonical_url' => [[ 'Rule A' ], ['Rule B']]
            ]
        ];

        $admin->handle_guideline_rules_form();

        $rules = get_option('gm2_guideline_rules');
        $this->assertSame("Rule A\nRule B", $rules['post_post']['canonical_url']);
    }

    public function test_form_flattens_scalar_value() {
        $admin = new \Gm2\Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);

        $_POST['gm2_guideline_rules_nonce'] = wp_create_nonce('gm2_guideline_rules_save');
        $_POST['gm2_guideline_rules'] = [
            'post_post' => 'One\nTwo'
        ];

        $admin->handle_guideline_rules_form();

        $rules = get_option('gm2_guideline_rules');
        $this->assertSame('One\nTwo', $rules['post_post']['general']);
    }
}

class GuidelineRulesNormalizationTest extends WP_Ajax_UnitTestCase {
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
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_research_guideline_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_research_guideline_rules'); } catch (WPAjaxDieContinueException $e) {}

        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('Title rule', $resp['data']['seo_title']);
        $this->assertSame('Desc rule', $resp['data']['seo_description']);
        $rules = get_option('gm2_guideline_rules');
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
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_research_guideline_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_research_guideline_rules'); } catch (WPAjaxDieContinueException $e) {}

        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertArrayHasKey('seo_title', $resp['data']);
        $this->assertArrayNotHasKey('unknown', $resp['data']);
        $rules = get_option('gm2_guideline_rules');
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
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_research_guideline_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_research_guideline_rules'); } catch (WPAjaxDieContinueException $e) {}

        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame("Rule A\nRule B", $resp['data']['canonical_url']);
        $rules = get_option('gm2_guideline_rules');
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
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_research_guideline_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_research_guideline_rules'); } catch (WPAjaxDieContinueException $e) {}

        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('Mapped rule', $resp['data']['content']);
        $rules = get_option('gm2_guideline_rules');
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
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_research_guideline_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_research_guideline_rules'); } catch (WPAjaxDieContinueException $e) {}

        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertFalse($resp['success']);
    }
}
?>

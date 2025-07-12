<?php
use Gm2\Gm2_SEO_Admin;

class GuidelinesAjaxTest extends WP_Ajax_UnitTestCase {
    public function test_guidelines_ajax_returns_text() {
        update_option('gm2_chatgpt_api_key', 'key');
        $filter = function($pre, $args, $url) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [ ['message' => ['content' => 'guidelines']] ]
                    ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $this->_setRole('administrator');
        $_POST['categories'] = 'on page';
        $_POST['target'] = 'gm2_seo_guidelines_post_post';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_research_guidelines');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try {
            $this->_handleAjax('gm2_research_guidelines');
        } catch (WPAjaxDieContinueException $e) {
        }
        remove_filter('pre_http_request', $filter, 10);
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('guidelines', $resp['data']);
        $this->assertSame('guidelines', get_option('gm2_seo_guidelines_post_post'));
    }
}

class AiResearchAjaxTest extends WP_Ajax_UnitTestCase {
    public function test_ai_research_returns_structured_json() {
        update_option('gm2_chatgpt_api_key', 'key');
        $data = [
            'seo_title' => 'New Title',
            'description' => 'Desc',
            'focus_keywords' => 'kw',
            'long_tail_keywords' => ['a','b'],
            'canonical' => 'https://example.com',
            'page_name' => 'Name',
            'slug' => 'new-post',
            'content_suggestions' => ['x','y'],
            'html_issues' => [ ['issue' => 'Missing alt', 'fix' => 'Add alt'] ]
        ];
        $filter = function($pre, $args, $url) use ($data) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [ ['message' => ['content' => json_encode($data)]] ]
                    ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $post_id = self::factory()->post->create([
            'post_title' => 'Post',
            'post_content' => '<h1>Hello</h1>'
        ]);
        update_post_meta($post_id, '_gm2_canonical', 'https://example.com');

        $this->_setRole('administrator');
        $_POST['post_id'] = $post_id;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try {
            $this->_handleAjax('gm2_ai_research');
        } catch (WPAjaxDieContinueException $e) {
        }
        remove_filter('pre_http_request', $filter, 10);
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('New Title', $resp['data']['seo_title']);
        $this->assertSame('Desc', $resp['data']['description']);
        $this->assertSame('Missing alt', $resp['data']['html_issues'][0]['issue']);
        $this->assertSame('new-post', $resp['data']['slug']);
    }

    public function test_ai_research_prompt_contains_snippet() {
        update_option('gm2_chatgpt_api_key', 'key');
        $captured = null;
        $filter = function($pre, $args, $url) use (&$captured) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                $body = json_decode($args['body'], true);
                $captured = $body['messages'][0]['content'];
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([ 'choices' => [ ['message' => ['content' => '{}']] ] ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $post_id = self::factory()->post->create([
            'post_title' => 'Post',
            'post_content' => '<h1>Hello</h1>'
        ]);
        update_post_meta($post_id, '_gm2_canonical', 'https://example.com');

        $this->_setRole('administrator');
        $_POST['post_id'] = $post_id;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try {
            $this->_handleAjax('gm2_ai_research');
        } catch (WPAjaxDieContinueException $e) {
        }
        remove_filter('pre_http_request', $filter, 10);

        $this->assertStringContainsString('Content snippet: Hello', $captured);
    }

    public function test_ai_research_parses_json_with_extra_text() {
        update_option('gm2_chatgpt_api_key', 'key');
        $json = json_encode(['seo_title' => 'Parsed']);
        $filter = function($pre, $args, $url) use ($json) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [ ['message' => ['content' => 'Intro text ' . $json . ' end']] ]
                    ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $post_id = self::factory()->post->create(['post_title' => 'Post', 'post_content' => 'Content']);

        $this->_setRole('administrator');
        $_POST['post_id'] = $post_id;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try {
            $this->_handleAjax('gm2_ai_research');
        } catch (WPAjaxDieContinueException $e) {
        }
        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('Parsed', $resp['data']['seo_title']);
    }

    public function test_ai_research_invalid_json_returns_error() {
        update_option('gm2_chatgpt_api_key', 'key');
        $filter = function($pre, $args, $url) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [ ['message' => ['content' => 'nonsense']] ]
                    ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $post_id = self::factory()->post->create(['post_title' => 'Post', 'post_content' => 'Content']);

        $this->_setRole('administrator');
        $_POST['post_id'] = $post_id;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try {
            $this->_handleAjax('gm2_ai_research');
        } catch (WPAjaxDieContinueException $e) {
        }
        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertFalse($resp['success']);
    }

    public function test_ai_research_detects_html_issues() {
        update_option('gm2_chatgpt_api_key', 'key');
        $filter = function($pre, $args, $url) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [ ['message' => ['content' => '{}']] ]
                    ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $html = '<h1>One</h1><p>Body</p><h1>Two</h1><img src="img.jpg">';
        $post_id = self::factory()->post->create([
            'post_title'   => 'Post',
            'post_content' => $html,
        ]);
        update_post_meta($post_id, '_gm2_canonical', 'https://example.com');

        $this->_setRole('administrator');
        $_POST['post_id'] = $post_id;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try {
            $this->_handleAjax('gm2_ai_research');
        } catch (WPAjaxDieContinueException $e) {
        }
        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertContains('Multiple <h1> tags found', $resp['data']['html_issues']);
        $this->assertContains('Image missing alt attribute', $resp['data']['html_issues']);
    }

    public function test_ai_research_handles_taxonomy_term() {
        update_option('gm2_chatgpt_api_key', 'key');
        $data = [ 'seo_title' => 'Term Title' ];
        $filter = function($pre, $args, $url) use ($data) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['choices' => [ ['message' => ['content' => json_encode($data)]] ]])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $captured = null;
        $capture_filter = function($content) use (&$captured) {
            global $post;
            $captured = $post instanceof WP_Post ? $post->ID : null;
            return $content;
        };
        add_filter('the_content', $capture_filter);

        $term_id = self::factory()->term->create(['taxonomy' => 'category', 'description' => 'Desc']);
        update_term_meta($term_id, '_gm2_canonical', 'https://example.com');

        $this->_setRole('administrator');
        $_POST['term_id'] = $term_id;
        $_POST['taxonomy'] = 'category';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_ai_research'); } catch (WPAjaxDieContinueException $e) {}

        remove_filter('pre_http_request', $filter, 10);
        remove_filter('the_content', $capture_filter);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('Term Title', $resp['data']['seo_title']);
        $this->assertSame(0, $captured);
    }

    public function test_the_content_filter_can_use_post_context_for_term() {
        update_option('gm2_chatgpt_api_key', 'key');
        $data = [ 'seo_title' => 'Term Title' ];
        $http_filter = function($pre, $args, $url) use ($data) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['choices' => [ ['message' => ['content' => json_encode($data)]] ]])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $http_filter, 10, 3);

        $captured = null;
        $content_filter = function($content) use (&$captured) {
            $captured = get_post_type();
            return $content;
        };
        add_filter('the_content', $content_filter);

        $term_id = self::factory()->term->create(['taxonomy' => 'category', 'description' => 'Desc']);
        update_term_meta($term_id, '_gm2_canonical', 'https://example.com');

        $this->_setRole('administrator');
        $_POST['term_id']  = $term_id;
        $_POST['taxonomy'] = 'category';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_ai_research'); } catch (WPAjaxDieContinueException $e) {}

        remove_filter('pre_http_request', $http_filter, 10);
        remove_filter('the_content', $content_filter);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('post', $captured);
    }

    public function test_ai_research_requires_edit_posts_cap() {
        $post_id = self::factory()->post->create();

        $this->_setRole('subscriber');
        $_POST['post_id'] = $post_id;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try {
            $this->_handleAjax('gm2_ai_research');
        } catch (WPAjaxDieContinueException $e) {
        }
        $resp = json_decode($this->_last_response, true);
        $this->assertFalse($resp['success']);
    }

    public function test_ai_research_requires_edit_term_cap() {
        $term_id = self::factory()->term->create(['taxonomy' => 'category']);

        $this->_setRole('subscriber');
        $_POST['term_id'] = $term_id;
        $_POST['taxonomy'] = 'category';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try {
            $this->_handleAjax('gm2_ai_research');
        } catch (WPAjaxDieContinueException $e) {
        }
        $resp = json_decode($this->_last_response, true);
        $this->assertFalse($resp['success']);
    }

    public function test_ai_research_handles_invalid_term_link() {
        update_option('gm2_chatgpt_api_key', 'key');

        $term_link_filter = function() {
            return new WP_Error('no_link', 'invalid');
        };
        add_filter('term_link', $term_link_filter, 10, 3);

        $captured = null;
        $http_filter = function($pre, $args, $url) use (&$captured) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                $body = json_decode($args['body'], true);
                $captured = $body['messages'][0]['content'];
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([ 'choices' => [ ['message' => ['content' => '{}']] ] ])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $http_filter, 10, 3);

        $term_id = self::factory()->term->create(['taxonomy' => 'category']);

        $this->_setRole('administrator');
        $_POST['term_id']  = $term_id;
        $_POST['taxonomy'] = 'category';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_ai_research'); } catch (WPAjaxDieContinueException $e) {}

        remove_filter('term_link', $term_link_filter, 10);
        remove_filter('pre_http_request', $http_filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertStringContainsString('URL: \n', $captured);
    }
}

class AdminTabsTest extends WP_UnitTestCase {
    public function test_dashboard_contains_guidelines_tab() {
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_dashboard();
        $out = ob_get_clean();
        $this->assertStringContainsString('SEO Guidelines', $out);
    }

    public function test_meta_box_contains_ai_seo_tab() {
        $post_id = self::factory()->post->create();
        $post = get_post($post_id);
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->render_seo_tabs_meta_box($post);
        $out = ob_get_clean();
        $this->assertStringContainsString('AI SEO', $out);
    }
}

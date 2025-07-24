<?php
use Gm2\Gm2_SEO_Admin;


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
        $this->assertSame(['a','b'], $resp['data']['long_tail_keywords']);
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

        $this->assertStringContainsString('[PAGE HTML]', $captured);
        $this->assertStringContainsString('Hello', $captured);
    }

    public function test_ai_research_prompt_includes_existing_keywords() {
        update_option('gm2_chatgpt_api_key', 'key');

        $p1 = self::factory()->post->create();
        $p2 = self::factory()->post->create();
        update_post_meta($p1, '_gm2_focus_keywords', 'Alpha');
        update_post_meta($p2, '_gm2_focus_keywords', 'Beta');

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

        $this->assertStringContainsString('existing focus keywords: alpha, beta', strtolower($captured));
    }

    public function test_second_prompt_includes_existing_keywords() {
        update_option('gm2_chatgpt_api_key', 'key');

        $p1 = self::factory()->post->create();
        $p2 = self::factory()->post->create();
        update_post_meta($p1, '_gm2_focus_keywords', 'Alpha');
        update_post_meta($p2, '_gm2_focus_keywords', 'Beta');

        $first  = ['seed_keywords' => ['gamma']];
        $second = ['seo_title' => 'Final'];
        $step   = 0;
        $captured = null;
        $filter = function($pre, $args, $url) use (&$step, $first, $second, &$captured) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                $body = json_decode($args['body'], true);
                if ($step === 0) {
                    $step++;
                    return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => json_encode($first)]] ]]) ];
                }
                $captured = $body['messages'][0]['content'];
                return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => json_encode($second)]] ]]) ];
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
        } catch (WPAjaxDieContinueException $e) {}
        remove_filter('pre_http_request', $filter, 10);

        $this->assertStringContainsString('existing focus keywords: alpha, beta', strtolower($captured));
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

    public function test_ai_research_second_response_with_newlines_and_comments() {
        update_option('gm2_chatgpt_api_key', 'key');

        $first = ['seed_keywords' => ['alpha']];
        $second_raw = "{ \"seo_title\": \"Line1\nLine2\", \"description\": \"Desc\" } // comment";
        $step = 0;
        $filter = function($pre, $args, $url) use (&$step, $first, $second_raw) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                if ($step === 0) {
                    $step++;
                    return [
                        'response' => ['code' => 200],
                        'body' => json_encode([
                            'choices' => [ ['message' => ['content' => json_encode($first)]] ]
                        ])
                    ];
                }
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [ ['message' => ['content' => $second_raw]] ]
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
        $this->assertSame("Line1\nLine2", $resp['data']['seo_title']);
    }

    public function test_sanitize_ai_json_handles_newlines() {
        $admin  = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'sanitize_ai_json');
        $method->setAccessible(true);

        $raw  = "{ \"updated_html\": \"<p>line1\nline2</p>\" }";
        $clean = $method->invoke($admin, $raw);
        $data  = json_decode($clean, true);

        $this->assertSame("<p>line1\nline2</p>", $data['updated_html']);
    }

    public function test_sanitize_ai_json_handles_braces_in_quotes() {
        $admin  = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'sanitize_ai_json');
        $method->setAccessible(true);

        $raw  = '{ "updated_html": "<p>{example}</p>" }';
        $clean = $method->invoke($admin, $raw);
        $data  = json_decode($clean, true);

        $this->assertNotNull($data);
        $this->assertSame('<p>{example}</p>', $data['updated_html']);
    }

    public function test_sanitize_ai_json_converts_braced_lists() {
        $admin  = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'sanitize_ai_json');
        $method->setAccessible(true);

        $raw  = '{ "content_suggestions": { "One", "Two" } }';
        $clean = $method->invoke($admin, $raw);
        $data  = json_decode($clean, true);

        $this->assertSame(['One', 'Two'], $data['content_suggestions']);
    }

    public function test_sanitize_ai_json_escapes_inch_quotes() {
        $admin  = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'sanitize_ai_json');
        $method->setAccessible(true);

        $raw  = '{ "size": "Approx 17.5" screen" }';
        $clean = $method->invoke($admin, $raw);
        $data  = json_decode($clean, true);

        $this->assertNotNull($data);
        $this->assertSame('Approx 17.5" screen', $data['size']);
    }

    public function test_sanitize_ai_json_allows_steel_wheels_string() {
        $admin  = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'sanitize_ai_json');
        $method->setAccessible(true);

        $raw  = '{ "spec": "17.5" steel wheels" }';
        $clean = $method->invoke($admin, $raw);
        $data  = json_decode($clean, true);

        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertNotNull($data);
        $this->assertSame('17.5" steel wheels', $data['spec']);
    }

    public function test_sanitize_ai_json_escapes_unescaped_quotes() {
        $admin  = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'sanitize_ai_json');
        $method->setAccessible(true);

        $raw  = '{ "seo_title":"New "Special" Title" }';
        $clean = $method->invoke($admin, $raw);

        $this->assertStringContainsString('New \"Special\" Title', $clean);

        $data  = json_decode($clean, true);

        $this->assertNotNull($data);
        $this->assertSame('New "Special" Title', $data['seo_title']);
    }

    public function test_sanitize_ai_json_strips_comments() {
        $admin  = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'sanitize_ai_json');
        $method->setAccessible(true);

        $raw  = "{ \"foo\": \"bar\" } /* c1 */ // c2\n\"";
        $clean = $method->invoke($admin, $raw);
        $data  = json_decode($clean, true);

        $this->assertNotNull($data);
        $this->assertSame('bar', $data['foo']);
    }

    public function test_sanitize_ai_json_extracts_first_object() {
        $admin  = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'sanitize_ai_json');
        $method->setAccessible(true);

        $raw  = '{ "one": 1 } { "two": 2 }';
        $clean = $method->invoke($admin, $raw);
        $data  = json_decode($clean, true);

        $this->assertSame(['one' => 1], $data);
    }

    public function test_sanitize_ai_json_normalizes_curly_quotes() {
        $admin  = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'sanitize_ai_json');
        $method->setAccessible(true);

        $raw  = "{ “seo_title”: “Curly” }";
        $clean = $method->invoke($admin, $raw);
        $data  = json_decode($clean, true);

        $this->assertSame('Curly', $data['seo_title']);
    }

    public function test_sanitize_ai_json_removes_trailing_commas() {
        $admin  = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'sanitize_ai_json');
        $method->setAccessible(true);

        $raw  = '{ "x": 1, }';
        $clean = $method->invoke($admin, $raw);
        $data  = json_decode($clean, true);
        $this->assertSame(['x' => 1], $data);

        $raw  = '{ "arr": ["a","b",] }';
        $clean = $method->invoke($admin, $raw);
        $data  = json_decode($clean, true);
        $this->assertSame(['arr' => ['a','b']], $data);
    }

    public function test_sanitize_ai_json_removes_chars_after_string() {
        $admin  = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'sanitize_ai_json');
        $method->setAccessible(true);

        $raw  = '{ "updated_html": "<p>text</p>"... }';
        $clean = $method->invoke($admin, $raw);
        $data  = json_decode($clean, true);

        $this->assertSame('<p>text</p>', $data['updated_html']);
    }

    public function test_sanitize_ai_json_converts_single_quotes() {
        $admin  = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'sanitize_ai_json');
        $method->setAccessible(true);

        $raw  = "{ 'seo_title': 'Single' }";
        $clean = $method->invoke($admin, $raw);
        $data  = json_decode($clean, true);

        $this->assertSame('Single', $data['seo_title']);
    }

    public function test_sanitize_ai_json_handles_preg_failure() {
        $admin  = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'sanitize_ai_json');
        $method->setAccessible(true);

        $raw = '{ "seo_title": "Title" }';

        $old = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '0');
        $clean = $method->invoke($admin, $raw);
        ini_set('pcre.backtrack_limit', $old);

        $this->assertSame($raw, $clean);
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

    public function test_ai_research_truncated_json_is_handled() {
        update_option('gm2_chatgpt_api_key', 'key');
        $raw = '{"seo_title":"Truncated {brace}", "description":"Desc"}{';
        $filter = function($pre, $args, $url) use ($raw) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['choices' => [ ['message' => ['content' => $raw]] ]])
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
        try { $this->_handleAjax('gm2_ai_research'); } catch (WPAjaxDieContinueException $e) {}
        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('Truncated {brace}', $resp['data']['seo_title']);
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

class LongTailKeywordsTest extends WP_UnitTestCase {
    public function test_long_tail_keywords_saved() {
        $post_id = self::factory()->post->create();
        $admin = new Gm2\Gm2_SEO_Admin();
        $_POST['gm2_seo_nonce'] = wp_create_nonce('gm2_save_seo_meta');
        $_POST['gm2_long_tail_keywords'] = 'alpha, beta ';
        $admin->save_post_meta($post_id);
        $this->assertSame('alpha, beta', get_post_meta($post_id, '_gm2_long_tail_keywords', true));
    }
}

class AiResearchPersistenceTest extends WP_Ajax_UnitTestCase {
    public function test_ai_research_saved_and_localized() {
        update_option('gm2_chatgpt_api_key', 'key');
        $resp_data = ['seo_title' => 'Saved Title'];
        $filter = function($pre, $args, $url) use ($resp_data) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['choices' => [ ['message' => ['content' => json_encode($resp_data)]] ]])
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
        try { $this->_handleAjax('gm2_ai_research'); } catch (WPAjaxDieContinueException $e) {}
        remove_filter('pre_http_request', $filter, 10);

        $stored = get_post_meta($post_id, '_gm2_ai_research', true);
        $this->assertNotEmpty($stored);
        $saved = json_decode($stored, true);
        $this->assertSame('Saved Title', $saved['seo_title']);

        $_GET['post'] = $post_id;
        $admin = new Gm2\Gm2_SEO_Admin();
        $admin->enqueue_editor_scripts();
        global $wp_scripts;
        $inline = $wp_scripts->get_data('gm2-ai-seo', 'data');
        $this->assertNotEmpty($inline);
        if (preg_match('/var gm2AiSeo = (.*);/', $inline, $m)) {
            $localized = json_decode($m[1], true);
        } else {
            $localized = [];
        }
        $this->assertSame('Saved Title', $localized['results']['seo_title']);
    }
}

class AiResearchKeywordSelectionTest extends WP_Ajax_UnitTestCase {
    public function test_seed_keywords_and_selection() {
        update_option('gm2_chatgpt_api_key', 'key');
        update_option('gm2_gads_developer_token', 'dev');
        update_option('gm2_gads_customer_id', '123-456-7890');
        update_option('gm2_google_refresh_token', 'refresh');
        update_option('gm2_google_access_token', 'access');
        update_option('gm2_google_expires_at', time() + 3600);

        $first = ['seed_keywords' => ['alpha', 'beta']];
        $second = ['seo_title' => 'Final'];
        $kwp = [
            'results' => [
                [
                    'text' => 'best alpha',
                    'keyword_idea_metrics' => [
                        'avg_monthly_searches' => 200,
                        'competition' => 'LOW',
                        'monthly_search_volumes' => [
                            ['year' => 2023, 'month' => 1, 'monthly_searches' => 200],
                            ['year' => 2023, 'month' => 2, 'monthly_searches' => 210],
                            ['year' => 2023, 'month' => 3, 'monthly_searches' => 220]
                        ]
                    ]
                ],
                [
                    'text' => 'cheap beta',
                    'keyword_idea_metrics' => [
                        'avg_monthly_searches' => 150,
                        'competition' => 'MEDIUM',
                        'monthly_search_volumes' => [
                            ['year' => 2023, 'month' => 1, 'monthly_searches' => 150],
                            ['year' => 2023, 'month' => 2, 'monthly_searches' => 145],
                            ['year' => 2023, 'month' => 3, 'monthly_searches' => 140]
                        ]
                    ]
                ]
            ]
        ];
        $step = 0;
        $captured = null;
        $filter = function($pre, $args, $url) use (&$step, $first, $second, $kwp, &$captured) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                $body = json_decode($args['body'], true);
                if ($step === 0) {
                    $step++;
                    return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => json_encode($first)]] ]]) ];
                } else {
                    $captured = $body['messages'][0]['content'];
                    return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => json_encode($second)]] ]]) ];
                }
            }
            if (false !== strpos($url, 'generateKeywordIdeas')) {
                return [ 'response' => ['code' => 200], 'body' => json_encode($kwp) ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $post_id = self::factory()->post->create(['post_title' => 'Post', 'post_content' => 'Content']);

        $this->_setRole('administrator');
        $_POST['post_id'] = $post_id;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_ai_research'); } catch (WPAjaxDieContinueException $e) {}
        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('Final', $resp['data']['seo_title']);
        $this->assertSame('best alpha', $resp['data']['focus_keywords']);
        $this->assertContains('cheap beta', $resp['data']['long_tail_keywords']);
        $this->assertStringContainsString('best alpha', $captured);
    }
}

class AiResearchErrorHandlingTest extends WP_Ajax_UnitTestCase {
    public function test_chatgpt_exception_handled() {
        update_option('gm2_chatgpt_api_key', 'key');
        $filter = function($pre, $args, $url) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                throw new Exception('fail');
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $post_id = self::factory()->post->create(['post_title' => 'Post', 'post_content' => 'Content']);

        $this->_setRole('administrator');
        $_POST['post_id'] = $post_id;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_ai_research'); } catch (WPAjaxDieContinueException $e) {}
        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertFalse($resp['success']);
    }

    public function test_keyword_planner_exception_handled() {
        update_option('gm2_chatgpt_api_key', 'key');
        update_option('gm2_gads_developer_token', 'dev');
        update_option('gm2_gads_customer_id', '123-456-7890');
        update_option('gm2_google_refresh_token', 'refresh');
        update_option('gm2_google_access_token', 'access');
        update_option('gm2_google_expires_at', time() + 3600);

        $step = 0;
        $filter = function($pre, $args, $url) use (&$step) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                if ($step === 0) {
                    $step++;
                    return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => json_encode(['seed_keywords' => ['alpha']])]] ]]) ];
                }
                return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => '{}']] ]]) ];
            }
            if (false !== strpos($url, 'generateKeywordIdeas')) {
                throw new Exception('kwp fail');
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $post_id = self::factory()->post->create(['post_title' => 'Post', 'post_content' => 'Content']);

        $this->_setRole('administrator');
        $_POST['post_id'] = $post_id;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_ai_research'); } catch (WPAjaxDieContinueException $e) {}
        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('alpha', $resp['data']['focus_keywords']);
        $this->assertSame([], $resp['data']['long_tail_keywords']);
        $this->assertSame('Google Ads keyword research unavailable—using AI suggestions only.', $resp['data']['kwp_notice']);
    }

    public function test_keyword_planner_no_metrics_handled() {
        update_option('gm2_chatgpt_api_key', 'key');
        update_option('gm2_gads_developer_token', 'dev');
        update_option('gm2_gads_customer_id', '123-456-7890');
        update_option('gm2_google_refresh_token', 'refresh');
        update_option('gm2_google_access_token', 'access');
        update_option('gm2_google_expires_at', time() + 3600);

        $step = 0;
        $filter = function($pre, $args, $url) use (&$step) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                if ($step === 0) {
                    $step++;
                    return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => json_encode(['seed_keywords' => ['alpha']])]] ]]) ];
                }
                return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => '{}']] ]]) ];
            }
            if (false !== strpos($url, 'generateKeywordIdeas')) {
                return [ 'response' => ['code' => 200], 'body' => json_encode(['results' => [ ['text' => 'alpha'] ]]) ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $post_id = self::factory()->post->create(['post_title' => 'Post', 'post_content' => 'Content']);

        $this->_setRole('administrator');
        $_POST['post_id'] = $post_id;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_ai_research'); } catch (WPAjaxDieContinueException $e) {}
        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('alpha', $resp['data']['focus_keywords']);
        $this->assertSame([], $resp['data']['long_tail_keywords']);
        $this->assertSame('Google Ads API did not return keyword metrics.', $resp['data']['kwp_notice']);
    }

    public function test_missing_ads_credentials_fallback() {
        update_option('gm2_chatgpt_api_key', 'key');
        $step = 0;
        $filter = function($pre, $args, $url) use (&$step) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                if ($step === 0) {
                    $step++;
                    return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => json_encode(['seed_keywords' => ['alpha','beta']])]] ]]) ];
                }
                return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => '{}']] ]]) ];
            }
            if (false !== strpos($url, 'generateKeywordIdeas')) {
                return false;
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $post_id = self::factory()->post->create(['post_title' => 'Post', 'post_content' => 'Content']);

        $this->_setRole('administrator');
        $_POST['post_id'] = $post_id;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_ai_research'); } catch (WPAjaxDieContinueException $e) {}
        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('alpha', $resp['data']['focus_keywords']);
        $this->assertContains('beta', $resp['data']['long_tail_keywords']);
        $this->assertSame('Google Ads keyword research unavailable—using AI suggestions only.', $resp['data']['kwp_notice']);
    }

    public function test_seed_keywords_missing_uses_ai_fallback() {
        update_option('gm2_chatgpt_api_key', 'key');
        update_option('gm2_gads_developer_token', 'dev');
        update_option('gm2_gads_customer_id', '123-456-7890');
        update_option('gm2_google_refresh_token', 'refresh');
        update_option('gm2_google_access_token', 'access');
        update_option('gm2_google_expires_at', time() + 3600);

        $step = 0;
        $captured = null;
        $filter = function($pre, $args, $url) use (&$step, &$captured) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                $body = json_decode($args['body'], true);
                if ($step === 0) {
                    $step++;
                    return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => '{}']] ]]) ];
                } elseif ($step === 1) {
                    $step++;
                    return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => 'alpha,beta']] ]]) ];
                } else {
                    $captured = $body['messages'][0]['content'];
                    return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => json_encode(['seo_title' => 'Title'])]] ]]) ];
                }
            }
            if (false !== strpos($url, 'generateKeywordIdeas')) {
                return [ 'response' => ['code' => 200], 'body' => json_encode(['results' => [ ['text' => 'alpha'], ['text' => 'beta'] ]]) ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $post_id = self::factory()->post->create(['post_title' => 'Post', 'post_content' => 'Content']);

        $this->_setRole('administrator');
        $_POST['post_id'] = $post_id;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_ai_research'); } catch (WPAjaxDieContinueException $e) {}
        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('alpha', $resp['data']['focus_keywords']);
        $this->assertContains('beta', $resp['data']['long_tail_keywords']);
        $this->assertSame('AI response contained no seed keywords—using generated suggestions.', $resp['data']['kwp_notice']);
        $this->assertStringContainsString('alpha', $captured);
    }

    public function test_seed_keywords_array_is_accepted() {
        update_option('gm2_chatgpt_api_key', 'key');

        $step = 0;
        $filter = function($pre, $args, $url) use (&$step) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                if ($step === 0) {
                    $step++;
                    return [
                        'response' => ['code' => 200],
                        'body' => json_encode([
                            'choices' => [ ['message' => ['content' => json_encode(['seed_keywords' => ['a','b']])]] ]
                        ])
                    ];
                }
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [ ['message' => ['content' => json_encode(['seo_title' => 'Title'])]] ]
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
        try { $this->_handleAjax('gm2_ai_research'); } catch (WPAjaxDieContinueException $e) {}
        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('Title', $resp['data']['seo_title']);
        $this->assertSame('a', $resp['data']['focus_keywords']);
        $this->assertContains('b', $resp['data']['long_tail_keywords']);
        $this->assertSame('a, b', $resp['data']['seed_keywords']);
    }

    public function test_focus_and_seed_keyword_arrays_are_accepted() {
        update_option('gm2_chatgpt_api_key', 'key');

        $step = 0;
        $filter = function($pre, $args, $url) use (&$step) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                if ($step === 0) {
                    $step++;
                    return [
                        'response' => ['code' => 200],
                        'body' => json_encode([
                            'choices' => [ ['message' => ['content' => json_encode([
                                'seed_keywords' => ['a','b'],
                                'focus_keywords' => ['a','b'],
                            ])]] ]
                        ])
                    ];
                }
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [ ['message' => ['content' => json_encode(['seo_title' => 'Title'])]] ]
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
        try { $this->_handleAjax('gm2_ai_research'); } catch (WPAjaxDieContinueException $e) {}
        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('Title', $resp['data']['seo_title']);
        $this->assertSame('a', $resp['data']['focus_keywords']);
        $this->assertContains('b', $resp['data']['long_tail_keywords']);
        $this->assertSame('a, b', $resp['data']['seed_keywords']);
    }

    public function test_duplicate_seed_keywords_removed() {
        update_option('gm2_chatgpt_api_key', 'key');

        $p = self::factory()->post->create();
        update_post_meta($p, '_gm2_focus_keywords', 'Alpha');

        $step = 0;
        $filter = function($pre, $args, $url) use (&$step) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                if ($step === 0) {
                    $step++;
                    return [
                        'response' => ['code' => 200],
                        'body' => json_encode([
                            'choices' => [ ['message' => ['content' => json_encode(['seed_keywords' => ['Alpha','Beta']])]] ]
                        ])
                    ];
                }
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [ ['message' => ['content' => json_encode(['seo_title' => 'Title'])]] ]
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
        try { $this->_handleAjax('gm2_ai_research'); } catch (WPAjaxDieContinueException $e) {}
        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('Title', $resp['data']['seo_title']);
        $this->assertSame('Beta', $resp['data']['focus_keywords']);
        $this->assertSame('Beta', $resp['data']['seed_keywords']);
    }

    public function test_duplicate_focus_keywords_filtered() {
        update_option('gm2_chatgpt_api_key', 'key');

        $p = self::factory()->post->create();
        update_post_meta($p, '_gm2_focus_keywords', 'Alpha');

        $first = ['seed_keywords' => ['beta']];
        $kwp = [
            'results' => [
                [
                    'text' => 'Alpha',
                    'keyword_idea_metrics' => [
                        'avg_monthly_searches' => 100,
                        'competition' => 'LOW',
                        'monthly_search_volumes' => []
                    ]
                ],
                [
                    'text' => 'Alpha',
                    'keyword_idea_metrics' => [
                        'avg_monthly_searches' => 90,
                        'competition' => 'LOW',
                        'monthly_search_volumes' => []
                    ]
                ]
            ]
        ];
        $second = ['seo_title' => 'Title'];
        $step = 0;
        $filter = function($pre, $args, $url) use (&$step, $first, $second, $kwp) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                if ($step === 0) {
                    $step++;
                    return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => json_encode($first)]] ]]) ];
                }
                return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => json_encode($second)]] ]]) ];
            }
            if (false !== strpos($url, 'generateKeywordIdeas')) {
                return [ 'response' => ['code' => 200], 'body' => json_encode($kwp) ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $post_id = self::factory()->post->create(['post_title' => 'Post', 'post_content' => 'Content']);

        $this->_setRole('administrator');
        $_POST['post_id'] = $post_id;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_research');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_ai_research'); } catch (WPAjaxDieContinueException $e) {}
        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('', $resp['data']['focus_keywords']);
        $this->assertSame([], $resp['data']['long_tail_keywords']);
    }
}

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

    public function test_guidelines_ajax_handles_array_json() {
        update_option('gm2_chatgpt_api_key', 'key');
        $filter = function($pre, $args, $url) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [ ['message' => ['content' => json_encode(['one','two'])]] ]
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
        $this->assertSame("one\ntwo", $resp['data']);
        $this->assertSame("one\ntwo", get_option('gm2_seo_guidelines_post_post'));
    }

    public function test_guidelines_ajax_handles_object_json() {
        update_option('gm2_chatgpt_api_key', 'key');
        $filter = function($pre, $args, $url) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'choices' => [ ['message' => ['content' => json_encode(['first' => 'one','second' => 'two'])]] ]
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
        $this->assertSame("one\ntwo", $resp['data']);
        $this->assertSame("one\ntwo", get_option('gm2_seo_guidelines_post_post'));
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

        $first = ['seed_keywords' => 'alpha,beta'];
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
                    return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => json_encode(['seed_keywords' => 'alpha'])]] ]]) ];
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
        $this->assertFalse($resp['success']);
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
                    return [ 'response' => ['code' => 200], 'body' => json_encode(['choices' => [ ['message' => ['content' => json_encode(['seed_keywords' => 'alpha'])]] ]]) ];
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
}

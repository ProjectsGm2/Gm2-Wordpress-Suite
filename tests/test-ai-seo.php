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
        $this->assertSame('guidelines', get_option('gm2_seo_guidelines'));
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

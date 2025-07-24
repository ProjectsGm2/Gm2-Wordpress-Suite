<?php
use Gm2\Gm2_SEO_Admin;

class TaxDescriptionAjaxTest extends WP_Ajax_UnitTestCase {
    public function test_generate_tax_description_updates_term() {
        update_option('gm2_chatgpt_api_key', 'key');
        update_option('gm2_guideline_rules', ['tax_category' => ['general' => 'guidelines']]);
        update_option('gm2_tax_desc_prompt', 'Prompt {name} {guidelines}');

        $captured = null;
        $filter = function($pre, $args, $url) use (&$captured) {
            if ($url === 'https://api.openai.com/v1/chat/completions') {
                $body = json_decode($args['body'], true);
                $captured = $body['messages'][0]['content'];
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['choices' => [ ['message' => ['content' => 'desc']] ]])
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $term_id = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Books']);
        update_term_meta($term_id, '_gm2_title', 'SEO T');
        update_term_meta($term_id, '_gm2_description', 'SEO D');
        update_term_meta($term_id, '_gm2_focus_keywords', 'focus');
        update_term_meta($term_id, '_gm2_long_tail_keywords', 'long');
        update_term_meta($term_id, '_gm2_canonical', 'https://ex.com');

        $this->_setRole('administrator');
        $_POST['taxonomy'] = 'category';
        $_POST['term_id'] = $term_id;
        $_POST['name'] = 'Books';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_ai_generate_tax_description');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];

        try { $this->_handleAjax('gm2_ai_generate_tax_description'); } catch (WPAjaxDieContinueException $e) {}

        remove_filter('pre_http_request', $filter, 10);

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('desc', $resp['data']);
        $term = get_term($term_id, 'category');
        $this->assertSame('desc', $term->description);
        $this->assertStringContainsString('Books', $captured);
        $this->assertStringContainsString('guidelines', $captured);
        $this->assertStringContainsString('Taxonomy type: post category', $captured);
        $this->assertStringContainsString('SEO Title: SEO T', $captured);
        $this->assertStringContainsString('SEO Description: SEO D', $captured);
        $this->assertStringContainsString('Focus Keywords: focus', $captured);
        $this->assertStringContainsString('Long-tail Keywords: long', $captured);
        $this->assertStringContainsString('Canonical URL: https://ex.com', $captured);
    }
}

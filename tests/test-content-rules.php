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
}
?>

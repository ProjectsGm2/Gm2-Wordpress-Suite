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
        $_POST['content'] = str_repeat('word ', 300);
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
}
?>

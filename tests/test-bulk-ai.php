<?php
use Gm2\Gm2_SEO_Admin;

class BulkAiPageTest extends WP_UnitTestCase {
    public function test_page_requires_edit_posts_cap() {
        $admin = new Gm2_SEO_Admin();
        $user = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_page();
        $out = ob_get_clean();
        $this->assertStringContainsString('Permission denied', $out);
    }
}

class BulkAiApplyAjaxTest extends WP_Ajax_UnitTestCase {
    public function test_apply_updates_post_meta() {
        $post_id = self::factory()->post->create();
        $this->_setRole('administrator');
        $_POST['post_id'] = $post_id;
        $_POST['seo_title'] = 'New';
        $_POST['seo_description'] = 'Desc';
        $_POST['slug'] = 'new-slug';
        $_POST['title'] = 'New Title';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_bulk_ai_apply');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_bulk_ai_apply'); } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $post = get_post($post_id);
        $this->assertSame('new-slug', $post->post_name);
        $this->assertSame('New', get_post_meta($post_id, '_gm2_title', true));
        $this->assertSame('Desc', get_post_meta($post_id, '_gm2_description', true));
    }

    public function test_apply_requires_cap() {
        $post_id = self::factory()->post->create();
        $this->_setRole('subscriber');
        $_POST['post_id'] = $post_id;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_bulk_ai_apply');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_bulk_ai_apply'); } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $this->assertFalse($resp['success']);
    }
}

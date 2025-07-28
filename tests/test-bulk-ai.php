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

class BulkAiFilterTest extends WP_UnitTestCase {
    public function test_post_type_filter_limits_results() {
        $post1 = self::factory()->post->create(['post_title' => 'Post One']);
        $page1 = self::factory()->post->create(['post_type' => 'page', 'post_title' => 'Page One']);
        update_option('gm2_bulk_ai_post_type', 'page');
        $admin = new Gm2_SEO_Admin();
        $user = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_page();
        $out = ob_get_clean();
        $this->assertStringContainsString('Page One', $out);
        $this->assertStringNotContainsString('Post One', $out);
    }

    public function test_category_filter_limits_results() {
        $cat1 = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Alpha']);
        $cat2 = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Beta']);
        $in = self::factory()->post->create(['post_title' => 'In', 'post_category' => [$cat1]]);
        $out_post = self::factory()->post->create(['post_title' => 'Out', 'post_category' => [$cat2]]);
        update_option('gm2_bulk_ai_post_type', 'post');
        update_option('gm2_bulk_ai_term', 'category:' . $cat1);
        $admin = new Gm2_SEO_Admin();
        $user = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_page();
        $html = ob_get_clean();
        $this->assertStringContainsString('In', $html);
        $this->assertStringNotContainsString('Out', $html);
    }
}

class BulkAiPaginationTest extends WP_UnitTestCase {
    public function test_second_page_shows_expected_posts() {
        update_option('gm2_bulk_ai_page_size', 2);
        $posts = self::factory()->post->create_many(3);
        $admin = new Gm2_SEO_Admin();
        $user = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        $_GET['paged'] = 2;
        ob_start();
        $admin->display_bulk_ai_page();
        $html = ob_get_clean();
        $this->assertStringContainsString(get_post($posts[2])->post_title, $html);
        $this->assertStringNotContainsString(get_post($posts[0])->post_title, $html);
        $this->assertStringContainsString('paged=1', $html);
        unset($_GET['paged']);
    }
}

class BulkAiCachedResultsTest extends WP_UnitTestCase {
    public function test_cached_results_preloaded() {
        $post_id = self::factory()->post->create(['post_title' => 'Post']);
        update_post_meta($post_id, '_gm2_ai_research', wp_json_encode(['seo_title' => 'Cached Title']));

        $admin = new Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_page();
        $html = ob_get_clean();
        $this->assertStringContainsString('Cached Title', $html);
        $this->assertStringContainsString('gm2-refresh-btn', $html);
    }
}

class BulkAiApplyBatchAjaxTest extends WP_Ajax_UnitTestCase {
    public function test_batch_apply_updates_posts() {
        $posts = self::factory()->post->create_many(2);
        $this->_setRole('administrator');
        $payload = [
            $posts[0] => [ 'seo_title' => 'One', 'seo_description' => 'Desc1' ],
            $posts[1] => [ 'slug' => 'two', 'title' => 'Two' ],
        ];
        $_POST['posts'] = wp_json_encode($payload);
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_bulk_ai_apply');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_bulk_ai_apply_batch'); } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('One', get_post_meta($posts[0], '_gm2_title', true));
        $this->assertSame('Desc1', get_post_meta($posts[0], '_gm2_description', true));
        $post2 = get_post($posts[1]);
        $this->assertSame('two', $post2->post_name);
        $this->assertSame('Two', $post2->post_title);
    }
}

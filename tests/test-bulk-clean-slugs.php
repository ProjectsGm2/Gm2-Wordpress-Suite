<?php
use Gm2\Gm2_SEO_Admin;

class BulkCleanSlugsTest extends WP_UnitTestCase {
    public function test_bulk_action_redirects_for_confirmation() {
        $post = self::factory()->post->create();
        $admin = new Gm2_SEO_Admin();
        $url = $admin->redirect_clean_slug_bulk_action('edit.php', 'gm2_bulk_clean_slugs', [$post]);
        $this->assertStringContainsString('gm2_clean_slugs_ids=' . $post, $url);
    }

    public function test_handle_bulk_clean_slugs_updates_posts() {
        update_option('gm2_slug_stopwords', 'the');
        $post = self::factory()->post->create(['post_name' => 'the-slug']);
        $admin = new Gm2_SEO_Admin();
        $_POST['ids'] = (string)$post;
        $_POST['_wpnonce'] = wp_create_nonce('gm2_bulk_clean_slugs');
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $admin->handle_bulk_clean_slugs();
        $post_obj = get_post($post);
        $this->assertSame('slug', $post_obj->post_name);
    }
}

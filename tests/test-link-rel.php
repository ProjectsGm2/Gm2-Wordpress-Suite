<?php
use Gm2\Gm2_SEO_Public;
class LinkRelTest extends WP_UnitTestCase {
    public function test_apply_link_rel_adds_attribute() {
        $post_id = self::factory()->post->create([
            'post_title' => 'Link rel',
            'post_content' => '<a href="https://example.com/">Example</a>'
        ]);
        update_post_meta($post_id, '_gm2_link_rel', json_encode(['https://example.com/' => 'nofollow']));
        $seo = new Gm2_SEO_Public();
        $seo->run();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        $output = apply_filters('the_content', get_post_field('post_content', $post_id));
        $this->assertStringContainsString('rel="nofollow"', $output);
    }
}

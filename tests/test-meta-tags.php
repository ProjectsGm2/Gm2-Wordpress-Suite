<?php
class MetaTagsTest extends WP_UnitTestCase {
    public function test_output_meta_tags_for_post() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Sample',
            'post_content' => 'Content',
        ]);
        update_post_meta($post_id, '_gm2_title', 'Custom Title');
        update_post_meta($post_id, '_gm2_description', 'Custom Description');
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();
        $this->assertStringContainsString('<title>Custom Title</title>', $output);
        $this->assertStringContainsString('content="Custom Description"', $output);
    }
}


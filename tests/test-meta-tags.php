<?php
class MetaTagsTest extends WP_UnitTestCase {
    public function test_output_meta_tags_for_post_without_title_support() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Sample',
            'post_content' => 'Content',
        ]);
        update_post_meta($post_id, '_gm2_title', 'Custom Title');
        update_post_meta($post_id, '_gm2_description', 'Custom Description');
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        remove_theme_support('title-tag');
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();
        $this->assertStringContainsString('<title>Custom Title</title>', $output);
        $this->assertStringContainsString('content="Custom Description"', $output);
        add_theme_support('title-tag');
    }

    public function test_output_meta_tags_for_post_with_title_support() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Sample',
            'post_content' => 'Content',
        ]);
        update_post_meta($post_id, '_gm2_title', 'Custom Title');
        update_post_meta($post_id, '_gm2_description', 'Custom Description');
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        add_theme_support('title-tag');
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();
        $this->assertStringNotContainsString('<title>Custom Title</title>', $output);
        $this->assertStringContainsString('content="Custom Description"', $output);
    }

    public function test_output_meta_tags_for_product_post() {
        register_post_type('product');
        $post_id = self::factory()->post->create([
            'post_type'    => 'product',
            'post_title'   => 'Product Sample',
            'post_content' => 'Content',
        ]);
        update_post_meta($post_id, '_gm2_title', 'Product Title');
        update_post_meta($post_id, '_gm2_description', 'Product Description');

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString('<meta property="og:title" content="Product Title"', $output);
        $this->assertStringContainsString('<meta name="twitter:title" content="Product Title"', $output);
        $this->assertStringContainsString('content="Product Description"', $output);
    }

    public function test_output_meta_tags_for_brand_term() {
        register_taxonomy('brand', 'post');
        $term_id = self::factory()->term->create([
            'taxonomy' => 'brand',
            'name'     => 'Brand One',
        ]);
        update_term_meta($term_id, '_gm2_title', 'Brand Title');
        update_term_meta($term_id, '_gm2_description', 'Brand Description');

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_term_link($term_id, 'brand'));
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString('<meta property="og:title" content="Brand Title"', $output);
        $this->assertStringContainsString('<meta name="twitter:title" content="Brand Title"', $output);
        $this->assertStringContainsString('content="Brand Description"', $output);
    }

    public function test_noindex_nofollow_outputs_correct_robots_meta() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Sample',
            'post_content' => 'Content',
        ]);
        update_post_meta($post_id, '_gm2_noindex', '1');
        update_post_meta($post_id, '_gm2_nofollow', '1');
        update_post_meta($post_id, '_gm2_canonical', 'https://example.com/canonical');

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        ob_start();
        $seo->output_meta_tags();
        $output = ob_get_clean();

        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow"', $output);
        $this->assertStringContainsString('<link rel="canonical" href="https://example.com/canonical" />', $output);
    }
}


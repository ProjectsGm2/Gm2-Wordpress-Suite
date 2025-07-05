<?php
class SitemapTest extends WP_UnitTestCase {
    public function test_generate_sitemap_creates_file() {
        $post_id = self::factory()->post->create();
        $sitemap = new Gm2_Sitemap();
        $sitemap->generate();
        $file = ABSPATH . 'sitemap.xml';
        $this->assertFileExists($file);
        $content = file_get_contents($file);
        $this->assertStringContainsString('<urlset', $content);
        $this->assertStringContainsString(get_permalink($post_id), $content);
    }

    public function test_product_and_category_in_sitemap() {
        register_post_type('product');
        register_taxonomy('product_cat', 'product');

        $term_id = self::factory()->term->create([
            'taxonomy' => 'product_cat',
            'name'     => 'Category One',
        ]);

        $post_id = self::factory()->post->create([
            'post_type' => 'product',
            'post_title' => 'Product',
            'post_status' => 'publish',
        ]);

        wp_set_object_terms($post_id, [$term_id], 'product_cat');

        $sitemap = new Gm2_Sitemap();
        $sitemap->generate();
        $content = file_get_contents(ABSPATH . 'sitemap.xml');

        $this->assertStringContainsString(get_permalink($post_id), $content);
        $this->assertStringContainsString(get_term_link($term_id, 'product_cat'), $content);
    }
}


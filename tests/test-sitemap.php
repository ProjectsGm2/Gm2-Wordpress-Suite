<?php
use Gm2\Gm2_Sitemap;
class SitemapTest extends WP_UnitTestCase {
    public function tearDown(): void {
        $file = get_option('gm2_sitemap_path', ABSPATH . 'sitemap.xml');
        if (file_exists($file)) {
            unlink($file);
        }
        $base = basename($file, '.xml');
        $dir  = trailingslashit(dirname($file));
        foreach (glob($dir . $base . '-*.xml') as $part) {
            unlink($part);
        }
        delete_option('gm2_sitemap_path');
        parent::tearDown();
    }
    public function test_generate_sitemap_creates_file() {
        $post_id = self::factory()->post->create();
        $sitemap = new Gm2_Sitemap();
        $sitemap->generate();
        $file = ABSPATH . 'sitemap.xml';
        $this->assertFileExists($file);
        $content = file_get_contents($file);
        $this->assertStringContainsString('<sitemapindex', $content);

        $part = ABSPATH . 'sitemap-1.xml';
        $this->assertFileExists($part);
        $pcontent = file_get_contents($part);
        $this->assertStringContainsString(get_permalink($post_id), $pcontent);
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
        $part = ABSPATH . 'sitemap-1.xml';
        $content = file_get_contents($part);

        $this->assertStringContainsString(get_permalink($post_id), $content);
        $this->assertStringContainsString(get_term_link($term_id, 'product_cat'), $content);
    }

    public function test_custom_post_type_in_sitemap() {
        register_post_type('book');
        $post_id = self::factory()->post->create([
            'post_type'   => 'book',
            'post_title'  => 'Book Title',
            'post_status' => 'publish',
        ]);

        $sitemap = new Gm2_Sitemap();
        $sitemap->generate();

        $content = file_get_contents(ABSPATH . 'sitemap-1.xml');
        $this->assertStringContainsString(get_permalink($post_id), $content);
    }

    public function test_generate_sitemap_pings_search_engines() {
        $urls = [];
        $filter = function($pre, $args, $url) use (&$urls) {
            $urls[] = $url;
            return [ 'response' => ['code' => 200], 'body' => 'ok' ];
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $sitemap = new Gm2_Sitemap();
        $sitemap->generate();

        remove_filter('pre_http_request', $filter, 10);

        $sitemap_url = home_url('/sitemap.xml');
        $google = 'https://www.google.com/ping?sitemap=' . rawurlencode($sitemap_url);
        $bing   = 'https://www.bing.com/ping?sitemap=' . rawurlencode($sitemap_url);

        $this->assertContains($google, $urls);
        $this->assertContains($bing, $urls);
    }

    public function test_custom_sitemap_path_option() {
        $custom = ABSPATH . 'custom-sitemap.xml';
        update_option('gm2_sitemap_path', $custom);
        $sitemap = new Gm2_Sitemap();
        $sitemap->generate();
        $this->assertFileExists($custom);
        $this->assertFileExists(ABSPATH . 'custom-sitemap-1.xml');
    }
}


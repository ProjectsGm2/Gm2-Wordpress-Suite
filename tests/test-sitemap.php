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
}


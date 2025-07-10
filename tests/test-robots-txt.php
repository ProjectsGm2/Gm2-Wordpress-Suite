<?php
use Gm2\Gm2_SEO_Public;
class RobotsTxtTest extends WP_UnitTestCase {
    public function tearDown(): void {
        delete_option('gm2_robots_txt');
        parent::tearDown();
    }
    public function test_custom_rules_output_in_filter() {
        update_option('gm2_robots_txt', "User-agent: *\nDisallow: /secret");
        $seo = new Gm2_SEO_Public();
        $seo->run();
        $output = apply_filters('robots_txt', '', true);
        $this->assertStringContainsString('Disallow: /secret', $output);
    }
}

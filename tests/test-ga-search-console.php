<?php
use Gm2\Gm2_SEO_Public;
class GaAndSearchConsoleTest extends WP_UnitTestCase {
    private $seo;
    public function setUp(): void {
        parent::setUp();
        remove_all_actions('wp_head');
        $this->seo = new Gm2_SEO_Public();
    }
    public function tearDown(): void {
        remove_all_actions('wp_head');
        delete_option('gm2_ga_measurement_id');
        delete_option('gm2_search_console_verification');
        parent::tearDown();
    }
    public function test_ga_tracking_code_added_to_wp_head() {
        update_option('gm2_ga_measurement_id', 'G-123456');
        $this->seo->run();
        ob_start();
        do_action('wp_head');
        $output = ob_get_clean();
        $this->assertStringContainsString('https://www.googletagmanager.com/gtag/js?id=G-123456', $output);
    }
    public function test_search_console_meta_added_to_wp_head() {
        update_option('gm2_search_console_verification', 'verify-me');
        $this->seo->run();
        ob_start();
        do_action('wp_head');
        $output = ob_get_clean();
        $this->assertStringContainsString('<meta name="google-site-verification" content="verify-me"', $output);
    }
}


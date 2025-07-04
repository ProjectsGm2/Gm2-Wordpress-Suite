<?php
class BreadcrumbsTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        remove_all_actions('wp_footer');
    }

    public function test_footer_breadcrumbs_disabled() {
        update_option('gm2_show_footer_breadcrumbs', '0');
        $seo = new Gm2_SEO_Public();
        $seo->run();
        $this->assertFalse( has_action('wp_footer', [$seo, 'output_breadcrumbs']) );
    }

    public function test_footer_breadcrumbs_enabled() {
        update_option('gm2_show_footer_breadcrumbs', '1');
        $seo = new Gm2_SEO_Public();
        $seo->run();
        $this->assertNotFalse( has_action('wp_footer', [$seo, 'output_breadcrumbs']) );
    }
}

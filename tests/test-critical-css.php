<?php
class CriticalCssTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        remove_all_actions('wp_head');
        add_action('wp_head', 'wp_print_styles', 8);
        AE_SEO_Render_Optimizer::update_option(AE_SEO_Critical_CSS::OPTION_ENABLE, '1');
    }

    public function tearDown(): void {
        remove_all_actions('wp_head');
        AE_SEO_Render_Optimizer::update_option(AE_SEO_Critical_CSS::OPTION_CSS_MAP, []);
        parent::tearDown();
    }

    public function test_critical_css_precedes_stylesheet_links() {
        self::go_to('/');
        AE_SEO_Render_Optimizer::update_option(
            AE_SEO_Critical_CSS::OPTION_CSS_MAP,
            [ 'home' => '.foo{color:red;}' ]
        );

        $critical = new AE_SEO_Critical_CSS();
        $critical->setup();

        wp_enqueue_style('dummy', 'https://example.com/dummy.css');

        ob_start();
        do_action('wp_head');
        $output = ob_get_clean();

        $style_pos = strpos($output, '<style id="ae-seo-critical-css">');
        $link_pos  = strpos($output, '<link rel=\'stylesheet\'');
        $this->assertIsInt($style_pos);
        $this->assertIsInt($link_pos);
        $this->assertTrue($style_pos < $link_pos, 'Critical CSS block should appear before stylesheet links.');
    }
}

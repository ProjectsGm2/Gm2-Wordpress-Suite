<?php

class CriticalCssTest extends WP_UnitTestCase {
    private $original_theme;

    protected function setUp(): void {
        parent::setUp();
        $this->original_theme = get_stylesheet();
        remove_all_actions('wp_head');
        add_action('wp_head', 'wp_print_styles', 8);
        AE_SEO_Render_Optimizer::update_option(AE_SEO_Critical_CSS::OPTION_CSS_MAP, []);
    }

    protected function tearDown(): void {
        switch_theme($this->original_theme);
        remove_all_actions('wp_head');
        AE_SEO_Render_Optimizer::update_option(AE_SEO_Critical_CSS::OPTION_ENABLE, '0');
        AE_SEO_Render_Optimizer::update_option(AE_SEO_Critical_CSS::OPTION_CSS_MAP, []);
        parent::tearDown();
    }

    public function themeProvider(): array {
        return [
            ['twentytwentyfour'],
            ['hello-elementor'],
        ];
    }

    private function maybe_install_theme(string $slug): void {
        $theme = wp_get_theme($slug);
        if ($theme->exists()) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        $url = sprintf('https://downloads.wordpress.org/theme/%s.zip', $slug);
        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            $this->markTestSkipped('Unable to download ' . $slug . ' theme');
        }
        $result = unzip_file($tmp, WP_CONTENT_DIR . '/themes');
        @unlink($tmp);
        if (is_wp_error($result)) {
            $this->markTestSkipped('Unable to install ' . $slug . ' theme');
        }
        wp_clean_themes_cache();
    }

    /**
     * @dataProvider themeProvider
     */
    public function test_inline_style_prints_when_enabled(string $theme): void {
        $this->maybe_install_theme($theme);
        switch_theme($theme);

        AE_SEO_Render_Optimizer::update_option(AE_SEO_Critical_CSS::OPTION_ENABLE, '1');
        AE_SEO_Render_Optimizer::update_option(
            AE_SEO_Critical_CSS::OPTION_CSS_MAP,
            [ 'home' => '.foo{color:red;}' ]
        );

        self::go_to('/');
        $critical = new AE_SEO_Critical_CSS();
        $critical->setup();

        ob_start();
        do_action('wp_head');
        $output = ob_get_clean();

        $this->assertStringContainsString('<style id="ae-seo-critical-css">.foo{color:red;}</style>', $output);
    }

    /**
     * @dataProvider themeProvider
     */
    public function test_link_tags_convert_to_async_pattern_without_map_entry(string $theme): void {
        $this->maybe_install_theme($theme);
        switch_theme($theme);

        AE_SEO_Render_Optimizer::update_option(AE_SEO_Critical_CSS::OPTION_ENABLE, '1');
        AE_SEO_Render_Optimizer::update_option(AE_SEO_Critical_CSS::OPTION_ASYNC_METHOD, 'preload_onload');

        $critical = new AE_SEO_Critical_CSS();
        $critical->setup();

        wp_enqueue_style('dummy', 'https://example.com/style.css');

        ob_start();
        wp_print_styles();
        $html = ob_get_clean();

        $this->assertStringContainsString('<noscript><link rel="stylesheet" href="https://example.com/style.css"></noscript>', $html);
        $this->assertStringContainsString('rel="preload"', $html);
    }

    /**
     * @dataProvider themeProvider
     */
    public function test_disabled_state_restores_original_tags(string $theme): void {
        $this->maybe_install_theme($theme);
        switch_theme($theme);

        AE_SEO_Render_Optimizer::update_option(AE_SEO_Critical_CSS::OPTION_ENABLE, '0');
        wp_enqueue_style('dummy', 'https://example.com/style.css');

        ob_start();
        wp_print_styles();
        $html = ob_get_clean();

        $this->assertStringContainsString("<link rel='stylesheet' id='dummy-css'", $html);
        $this->assertStringNotContainsString('<noscript>', $html);
    }
}


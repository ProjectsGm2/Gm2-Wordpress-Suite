<?php
use Gm2\Font_Performance\Font_Performance;

class FontPreloadLinksTest extends WP_UnitTestCase {
    protected function setUp(): void {
        parent::setUp();

        remove_all_actions('wp_head');

        $ref  = new ReflectionClass(Font_Performance::class);
        $prop = $ref->getProperty('hooks_added');
        $prop->setAccessible(true);
        $prop->setValue(null, false);
        $prop = $ref->getProperty('options');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    public function test_preloads_only_valid_woff2_fonts(): void {
        update_option('gm2seo_fonts', [
            'enabled'             => true,
            'inject_display_swap' => false,
            'google_url_rewrite'  => false,
            'preconnect'          => [],
            'preload'             => [
                'https://example.com/a.woff2',
                'https://example.com/a.woff2',
                'https://example.com/b.woff2?ver=1',
                'https://example.com/c.woff2',
                'https://example.com/d.woff2',
                'https://example.com/style.css',
                'https://example.com/e.woff',
                'not-a-url',
            ],
            'self_host'           => false,
            'families'            => [],
            'limit_variants'      => false,
            'system_fallback_css' => false,
            'cache_headers'       => false,
        ]);
        Font_Performance::bootstrap();

        ob_start();
        do_action('wp_head');
        $html = ob_get_clean();

        $this->assertSame(3, substr_count($html, 'rel="preload"'));
        $this->assertStringContainsString('rel="preload" as="font" type="font/woff2" href="https://example.com/a.woff2" crossorigin', $html);
        $this->assertStringContainsString('href="https://example.com/b.woff2?ver=1"', $html);
        $this->assertStringContainsString('href="https://example.com/c.woff2"', $html);
        $this->assertStringNotContainsString('style.css', $html);
        $this->assertStringNotContainsString('d.woff2', $html);
        $this->assertStringNotContainsString('.woff"', $html);
    }
}

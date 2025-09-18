<?php
use Gm2\Font_Performance\Font_Performance;
use Gm2\Font_Performance\Font_CSS_Util;

class FontCssUtilTest extends WP_UnitTestCase {
    protected function setUp(): void {
        parent::setUp();

        $ref = new ReflectionClass(Font_Performance::class);
        foreach (['hooks_added' => false, 'options' => []] as $prop_name => $value) {
            $prop = $ref->getProperty($prop_name);
            $prop->setAccessible(true);
            $prop->setValue($value);
        }

        update_option('gm2seo_fonts', [
            'enabled'             => true,
            'cache_headers'       => true,
            'limit_variants'      => true,
            'variant_suggestions' => ['400 normal'],
        ]);

        Font_Performance::bootstrap();
    }

    public function test_process_font_faces(): void {
        $uploads = wp_upload_dir();
        $base    = trailingslashit($uploads['baseurl']) . 'gm2seo-fonts';
        $css = "@font-face{font-family:'Foo';src:url('{$base}/foo/foo.woff2');font-weight:400;}" .
               "@font-face{font-family:'Foo';src:url('{$base}/foo/foo-bold.woff2');font-weight:700;}";
        $out = Font_CSS_Util::process($css);

        $this->assertStringContainsString('font-display:swap', $out);
        $this->assertStringContainsString('gm2seo/v1/font?', $out);
        $this->assertStringContainsString('file=' . rawurlencode('foo/foo.woff2'), $out);
        $this->assertStringContainsString('token=', $out);
        $this->assertStringNotContainsString('foo-bold.woff2', $out);
    }
}

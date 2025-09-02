<?php
use Gm2\Gm2_SEO_Public;
class MinifyTest extends WP_UnitTestCase {
    private $seo;

    public function setUp(): void {
        parent::setUp();
        $this->seo = new Gm2_SEO_Public();
    }

    public function tearDown(): void {
        while (ob_get_level()) { ob_end_clean(); }
        delete_option('gm2_minify_html');
        delete_option('gm2_minify_css');
        delete_option('gm2_minify_js');
        parent::tearDown();
    }

    public function test_maybe_buffer_output_starts_buffer_when_enabled() {
        update_option('gm2_minify_html', '1');
        $this->seo->maybe_buffer_output();
        $this->assertGreaterThan(0, ob_get_level());
    }

    public function test_maybe_buffer_output_does_nothing_when_disabled() {
        update_option('gm2_minify_html', '0');
        $this->seo->maybe_buffer_output();
        $this->assertSame(0, ob_get_level());
    }

    public function test_minify_output_respects_html_option() {
        $html = "<div>  A  </div>";
        update_option('gm2_minify_html', '1');
        $result = $this->seo->minify_output($html);
        $this->assertLessThanOrEqual(strlen($html), strlen($result));
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($result);
        $this->assertSame('A', trim($dom->getElementsByTagName('div')->item(0)->textContent));
    }

    public function test_minify_output_skips_when_disabled() {
        $html = "<div>  A  </div>";
        update_option('gm2_minify_html', '0');
        $result = $this->seo->minify_output($html);
        $this->assertSame($html, $result);
    }

    public function test_minify_output_respects_css_option() {
        $html = '<style>\n  body { color: red; }\n</style>';
        update_option('gm2_minify_css', '1');
        $result = $this->seo->minify_output($html);
        $this->assertLessThanOrEqual(strlen($html), strlen($result));
        preg_match('#<style>(.*)</style>#', $result, $m);
        $css = $m[1];
        $this->assertSame(substr_count($css, '{'), substr_count($css, '}'));
    }

    public function test_minify_output_respects_js_option() {
        $html = '<script>\n  var x = 1;\n</script>';
        update_option('gm2_minify_js', '1');
        $result = $this->seo->minify_output($html);
        $this->assertLessThanOrEqual(strlen($html), strlen($result));
        preg_match('#<script>(.*)</script>#', $result, $m);
        $js = $m[1];
        $cmd = 'node -e ' . escapeshellarg($js);
        exec($cmd, $out, $code);
        $this->assertSame(0, $code);
    }
}

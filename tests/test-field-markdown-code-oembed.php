<?php

class FieldMarkdownCodeOembedTest extends WP_UnitTestCase {
    public function test_markdown_rendering() {
        $html = gm2_render_markdown('Hello **World**');
        $this->assertStringContainsString('<strong>World</strong>', $html);
        $this->assertStringContainsString('<p>Hello', $html);
    }

    public function test_code_rendering_with_language() {
        $html = gm2_render_code('<?php echo 1; ?>', 'php');
        $this->assertSame('<pre><code class="language-php">&lt;?php echo 1; ?&gt;</code></pre>', $html);
    }

    public function test_oembed_rendering() {
        $url = 'https://example.com/embed';
        add_filter('pre_oembed_result', function($pre, $url_param) use ($url) {
            if ($url_param === $url) {
                return '<iframe src="' . esc_url($url) . '"></iframe>';
            }
            return $pre;
        }, 10, 2);
        $this->assertSame('<iframe src="' . esc_url($url) . '"></iframe>', gm2_render_oembed($url));
        remove_all_filters('pre_oembed_result');
    }
}

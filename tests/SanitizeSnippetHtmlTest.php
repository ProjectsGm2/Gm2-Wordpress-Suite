<?php
use Gm2\Gm2_SEO_Admin;

class SanitizeSnippetHtmlTest extends WP_UnitTestCase {
    public function test_style_tags_removed() {
        $admin = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'sanitize_snippet_html');
        $method->setAccessible(true);

        $html = '<p>Text</p><style>.foo{color:red;}</style>';
        $sanitized = $method->invoke($admin, $html);

        $this->assertStringContainsString('<p>Text</p>', $sanitized);
        $this->assertStringNotContainsString('color:red', $sanitized);
        $this->assertStringNotContainsString('<style', $sanitized);
    }

    public function test_nbsp_and_spaces_removed() {
        $admin = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'sanitize_snippet_html');
        $method->setAccessible(true);

        $html = '<p>Foo&nbsp;&nbsp; Bar&nbsp; &nbsp;Baz</p>';
        $sanitized = $method->invoke($admin, $html);

        $this->assertSame('<p>Foo Bar Baz</p>', $sanitized);
    }

    public function test_get_rendered_html_returns_clean_string() {
        $post_id = self::factory()->post->create([
            'post_title'   => 'Clean',
            'post_content' => '<p>Foo&nbsp; &nbsp;Bar</p>'
        ]);

        $admin = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'get_rendered_html');
        $method->setAccessible(true);

        $html = $method->invoke($admin, $post_id, 0, null);

        $this->assertIsString($html);
        $this->assertStringNotContainsString('&nbsp;', $html);
        $this->assertStringNotContainsString("\xc2\xa0", $html);
        $this->assertSame(0, preg_match('/ {2,}/', $html));
    }
}

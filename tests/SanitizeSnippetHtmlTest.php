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
}

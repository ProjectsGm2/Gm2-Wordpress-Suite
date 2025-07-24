<?php
use Gm2\Gm2_SEO_Admin;

class BuilderMarkupTest extends WP_UnitTestCase {
    public function test_builder_markup_is_sanitized() {
        $content = '<div class="builder"><div><h2>Title</h2><p>Foo&nbsp;  <strong>Bar</strong>   </p><span>Ignore</span></div><script>alert("x")</script></div>';
        $post_id = self::factory()->post->create([
            'post_title'   => 'Builder',
            'post_content' => $content,
        ]);

        $admin = new Gm2_SEO_Admin();
        $method = new ReflectionMethod(Gm2_SEO_Admin::class, 'get_rendered_html');
        $method->setAccessible(true);
        $html = $method->invoke($admin, $post_id, 0, null);

        // Allowed tags should remain.
        $this->assertStringContainsString('<h2>Title</h2>', $html);
        $this->assertStringContainsString('<p>Foo <strong>Bar</strong></p>', $html);
        // Builder wrapper tags should be removed.
        $this->assertStringNotContainsString('<div', $html);
        $this->assertStringNotContainsString('<span', $html);
        $this->assertStringNotContainsString('<script', $html);
        // Spacing should be normalized.
        $this->assertStringNotContainsString('&nbsp;', $html);
        $this->assertSame(0, preg_match('/ {2,}/', $html));
    }
}

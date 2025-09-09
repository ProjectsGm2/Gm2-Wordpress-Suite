<?php
use Gm2\Font_Performance\Font_Performance;

class FontVariantLimitingSmokeTest extends WP_UnitTestCase {
    protected function setUp(): void {
        parent::setUp();

        // Reset static properties.
        $ref = new ReflectionClass(Font_Performance::class);
        foreach (['hooks_added' => false, 'options' => []] as $prop => $val) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue(null, $val);
        }

        // Clean up uploads dir before running.
        $uploads = wp_upload_dir();
        $dir     = trailingslashit($uploads['basedir']) . 'gm2seo-fonts';
        if (is_dir($dir)) {
            $this->rrmdir($dir);
        }

        update_option('gm2seo_fonts', [
            'variant_suggestions' => ['400 normal', '700 italic'],
            'limit_variants'      => true,
        ]);
    }

    /** Recursively remove a directory. */
    private function rrmdir(string $dir): void {
        $items = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function test_variant_limiting_smoke(): void {
        // Create administrator and nonce.
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        $_REQUEST['_wpnonce_gm2_self_host_fonts'] = wp_create_nonce('gm2_self_host_fonts');

        // Enqueue a Google Fonts stylesheet.
        wp_enqueue_style('google-font', 'https://fonts.googleapis.com/css?family=Foo');

        // Mock remote requests.
        $css_url      = 'https://fonts.googleapis.com/css?family=Foo';
        $font_400n    = 'https://example.com/foo-400-normal.woff2';
        $font_400i    = 'https://example.com/foo-400-italic.woff2';
        $font_700i    = 'https://example.com/foo-700-italic.woff2';
        add_filter('pre_http_request', function ($response, $args, $url) use ($css_url, $font_400n, $font_400i, $font_700i) {
            if ($url === $css_url) {
                $css = "@font-face{font-family:'Foo';font-weight:400;font-style:normal;src:url($font_400n) format('woff2');}"
                     ."@font-face{font-family:'Foo';font-weight:400;font-style:italic;src:url($font_400i) format('woff2');}"
                     ."@font-face{font-family:'Foo';font-weight:700;font-style:italic;src:url($font_700i) format('woff2');}";
                return ['body' => $css];
            }
            if ($url === $font_400n) {
                return ['body' => str_repeat('A', 10)];
            }
            if ($url === $font_700i) {
                return ['body' => str_repeat('B', 20)];
            }
            if ($url === $font_400i) {
                return ['body' => str_repeat('C', 15)];
            }
            return $response;
        }, 10, 3);

        // Prevent redirect exit.
        add_filter('wp_redirect', function ($location) {
            throw new Exception($location);
        });

        try {
            Font_Performance::self_host_fonts();
        } catch (Exception $e) {
            // Expected redirect.
        }

        $uploads = wp_upload_dir();
        $base    = trailingslashit($uploads['basedir']) . 'gm2seo-fonts/';

        $this->assertFileExists($base . 'fonts-local.css');
        $css_out = file_get_contents($base . 'fonts-local.css');
        $min     = preg_replace('/\s+/', '', $css_out);
        $this->assertStringContainsString('font-weight:400', $min);
        $this->assertStringContainsString('font-style:normal', $min);
        $this->assertStringContainsString('font-weight:700', $min);
        $this->assertStringContainsString('font-style:italic', $min);
        $this->assertStringNotContainsString('font-weight:400;font-style:italic', $min);

        $this->assertFileExists($base . 'foo/foo-400-normal.woff2');
        $this->assertFileExists($base . 'foo/foo-700-italic.woff2');
        $this->assertFileDoesNotExist($base . 'foo/foo-400-italic.woff2');

        $savings = Font_Performance::compute_variant_savings(['400 normal']);
        $this->assertSame(30, $savings['total']);
        $this->assertSame(10, $savings['allowed']);
        $this->assertSame(20, $savings['reduction']);
    }
}

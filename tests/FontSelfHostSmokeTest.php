<?php
use Gm2\Font_Performance\Font_Performance;

class FontSelfHostSmokeTest extends WP_UnitTestCase {
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

    public function test_self_host_smoke(): void {
        // Create administrator and nonce.
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);
        $_REQUEST['_wpnonce_gm2_self_host_fonts'] = wp_create_nonce('gm2_self_host_fonts');

        // Enqueue a Google Fonts stylesheet.
        wp_enqueue_style('google-font', 'https://fonts.googleapis.com/css?family=Foo');

        // Mock remote requests.
        $css_url  = 'https://fonts.googleapis.com/css?family=Foo';
        $font_url = 'https://example.com/foo.woff2';
        add_filter('pre_http_request', function ($response, $args, $url) use ($css_url, $font_url) {
            if ($url === $css_url) {
                return ['body' => "@font-face{font-family:'Foo';src:url($font_url) format('woff2');}"];
            }
            if ($url === $font_url) {
                return ['body' => 'FONTDATA'];
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

        $this->assertFileExists($base . 'foo/foo.woff2');
        $this->assertFileExists($base . 'fonts-local.css');
        $this->assertTrue(wp_style_is('gm2seo-fonts-local', 'enqueued'));
        $this->assertFalse(wp_style_is('google-font', 'registered'));
    }
}

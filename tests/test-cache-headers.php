<?php
use Gm2\Gm2_Cache_Headers_Apache;

class CacheHeadersTest extends WP_UnitTestCase {
    private $file;

    public function setUp(): void {
        parent::setUp();
        $this->file = ABSPATH . '.htaccess';
        if (file_exists($this->file)) {
            unlink($this->file);
        }
        $_SERVER['SERVER_SOFTWARE'] = 'Apache';
    }

    public function tearDown(): void {
        if (file_exists($this->file)) {
            unlink($this->file);
        }
        parent::tearDown();
    }

    public function test_write_twice_and_flush() {
        Gm2_Cache_Headers_Apache::write_rules();
        Gm2_Cache_Headers_Apache::write_rules();
        $contents = file_get_contents($this->file);
        $this->assertSame(2, substr_count($contents, Gm2_Cache_Headers_Apache::MARKER));
        update_option('permalink_structure', '/%postname%/');
        flush_rewrite_rules();
        $contents = file_get_contents($this->file);
        $this->assertSame(2, substr_count($contents, Gm2_Cache_Headers_Apache::MARKER));
    }

    public function test_remove_rules_only_clears_own_block() {
        file_put_contents($this->file, "# BEGIN WordPress\n# END WordPress\n");
        Gm2_Cache_Headers_Apache::write_rules();
        Gm2_Cache_Headers_Apache::remove_rules();
        $contents = file_get_contents($this->file);
        $this->assertStringContainsString('WordPress', $contents);
        $this->assertStringNotContainsString(Gm2_Cache_Headers_Apache::MARKER, $contents);
    }

    public function test_cdn_detection_returns_already_handled() {
        add_filter('pre_http_request', function($pre, $args, $url) {
            return [
                'headers' => [ 'cache-control' => 'max-age=100' ],
                'body' => '',
                'response' => [ 'code' => 200, 'message' => 'OK' ],
            ];
        }, 10, 3);
        $result = Gm2_Cache_Headers_Apache::maybe_apply();
        remove_all_filters('pre_http_request');
        $this->assertIsArray($result);
        $this->assertSame('already_handled', $result['status']);
    }
}

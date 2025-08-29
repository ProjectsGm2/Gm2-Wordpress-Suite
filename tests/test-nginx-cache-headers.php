<?php
use Gm2\Gm2_Cache_Headers_Nginx;

class NginxCacheHeadersTest extends WP_UnitTestCase {
    private $file;

    public function setUp(): void {
        parent::setUp();
        $_SERVER['SERVER_SOFTWARE'] = 'nginx';
        $this->file = Gm2_Cache_Headers_Nginx::get_file_path();
        if (file_exists($this->file)) {
            unlink($this->file);
        }
    }

    public function tearDown(): void {
        if (file_exists($this->file)) {
            unlink($this->file);
        }
        parent::tearDown();
    }

    public function test_write_twice_overwrites() {
        Gm2_Cache_Headers_Nginx::write_rules();
        Gm2_Cache_Headers_Nginx::write_rules();
        $contents = file_get_contents($this->file);
        $this->assertSame(Gm2_Cache_Headers_Nginx::$rules, $contents);
    }

    public function test_verify_confirms_headers() {
        add_filter('pre_http_request', function($pre, $args, $url) {
            return [
                'headers' => [ 'cache-control' => 'public, max-age=31536000, immutable' ],
                'body' => '',
                'response' => [ 'code' => 200, 'message' => 'OK' ],
            ];
        }, 10, 3);
        $this->assertTrue(Gm2_Cache_Headers_Nginx::verify());
        remove_all_filters('pre_http_request');
    }
}

<?php
use Gm2\Gm2_Remote_Mirror;

class RemoteMirrorTest extends WP_UnitTestCase {
    private $mirror;

    public function setUp(): void {
        parent::setUp();
        add_filter('pre_http_request', [$this, 'mock_http'], 10, 3);
        update_option('gm2_remote_mirror_vendors', ['facebook' => 1]);
        $this->mirror = Gm2_Remote_Mirror::init();
    }

    public function tearDown(): void {
        remove_filter('pre_http_request', [$this, 'mock_http'], 10);
        parent::tearDown();
    }

    public function mock_http($pre, $args, $url) {
        if (str_contains($url, 'connect.facebook.net')) {
            return [
                'headers'  => [],
                'body'     => 'console.log("fb");',
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies'  => [],
            ];
        }
        return $pre;
    }

    public function test_rewrite_enqueued_script() {
        $src       = 'https://connect.facebook.net/en_US/fbevents.js';
        $rewritten = apply_filters('script_loader_src', $src, 'fb');
        $expected  = $this->mirror->get_local_url('facebook', 'fbevents.js');
        $this->assertSame($expected, $rewritten);
    }

    public function test_replace_hardcoded_script() {
        ob_start();
        $this->mirror->start_buffer();
        echo '<script src="https://connect.facebook.net/en_US/fbevents.js"></script>';
        $this->mirror->end_buffer();
        $output = ob_get_clean();
        $this->assertStringContainsString(
            $this->mirror->get_local_url('facebook', 'fbevents.js'),
            $output
        );
    }
}

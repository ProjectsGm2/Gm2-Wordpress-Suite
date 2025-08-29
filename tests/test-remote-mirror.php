<?php
use Gm2\Gm2_Remote_Mirror;

class RemoteMirrorTest extends WP_UnitTestCase {
    private $mirror;
    private string $body;
    private string $filename = 'fbevents.js';

    public function setUp(): void {
        parent::setUp();
        $this->body = 'console.log("fb1");';
        add_filter('pre_http_request', [$this, 'mock_http'], 10, 3);
        update_option('gm2_remote_mirror_vendors', ['facebook' => 1]);
        wp_clear_scheduled_hook('gm2_remote_mirror_refresh');
        $this->mirror = Gm2_Remote_Mirror::init();
    }

    public function tearDown(): void {
        remove_filter('pre_http_request', [$this, 'mock_http'], 10);
        $path = $this->mirror->get_local_path('facebook', $this->filename);
        if (file_exists($path)) {
            unlink($path);
            @rmdir(dirname($path));
        }
        wp_clear_scheduled_hook('gm2_remote_mirror_refresh');
        parent::tearDown();
    }

    public function mock_http($pre, $args, $url) {
        if (str_contains($url, 'connect.facebook.net')) {
            return [
                'headers'  => ['content-type' => 'application/javascript'],
                'body'     => $this->body,
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

    public function test_serves_cached_script() {
        $remote   = 'https://connect.facebook.net/en_US/fbevents.js';
        $path     = $this->mirror->get_local_path('facebook', $this->filename);
        $this->assertFileDoesNotExist($path);
        $rewritten = apply_filters('script_loader_src', $remote, 'fb');
        $this->assertSame($this->mirror->get_local_url('facebook', $this->filename), $rewritten);
        $this->assertFileExists($path);
        $this->assertStringContainsString('fb1', file_get_contents($path));
    }

    public function test_cron_refresh_updates_cache() {
        $remote = 'https://connect.facebook.net/en_US/fbevents.js';
        $path   = $this->mirror->get_local_path('facebook', $this->filename);
        apply_filters('script_loader_src', $remote, 'fb');
        $this->assertFileExists($path);
        $original = file_get_contents($path);

        $this->body = 'console.log("fb2");';
        do_action('gm2_remote_mirror_refresh');
        $this->assertFileExists($path);
        $this->assertNotSame($original, file_get_contents($path));
        $this->assertStringContainsString('fb2', file_get_contents($path));
    }

    public function test_disabling_vendor_restores_remote_url() {
        $remote    = 'https://connect.facebook.net/en_US/fbevents.js';
        $rewritten = apply_filters('script_loader_src', $remote, 'fb');
        $this->assertSame($this->mirror->get_local_url('facebook', $this->filename), $rewritten);
        update_option('gm2_remote_mirror_vendors', []);
        $rewritten = apply_filters('script_loader_src', $remote, 'fb');
        $this->assertSame($remote, $rewritten);
    }
}

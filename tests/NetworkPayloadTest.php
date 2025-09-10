<?php
use Gm2\NetworkPayload\Module;

class NetworkPayloadTest extends WP_UnitTestCase {
    private int $admin_id;

    public function setUp(): void {
        parent::setUp();
        $this->admin_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($this->admin_id);
        Module::activate(false);
        Module::boot();
        do_action('rest_api_init');
    }

    public function tearDown(): void {
        delete_option('gm2_netpayload_settings');
        delete_option('gm2_netpayload_stats');
        parent::tearDown();
    }

    public function test_default_settings_created() {
        $opts = get_option('gm2_netpayload_settings');
        $this->assertIsArray($opts);
        $this->assertTrue($opts['nextgen_images']);
        $this->assertTrue($opts['webp']);
        $this->assertTrue($opts['avif']);
        $this->assertFalse($opts['no_originals']);
        $this->assertEquals(2560, $opts['big_image_cap']);
        $this->assertSame('detect', $opts['gzip_detection']);
        $this->assertTrue($opts['smart_lazyload']);
        $this->assertTrue($opts['asset_budget']);
        $this->assertEquals(1258291, $opts['asset_budget_limit']);
    }

    public function test_rest_endpoint_updates_average() {
        $request = new WP_REST_Request('POST', '/gm2/v1/netpayload');
        $request->set_param('payload', 100);
        $request->set_param('budget', 2000);
        rest_get_server()->dispatch($request);
        $stats = get_option('gm2_netpayload_stats');
        $this->assertEquals(100, $stats['average']);
        $this->assertEquals(2000, $stats['budget']);

        $request2 = new WP_REST_Request('POST', '/gm2/v1/netpayload');
        $request2->set_param('payload', 300);
        $request2->set_param('budget', 2000);
        rest_get_server()->dispatch($request2);
        $stats = get_option('gm2_netpayload_stats');
        $this->assertEquals(200, $stats['average']);
    }

    public function test_handle_auditor_records_assets() {
        $_SERVER['REQUEST_URI'] = '/test-page?foo=bar';
        do_action('init');
        wp_enqueue_script('jquery');
        wp_enqueue_style('admin-bar');
        do_action('wp_enqueue_scripts');
        do_action('wp_print_scripts');
        $url = home_url('/test-page');
        $key = 'gm2_np_' . md5($url);
        $data = get_transient($key);
        $this->assertIsArray($data);
        $this->assertEquals($url, $data['url']);
        $stats = get_option('gm2_netpayload_stats');
        $this->assertArrayHasKey('assets', $stats);
    }
}

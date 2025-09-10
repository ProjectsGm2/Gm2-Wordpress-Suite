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
    }

    public function test_rest_endpoint_updates_average() {
        $request = new WP_REST_Request('POST', '/gm2/v1/netpayload');
        $request->set_param('payload', 100);
        rest_get_server()->dispatch($request);
        $stats = get_option('gm2_netpayload_stats');
        $this->assertEquals(100, $stats['average']);

        $request2 = new WP_REST_Request('POST', '/gm2/v1/netpayload');
        $request2->set_param('payload', 300);
        rest_get_server()->dispatch($request2);
        $stats = get_option('gm2_netpayload_stats');
        $this->assertEquals(200, $stats['average']);
    }
}

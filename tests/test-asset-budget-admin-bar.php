<?php
use Gm2\NetworkPayload\Module;

class AssetBudgetAdminBarTest extends WP_UnitTestCase {
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
        @unlink(ABSPATH . 'big.js');
        @unlink(ABSPATH . 'big.css');
        parent::tearDown();
    }

    public function test_admin_bar_shows_warning_when_budget_exceeded() {
        // Force admin bar to show and simulate frontend.
        set_current_screen('front');
        show_admin_bar(true);

        // Set budget to 1 MB.
        $opts = Module::get_settings();
        $opts['asset_budget'] = true;
        $opts['asset_budget_limit'] = 1024 * 1024; // 1 MB in bytes.
        update_option('gm2_netpayload_settings', $opts);

        // Create large JS and CSS files totalling >1 MB.
        file_put_contents(ABSPATH . 'big.js', str_repeat('a', 800 * 1024));
        wp_register_script('big-js', home_url('/big.js'), [], null);
        wp_enqueue_script('big-js');

        file_put_contents(ABSPATH . 'big.css', str_repeat('a', 400 * 1024));
        wp_register_style('big-css', home_url('/big.css'), [], null);
        wp_enqueue_style('big-css');

        $_SERVER['REQUEST_URI'] = '/budget-test';
        do_action('init');
        do_action('wp_enqueue_scripts');
        do_action('wp_print_scripts');

        require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
        global $wp_admin_bar;
        $wp_admin_bar = new WP_Admin_Bar();
        do_action('admin_bar_menu', $wp_admin_bar);

        $node = $wp_admin_bar->get_node('gm2-asset-budget');
        $this->assertNotNull($node, 'Admin bar node missing');
        $this->assertStringContainsString('Asset payload', $node->title);
        $this->assertStringContainsString('exceeds limit', $node->title);
    }
}

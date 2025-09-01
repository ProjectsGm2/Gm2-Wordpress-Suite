<?php
use Gm2\Gm2_Cache_Audit_Admin;
use Gm2\Gm2_Cache_Audit;

class CacheAuditAdminAjaxFixTest extends WP_Ajax_UnitTestCase {
    private function setup_results($assets) {
        Gm2_Cache_Audit::save_results([
            'scanned_at' => '2024-01-01 00:00:00',
            'handles'    => ['scripts' => [], 'styles' => []],
            'assets'     => $assets,
        ]);
    }

    public function setUp(): void {
        parent::setUp();
        $this->_setRole('administrator');
        update_option('gm2_script_attributes', []);
        Gm2_Cache_Audit::clear_results();
        $admin = new Gm2_Cache_Audit_Admin();
        $admin->run();
    }

    public function test_ajax_fix_stores_hyphenated_handle() {
        $this->setup_results([
            [
                'url'            => 'https://cdn.example.com/jquery-core.js',
                'type'           => 'script',
                'issues'         => [],
                'needs_attention'=> true,
            ],
        ]);

        $_POST['nonce']      = wp_create_nonce('gm2_cache_audit_fix');
        $_POST['url']        = 'https://cdn.example.com/jquery-core.js';
        $_POST['asset_type'] = 'script';
        $_POST['handle']     = 'jquery-core';

        try { $this->_handleAjax('gm2_cache_audit_fix'); } catch (WPAjaxDieContinueException $e) {}

        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $attrs = get_option('gm2_script_attributes', []);
        $this->assertSame('defer', $attrs['jquery-core']);
        $results = Gm2_Cache_Audit::get_results();
        $this->assertSame('jquery-core', $results['assets'][0]['handle']);
        $this->assertFalse($results['assets'][0]['needs_attention']);
    }

    public function test_bulk_ajax_fix_updates_multiple_assets() {
        $this->setup_results([
            [
                'url'            => 'https://cdn.example.com/jquery-core.js',
                'type'           => 'script',
                'issues'         => [],
                'needs_attention'=> true,
            ],
            [
                'url'            => 'https://cdn.example.com/jquery-migrate.js',
                'type'           => 'script',
                'issues'         => [],
                'needs_attention'=> true,
            ],
        ]);

        // First asset
        $_POST = [
            'nonce'      => wp_create_nonce('gm2_cache_audit_fix'),
            'url'        => 'https://cdn.example.com/jquery-core.js',
            'asset_type' => 'script',
            'handle'     => 'jquery-core',
        ];
        try { $this->_handleAjax('gm2_cache_audit_fix'); } catch (WPAjaxDieContinueException $e) {}

        // Second asset
        $_POST = [
            'nonce'      => wp_create_nonce('gm2_cache_audit_fix'),
            'url'        => 'https://cdn.example.com/jquery-migrate.js',
            'asset_type' => 'script',
            'handle'     => 'jquery-migrate',
        ];
        try { $this->_handleAjax('gm2_cache_audit_fix'); } catch (WPAjaxDieContinueException $e) {}

        $attrs = get_option('gm2_script_attributes', []);
        $this->assertSame('defer', $attrs['jquery-core']);
        $this->assertSame('defer', $attrs['jquery-migrate']);
        $results = Gm2_Cache_Audit::get_results();
        $this->assertFalse($results['assets'][0]['needs_attention']);
        $this->assertFalse($results['assets'][1]['needs_attention']);
    }
}

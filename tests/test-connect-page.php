<?php
use Gm2\Gm2_SEO_Admin;
class GoogleConnectPageTest extends WP_UnitTestCase {
    public function tearDown(): void {
        delete_option('gm2_google_refresh_token');
        delete_option('gm2_ga_measurement_id');
        delete_option('gm2_gads_customer_id');
        remove_all_filters('gm2_google_oauth_instance');
        parent::tearDown();
    }

    public function test_connect_page_shows_login_when_no_token() {
        delete_option('gm2_google_refresh_token');
        add_filter('gm2_google_oauth_instance', function() {
            return new class {
                public function is_connected() { return false; }
                public function get_auth_url() { return 'https://accounts.google.com/mock'; }
                public function handle_callback($code) { return false; }
            };
        });
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_google_connect_page();
        $output = ob_get_clean();
        $this->assertStringContainsString('accounts.google.com', $output);
    }

    public function test_connect_page_callback_saves_token() {
        delete_option('gm2_google_refresh_token');
        $_GET['code'] = 'abc';
        add_filter('gm2_google_oauth_instance', function() {
            return new class {
                public function is_connected() { return false; }
                public function get_auth_url() { return ''; }
                public function handle_callback($code) {
                    update_option('gm2_google_refresh_token', 'saved');
                    return true;
                }
                public function list_analytics_properties() { return []; }
            };
        });
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_google_connect_page();
        ob_end_clean();
        $this->assertSame('saved', get_option('gm2_google_refresh_token'));
    }

    public function test_properties_list_saved_after_callback() {
        delete_option('gm2_google_refresh_token');
        delete_option('gm2_ga_measurement_id');
        $_GET['code'] = 'abc';
        add_filter('gm2_google_oauth_instance', function() {
            return new class {
                public function is_connected() { return true; }
                public function get_auth_url() { return ''; }
                public function handle_callback($code) { return true; }
                public function list_analytics_properties() { return ['G-1' => 'Site 1', 'G-2' => 'Site 2']; }
                public function list_ads_accounts() { return []; }
            };
        });
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_google_connect_page();
        $output = ob_get_clean();
        $this->assertSame('G-1', get_option('gm2_ga_measurement_id'));
        $this->assertStringContainsString('<select', $output);
        $this->assertStringContainsString('G-1', $output);
        $this->assertStringContainsString('G-2', $output);
    }

    public function test_ads_accounts_list_saved_after_callback() {
        delete_option('gm2_google_refresh_token');
        delete_option('gm2_gads_customer_id');
        $_GET['code'] = 'abc';
        add_filter('gm2_google_oauth_instance', function() {
            return new class {
                public function is_connected() { return true; }
                public function get_auth_url() { return ''; }
                public function handle_callback($code) { return true; }
                public function list_analytics_properties() { return []; }
                public function list_ads_accounts() { return ['123' => '123', '456' => '456']; }
            };
        });
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_google_connect_page();
        $output = ob_get_clean();
        $this->assertSame('123', get_option('gm2_gads_customer_id'));
        $this->assertStringContainsString('gm2_gads_account', $output);
        $this->assertStringContainsString('123', $output);
        $this->assertStringContainsString('456', $output);
    }

    public function test_existing_options_not_overwritten_on_callback() {
        update_option('gm2_ga_measurement_id', 'G-EXIST');
        update_option('gm2_gads_customer_id', '999');
        $_GET['code'] = 'abc';
        add_filter('gm2_google_oauth_instance', function() {
            return new class {
                public function is_connected() { return true; }
                public function get_auth_url() { return ''; }
                public function handle_callback($code) { return true; }
                public function list_analytics_properties() { return ['G-NEW' => 'New']; }
                public function list_ads_accounts() { return ['111' => '111']; }
            };
        });
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_google_connect_page();
        ob_end_clean();
        $this->assertSame('G-EXIST', get_option('gm2_ga_measurement_id'));
        $this->assertSame('999', get_option('gm2_gads_customer_id'));
    }

    public function test_notice_shown_when_no_properties() {
        delete_option('gm2_google_refresh_token');
        add_filter('gm2_google_oauth_instance', function() {
            return new class {
                public function is_connected() { return true; }
                public function get_auth_url() { return ''; }
                public function handle_callback($code) { return true; }
                public function list_analytics_properties() { return []; }
                public function list_ads_accounts() { return []; }
            };
        });
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_google_connect_page();
        $output = ob_get_clean();
        $this->assertStringContainsString('No Analytics properties found', $output);
    }

    public function test_notice_shown_when_no_ads_accounts() {
        delete_option('gm2_google_refresh_token');
        add_filter('gm2_google_oauth_instance', function() {
            return new class {
                public function is_connected() { return true; }
                public function get_auth_url() { return ''; }
                public function handle_callback($code) { return true; }
                public function list_analytics_properties() { return ['G-1' => 'Site 1']; }
                public function list_ads_accounts() { return []; }
            };
        });
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_google_connect_page();
        $output = ob_get_clean();
        $this->assertStringContainsString('No Ads accounts found', $output);
    }

    public function test_disconnect_form_removes_token() {
        update_option('gm2_google_refresh_token', 'tok');
        $_POST['gm2_google_disconnect'] = wp_create_nonce('gm2_google_disconnect');
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_google_connect_page();
        ob_end_clean();
        $this->assertSame('', get_option('gm2_google_refresh_token'));
    }
}

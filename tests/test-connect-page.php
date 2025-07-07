<?php
use Gm2\Gm2_SEO_Admin;
class GoogleConnectPageTest extends WP_UnitTestCase {
    public function tearDown(): void {
        delete_option('gm2_google_refresh_token');
        remove_all_filters('gm2_google_oauth_instance');
        parent::tearDown();
    }

    public function test_connect_page_shows_login_when_no_token() {
        delete_option('gm2_google_refresh_token');
        add_filter('gm2_google_oauth_instance', function() {
            return new class {
                public function is_connected() { return false; }
                public function get_auth_url() { return 'https://accounts.google.com/mock'; }
                public function handle_callback() { return false; }
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
                public function handle_callback() {
                    update_option('gm2_google_refresh_token', 'saved');
                    return true;
                }
            };
        });
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_google_connect_page();
        ob_end_clean();
        $this->assertSame('saved', get_option('gm2_google_refresh_token'));
    }
}

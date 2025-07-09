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
        $this->assertStringContainsString('enable the Analytics', $output);
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
        $this->assertStringContainsString('enable the Analytics', $output);
    }

    public function test_error_displayed_when_ads_developer_token_missing() {
        delete_option('gm2_google_refresh_token');
        add_filter('gm2_google_oauth_instance', function() {
            return new class {
                public function is_connected() { return true; }
                public function get_auth_url() { return ''; }
                public function handle_callback($code) { return true; }
                public function list_analytics_properties() { return []; }
                public function list_ads_accounts() {
                    return new WP_Error('missing_developer_token', 'A Google Ads developer token is required to list accounts.');
                }
            };
        });
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_google_connect_page();
        $output = ob_get_clean();
        $this->assertStringContainsString('developer token', $output);
        $this->assertStringContainsString('Tools → API Center', $output);
    }

    public function test_error_displayed_when_analytics_api_fails() {
        delete_option('gm2_google_refresh_token');
        add_filter('gm2_google_oauth_instance', function() {
            return new class {
                public function is_connected() { return true; }
                public function get_auth_url() { return ''; }
                public function handle_callback($code) { return true; }
                public function list_analytics_properties() {
                    return new WP_Error('api_error', 'HTTP 500 response');
                }
                public function list_ads_accounts() { return []; }
            };
        });
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_google_connect_page();
        $output = ob_get_clean();
        $this->assertStringContainsString('HTTP 500 response', $output);
        $this->assertStringContainsString('enable the Analytics', $output);
    }

    public function test_analytics_api_error_shown_once() {
        delete_option('gm2_google_refresh_token');
        add_filter('gm2_google_oauth_instance', function() {
            return new class {
                public function is_connected() { return true; }
                public function get_auth_url() { return ''; }
                public function handle_callback($code) { return true; }
                public function list_analytics_properties() {
                    return new WP_Error('api_error', 'HTTP 500 response');
                }
                public function list_ads_accounts() { return []; }
            };
        });
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_google_connect_page();
        $output = ob_get_clean();
        $this->assertSame(1, substr_count($output, 'HTTP 500 response'));
        $this->assertStringNotContainsString('No Analytics properties found', $output);
    }

    public function test_error_displayed_when_ads_api_fails() {
        delete_option('gm2_google_refresh_token');
        add_filter('gm2_google_oauth_instance', function() {
            return new class {
                public function is_connected() { return true; }
                public function get_auth_url() { return ''; }
                public function handle_callback($code) { return true; }
                public function list_analytics_properties() { return ['G-1' => 'Site']; }
                public function list_ads_accounts() {
                    return new WP_Error('api_error', 'HTTP 500 response');
                }
            };
        });
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_google_connect_page();
        $output = ob_get_clean();
        $this->assertStringContainsString('HTTP 500 response', $output);
        $this->assertStringContainsString('enable the Analytics', $output);
    }

    public function test_ads_api_error_shown_once() {
        delete_option('gm2_google_refresh_token');
        add_filter('gm2_google_oauth_instance', function() {
            return new class {
                public function is_connected() { return true; }
                public function get_auth_url() { return ''; }
                public function handle_callback($code) { return true; }
                public function list_analytics_properties() { return ['G-1' => 'Site']; }
                public function list_ads_accounts() {
                    return new WP_Error('api_error', 'HTTP 500 response');
                }
            };
        });
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_google_connect_page();
        $output = ob_get_clean();
        $this->assertSame(1, substr_count($output, 'HTTP 500 response'));
        $this->assertStringNotContainsString('No Ads accounts found', $output);
    }

    public function test_invalid_state_displays_help() {
        delete_option('gm2_google_refresh_token');
        $_GET['code'] = 'abc';
        add_filter('gm2_google_oauth_instance', function() {
            return new class {
                public function is_connected() { return false; }
                public function get_auth_url() { return ''; }
                public function handle_callback($code) {
                    return new WP_Error('invalid_state', 'Invalid OAuth state');
                }
            };
        });
        $admin = new Gm2_SEO_Admin();
        ob_start();
        $admin->display_google_connect_page();
        $output = ob_get_clean();
        $this->assertStringContainsString('Invalid OAuth state', $output);
        $this->assertStringContainsString('enable the Analytics', $output);
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

<?php
use Gm2\Gm2_Google_OAuth;

class OAuthTest extends WP_UnitTestCase {
    public function test_get_auth_url_contains_accounts_domain() {
        update_option('gm2_gads_client_id', 'id');
        update_option('gm2_gads_client_secret', 'secret');

        $oauth = new Gm2_Google_OAuth();
        $url   = $oauth->get_auth_url();

        $this->assertStringContainsString('accounts.google.com', $url);
    }

    public function test_handle_callback_saves_token() {
        update_option('gm2_gads_client_id', 'id');
        update_option('gm2_gads_client_secret', 'secret');
        update_option('gm2_google_refresh_token', '');

        $_GET['code'] = 'test';

        $responses = [
            'https://oauth2.googleapis.com/token' => [
                'response' => ['code' => 200],
                'body'     => json_encode([
                    'refresh_token' => 'saved',
                    'access_token'  => 'acc',
                    'expires_in'    => 3600,
                ]),
            ],
            'https://www.googleapis.com/oauth2/v2/userinfo' => [
                'response' => ['code' => 200],
                'body'     => json_encode([
                    'id'    => '1',
                    'email' => 'test@example.com',
                    'name'  => 'Test',
                ]),
            ],
        ];

        $filter = function ($pre, $r, $url) use ($responses) {
            foreach ($responses as $endpoint => $data) {
                if (0 === strpos($url, $endpoint)) {
                    return $data;
                }
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $oauth = new Gm2_Google_OAuth();
        $url   = $oauth->get_auth_url();
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $_GET['state'] = $params['state'];

        $oauth->handle_callback('test');

        remove_filter('pre_http_request', $filter, 10);

        $this->assertSame('saved', get_option('gm2_google_refresh_token'));
    }

    public function test_ads_request_includes_developer_token_header() {
        update_option('gm2_google_refresh_token', 'refresh');
        update_option('gm2_google_access_token', 'access');
        update_option('gm2_google_expires_at', time() + 3600);
        update_option('gm2_gads_developer_token', 'devtoken');

        $captured = null;
        $expected = 'https://googleads.googleapis.com/' . Gm2_Google_OAuth::GOOGLE_ADS_API_VERSION . '/customers:listAccessibleCustomers';
        $filter   = function ($pre, $args, $url) use (&$captured, $expected) {
            if (0 === strpos($url, $expected)) {
                $captured = $args;
                return [
                    'response' => ['code' => 200],
                    'body'     => json_encode(['resourceNames' => []]),
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $oauth = new Gm2_Google_OAuth();
        $oauth->list_ads_accounts();

        remove_filter('pre_http_request', $filter, 10);

        $this->assertIsArray($captured);
        $this->assertSame('devtoken', $captured['headers']['developer-token']);
    }

    public function test_ads_request_includes_login_customer_header() {
        update_option('gm2_google_refresh_token', 'refresh');
        update_option('gm2_google_access_token', 'access');
        update_option('gm2_google_expires_at', time() + 3600);
        update_option('gm2_gads_developer_token', 'devtoken');
        update_option('gm2_gads_login_customer_id', '123-456-7890');

        $captured = null;
        $expected = 'https://googleads.googleapis.com/' . Gm2_Google_OAuth::GOOGLE_ADS_API_VERSION . '/customers:listAccessibleCustomers';
        $filter   = function ($pre, $args, $url) use (&$captured, $expected) {
            if (0 === strpos($url, $expected)) {
                $captured = $args;
                return [
                    'response' => ['code' => 200],
                    'body'     => json_encode(['resourceNames' => []]),
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $oauth = new Gm2_Google_OAuth();
        $oauth->list_ads_accounts();

        remove_filter('pre_http_request', $filter, 10);

        $this->assertIsArray($captured);
        $this->assertSame('1234567890', $captured['headers']['login-customer-id']);
    }

    public function test_ads_request_includes_login_customer_header_when_set() {
        update_option('gm2_google_refresh_token', 'refresh');
        update_option('gm2_google_access_token', 'access');
        update_option('gm2_google_expires_at', time() + 3600);
        update_option('gm2_gads_developer_token', 'devtoken');
        update_option('gm2_gads_login_customer_id', '222-333-4444');

        $captured = null;
        $expected = 'https://googleads.googleapis.com/' . Gm2_Google_OAuth::GOOGLE_ADS_API_VERSION . '/customers:listAccessibleCustomers';
        $filter   = function ($pre, $args, $url) use (&$captured, $expected) {
            if (0 === strpos($url, $expected)) {
                $captured = $args;
                return [
                    'response' => ['code' => 200],
                    'body'     => json_encode(['resourceNames' => []]),
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $oauth = new Gm2_Google_OAuth();
        $oauth->list_ads_accounts();

        remove_filter('pre_http_request', $filter, 10);

        $this->assertIsArray($captured);
        $this->assertSame('2223334444', $captured['headers']['login-customer-id']);
    }

    public function test_error_returned_when_no_developer_token() {
        update_option('gm2_google_refresh_token', 'refresh');
        update_option('gm2_google_access_token', 'access');
        update_option('gm2_google_expires_at', time() + 3600);
        delete_option('gm2_gads_developer_token');

        $oauth  = new Gm2_Google_OAuth();
        $result = $oauth->list_ads_accounts();

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame('missing_developer_token', $result->get_error_code());
    }

    public function test_ga4_properties_returned() {
        update_option('gm2_google_refresh_token', 'refresh');
        update_option('gm2_google_access_token', 'access');
        update_option('gm2_google_expires_at', time() + 3600);

        $filter = function ($pre, $args, $url) {
            $acct_url = sprintf(
                'https://analyticsadmin.googleapis.com/%s/accountSummaries',
                Gm2_Google_OAuth::ANALYTICS_ADMIN_API_VERSION
            );
            if (0 === strpos($url, $acct_url)) {
                return [
                    'response' => ['code' => 200],
                    'body'     => json_encode([
                        'accountSummaries' => [
                            [
                                'propertySummaries' => [
                                    [
                                        'property'    => 'properties/123',
                                        'displayName' => 'GA4 Prop',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ];
            }
            $stream_url = sprintf(
                'https://analyticsadmin.googleapis.com/%s/properties/123/dataStreams',
                Gm2_Google_OAuth::ANALYTICS_ADMIN_API_VERSION
            );
            if (0 === strpos($url, $stream_url)) {
                return [
                    'response' => ['code' => 200],
                    'body'     => json_encode([
                        'dataStreams' => [
                            [
                                'type' => 'WEB_DATA_STREAM',
                                'webStreamData' => ['measurementId' => 'G-ABC123'],
                            ],
                        ],
                    ]),
                ];
            }
            if (false !== strpos($url, 'analytics/v3/')) {
                return [ 'response' => ['code' => 200], 'body' => json_encode(['items' => []]) ];
            }
            return false;
        };

        add_filter('pre_http_request', $filter, 10, 3);

        $oauth = new Gm2_Google_OAuth();
        $props = $oauth->list_analytics_properties();

        remove_filter('pre_http_request', $filter, 10);

        $this->assertArrayHasKey('G-ABC123', $props);
        $this->assertSame('GA4 Prop', $props['G-ABC123']);
    }

    public function test_api_request_error_message_includes_status() {
        update_option('gm2_google_refresh_token', 'refresh');
        update_option('gm2_google_access_token', 'access');
        update_option('gm2_google_expires_at', time() + 3600);
        update_option('gm2_gads_developer_token', 'devtoken');

        $filter = function ($pre, $args, $url) {
            if (false !== strpos($url, 'customers:listAccessibleCustomers')) {
                return [
                    'response' => ['code' => 403],
                    'body'     => 'forbidden',
                ];
            }
            return false;
        };
        add_filter('pre_http_request', $filter, 10, 3);

        $oauth  = new Gm2_Google_OAuth();
        $result = $oauth->list_ads_accounts();

        remove_filter('pre_http_request', $filter, 10);

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame('api_error', $result->get_error_code());
        $this->assertSame('HTTP 403 response', $result->get_error_message());
    }

    public function test_ga4_error_does_not_block_other_properties() {
        update_option('gm2_google_refresh_token', 'refresh');
        update_option('gm2_google_access_token', 'access');
        update_option('gm2_google_expires_at', time() + 3600);

        $filter = function ($pre, $args, $url) {
            $acct_url = sprintf(
                'https://analyticsadmin.googleapis.com/%s/accountSummaries',
                Gm2_Google_OAuth::ANALYTICS_ADMIN_API_VERSION
            );
            if (0 === strpos($url, $acct_url)) {
                return [
                    'response' => ['code' => 200],
                    'body'     => json_encode([
                        'accountSummaries' => [
                            [
                                'propertySummaries' => [
                                    [ 'property' => 'properties/123', 'displayName' => 'GA4 A' ],
                                    [ 'property' => 'properties/456', 'displayName' => 'GA4 B' ],
                                ],
                            ],
                        ],
                    ]),
                ];
            }

            $stream_a = sprintf(
                'https://analyticsadmin.googleapis.com/%s/properties/123/dataStreams',
                Gm2_Google_OAuth::ANALYTICS_ADMIN_API_VERSION
            );
            if (0 === strpos($url, $stream_a)) {
                return new WP_Error('fail', 'oops');
            }

            $stream_b = sprintf(
                'https://analyticsadmin.googleapis.com/%s/properties/456/dataStreams',
                Gm2_Google_OAuth::ANALYTICS_ADMIN_API_VERSION
            );
            if (0 === strpos($url, $stream_b)) {
                return [
                    'response' => ['code' => 200],
                    'body'     => json_encode([
                        'dataStreams' => [
                            [
                                'type' => 'WEB_DATA_STREAM',
                                'webStreamData' => ['measurementId' => 'G-DEF456'],
                            ],
                        ],
                    ]),
                ];
            }

            if (false !== strpos($url, 'analytics/v3/')) {
                return [ 'response' => ['code' => 200], 'body' => json_encode(['items' => []]) ];
            }

            return false;
        };

        add_filter('pre_http_request', $filter, 10, 3);

        $oauth = new Gm2_Google_OAuth();
        $props = $oauth->list_analytics_properties();

        remove_filter('pre_http_request', $filter, 10);

        $this->assertArrayHasKey('G-DEF456', $props);
        $this->assertSame('GA4 B', $props['G-DEF456']);
    }
}

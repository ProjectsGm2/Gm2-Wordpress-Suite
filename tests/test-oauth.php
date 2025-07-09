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
            if (0 === strpos($url, 'https://analyticsadmin.googleapis.com/v1/accountSummaries')) {
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
            if (0 === strpos($url, 'https://analyticsadmin.googleapis.com/v1/properties/123/dataStreams')) {
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
}

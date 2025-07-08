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

        $oauth->handle_callback();

        remove_filter('pre_http_request', $filter, 10);

        $this->assertSame('saved', get_option('gm2_google_refresh_token'));
    }
}

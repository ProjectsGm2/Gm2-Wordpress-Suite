<?php
use Gm2\Gm2_Google_OAuth;
class OAuthTest extends WP_UnitTestCase {
    public function test_get_auth_url_contains_accounts_domain() {
        $client = new class {
            public function setClientId($id) {}
            public function setClientSecret($sec) {}
            public function setRedirectUri($uri) {}
            public function setAccessType($type) {}
            public function setPrompt($prompt) {}
            public function setScopes($scopes) {}
            public function refreshToken($token) {}
            public function createAuthUrl() { return 'https://accounts.google.com/o/oauth2/auth'; }
            public function fetchAccessTokenWithAuthCode($code) { return ['refresh_token'=>'ref']; }
        };
        $oauth = new Gm2_Google_OAuth($client);
        $url = $oauth->get_auth_url();
        $this->assertStringContainsString('accounts.google.com', $url);
    }

    public function test_handle_callback_saves_token() {
        update_option('gm2_google_refresh_token', '');
        $_GET['code'] = 'test';
        $client = new class {
            public function setClientId($id) {}
            public function setClientSecret($sec) {}
            public function setRedirectUri($uri) {}
            public function setAccessType($type) {}
            public function setPrompt($prompt) {}
            public function setScopes($scopes) {}
            public function refreshToken($token) {}
            public function fetchAccessTokenWithAuthCode($code) { return ['refresh_token'=>'saved']; }
        };
        $oauth = new Gm2_Google_OAuth($client);
        $oauth->handle_callback();
        $this->assertSame('saved', get_option('gm2_google_refresh_token'));
    }
}

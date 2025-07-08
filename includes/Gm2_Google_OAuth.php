<?php

namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Google_OAuth {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $scopes;

    public function __construct() {
        $this->client_id     = get_option('gm2_gads_client_id', '');
        $this->client_secret = get_option('gm2_gads_client_secret', '');
        $this->redirect_uri  = admin_url('admin.php?page=gm2-google-connect');
        $this->scopes = [
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/webmasters.readonly',
            'https://www.googleapis.com/auth/adwords',
            'openid',
            'profile',
            'email',
        ];
    }

    public function is_connected() {
        return (bool) get_option('gm2_google_refresh_token');
    }

    private function api_request($method, $url, $body = null, $headers = []) {
        $args = [
            'method'  => $method,
            'timeout' => 20,
            'headers' => $headers,
        ];
        if (!is_null($body)) {
            $args['body'] = wp_json_encode($body);
            $args['headers']['Content-Type'] = 'application/json';
        }
        $resp = wp_remote_request($url, $args);
        if (is_wp_error($resp)) {
            return $resp;
        }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            return new \WP_Error('api_error', 'Non-2xx response', [
                'code' => $code,
                'body' => wp_remote_retrieve_body($resp),
            ]);
        }
        $body = wp_remote_retrieve_body($resp);
        return $body !== '' ? json_decode($body, true) : [];
    }

    public function get_auth_url() {
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'scope' => implode(' ', $this->scopes),
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function handle_callback() {
        if (!isset($_GET['code'])) {
            return false;
        }
        $resp = $this->api_request('POST', 'https://oauth2.googleapis.com/token', [
            'code' => $_GET['code'],
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $this->redirect_uri,
            'grant_type' => 'authorization_code',
        ]);
        if (is_wp_error($resp) || empty($resp['refresh_token'])) {
            return false;
        }
        update_option('gm2_google_refresh_token', $resp['refresh_token']);
        update_option('gm2_google_access_token', $resp['access_token']);
        update_option('gm2_google_expires_at', time() + (int) $resp['expires_in']);

        $user = $this->api_request('GET', 'https://www.googleapis.com/oauth2/v2/userinfo', null, [
            'Authorization' => 'Bearer ' . $resp['access_token'],
        ]);
        if (!is_wp_error($user)) {
            update_option('gm2_google_profile', [
                'id'    => $user['id'] ?? '',
                'email' => $user['email'] ?? '',
                'name'  => $user['name'] ?? '',
            ]);
        }
        return true;
    }

    private function refresh_token() {
        $refresh = get_option('gm2_google_refresh_token');
        if (!$refresh) {
            return '';
        }
        $resp = $this->api_request('POST', 'https://oauth2.googleapis.com/token', [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'refresh_token' => $refresh,
            'grant_type' => 'refresh_token',
        ]);
        if (is_wp_error($resp) || empty($resp['access_token'])) {
            return '';
        }
        update_option('gm2_google_access_token', $resp['access_token']);
        update_option('gm2_google_expires_at', time() + (int) $resp['expires_in']);
        return $resp['access_token'];
    }

    public function get_access_token() {
        $token = get_option('gm2_google_access_token');
        $expires = (int) get_option('gm2_google_expires_at', 0);
        if (!$token || time() >= $expires) {
            $token = $this->refresh_token();
        }
        return $token;
    }

    public function list_analytics_properties() {
        if (!$this->is_connected()) {
            return [];
        }
        $token = $this->get_access_token();
        if (!$token) {
            return [];
        }
        $accounts = $this->api_request('GET', 'https://analytics.googleapis.com/analytics/v3/management/accounts', null, [
            'Authorization' => 'Bearer ' . $token,
        ]);
        if (is_wp_error($accounts) || empty($accounts['items'])) {
            return [];
        }
        $props = [];
        foreach ($accounts['items'] as $acct) {
            $url = sprintf('https://analytics.googleapis.com/analytics/v3/management/accounts/%s/webproperties', $acct['id']);
            $webprops = $this->api_request('GET', $url, null, [
                'Authorization' => 'Bearer ' . $token,
            ]);
            if (!is_wp_error($webprops) && !empty($webprops['items'])) {
                foreach ($webprops['items'] as $p) {
                    $props[$p['id']] = $p['name'];
                }
            }
        }
        return $props;
    }

    public function list_search_console_sites() {
        if (!$this->is_connected()) {
            return [];
        }
        $token = $this->get_access_token();
        if (!$token) {
            return [];
        }
        $sites = $this->api_request('GET', 'https://searchconsole.googleapis.com/webmasters/v3/sites', null, [
            'Authorization' => 'Bearer ' . $token,
        ]);
        if (is_wp_error($sites) || empty($sites['siteEntry'])) {
            return [];
        }
        $list = [];
        foreach ($sites['siteEntry'] as $s) {
            $list[$s['siteUrl']] = $s['siteUrl'];
        }
        return $list;
    }

    public function list_ads_accounts() {
        if (!$this->is_connected()) {
            return [];
        }
        $token = $this->get_access_token();
        if (!$token) {
            return [];
        }
        $resp = $this->api_request('GET', 'https://googleads.googleapis.com/v15/customers:listAccessibleCustomers', null, [
            'Authorization' => 'Bearer ' . $token,
        ]);
        if (is_wp_error($resp) || empty($resp['resourceNames'])) {
            return [];
        }
        $list = [];
        foreach ($resp['resourceNames'] as $name) {
            $id = str_replace('customers/', '', $name);
            $list[$id] = $id;
        }
        return $list;
    }
}

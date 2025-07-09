<?php

namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Google_OAuth {
    /** Latest supported Google Ads API version. */
    public const GOOGLE_ADS_API_VERSION = 'v18';

    /** Latest supported Analytics Admin API version for GA4 requests. */
    public const ANALYTICS_ADMIN_API_VERSION = 'v1beta';

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
            'timeout' => 20,
            'headers' => $headers,
        ];
        if (!is_null($body)) {
            $args['body'] = wp_json_encode($body);
            $args['headers']['Content-Type'] = 'application/json';
        }
        if (strtoupper($method) === 'POST') {
            $resp = wp_remote_post($url, $args);
        } else {
            $resp = wp_remote_get($url, $args);
        }
        if (is_wp_error($resp)) {
            return $resp;
        }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            return new \WP_Error('api_error', "HTTP $code response", [
                'code' => $code,
                'body' => wp_remote_retrieve_body($resp),
            ]);
        }
        $body = wp_remote_retrieve_body($resp);
        return $body !== '' ? json_decode($body, true) : [];
    }

    public function get_auth_url() {
        $state = wp_create_nonce('gm2_oauth_state');
        update_user_meta(get_current_user_id(), 'gm2_oauth_state', $state);

        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'scope' => implode(' ', $this->scopes),
            'state' => $state,
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function handle_callback($code = '') {
        if ('' === $code) {
            return false;
        }

        $code  = sanitize_text_field($code);
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';

        $expected_state = get_user_meta(get_current_user_id(), 'gm2_oauth_state', true);
        delete_user_meta(get_current_user_id(), 'gm2_oauth_state');
        if ('' === $state || $state !== $expected_state) {
            return new \WP_Error('invalid_state', 'Invalid OAuth state');
        }

        $resp = $this->api_request('POST', 'https://oauth2.googleapis.com/token', [
            'code' => $code,
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
        $props = [];
        $had_error   = false;
        $last_error  = null;

        // GA4 properties via Analytics Admin API.
        $accounts = $this->api_request(
            'GET',
            sprintf('https://analyticsadmin.googleapis.com/%s/accountSummaries', self::ANALYTICS_ADMIN_API_VERSION),
            null,
            [
            'Authorization' => 'Bearer ' . $token,
            ]
        );
        if (is_wp_error($accounts)) {
            return $accounts;
        }
        if (!empty($accounts['accountSummaries'])) {
            foreach ($accounts['accountSummaries'] as $acct) {
                if (empty($acct['propertySummaries'])) {
                    continue;
                }
                foreach ($acct['propertySummaries'] as $p) {
                    $propName = $p['displayName'] ?? $p['property'];
                    $propId   = $p['property'];
                    // Query for web data streams to get measurement ID.
                    $streams = $this->api_request(
                        'GET',
                        sprintf(
                            'https://analyticsadmin.googleapis.com/%s/%s/dataStreams',
                            self::ANALYTICS_ADMIN_API_VERSION,
                            $propId
                        ),
                        null,
                        [
                            'Authorization' => 'Bearer ' . $token,
                        ]
                    );
                    if (is_wp_error($streams)) {
                        $had_error  = true;
                        $last_error = $streams;
                        continue;
                    }
                    if (!empty($streams['dataStreams'])) {
                        foreach ($streams['dataStreams'] as $stream) {
                            if (($stream['type'] ?? '') === 'WEB_DATA_STREAM' && !empty($stream['webStreamData']['measurementId'])) {
                                $mid = $stream['webStreamData']['measurementId'];
                                $props[$mid] = $propName;
                                break;
                            }
                        }
                    }
                }
            }
        }

        // UA properties via the legacy v3 API for backwards compatibility.
        $ua_accounts = $this->api_request('GET', 'https://analytics.googleapis.com/analytics/v3/management/accounts', null, [
            'Authorization' => 'Bearer ' . $token,
        ]);
        if (is_wp_error($ua_accounts)) {
            return $ua_accounts;
        }
        if (!empty($ua_accounts['items'])) {
            foreach ($ua_accounts['items'] as $acct) {
                $url = sprintf('https://analytics.googleapis.com/analytics/v3/management/accounts/%s/webproperties', $acct['id']);
                $webprops = $this->api_request('GET', $url, null, [
                    'Authorization' => 'Bearer ' . $token,
                ]);
                if (is_wp_error($webprops)) {
                    $had_error  = true;
                    $last_error = $webprops;
                    continue;
                }
                if (!empty($webprops['items'])) {
                    foreach ($webprops['items'] as $p) {
                        if (!isset($props[$p['id']])) {
                            $props[$p['id']] = $p['name'];
                        }
                    }
                }
            }
        }

        if ($had_error && empty($props) && $last_error instanceof \WP_Error) {
            return new \WP_Error(
                $last_error->get_error_code(),
                $last_error->get_error_message(),
                $last_error->get_error_data()
            );
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
        $access = $this->get_access_token();
        if (!$access) {
            return [];
        }
        $token = trim(get_option('gm2_gads_developer_token', ''));
        if ($token === '') {
            return new \WP_Error(
                'missing_developer_token',
                __('A Google Ads developer token is required to list accounts.', 'gm2-wordpress-suite')
            );
        }
        $url  = sprintf('https://googleads.googleapis.com/%s/customers:listAccessibleCustomers', self::GOOGLE_ADS_API_VERSION);
        $resp = $this->api_request('GET', $url, null, [
            'Authorization'   => 'Bearer ' . $access,
            'developer-token' => $token,
        ]);
        if (is_wp_error($resp)) {
            return $resp;
        }
        if (empty($resp['resourceNames'])) {
            return [];
        }
        $list = [];
        foreach ($resp['resourceNames'] as $name) {
            $id = str_replace('customers/', '', $name);
            $list[$id] = $id;
        }
        return $list;
    }

    public function disconnect() {
        delete_option('gm2_google_refresh_token');
        delete_option('gm2_google_access_token');
        delete_option('gm2_google_expires_at');
        delete_option('gm2_google_profile');
    }
}

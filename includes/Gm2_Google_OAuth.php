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
    private $gcloud_project;
    private $service_account;

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
        $this->gcloud_project   = defined('GM2_GCLOUD_PROJECT_ID') ? GM2_GCLOUD_PROJECT_ID : '';
        $this->gcloud_project   = apply_filters('gm2_gcloud_project_id', $this->gcloud_project);
        $this->service_account  = defined('GM2_SERVICE_ACCOUNT_JSON') ? GM2_SERVICE_ACCOUNT_JSON : '';
        $this->service_account  = apply_filters('gm2_service_account_json', $this->service_account);
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

    private function get_service_token() {
        if (!$this->gcloud_project || !$this->service_account || !file_exists($this->service_account)) {
            return '';
        }
        $data = json_decode(file_get_contents($this->service_account), true);
        if (!$data || empty($data['client_email']) || empty($data['private_key'])) {
            return '';
        }
        $now  = time();
        $hdr  = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $clm  = base64_encode(json_encode([
            'iss'   => $data['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ]));
        $hdr  = rtrim(strtr($hdr, '+/', '-_'), '=');
        $clm  = rtrim(strtr($clm, '+/', '-_'), '=');
        $sig_data = $hdr . '.' . $clm;
        if (!function_exists('openssl_sign')) {
            error_log('OpenSSL extension missing: cannot sign JWT.');
            return '';
        }
        openssl_sign($sig_data, $signature, $data['private_key'], 'sha256');
        $sig = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        $jwt = $sig_data . '.' . $sig;
        $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ],
            'timeout' => 20,
        ]);
        if (is_wp_error($resp)) {
            return '';
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return $body['access_token'] ?? '';
    }

    private function maybe_add_redirect_uri() {
        if (!$this->gcloud_project || !$this->client_id) {
            return;
        }
        $token = $this->get_service_token();
        if (!$token) {
            return;
        }
        $base = sprintf('https://oauth2.googleapis.com/v2/projects/%s/clients/%s',
            rawurlencode($this->gcloud_project), rawurlencode($this->client_id));
        $info = $this->api_request('GET', $base, null, ['Authorization' => 'Bearer ' . $token]);
        if (is_wp_error($info)) {
            return;
        }
        $uris = $info['redirectUris'] ?? [];
        if (in_array($this->redirect_uri, $uris, true)) {
            return;
        }
        $uris[] = $this->redirect_uri;
        $this->api_request('PATCH', $base . '?updateMask=redirectUris', [ 'redirectUris' => $uris ], [
            'Authorization' => 'Bearer ' . $token,
        ]);
    }

    public function get_auth_url() {
        $this->maybe_add_redirect_uri();
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

    public function get_search_console_queries($site_url, $limit = 10) {
        if (!$this->is_connected()) {
            return [];
        }
        $token = $this->get_access_token();
        if (!$token) {
            return [];
        }

        $body = [
            'startDate' => date('Y-m-d', strtotime('-30 days')),
            'endDate'   => date('Y-m-d'),
            'dimensions' => ['query'],
            'rowLimit'   => absint($limit),
            'orderBy'    => [ [ 'field' => 'clicks', 'descending' => true ] ],
        ];

        $url = sprintf(
            'https://searchconsole.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query',
            rawurlencode($site_url)
        );

        $resp = $this->api_request('POST', $url, $body, [
            'Authorization' => 'Bearer ' . $token,
        ]);

        if (is_wp_error($resp) || empty($resp['rows'])) {
            return [];
        }

        $queries = [];
        foreach ($resp['rows'] as $row) {
            if (!empty($row['keys'][0])) {
                $queries[] = $row['keys'][0];
            }
        }
        return $queries;
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
        $url     = sprintf('https://googleads.googleapis.com/%s/customers:listAccessibleCustomers', self::GOOGLE_ADS_API_VERSION);
        $headers = [
            'Authorization'   => 'Bearer ' . $access,
            'developer-token' => $token,
        ];

        if ($login = preg_replace('/\D/', '', get_option('gm2_gads_login_customer_id'))) {
            $headers['login-customer-id'] = $login;
        }

        $resp = $this->api_request('GET', $url, null, $headers);
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

    public function get_analytics_metrics($property_id, $days = 30) {
        if (!$this->is_connected()) {
            return [];
        }
        $token = $this->get_access_token();
        if (!$token) {
            return [];
        }

        $end   = date('Y-m-d');
        $start = date('Y-m-d', strtotime('-' . absint($days) . ' days'));

        if (strpos($property_id, 'UA-') === 0) {
            $url  = 'https://analyticsreporting.googleapis.com/v4/reports:batchGet';
            $body = [
                'reportRequests' => [
                    [
                        'viewId'    => $property_id,
                        'dateRanges'=> [ [ 'startDate' => $start, 'endDate' => $end ] ],
                        'metrics'   => [
                            [ 'expression' => 'ga:sessions' ],
                            [ 'expression' => 'ga:bounceRate' ],
                        ],
                    ],
                ],
            ];
        } else {
            $prop = preg_replace('/^properties\//', '', $property_id);
            $url  = sprintf('https://analyticsdata.googleapis.com/v1beta/properties/%s:runReport', $prop);
            $body = [
                'dateRanges' => [ [ 'startDate' => $start, 'endDate' => $end ] ],
                'metrics'    => [
                    [ 'name' => 'sessions' ],
                    [ 'name' => 'bounceRate' ],
                ],
            ];
        }

        $resp = $this->api_request('POST', $url, $body, [
            'Authorization' => 'Bearer ' . $token,
        ]);

        if (is_wp_error($resp)) {
            return [];
        }

        if (isset($resp['reports'][0]['data']['totals'][0]['values'])) {
            $vals = $resp['reports'][0]['data']['totals'][0]['values'];
            return [
                'sessions'    => (int) ($vals[0] ?? 0),
                'bounce_rate' => (float) ($vals[1] ?? 0),
            ];
        }

        if (!empty($resp['rows'][0]['metricValues'])) {
            $vals = $resp['rows'][0]['metricValues'];
            return [
                'sessions'    => (int) ($vals[0]['value'] ?? 0),
                'bounce_rate' => (float) ($vals[1]['value'] ?? 0),
            ];
        }

        return [];
    }

    public function disconnect() {
        delete_option('gm2_google_refresh_token');
        delete_option('gm2_google_access_token');
        delete_option('gm2_google_expires_at');
        delete_option('gm2_google_profile');
        delete_option('gm2_ga_measurement_id');
        delete_option('gm2_gads_customer_id');
    }
}

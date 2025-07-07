<?php
if (!defined('ABSPATH')) {
    exit;
}

use Google_Client;
use Google_Service_Oauth2;
use Google_Service_GoogleAds;
use Google_Service_SearchConsole;
use Google_Service_Analytics;

class Gm2_Google_OAuth {
    /** @var Google_Client */
    private $client;

    public function __construct($client = null) {
        $this->client = $client ?: new Google_Client();
        $this->setup_client();
    }

    private function setup_client() {
        $this->client->setClientId(get_option('gm2_gads_client_id', ''));
        $this->client->setClientSecret(get_option('gm2_gads_client_secret', ''));
        $this->client->setRedirectUri(admin_url('admin.php?page=gm2-google-connect'));
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        $this->client->setScopes([
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/webmasters.readonly',
            'https://www.googleapis.com/auth/adwords',
            'openid','profile','email'
        ]);
        $token = get_option('gm2_google_refresh_token');
        if ($token) {
            $this->client->refreshToken($token);
        }
    }

    public function is_connected() {
        return (bool) get_option('gm2_google_refresh_token');
    }

    public function get_auth_url() {
        return $this->client->createAuthUrl();
    }

    public function handle_callback() {
        if (isset($_GET['code'])) {
            $token = $this->client->fetchAccessTokenWithAuthCode($_GET['code']);
            if (!empty($token['refresh_token'])) {
                update_option('gm2_google_refresh_token', $token['refresh_token']);
                $oauth2 = new Google_Service_Oauth2($this->client);
                $me = $oauth2->userinfo->get();
                update_option('gm2_google_profile', [
                    'id'    => $me->id,
                    'email' => $me->email,
                    'name'  => $me->name,
                ]);
                return true;
            }
        }
        return false;
    }

    public function list_analytics_properties() {
        if (!$this->is_connected()) return [];
        $service = new Google_Service_Analytics($this->client);
        $accounts = $service->management_accounts->listManagementAccounts();
        $props = [];
        if ($accounts->getItems()) {
            foreach ($accounts->getItems() as $acct) {
                $properties = $service->management_webproperties->listManagementWebproperties($acct->getId());
                foreach ((array) $properties->getItems() as $p) {
                    $props[$p->getId()] = $p->getName();
                }
            }
        }
        return $props;
    }

    public function list_search_console_sites() {
        if (!$this->is_connected()) return [];
        $service = new Google_Service_SearchConsole($this->client);
        $sites = $service->sites->listSites();
        $list = [];
        if ($sites->getSiteEntry()) {
            foreach ($sites->getSiteEntry() as $s) {
                $list[$s->getSiteUrl()] = $s->getSiteUrl();
            }
        }
        return $list;
    }

    public function list_ads_accounts() {
        if (!$this->is_connected()) return [];
        $service = new Google_Service_GoogleAds($this->client);
        $resp = $service->customers->listCustomers();
        $list = [];
        if ($resp->getCustomers()) {
            foreach ($resp->getCustomers() as $c) {
                $list[$c->getId()] = $c->getDescriptiveName();
            }
        }
        return $list;
    }
}

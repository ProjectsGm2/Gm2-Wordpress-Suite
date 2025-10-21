<?php
namespace Gm2\Updater;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle the GitHub device authorization flow for generating access tokens.
 */
class Gm2_GitHub_OAuth {
    private const DEVICE_CODE_URL = 'https://github.com/login/device/code';
    private const ACCESS_TOKEN_URL = 'https://github.com/login/oauth/access_token';
    private const USER_ENDPOINT = 'https://api.github.com/user';
    private const TRANSIENT_KEY = 'gm2_github_oauth_state_%d';

    /**
     * Get the configured GitHub OAuth client ID.
     */
    public static function get_client_id() {
        $client_id = '';

        if (defined('GM2_GITHUB_CLIENT_ID') && GM2_GITHUB_CLIENT_ID) {
            $client_id = (string) GM2_GITHUB_CLIENT_ID;
        }

        if ($client_id === '') {
            $client_id = (string) get_option('gm2_github_client_id', '');
        }

        /**
         * Filter the GitHub OAuth client ID used for the device flow.
         *
         * @param string $client_id Client ID value.
         */
        $client_id = (string) apply_filters('gm2_github_oauth_client_id', $client_id);

        return trim($client_id);
    }

    /**
     * Get the scopes requested when generating a GitHub access token.
     *
     * @return string[]
     */
    public static function get_scopes() {
        $scopes = ['repo'];

        /**
         * Filter the GitHub OAuth scopes requested during the device flow.
         *
         * @param string[] $scopes List of scopes.
         */
        $scopes = apply_filters('gm2_github_oauth_scopes', $scopes);

        if (!is_array($scopes)) {
            $scopes = ['repo'];
        }

        $sanitized = [];
        foreach ($scopes as $scope) {
            $scope = trim((string) $scope);
            if ($scope !== '') {
                $sanitized[$scope] = $scope;
            }
        }

        if (empty($sanitized)) {
            $sanitized['repo'] = 'repo';
        }

        return array_values($sanitized);
    }

    /**
     * Begin the GitHub device flow for the current user.
     *
     * @param int $user_id WordPress user ID.
     *
     * @return array<string, mixed>|WP_Error
     */
    public static function begin_device_flow($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return new WP_Error('github_oauth_user', __('Invalid user.', 'gm2-wordpress-suite'));
        }

        $client_id = self::get_client_id();
        if ($client_id === '') {
            return new WP_Error('github_oauth_client', __('GitHub OAuth client ID is not configured.', 'gm2-wordpress-suite'));
        }

        $response = wp_safe_remote_post(self::DEVICE_CODE_URL, [
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'body'    => [
                'client_id' => $client_id,
                'scope'     => implode(' ', self::get_scopes()),
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('github_oauth_http', sprintf(__('GitHub returned HTTP %d while starting authorization.', 'gm2-wordpress-suite'), (int) $code));
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['device_code']) || empty($data['user_code']) || empty($data['verification_uri'])) {
            return new WP_Error('github_oauth_response', __('Unexpected response from GitHub when starting authorization.', 'gm2-wordpress-suite'));
        }

        $interval   = isset($data['interval']) ? max(5, absint($data['interval'])) : 5;
        $expires_in = isset($data['expires_in']) ? max($interval, absint($data['expires_in'])) : 900;

        $state = [
            'device_code' => (string) $data['device_code'],
            'interval'    => $interval,
            'expires_at'  => time() + $expires_in,
            'client_id'   => $client_id,
        ];

        set_transient(self::get_transient_key($user_id), $state, $expires_in);

        return [
            'device_code'               => $state['device_code'],
            'user_code'                 => (string) $data['user_code'],
            'verification_uri'          => (string) $data['verification_uri'],
            'verification_uri_complete' => isset($data['verification_uri_complete']) ? (string) $data['verification_uri_complete'] : '',
            'interval'                  => $interval,
            'expires_in'                => $expires_in,
        ];
    }

    /**
     * Poll GitHub for a device flow access token.
     *
     * @param int $user_id WordPress user ID.
     *
     * @return array<string, mixed>|WP_Error
     */
    public static function poll_device_flow($user_id) {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return new WP_Error('github_oauth_user', __('Invalid user.', 'gm2-wordpress-suite'));
        }

        $state = get_transient(self::get_transient_key($user_id));
        if (!is_array($state) || empty($state['device_code']) || empty($state['client_id'])) {
            return new WP_Error('github_oauth_state', __('GitHub authorization session expired. Start again.', 'gm2-wordpress-suite'));
        }

        if (!empty($state['expires_at']) && time() >= (int) $state['expires_at']) {
            delete_transient(self::get_transient_key($user_id));
            return new WP_Error('github_oauth_expired', __('GitHub authorization expired. Please try again.', 'gm2-wordpress-suite'));
        }

        $response = wp_safe_remote_post(self::ACCESS_TOKEN_URL, [
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'body'    => [
                'client_id'   => (string) $state['client_id'],
                'device_code' => (string) $state['device_code'],
                'grant_type'  => 'urn:ietf:params:oauth:grant-type:device_code',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('github_oauth_http', sprintf(__('GitHub returned HTTP %d while completing authorization.', 'gm2-wordpress-suite'), (int) $code));
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            return new WP_Error('github_oauth_response', __('Unexpected response from GitHub when completing authorization.', 'gm2-wordpress-suite'));
        }

        if (!empty($data['error'])) {
            $error = (string) $data['error'];
            if ($error === 'authorization_pending') {
                return [
                    'status'  => 'pending',
                    'interval'=> isset($data['interval']) ? max(5, absint($data['interval'])) : (int) $state['interval'],
                ];
            }

            if ($error === 'slow_down') {
                $state['interval'] = isset($state['interval']) ? (int) $state['interval'] + 5 : 10;
                set_transient(self::get_transient_key($user_id), $state, max(60, (int) ($state['expires_at'] - time())));

                return [
                    'status'   => 'slow_down',
                    'interval' => $state['interval'],
                ];
            }

            delete_transient(self::get_transient_key($user_id));

            $message = isset($data['error_description']) ? (string) $data['error_description'] : __('GitHub authorization failed.', 'gm2-wordpress-suite');

            return new WP_Error('github_oauth_error', $message);
        }

        if (empty($data['access_token'])) {
            return new WP_Error('github_oauth_response', __('GitHub did not return an access token.', 'gm2-wordpress-suite'));
        }

        delete_transient(self::get_transient_key($user_id));

        return [
            'status'       => 'success',
            'access_token' => (string) $data['access_token'],
            'scope'        => isset($data['scope']) ? (string) $data['scope'] : '',
            'token_type'   => isset($data['token_type']) ? (string) $data['token_type'] : 'bearer',
        ];
    }

    /**
     * Fetch the GitHub user associated with a token.
     *
     * @param string $token Access token string.
     *
     * @return array<string, mixed>|WP_Error
     */
    public static function fetch_user($token) {
        $token = trim((string) $token);
        if ($token === '') {
            return new WP_Error('github_oauth_token', __('Missing GitHub token.', 'gm2-wordpress-suite'));
        }

        $response = wp_safe_remote_get(self::USER_ENDPOINT, [
            'timeout' => 20,
            'headers' => [
                'Accept'        => 'application/vnd.github+json',
                'Authorization' => 'Bearer ' . $token,
                'User-Agent'    => 'Gm2-WordPress-Suite',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('github_oauth_http', sprintf(__('GitHub returned HTTP %d when fetching the user profile.', 'gm2-wordpress-suite'), (int) $code));
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['login'])) {
            return new WP_Error('github_oauth_response', __('Unable to determine the GitHub account for this token.', 'gm2-wordpress-suite'));
        }

        return $data;
    }

    /**
     * Build the transient key for the supplied user ID.
     */
    protected static function get_transient_key($user_id) {
        return sprintf(self::TRANSIENT_KEY, absint($user_id));
    }
}

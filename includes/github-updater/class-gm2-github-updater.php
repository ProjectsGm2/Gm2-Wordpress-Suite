<?php
namespace Gm2\Updater;

use stdClass;
use WP_CLI; // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GitHub updater integration for the Gm2 WordPress Suite.
 */
class Gm2_GitHub_Updater {
    /**
     * Absolute plugin file path.
     *
     * @var string
     */
    protected $plugin_file;

    /**
     * Settings controlling the updater behaviour.
     *
     * @var array<string, mixed>
     */
    protected $settings = [];

    /**
     * Flag to ensure hooks are only registered once.
     *
     * @var bool
     */
    protected $initialized = false;

    /**
     * Cached result of the most recent remote version lookup.
     *
     * @var array<string, mixed>|null
     */
    protected $remote_version;

    /**
     * Last error message encountered.
     *
     * @var string|null
     */
    protected $last_error;

    /**
     * Plugin basename derived from the plugin file.
     *
     * @var string
     */
    protected $plugin_basename;

    /**
     * Constructor.
     *
     * @param string               $plugin_file Absolute path to the main plugin file.
     * @param array<string, mixed> $settings    Updater settings sourced from the database.
     */
    public function __construct($plugin_file, array $settings) {
        $this->plugin_file     = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->settings        = wp_parse_args($settings, [
            'owner'       => '',
            'repo'        => '',
            'branch'      => 'main',
            'token'       => '',
            'channel'     => 'release',
            'cache_ttl'   => 15 * MINUTE_IN_SECONDS,
            'beta'        => false,
            'private'     => false,
        ]);
    }

    /**
     * Register WordPress hooks.
     */
    public function run() {
        if ($this->initialized || !$this->is_configured()) {
            if (!$this->initialized) {
                if (!$this->is_configured()) {
                    $this->maybe_record_notice('missing_repo', __('GitHub updater is not configured. Please provide an owner and repository.', 'gm2-wordpress-suite'));
                } elseif (!empty($this->settings['private']) && empty($this->settings['token'])) {
                    $this->maybe_record_notice('missing_token', __('GitHub access token is required to update from a private repository.', 'gm2-wordpress-suite'));
                }
            }

            $this->initialized = true;
            return;
        }

        if (!empty($this->settings['private']) && empty($this->settings['token'])) {
            $this->maybe_record_notice('missing_token', __('GitHub access token is required to update from a private repository.', 'gm2-wordpress-suite'));
        }

        $this->initialized = true;

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugins_api'], 10, 3);
        add_filter('http_request_args', [$this, 'authenticate_http'], 10, 2);
        add_filter('upgrader_pre_download', [$this, 'intercept_download'], 10, 4);
        add_action('gm2_github_updater_warmup', [$this, 'cron_warmup']);
        add_action('admin_notices', [$this, 'render_admin_notices']);
        add_action('wp_ajax_gm2_github_updater_refresh', [$this, 'handle_ajax_refresh']);

        if (defined('WP_CLI') && WP_CLI) {
            $this->register_cli_commands();
        }

        if (!wp_next_scheduled('gm2_github_updater_warmup')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'gm2_github_updater_warmup');
        }
    }

    /**
     * Determine if the updater has enough configuration to run.
     *
     * @return bool
     */
    protected function is_configured() {
        return (bool) ($this->settings['owner'] && $this->settings['repo']);
    }

    /**
     * Register CLI commands when WP-CLI is present.
     */
    protected function register_cli_commands() {
        $updater = $this;
        $command = new class($updater) {
            /**
             * @var Gm2_GitHub_Updater
             */
            protected $updater;

            public function __construct($updater) {
                $this->updater = $updater;
            }

            public function check($args, $assoc_args) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
                $result = $this->updater->refresh();
                if (is_wp_error($result)) {
                    WP_CLI::error($result->get_error_message());
                }
                WP_CLI::success(__('Update check completed.', 'gm2-wordpress-suite'));
            }

            public function update($args, $assoc_args) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
                $meta = $this->updater->refresh();
                if (is_wp_error($meta)) {
                    WP_CLI::error($meta->get_error_message());
                }
                if (empty($meta['package'])) {
                    WP_CLI::error(__('No download package URL found.', 'gm2-wordpress-suite'));
                }

                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
                $upgrader = new \Plugin_Upgrader();
                $result   = $upgrader->upgrade($this->updater->get_plugin_basename());

                if ($result instanceof WP_Error) {
                    WP_CLI::error($result->get_error_message());
                }

                if (false === $result) {
                    WP_CLI::error(__('Plugin upgrade failed.', 'gm2-wordpress-suite'));
                }

                WP_CLI::success(__('Plugin updated from GitHub.', 'gm2-wordpress-suite'));
            }
        };

        WP_CLI::add_command('gm2 updater', $command);
    }

    /**
     * Hooked into `pre_set_site_transient_update_plugins` to provide update data.
     *
     * @param object|array $transient Update transient.
     *
     * @return object|array
     */
    public function check_for_update($transient) {
        if (is_wp_error($transient)) {
            return $transient;
        }

        $meta = $this->get_remote_version();
        if (is_wp_error($meta)) {
            $this->set_last_error($meta->get_error_message());
            $this->maybe_record_notice('update_error', $this->last_error);
            return $transient;
        }

        if (!is_object($transient)) {
            $transient = new stdClass();
        }

        if (!isset($transient->response)) {
            $transient->response = [];
        }

        $current_version = $this->get_local_version();
        if ($meta && version_compare($meta['version'], $current_version, '>')) {
            $plugin              = new stdClass();
            $plugin->slug        = dirname($this->plugin_basename);
            $plugin->plugin      = $this->plugin_basename;
            $plugin->new_version = $meta['version'];
            $plugin->tested      = isset($meta['tested']) ? $meta['tested'] : '';
            $plugin->url         = isset($meta['changelog_url']) ? $meta['changelog_url'] : '';
            $plugin->package     = $meta['package'];
            $plugin->icons       = [];
            $transient->response[$this->plugin_basename] = $plugin;
        } else {
            unset($transient->response[$this->plugin_basename]);
        }

        return $transient;
    }

    /**
     * Provide plugin metadata in the WordPress plugins UI.
     *
     * @param false|object|array $result Result.
     * @param string             $action Action name.
     * @param object             $args   Arguments.
     *
     * @return mixed
     */
    public function plugins_api($result, $action, $args) {
        if ('plugin_information' !== $action || !isset($args->slug) || $args->slug !== dirname($this->plugin_basename)) {
            return $result;
        }

        $meta = $this->get_remote_version();
        if (is_wp_error($meta)) {
            return $meta;
        }

        $info              = new stdClass();
        $info->name        = 'Gm2 WordPress Suite';
        $info->slug        = dirname($this->plugin_basename);
        $info->version     = $meta['version'];
        $info->tested      = isset($meta['tested']) ? $meta['tested'] : '';
        $info->requires    = isset($meta['requires']) ? $meta['requires'] : '';
        $info->sections    = [
            'description' => !empty($meta['description']) ? $meta['description'] : '',
            'changelog'   => !empty($meta['changelog']) ? $meta['changelog'] : '',
        ];
        $info->download_link = $meta['package'];
        $info->last_updated  = isset($meta['last_updated']) ? $meta['last_updated'] : '';

        return $info;
    }

    /**
     * Inject HTTP headers for GitHub authenticated requests.
     *
     * @param array  $args Request args.
     * @param string $url  Request URL.
     *
     * @return array
     */
    public function authenticate_http($args, $url) {
        if (!$this->is_github_host($url)) {
            return $args;
        }

        if (!isset($args['headers'])) {
            $args['headers'] = [];
        }

        $args['headers']['User-Agent'] = 'Gm2WP-Updater';
        $token                          = trim((string) $this->settings['token']);
        if ($token !== '') {
            $args['headers']['Authorization'] = 'token ' . $token;
        }

        return $args;
    }

    /**
     * Ensure the download package inherits headers.
     *
     * @param bool        $reply   Whether to bail early.
     * @param array       $package Package data.
     * @param \Plugin_Upgrader $upgrader Upgrader instance.
     * @param array       $hook_extra Extra data.
     *
     * @return bool
     */
    public function intercept_download($reply, $package, $upgrader, $hook_extra) {
        if (empty($package) || !is_string($package)) {
            return $reply;
        }

        add_filter('http_request_args', [$this, 'authenticate_http'], 10, 2);

        if (is_wp_error($reply)) {
            $this->maybe_record_notice('download_error', sprintf(__('Download failed (%s).', 'gm2-wordpress-suite'), $reply->get_error_code()));
        }

        return $reply;
    }

    /**
     * Run the warmup cron event.
     */
    public function cron_warmup() {
        $this->log('Running GitHub updater warmup.');
        $meta = $this->get_remote_version(true);
        if (is_wp_error($meta)) {
            $this->log('Warmup failed: ' . $meta->get_error_message());
        }
    }

    /**
     * Clear cached metadata and backoff timers.
     */
    public function clear_cache() {
        $cache_key = $this->get_cache_key();
        wp_cache_delete($cache_key, 'gm2_github_updater');
        wp_cache_delete($cache_key . '_backoff', 'gm2_github_updater');
        $this->remote_version = null;
    }

    /**
     * Retrieve remote metadata.
     *
     * @param bool $force Force refresh.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function get_remote_version($force = false) {
        if (!$force && $this->remote_version !== null) {
            return $this->remote_version;
        }

        $cache_key = $this->get_cache_key();
        if (!$force) {
            $cached = wp_cache_get($cache_key, 'gm2_github_updater');
            if ($cached !== false) {
                $this->remote_version = $cached;
                return $cached;
            }
        }

        $backoff_until = (int) wp_cache_get($cache_key . '_backoff', 'gm2_github_updater');
        if ($backoff_until && $backoff_until > time()) {
            $message = __('GitHub API temporarily rate limited. Please try again later.', 'gm2-wordpress-suite');
            $this->set_last_error($message);
            return new WP_Error('gm2_updater_backoff', $message);
        }

        $endpoint = $this->settings['channel'] === 'branch'
            ? sprintf('https://api.github.com/repos/%1$s/%2$s/commits/%3$s', rawurlencode($this->settings['owner']), rawurlencode($this->settings['repo']), rawurlencode($this->settings['branch']))
            : sprintf('https://api.github.com/repos/%1$s/%2$s/releases/latest', rawurlencode($this->settings['owner']), rawurlencode($this->settings['repo']));

        $args = [
            'timeout' => 20,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'Gm2WP-Updater',
            ],
        ];

        $token = trim((string) $this->settings['token']);
        if ($token !== '') {
            $args['headers']['Authorization'] = 'token ' . $token;
        }

        $response = wp_remote_get($endpoint, $args);
        if (is_wp_error($response)) {
            $this->set_last_error($response->get_error_message());
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if (403 === $code) {
            $retry_after = (int) wp_remote_retrieve_header($response, 'Retry-After');
            if ($retry_after <= 0) {
                $retry_after = HOUR_IN_SECONDS;
            }
            $backoff = time() + min(DAY_IN_SECONDS, max(HOUR_IN_SECONDS, $retry_after * 2));
            wp_cache_set($cache_key . '_backoff', $backoff, 'gm2_github_updater', $retry_after * 2);
            $message = __('GitHub API rate limit exceeded.', 'gm2-wordpress-suite');
            $this->set_last_error($message);
            return new WP_Error('gm2_updater_rate_limit', $message);
        }

        if ($code < 200 || $code >= 300) {
            $message = sprintf(__('GitHub API request failed (HTTP %d).', 'gm2-wordpress-suite'), $code);
            $this->set_last_error($message);
            return new WP_Error('gm2_updater_http_error', __('GitHub API request failed.', 'gm2-wordpress-suite'));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return new WP_Error('gm2_updater_invalid_json', __('Invalid response from GitHub API.', 'gm2-wordpress-suite'));
        }

        $meta = $this->normalize_metadata($data);
        if (is_wp_error($meta)) {
            $this->set_last_error($meta->get_error_message());
            return $meta;
        }

        $ttl = absint($this->settings['cache_ttl']);
        if ($ttl <= 0) {
            $ttl = 15 * MINUTE_IN_SECONDS;
        }

        wp_cache_set($cache_key, $meta, 'gm2_github_updater', $ttl);
        $this->remote_version = $meta;

        return $meta;
    }

    /**
     * Normalise GitHub API payload into our metadata array.
     *
     * @param array<string, mixed> $data API data.
     *
     * @return array<string, mixed>|WP_Error
     */
    protected function normalize_metadata(array $data) {
        $meta = [
            'version'       => '',
            'description'   => '',
            'changelog'     => '',
            'tested'        => '',
            'requires'      => '',
            'package'       => '',
            'changelog_url' => '',
            'last_updated'  => '',
        ];

        if ($this->settings['channel'] === 'branch') {
            if (empty($data['sha'])) {
                $this->maybe_record_notice('invalid_branch', __('Unable to resolve branch commit.', 'gm2-wordpress-suite'));
                return new WP_Error('gm2_updater_invalid_branch', __('Unable to resolve branch commit.', 'gm2-wordpress-suite'));
            }
            $date = isset($data['commit']['committer']['date']) ? strtotime($data['commit']['committer']['date']) : time();
            $meta['version']      = gmdate('Y.m.d', $date) . '-' . substr($data['sha'], 0, 7);
            $meta['last_updated'] = gmdate('c', $date);
            $meta['package']      = $this->download_package_url($data['sha']);
            $meta['description']  = !empty($data['commit']['message']) ? $data['commit']['message'] : '';
        } else {
            if (empty($data['tag_name']) && empty($data['name'])) {
                $this->maybe_record_notice('invalid_release', __('Release is missing a version tag.', 'gm2-wordpress-suite'));
                return new WP_Error('gm2_updater_invalid_release', __('Release is missing a version tag.', 'gm2-wordpress-suite'));
            }
            $version = !empty($data['tag_name']) ? ltrim((string) $data['tag_name'], 'v') : (string) $data['name'];
            $meta['version']       = $version;
            if (!empty($data['zipball_url'])) {
                $meta['package'] = $data['zipball_url'];
            } else {
                $meta['package'] = $this->download_package_url(isset($data['tag_name']) ? $data['tag_name'] : '');
            }
            $meta['description']   = isset($data['body']) ? $data['body'] : '';
            $meta['changelog']     = isset($data['body']) ? $data['body'] : '';
            $meta['changelog_url'] = isset($data['html_url']) ? $data['html_url'] : '';
            $meta['last_updated']  = isset($data['published_at']) ? $data['published_at'] : '';
        }

        if (empty($meta['package'])) {
            $meta['package'] = $this->download_package_url();
        }

        $readme = $this->fetch_readme();
        if ($readme) {
            $meta['tested']   = isset($readme['tested']) ? $readme['tested'] : $meta['tested'];
            $meta['requires'] = isset($readme['requires']) ? $readme['requires'] : $meta['requires'];
            if (!$meta['description'] && isset($readme['description'])) {
                $meta['description'] = $readme['description'];
            }
            if (!$meta['changelog'] && isset($readme['changelog'])) {
                $meta['changelog'] = $readme['changelog'];
            }
        }

        if (empty($meta['version'])) {
            $meta['version'] = gmdate('YmdHi') . '-dev';
        }

        return $meta;
    }

    /**
     * Determine the download URL for the plugin package.
     *
     * @param string $ref Optional reference.
     *
     * @return string
     */
    public function download_package_url($ref = '') {
        $owner = rawurlencode($this->settings['owner']);
        $repo  = rawurlencode($this->settings['repo']);

        if ($this->settings['channel'] === 'branch') {
            $branch = $ref !== '' ? $ref : $this->settings['branch'];
            return sprintf('https://api.github.com/repos/%s/%s/zipball/%s', $owner, $repo, rawurlencode($branch));
        }

        if ($ref === '') {
            $ref = $this->settings['channel'] === 'release' ? 'HEAD' : $this->settings['branch'];
        }

        if (preg_match('/^v?\d+\.\d+\.\d+.*$/', $ref)) {
            return sprintf('https://api.github.com/repos/%s/%s/zipball/%s', $owner, $repo, rawurlencode($ref));
        }

        return sprintf('https://api.github.com/repos/%s/%s/zipball/%s', $owner, $repo, rawurlencode($ref));
    }

    /**
     * Fetch readme metadata to enrich plugin information.
     *
     * @return array<string, string>
     */
    protected function fetch_readme() {
        $owner = rawurlencode($this->settings['owner']);
        $repo  = rawurlencode($this->settings['repo']);
        $ref   = $this->settings['channel'] === 'release' ? 'HEAD' : $this->settings['branch'];
        $url   = sprintf('https://raw.githubusercontent.com/%s/%s/%s/readme.txt', $owner, $repo, $ref);

        $args = [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Gm2WP-Updater',
            ],
        ];

        $token = trim((string) $this->settings['token']);
        if ($token !== '') {
            $args['headers']['Authorization'] = 'token ' . $token;
        }

        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            return [];
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        if ($body === '') {
            return [];
        }

        $result = [
            'tested'      => '',
            'requires'    => '',
            'description' => '',
            'changelog'   => '',
        ];

        if (preg_match('/Tested up to:\s*(.+)/i', $body, $matches)) {
            $result['tested'] = trim($matches[1]);
        }
        if (preg_match('/Requires at least:\s*(.+)/i', $body, $matches)) {
            $result['requires'] = trim($matches[1]);
        }
        if (preg_match('/==\s*Description\s*==(.+?)==/is', $body, $matches)) {
            $result['description'] = trim($matches[1]);
        }
        if (preg_match('/==\s*Changelog\s*==(.+)/is', $body, $matches)) {
            $result['changelog'] = trim($matches[1]);
        }

        return $result;
    }

    /**
     * Get the installed plugin version.
     *
     * @return string
     */
    protected function get_local_version() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $data = get_plugin_data($this->plugin_file, false, false);
        return isset($data['Version']) ? $data['Version'] : '0.0.0';
    }

    /**
     * Determine if the URL targets GitHub.
     *
     * @param string $url URL.
     *
     * @return bool
     */
    protected function is_github_host($url) {
        return (bool) preg_match('~https?://([^/]+\.)?github\.com/~i', $url) || (bool) preg_match('~https?://api\.github\.com/~i', $url);
    }

    /**
     * Download metadata cache key.
     *
     * @return string
     */
    protected function get_cache_key() {
        return 'gm2_updater_' . md5($this->settings['owner'] . '/' . $this->settings['repo'] . '|' . $this->settings['branch'] . '|' . $this->settings['channel']);
    }

    /**
     * Record an admin notice for later output.
     *
     * @param string $code    Notice code.
     * @param string $message Message.
     */
    protected function maybe_record_notice($code, $message) {
        $notices = get_option('gm2_github_updater_notices', []);
        if (!is_array($notices)) {
            $notices = [];
        }
        $notices[$code] = sanitize_text_field($message);
        update_option('gm2_github_updater_notices', $notices, false);
    }

    /**
     * Output admin notices captured earlier.
     */
    public function render_admin_notices() {
        $notices = get_option('gm2_github_updater_notices', []);
        if (empty($notices) || !is_array($notices)) {
            return;
        }

        foreach ($notices as $code => $message) {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
        }

        delete_option('gm2_github_updater_notices');
    }

    /**
     * Warmup helper exposed for AJAX/CLI.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function refresh() {
        $this->clear_cache();
        $meta = $this->get_remote_version(true);
        if (is_wp_error($meta)) {
            return $meta;
        }
        $this->check_for_update(new stdClass());
        return $meta;
    }

    /**
     * AJAX handler to refresh metadata on demand.
     */
    public function handle_ajax_refresh() {
        if (!current_user_can('update_plugins')) {
            wp_send_json_error(['message' => __('You do not have permission to run this action.', 'gm2-wordpress-suite')], 403);
        }

        $result = $this->refresh();
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 500);
        }

        wp_send_json_success(['version' => $result['version']]);
    }

    /**
     * Retrieve the plugin basename.
     *
     * @return string
     */
    public function get_plugin_basename() {
        return $this->plugin_basename;
    }

    /**
     * Get the last recorded error message.
     *
     * @return string|null
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Update the last error property with sanitized message.
     *
     * @param string $message Message.
     */
    protected function set_last_error($message) {
        $this->last_error = sanitize_text_field($message);
    }

    /**
     * Simple logging wrapper respecting gm2_updater_enable_logging.
     *
     * @param string $message Log message.
     */
    protected function log($message) {
        if (!apply_filters('gm2_updater_enable_logging', false) && !get_option('gm2_updater_enable_logging')) {
            return;
        }
        if (function_exists('error_log')) {
            error_log('[Gm2_GitHub_Updater] ' . $message);
        }
    }
}

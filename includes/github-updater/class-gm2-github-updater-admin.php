<?php
namespace Gm2\Updater;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin controller for the GitHub updater integration.
 */
class Gm2_GitHub_Updater_Admin {
    public const OPTION_KEY = 'gm2_github_updater_settings';
    protected const SETTINGS_GROUP = 'gm2_github_updater';
    protected const MENU_SLUG = 'gm2-github-updater';
    protected const AJAX_TEST = 'gm2_github_updater_test';
    protected const AJAX_CHECK = 'gm2_github_updater_check';
    protected const NONCE_ACTION = 'gm2_github_updater_actions';

    /**
     * Currently configured cron interval in seconds.
     *
     * @var int
     */
    protected $interval_seconds = 3600;

    public function __construct() {
        $this->interval_seconds = $this->determine_interval_seconds();
    }

    /**
     * Register hooks for the admin controller.
     */
    public function hooks() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_notices', [$this, 'render_settings_notices']);
        add_action('wp_ajax_' . self::AJAX_TEST, [$this, 'ajax_test_connection']);
        add_action('wp_ajax_' . self::AJAX_CHECK, [$this, 'ajax_check_now']);
        add_action('updated_option', [$this, 'handle_option_update'], 10, 3);
        add_action('added_option', [$this, 'handle_option_add'], 10, 2);
        add_filter('cron_schedules', [$this, 'filter_cron_schedules']);
    }

    /**
     * Register submenu entry for the updater settings.
     */
    public function register_menu() {
        add_submenu_page(
            'gm2',
            esc_html__('GitHub Updater', 'gm2-wordpress-suite'),
            esc_html__('GitHub Updater', 'gm2-wordpress-suite'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_page']
        );
    }

    /**
     * Register the updater option and sanitization callback.
     */
    public function register_settings() {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_KEY,
            [
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default'           => [],
                'autoload'          => false,
            ]
        );
    }

    /**
     * Sanitize and persist updater settings.
     *
     * @param array<string, mixed>|null $input Raw input.
     *
     * @return array<string, mixed>
     */
    public function sanitize_settings($input) {
        $input    = is_array($input) ? $input : [];
        $existing = get_option(self::OPTION_KEY, []);
        if (!is_array($existing)) {
            $existing = [];
        }

        $sanitized = [
            'owner'          => '',
            'repo'           => '',
            'channel'        => 'release',
            'branch'         => 'main',
            'token'          => '',
            'token_keep'     => '',
            'check_interval' => 60,
        ];

        $sanitized['owner'] = isset($input['owner']) ? sanitize_text_field(trim((string) $input['owner'])) : '';
        $sanitized['repo']  = isset($input['repo']) ? sanitize_text_field(trim((string) $input['repo'])) : '';

        $channel = isset($input['channel']) ? sanitize_text_field((string) $input['channel']) : 'release';
        $sanitized['channel'] = in_array($channel, ['release', 'branch'], true) ? $channel : 'release';

        $branch = isset($input['branch']) ? sanitize_text_field(trim((string) $input['branch'])) : 'main';
        $sanitized['branch'] = $branch !== '' ? $branch : 'main';

        $allowed_intervals = [5, 15, 30, 60, 120, 360, 720, 1440];
        $interval          = isset($input['check_interval']) ? absint($input['check_interval']) : 60;
        if (!in_array($interval, $allowed_intervals, true)) {
            $interval = 60;
        }
        $sanitized['check_interval'] = $interval;

        $keep_existing = !empty($input['token_keep']);
        $token_value   = isset($input['token']) ? sanitize_text_field(trim((string) $input['token'])) : '';

        if ($keep_existing && empty($token_value)) {
            $sanitized['token'] = isset($existing['token']) ? (string) $existing['token'] : '';
        } else {
            if ($token_value !== '') {
                $sanitized['token'] = self::obfuscate_token($token_value);
            } else {
                $sanitized['token'] = '';
            }
        }

        if ($sanitized['owner'] === '' || $sanitized['repo'] === '') {
            add_settings_error(
                self::OPTION_KEY,
                'gm2_github_updater_missing_repo',
                esc_html__('Both the owner and repository fields are required.', 'gm2-wordpress-suite'),
                'error'
            );
        }

        $this->interval_seconds = max(5, $sanitized['check_interval']) * MINUTE_IN_SECONDS;

        unset($sanitized['token_keep']);

        return $sanitized;
    }

    /**
     * Enqueue assets for the settings screen.
     *
     * @param string $hook Current hook suffix.
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_gm2' && $hook !== 'gm2_page_' . self::MENU_SLUG) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'gm2_page_' . self::MENU_SLUG) {
            return;
        }

        wp_enqueue_script(
            'gm2-github-updater-admin',
            GM2_PLUGIN_URL . 'assets/js/github-updater-admin.js',
            ['jquery'],
            defined('GM2_VERSION') ? GM2_VERSION : '1.0.0',
            true
        );

        wp_localize_script(
            'gm2-github-updater-admin',
            'gm2GitHubUpdaterAdmin',
            [
                'optionKey' => self::OPTION_KEY,
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce(self::NONCE_ACTION),
                'i18n'    => [
                    'testing'      => esc_html__('Testing connection…', 'gm2-wordpress-suite'),
                    'checking'     => esc_html__('Checking for updates…', 'gm2-wordpress-suite'),
                    'testSuccess'  => esc_html__('Connection successful.', 'gm2-wordpress-suite'),
                    'checkSuccess' => esc_html__('Update metadata refreshed.', 'gm2-wordpress-suite'),
                    'unknownError' => esc_html__('An unexpected error occurred.', 'gm2-wordpress-suite'),
                    'showToken'    => esc_html__('Reveal Token Field', 'gm2-wordpress-suite'),
                    'hideToken'    => esc_html__('Hide Token Field', 'gm2-wordpress-suite'),
                    'defaultBranch'=> esc_html__('Default branch: %s', 'gm2-wordpress-suite'),
                    'privateRepo'  => esc_html__('Private repository', 'gm2-wordpress-suite'),
                    'versionLabel' => esc_html__('Version: %s', 'gm2-wordpress-suite'),
                    'updatedLabel' => esc_html__('Published: %s', 'gm2-wordpress-suite'),
                    'dismiss'      => esc_html__('Dismiss this notice.', 'gm2-wordpress-suite'),
                ],
            ]
        );
    }

    /**
     * Render settings errors and updater notices on the settings page.
     */
    public function render_settings_notices() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'gm2_page_' . self::MENU_SLUG) {
            return;
        }

        settings_errors(self::OPTION_KEY);

        $notices = get_option('gm2_github_updater_notices', []);
        if (is_array($notices)) {
            foreach ($notices as $message) {
                printf(
                    '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                    esc_html($message)
                );
            }
            if (!empty($notices)) {
                delete_option('gm2_github_updater_notices');
            }
        }

        $updater = gm2_github_updater();
        if ($updater instanceof Gm2_GitHub_Updater) {
            $last_error = $updater->get_last_error();
            if ($last_error) {
                printf(
                    '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                    esc_html($last_error)
                );
            }
        }
    }

    /**
     * Render the updater settings page.
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings   = get_option(self::OPTION_KEY, []);
        $has_token  = !empty($settings['token']);
        $owner      = isset($settings['owner']) ? $settings['owner'] : '';
        $repo       = isset($settings['repo']) ? $settings['repo'] : '';
        $channel    = isset($settings['channel']) ? $settings['channel'] : 'release';
        $branch     = isset($settings['branch']) ? $settings['branch'] : 'main';
        $interval   = isset($settings['check_interval']) ? (int) $settings['check_interval'] : 60;
        $intervals  = [5, 15, 30, 60, 120, 360, 720, 1440];
        if (!in_array($interval, $intervals, true)) {
            $interval = 60;
        }
        $token_keep = $has_token ? '1' : '0';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('GitHub Updater', 'gm2-wordpress-suite'); ?></h1>
            <form method="post" action="options.php" id="gm2-github-updater-form">
                <?php
                settings_fields(self::SETTINGS_GROUP);
                ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="gm2-github-owner"><?php esc_html_e('Repository Owner', 'gm2-wordpress-suite'); ?></label></th>
                            <td>
                                <input type="text" id="gm2-github-owner" name="<?php echo esc_attr(self::OPTION_KEY); ?>[owner]" value="<?php echo esc_attr($owner); ?>" class="regular-text" autocomplete="off" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="gm2-github-repo"><?php esc_html_e('Repository Name', 'gm2-wordpress-suite'); ?></label></th>
                            <td>
                                <input type="text" id="gm2-github-repo" name="<?php echo esc_attr(self::OPTION_KEY); ?>[repo]" value="<?php echo esc_attr($repo); ?>" class="regular-text" autocomplete="off" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Channel', 'gm2-wordpress-suite'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio" name="<?php echo esc_attr(self::OPTION_KEY); ?>[channel]" value="release" <?php checked($channel, 'release'); ?> />
                                        <?php esc_html_e('Latest Release', 'gm2-wordpress-suite'); ?>
                                    </label><br />
                                    <label>
                                        <input type="radio" name="<?php echo esc_attr(self::OPTION_KEY); ?>[channel]" value="branch" <?php checked($channel, 'branch'); ?> />
                                        <?php esc_html_e('Specific Branch', 'gm2-wordpress-suite'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr id="gm2-github-branch-row">
                            <th scope="row"><label for="gm2-github-branch"><?php esc_html_e('Branch', 'gm2-wordpress-suite'); ?></label></th>
                            <td>
                                <input type="text" id="gm2-github-branch" name="<?php echo esc_attr(self::OPTION_KEY); ?>[branch]" value="<?php echo esc_attr($branch); ?>" class="regular-text" autocomplete="off" />
                                <p class="description"><?php esc_html_e('Used when the channel is set to Branch.', 'gm2-wordpress-suite'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Access Token', 'gm2-wordpress-suite'); ?></th>
                            <td>
                                <input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[token_keep]" id="gm2-github-token-keep" value="<?php echo esc_attr($token_keep); ?>" />
                                <button type="button" class="button" id="gm2-github-token-toggle" aria-expanded="false">
                                    <?php echo $has_token ? esc_html__('Reveal Token Field', 'gm2-wordpress-suite') : esc_html__('Add Token', 'gm2-wordpress-suite'); ?>
                                </button>
                                <div id="gm2-github-token-field" class="gm2-github-token-field" aria-hidden="true" style="display:none;">
                                    <label for="gm2-github-token" class="screen-reader-text"><?php esc_html_e('GitHub Access Token', 'gm2-wordpress-suite'); ?></label>
                                    <input type="password" id="gm2-github-token" name="<?php echo esc_attr(self::OPTION_KEY); ?>[token]" value="" class="regular-text" autocomplete="new-password" disabled />
                                    <p class="description"><?php esc_html_e('Tokens are stored in an encrypted format. Leave blank to keep the existing token or save an empty field to remove it.', 'gm2-wordpress-suite'); ?></p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="gm2-github-interval"><?php esc_html_e('Update Check Interval', 'gm2-wordpress-suite'); ?></label></th>
                            <td>
                                <select id="gm2-github-interval" name="<?php echo esc_attr(self::OPTION_KEY); ?>[check_interval]">
                                    <?php
                                    foreach ($intervals as $minutes) {
                                        printf(
                                            '<option value="%1$d" %2$s>%3$s</option>',
                                            (int) $minutes,
                                            selected($interval, $minutes, false),
                                            esc_html(sprintf(_n('%d minute', '%d minutes', $minutes, 'gm2-wordpress-suite'), $minutes))
                                        );
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save', 'gm2-wordpress-suite'); ?></button>
                    <button type="button" class="button" id="gm2-github-test" data-action="<?php echo esc_attr(self::AJAX_TEST); ?>"><?php esc_html_e('Test Connection', 'gm2-wordpress-suite'); ?></button>
                    <button type="button" class="button" id="gm2-github-check" data-action="<?php echo esc_attr(self::AJAX_CHECK); ?>"><?php esc_html_e('Check Now', 'gm2-wordpress-suite'); ?></button>
                </p>
            </form>
            <div id="gm2-github-updater-feedback" aria-live="polite"></div>
        </div>
        <?php
    }

    /**
     * Handle "Test Connection" AJAX requests.
     */
    public function ajax_test_connection() {
        $this->verify_ajax_permissions();

        $settings = self::prepare_settings_for_runtime(get_option(self::OPTION_KEY, []));
        if (empty($settings['owner']) || empty($settings['repo'])) {
            wp_send_json_error(['message' => esc_html__('Please configure the owner and repository first.', 'gm2-wordpress-suite')], 400);
        }

        $endpoint = sprintf('https://api.github.com/repos/%1$s/%2$s', rawurlencode($settings['owner']), rawurlencode($settings['repo']));
        $args     = [
            'timeout' => 15,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'Gm2WP-Updater',
            ],
        ];
        if (!empty($settings['token'])) {
            $args['headers']['Authorization'] = 'token ' . $settings['token'];
        }

        $response = wp_remote_get($endpoint, $args);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $message = sprintf(
                esc_html__('GitHub responded with HTTP %d.', 'gm2-wordpress-suite'),
                $code
            );
            if ($code === 404) {
                $message = esc_html__('Repository not found. Check the owner, repository name, and token.', 'gm2-wordpress-suite');
            }
            wp_send_json_error(['message' => $message], $code);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            wp_send_json_success(['repository' => []]);
        }

        $payload = [
            'full_name'      => isset($data['full_name']) ? sanitize_text_field($data['full_name']) : '',
            'default_branch' => isset($data['default_branch']) ? sanitize_text_field($data['default_branch']) : '',
            'private'        => !empty($data['private']),
            'description'    => isset($data['description']) ? sanitize_text_field($data['description']) : '',
        ];

        wp_send_json_success(['repository' => $payload]);
    }

    /**
     * Handle "Check Now" AJAX requests.
     */
    public function ajax_check_now() {
        $this->verify_ajax_permissions();

        $updater = gm2_github_updater(true);
        if (!$updater instanceof Gm2_GitHub_Updater) {
            wp_send_json_error(['message' => esc_html__('The updater is not configured.', 'gm2-wordpress-suite')], 400);
        }

        $meta = $updater->refresh();
        if ($meta instanceof WP_Error) {
            wp_send_json_error(['message' => $meta->get_error_message()], 500);
        }

        $response = [
            'version'      => isset($meta['version']) ? sanitize_text_field($meta['version']) : '',
            'description'  => isset($meta['description']) ? sanitize_textarea_field($meta['description']) : '',
            'last_updated' => isset($meta['last_updated']) ? sanitize_text_field($meta['last_updated']) : '',
            'package'      => isset($meta['package']) ? esc_url_raw($meta['package']) : '',
        ];

        wp_send_json_success(['metadata' => $response]);
    }

    /**
     * Verify nonce and capability for AJAX callbacks.
     */
    protected function verify_ajax_permissions() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('You do not have permission to perform this action.', 'gm2-wordpress-suite')], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');
    }

    /**
     * React when the updater option is updated.
     *
     * @param string $option    Option name.
     * @param mixed  $old_value Old value.
     * @param mixed  $value     New value.
     */
    public function handle_option_update($option, $old_value, $value) {
        if ($option !== self::OPTION_KEY) {
            return;
        }

        $this->after_settings_saved($value);
    }

    /**
     * React when the updater option is first added.
     *
     * @param string $option Option name.
     * @param mixed  $value  Option value.
     */
    public function handle_option_add($option, $value) {
        if ($option !== self::OPTION_KEY) {
            return;
        }

        $this->after_settings_saved($value);
    }

    /**
     * Run follow-up tasks after saving settings.
     *
     * @param mixed $value Saved value.
     */
    protected function after_settings_saved($value) {
        if (!is_array($value)) {
            return;
        }

        $runtime = self::prepare_settings_for_runtime($value);
        $this->interval_seconds = max(5, $runtime['check_interval']) * MINUTE_IN_SECONDS;

        if (empty($runtime['owner']) || empty($runtime['repo'])) {
            wp_clear_scheduled_hook('gm2_github_updater_warmup');
            return;
        }

        $updater = gm2_github_updater(true);
        if ($updater instanceof Gm2_GitHub_Updater) {
            $updater->clear_cache();
            $updater->run();
        }

        $this->reschedule_warmup($runtime['check_interval']);
    }

    /**
     * Reschedule the updater warmup cron according to the interval.
     *
     * @param int $minutes Interval in minutes.
     */
    protected function reschedule_warmup($minutes) {
        $minutes = max(5, absint($minutes));
        $seconds = $minutes * MINUTE_IN_SECONDS;

        wp_clear_scheduled_hook('gm2_github_updater_warmup');
        wp_schedule_event(time() + $seconds, 'gm2_github_updater_interval', 'gm2_github_updater_warmup');
    }

    /**
     * Register the custom cron interval used by the updater.
     *
     * @param array<string, array<string, mixed>> $schedules Existing schedules.
     *
     * @return array<string, array<string, mixed>>
     */
    public function filter_cron_schedules($schedules) {
        $seconds = $this->interval_seconds;
        $schedules['gm2_github_updater_interval'] = [
            'interval' => $seconds,
            'display'  => sprintf(
                /* translators: %d: interval in minutes */
                esc_html__('Gm2 GitHub Updater (%d minutes)', 'gm2-wordpress-suite'),
                max(1, (int) round($seconds / MINUTE_IN_SECONDS))
            ),
        ];

        return $schedules;
    }

    /**
     * Prepare settings for runtime usage by the updater.
     *
     * @param array<string, mixed> $settings Stored settings.
     *
     * @return array<string, mixed>
     */
    public static function prepare_settings_for_runtime(array $settings) {
        $defaults = [
            'owner'          => '',
            'repo'           => '',
            'channel'        => 'release',
            'branch'         => 'main',
            'token'          => '',
            'check_interval' => 60,
        ];

        $settings = wp_parse_args($settings, $defaults);
        if (!in_array($settings['channel'], ['release', 'branch'], true)) {
            $settings['channel'] = 'release';
        }
        $settings['token'] = self::reveal_token(isset($settings['token']) ? (string) $settings['token'] : '');
        $settings['check_interval'] = max(5, absint($settings['check_interval']));
        $settings['cache_ttl']      = $settings['check_interval'] * MINUTE_IN_SECONDS;

        return $settings;
    }

    /**
     * Determine the interval seconds from stored settings.
     *
     * @return int
     */
    protected function determine_interval_seconds() {
        $settings = get_option(self::OPTION_KEY, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        if (isset($settings['check_interval'])) {
            $minutes = max(5, absint($settings['check_interval']));
        } else {
            $minutes = 60;
        }

        return $minutes * MINUTE_IN_SECONDS;
    }

    /**
     * Obfuscate the supplied token for storage.
     *
     * @param string $token Token string.
     *
     * @return string
     */
    public static function obfuscate_token($token) {
        $token = trim((string) $token);
        if ($token === '') {
            return '';
        }

        $key = hash('sha256', wp_salt('gm2_github_updater'), true);
        $iv  = substr(hash('sha256', wp_salt('gm2_github_updater_iv')), 0, 16);

        $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
        if ($encrypted === false) {
            return '';
        }

        return base64_encode($encrypted);
    }

    /**
     * Decrypt a stored token.
     *
     * @param string $token Stored token value.
     *
     * @return string
     */
    public static function reveal_token($token) {
        $token = trim((string) $token);
        if ($token === '') {
            return '';
        }

        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return '';
        }

        $key = hash('sha256', wp_salt('gm2_github_updater'), true);
        $iv  = substr(hash('sha256', wp_salt('gm2_github_updater_iv')), 0, 16);

        $decrypted = openssl_decrypt($decoded, 'AES-256-CBC', $key, 0, $iv);
        if ($decrypted === false) {
            return '';
        }

        return $decrypted;
    }
}

<?php
namespace Gm2\NetworkPayload;

if (!defined('ABSPATH')) {
    exit;
}

class Module {
    private const OPTION_KEY     = 'gm2_netpayload_settings';
    private const STATS_KEY      = 'gm2_netpayload_stats';
    private const REGEN_STATE_KEY = 'gm2_np_regen_state';

    private static bool $booted = false;
    private static string $page_hook = '';

    private static array $defaults = [
        'nextgen_images'   => true,
        'webp'             => true,
        'avif'             => true,
        'no_originals'     => false,
        'big_image_cap'    => 2560,
        'gzip_detection'   => 'detect',
        'smart_lazyload'   => true,
        'asset_budget'     => true,
    ];

    /**
     * Boot the module once.
     */
    public static function boot(): void {
        if (self::$booted || did_action('gm2_netpayload_boot')) {
            return;
        }
        self::$booted = true;
        do_action('gm2_netpayload_boot');

        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_menu', [__CLASS__, 'register_admin_page']);
        add_action('network_admin_menu', [__CLASS__, 'register_admin_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_route']);
        add_action('admin_notices', [__CLASS__, 'maybe_show_missing_notice']);
        add_action('network_admin_notices', [__CLASS__, 'maybe_show_missing_notice']);
        add_action('gm2_np_regen_batch', [__CLASS__, 'process_regen_batch']);

        if (self::any_feature_enabled()) {
            add_action('init', [__CLASS__, 'maybe_run_features']);
        }
    }

    /** Register option for settings. */
    public static function register_settings(): void {
        register_setting('gm2_netpayload', self::OPTION_KEY, [
            'type'      => 'array',
            'default'   => self::$defaults,
            'show_in_rest' => false,
            'autoload'  => false,
        ]);
    }

    /** Determine if any toggle is enabled. */
    private static function any_feature_enabled(): bool {
        $opts = self::get_settings();
        return !empty(array_filter([
            $opts['nextgen_images'],
            $opts['gzip_detection'] !== 'off',
            $opts['smart_lazyload'],
            $opts['asset_budget'],
        ]));
    }

    /** Placeholder for real feature hooks; gated. */
    public static function maybe_run_features(): void {
        if (!self::any_feature_enabled()) {
            return;
        }
        $opts = self::get_settings();
        if (!empty($opts['nextgen_images'])) {
            add_filter('wp_generate_attachment_metadata', [__CLASS__, 'add_nextgen_variants'], 10, 2);
            add_filter('big_image_size_threshold', [__CLASS__, 'filter_big_image_cap'], 10, 4);
        }
        // Actual feature hooks would be added here.
    }

    /** Start background regeneration of next-gen images. */
    public static function start_regeneration(bool $only_missing = true): void {
        update_option(self::REGEN_STATE_KEY, ['offset' => 0, 'only_missing' => $only_missing], false);
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('gm2_np_regen_batch');
        } else {
            wp_schedule_single_event(time() + 1, 'gm2_np_regen_batch');
        }
    }

    /** Process a batch of attachment regeneration. */
    public static function process_regen_batch(): void {
        $state = get_option(self::REGEN_STATE_KEY);
        if (!is_array($state)) {
            return;
        }
        $offset       = intval($state['offset'] ?? 0);
        $only_missing = !empty($state['only_missing']);
        $args = [
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => 20,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'offset'         => $offset,
        ];
        $ids = get_posts($args);
        if (!$ids) {
            delete_option(self::REGEN_STATE_KEY);
            return;
        }
        foreach ($ids as $id) {
            self::regenerate_attachment($id, $only_missing);
            $offset++;
        }
        $state['offset'] = $offset;
        update_option(self::REGEN_STATE_KEY, $state, false);
        if (count($ids) === intval($args['posts_per_page'])) {
            if (function_exists('as_enqueue_async_action')) {
                as_enqueue_async_action('gm2_np_regen_batch');
            } else {
                wp_schedule_single_event(time() + 1, 'gm2_np_regen_batch');
            }
        } else {
            delete_option(self::REGEN_STATE_KEY);
        }
    }

    /** Regenerate next-gen files for a single attachment. */
    private static function regenerate_attachment(int $attachment_id, bool $only_missing): bool {
        $meta = wp_get_attachment_metadata($attachment_id);
        if (!is_array($meta)) {
            return false;
        }
        if ($only_missing && !empty($meta['gm2_nextgen']['full'])) {
            $opts = self::get_settings();
            $needs = false;
            foreach (['webp', 'avif'] as $fmt) {
                if (!empty($opts[$fmt]) && empty($meta['gm2_nextgen']['full'][$fmt])) {
                    $needs = true;
                    break;
                }
            }
            if (!$needs) {
                return false;
            }
        }
        $meta = self::add_nextgen_variants($meta, $attachment_id);
        wp_update_attachment_metadata($attachment_id, $meta);
        return true;
    }

    /** Regenerate all images, optionally only those missing variants. */
    public static function regenerate_all_images(bool $only_missing = true, ?callable $progress = null): int {
        $args = [
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'offset'         => 0,
        ];
        $count = 0;
        do {
            $ids = get_posts($args);
            foreach ($ids as $id) {
                if (self::regenerate_attachment($id, $only_missing)) {
                    $count++;
                }
                if ($progress) {
                    $progress($id);
                }
            }
            $args['offset'] += $args['posts_per_page'];
        } while ($ids);
        return $count;
    }

    /**
     * Generate next-gen image formats for intermediate sizes.
     */
    public static function add_nextgen_variants(array $metadata, int $attachment_id): array {
        if (empty($metadata['sizes']) || !wp_attachment_is_image($attachment_id)) {
            return $metadata;
        }

        $opts        = self::get_settings();
        $editor_args = ['methods' => ['Imagick', 'GD']];
        $supports_webp = !empty($opts['webp']) && wp_image_editor_supports(['mime_type' => 'image/webp'] + $editor_args);
        $supports_avif = !empty($opts['avif']) && wp_image_editor_supports(['mime_type' => 'image/avif'] + $editor_args);

        if (!$supports_webp && !$supports_avif) {
            return $metadata;
        }

        $file     = get_attached_file($attachment_id);
        $base_dir = dirname($file);

        if (empty($opts['no_originals']) && file_exists($file)) {
            $type     = wp_check_filetype($file);
            $mime     = $type['type'];
            $lossless = true;
            if ($mime === 'image/png') {
                $is_large  = filesize($file) > 500 * 1024;
                $has_alpha = self::png_has_alpha($file);
                if ($is_large && !$has_alpha) {
                    $lossless = false;
                }
            }
            if ($supports_webp) {
                $webp_path = preg_replace('/\.[^\.]+$/', '.webp', $file);
                $editor    = wp_get_image_editor($file, $editor_args);
                if (!is_wp_error($editor)) {
                    $editor->save($webp_path, 'image/webp', ['lossless' => $lossless]);
                    $metadata['gm2_nextgen']['full']['webp'] = basename($webp_path);
                }
            }
            $can_avif = $supports_avif;
            if ($mime === 'image/gif' && self::is_animated_gif($file)) {
                $can_avif = false;
            }
            if ($can_avif) {
                $avif_path = preg_replace('/\.[^\.]+$/', '.avif', $file);
                $editor    = wp_get_image_editor($file, $editor_args);
                if (!is_wp_error($editor)) {
                    $editor->save($avif_path, 'image/avif', ['lossless' => $lossless]);
                    $metadata['gm2_nextgen']['full']['avif'] = basename($avif_path);
                }
            }
        }

        foreach ($metadata['sizes'] as $size => $data) {
            $size_file = $base_dir . '/' . $data['file'];
            if (!file_exists($size_file)) {
                continue;
            }

            $type      = wp_check_filetype($size_file);
            $mime      = $type['type'];
            $lossless  = true;

            if ($mime === 'image/png') {
                $is_large = filesize($size_file) > 500 * 1024; // >500KB
                $has_alpha = self::png_has_alpha($size_file);
                if ($is_large && !$has_alpha) {
                    $lossless = false;
                }
            }

            if ($supports_webp) {
                $webp_path = preg_replace('/\.[^\.]+$/', '.webp', $size_file);
                $editor    = wp_get_image_editor($size_file, $editor_args);
                if (!is_wp_error($editor)) {
                    $editor->save($webp_path, 'image/webp', ['lossless' => $lossless]);
                    $metadata['gm2_nextgen'][$size]['webp'] = basename($webp_path);
                }
            }

            $can_avif = $supports_avif;
            if ($mime === 'image/gif' && self::is_animated_gif($size_file)) {
                $can_avif = false;
            }
            if ($can_avif) {
                $avif_path = preg_replace('/\.[^\.]+$/', '.avif', $size_file);
                $editor    = wp_get_image_editor($size_file, $editor_args);
                if (!is_wp_error($editor)) {
                    $editor->save($avif_path, 'image/avif', ['lossless' => $lossless]);
                    $metadata['gm2_nextgen'][$size]['avif'] = basename($avif_path);
                }
            }
        }

        return $metadata;
    }

    /** Determine if PNG has an alpha channel by reading its header. */
    private static function png_has_alpha(string $file): bool {
        $data = @file_get_contents($file, false, null, 0, 26);
        if ($data === false || strlen($data) < 26) {
            return false;
        }
        return (ord($data[25]) & 4) === 4; // Color type bit for alpha.
    }

    /** Quickly detect if a GIF is animated. */
    private static function is_animated_gif(string $file): bool {
        $fh = @fopen($file, 'rb');
        if (!$fh) {
            return false;
        }
        $count = 0;
        while (!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); // 100KB
            $count += preg_match_all('/\x00\x21\xF9\x04/', $chunk, $m);
        }
        fclose($fh);
        return $count > 1;
    }

    /** Filter the big image size threshold. */
    public static function filter_big_image_cap($threshold, $imagesize = null, $file = '', $attachment_id = 0) {
        $opts = self::get_settings();
        $cap  = intval($opts['big_image_cap']);
        if ($cap > 0) {
            return $cap;
        }
        return $threshold;
    }

    /** Warn if server lacks next-gen support. */
    public static function maybe_show_missing_notice(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $opts = self::get_settings();
        if (empty($opts['nextgen_images'])) {
            return;
        }
        $editor_args = ['methods' => ['Imagick', 'GD']];
        $missing = [];
        if (!empty($opts['webp']) && !wp_image_editor_supports(['mime_type' => 'image/webp'] + $editor_args)) {
            $missing[] = __('WebP', 'gm2-wordpress-suite');
        }
        if (!empty($opts['avif']) && !wp_image_editor_supports(['mime_type' => 'image/avif'] + $editor_args)) {
            $missing[] = __('AVIF', 'gm2-wordpress-suite');
        }
        if (!$missing) {
            return;
        }
        $formats = implode(' & ', $missing);
        echo '<div class="notice notice-warning"><p>' . esc_html(sprintf(
            /* translators: list of image formats */
            __('Your server does not support %s. Responsive srcset will remain intact.', 'gm2-wordpress-suite'),
            $formats
        )) . '</p></div>';
    }

    /** Retrieve settings merged with network defaults. */
    public static function get_settings(): array {
        $network = is_multisite() ? self::get_raw_options(true) : [];
        $site    = self::get_raw_options(false);
        $opts    = wp_parse_args($site, wp_parse_args($network, self::$defaults));
        $opts['big_image_cap'] = intval($opts['big_image_cap']);
        return $opts;
    }

    private static function get_raw_options(bool $network): array {
        $fn   = $network ? 'get_site_option' : 'get_option';
        $opts = $fn(self::OPTION_KEY, []);
        if (!is_array($opts)) {
            $opts = [];
        }
        return $opts;
    }

    /** Add submenu page for settings. */
    public static function register_admin_page(): void {
        self::$page_hook = add_submenu_page(
            'gm2',
            __('Network Payload', 'gm2-wordpress-suite'),
            __('Network Payload', 'gm2-wordpress-suite'),
            'manage_options',
            'gm2_netpayload',
            [__CLASS__, 'render_settings_page']
        );
        add_action('load-' . self::$page_hook, [__CLASS__, 'add_help_tabs']);
    }

    /** Enqueue scripts only on our settings page. */
    public static function enqueue_admin_assets(string $hook): void {
        if ($hook !== self::$page_hook) {
            return;
        }
        $base_url = GM2_PLUGIN_URL . 'modules/network-payload/assets/';
        $base_dir = GM2_PLUGIN_DIR . 'modules/network-payload/assets/';
        wp_enqueue_style(
            'gm2-netpayload-admin',
            $base_url . 'admin.css',
            [],
            file_exists($base_dir . 'admin.css') ? filemtime($base_dir . 'admin.css') : GM2_VERSION
        );
        wp_enqueue_script(
            'gm2-netpayload-admin',
            $base_url . 'admin.js',
            [],
            file_exists($base_dir . 'admin.js') ? filemtime($base_dir . 'admin.js') : GM2_VERSION,
            true
        );
        wp_localize_script('gm2-netpayload-admin', 'gm2Netpayload', [
            'restUrl' => rest_url('gm2/v1/netpayload'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    /** Add contextual help tabs. */
    public static function add_help_tabs(): void {
        $screen = get_current_screen();
        $tabs = [
            ['gm2_np_nextgen', __('Next‑Gen Images', 'gm2-wordpress-suite'), __('Serve images in modern formats where possible.', 'gm2-wordpress-suite')],
            ['gm2_np_gzip', __('Gzip Detection', 'gm2-wordpress-suite'), __('Detect whether the server compresses responses.', 'gm2-wordpress-suite')],
            ['gm2_np_lazy', __('Smart Lazyload', 'gm2-wordpress-suite'), __('Delay offscreen assets for faster paint.', 'gm2-wordpress-suite')],
            ['gm2_np_budget', __('Asset Budget', 'gm2-wordpress-suite'), __('Alert when pages exceed size thresholds.', 'gm2-wordpress-suite')],
        ];
        foreach ($tabs as $tab) {
            $screen->add_help_tab([
                'id'      => $tab[0],
                'title'   => $tab[1],
                'content' => '<p>' . esc_html($tab[2]) . '</p>',
            ]);
        }
    }

    /** Render settings form. */
    public static function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'gm2-wordpress-suite'));
        }
        if (isset($_POST['gm2_regen_nextgen'])) {
            check_admin_referer('gm2_regen_nextgen');
            self::start_regeneration(true);
            echo '<div class="updated"><p>' . esc_html__('Regeneration started in the background.', 'gm2-wordpress-suite') . '</p></div>';
        }
        if (isset($_POST[self::OPTION_KEY])) {
            check_admin_referer('gm2_netpayload_settings');
            $input = wp_unslash($_POST[self::OPTION_KEY]);
            $opts  = self::get_settings();
            $opts['nextgen_images'] = !empty($input['nextgen_images']);
            $opts['webp']           = !empty($input['webp']);
            $opts['avif']           = !empty($input['avif']);
            $opts['no_originals']   = !empty($input['no_originals']);
            $opts['big_image_cap']  = isset($input['big_image_cap']) ? intval($input['big_image_cap']) : self::$defaults['big_image_cap'];
            $opts['gzip_detection'] = isset($input['gzip_detection']) ? sanitize_text_field($input['gzip_detection']) : 'detect';
            $opts['smart_lazyload'] = !empty($input['smart_lazyload']);
            $opts['asset_budget']   = !empty($input['asset_budget']);
            if (is_network_admin()) {
                update_site_option(self::OPTION_KEY, $opts, false);
            } else {
                update_option(self::OPTION_KEY, $opts, false);
            }
            echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'gm2-wordpress-suite') . '</p></div>';
        }
        $opts  = self::get_settings();
        $stats = get_option(self::STATS_KEY, ['average' => 0]);
        ?>
        <div class="wrap gm2-netpayload-wrap">
            <h1><?php esc_html_e('Network Payload Optimizer', 'gm2-wordpress-suite'); ?></h1>
            <p class="status"><?php printf(esc_html__('7‑day average payload: %s KB', 'gm2-wordpress-suite'), number_format_i18n(floatval($stats['average']), 2)); ?></p>
            <form method="post">
                <?php wp_nonce_field('gm2_netpayload_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Next‑Gen Images', 'gm2-wordpress-suite'); ?></th>
                        <td><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[nextgen_images]" value="1" <?php checked($opts['nextgen_images']); ?> /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable WebP', 'gm2-wordpress-suite'); ?></th>
                        <td><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[webp]" value="1" <?php checked($opts['webp']); ?> /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable AVIF', 'gm2-wordpress-suite'); ?></th>
                        <td><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[avif]" value="1" <?php checked($opts['avif']); ?> /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e("Don't convert originals", 'gm2-wordpress-suite'); ?></th>
                        <td><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[no_originals]" value="1" <?php checked($opts['no_originals']); ?> /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Big image cap', 'gm2-wordpress-suite'); ?></th>
                        <td><input type="number" name="<?php echo esc_attr(self::OPTION_KEY); ?>[big_image_cap]" value="<?php echo esc_attr(intval($opts['big_image_cap'])); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Gzip Detection', 'gm2-wordpress-suite'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[gzip_detection]">
                                <option value="detect" <?php selected($opts['gzip_detection'], 'detect'); ?>><?php esc_html_e('Detect', 'gm2-wordpress-suite'); ?></option>
                                <option value="off" <?php selected($opts['gzip_detection'], 'off'); ?>><?php esc_html_e('Off', 'gm2-wordpress-suite'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Smart Lazyload', 'gm2-wordpress-suite'); ?></th>
                        <td><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[smart_lazyload]" value="1" <?php checked($opts['smart_lazyload']); ?> /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Asset Budget', 'gm2-wordpress-suite'); ?></th>
                        <td><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[asset_budget]" value="1" <?php checked($opts['asset_budget']); ?> /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <form method="post" style="margin-top:1em;">
                <?php wp_nonce_field('gm2_regen_nextgen'); ?>
                <input type="hidden" name="gm2_regen_nextgen" value="1" />
                <?php submit_button(__('Regenerate Next‑Gen Images', 'gm2-wordpress-suite'), 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    /** Register REST route for beacon data. */
    public static function register_rest_route(): void {
        register_rest_route('gm2/v1', '/netpayload', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'callback'            => [__CLASS__, 'handle_rest'],
        ]);
    }

    /** Handle beacon POST and store rolling average. */
    public static function handle_rest(\WP_REST_Request $request) {
        $payload = intval($request['payload']);
        $stats   = get_option(self::STATS_KEY, ['samples' => [], 'average' => 0]);
        if (!is_array($stats['samples'] ?? null)) {
            $stats['samples'] = [];
        }
        $now = time();
        $stats['samples'][] = ['t' => $now, 'p' => $payload];
        $cutoff = $now - 7 * DAY_IN_SECONDS;
        $stats['samples'] = array_values(array_filter($stats['samples'], function ($s) use ($cutoff) {
            return isset($s['t']) && $s['t'] >= $cutoff;
        }));
        $total = 0;
        foreach ($stats['samples'] as $s) {
            $total += intval($s['p']);
        }
        $count = count($stats['samples']);
        $stats['average'] = $count ? $total / $count : 0;
        update_option(self::STATS_KEY, $stats, false);
        return rest_ensure_response(['average' => $stats['average']]);
    }

    /** Activation: create options. */
    public static function activate(bool $network_wide = false): void {
        $defaults = self::$defaults;
        if ($network_wide && is_multisite()) {
            add_site_option(self::OPTION_KEY, $defaults, '', 'no');
        }
        add_option(self::OPTION_KEY, $defaults, '', 'no');
        add_option(self::STATS_KEY, ['samples' => [], 'average' => 0], '', 'no');
    }

    /** Deactivation: clear scheduled hooks. */
    public static function deactivate(bool $network_wide = false): void {
        wp_clear_scheduled_hook('gm2_netpayload_cron');
    }
}

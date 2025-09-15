<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mirror remote JavaScript assets locally for integrity and availability.
 */
class Gm2_Remote_Mirror {
    /** @var self|null */
    protected static $instance = null;

    /**
     * Option key storing vendor enablement state.
     * @var string
     */
    protected const OPTION_KEY = 'gm2_remote_mirror_vendors';

    /**
     * Option storing additional custom script URLs.
     * @var string
     */
    protected const CUSTOM_URLS_OPTION = 'gm2_remote_mirror_custom_urls';

    /** @var bool Tracks if rewrite hooks are applied */
    protected $hooks_applied = false;

    /** @var int|null Records the output buffer level started by this class */
    protected $buffer_level = null;

    /**
     * Vendor registry.
     * @var array<string, array{urls: array<int, string>, tos: string}>
     */
    protected $vendors = [
        'facebook' => [
            'urls' => [
                'https://connect.facebook.net/en_US/fbevents.js',
            ],
            'tos'  => 'https://www.facebook.com/legal/terms/plain_text_terms',
        ],
        'google' => [
            'urls' => [
                'https://www.googletagmanager.com/gtag/js',
            ],
            'tos'  => 'https://marketingplatform.google.com/about/analytics/terms/us/',
        ],
    ];

    /**
     * Initialise the mirror singleton.
     */
    public static function init(): self {
        if (self::$instance === null) {
            self::$instance = new self();

            $custom = get_option(self::CUSTOM_URLS_OPTION, []);
            if (is_array($custom) && !empty($custom)) {
                self::$instance->register_vendor('custom', $custom);
            }

            self::$instance->maybe_schedule_refresh();
            add_action('gm2_remote_mirror_refresh', [self::$instance, 'refresh_all']);
            add_action('update_option_' . self::OPTION_KEY, [self::$instance, 'on_option_update'], 10, 2);
            add_action('update_option_' . self::CUSTOM_URLS_OPTION, [self::$instance, 'on_custom_urls_update'], 10, 2);
            self::$instance->maybe_apply_hooks();
        }
        return self::$instance;
    }

    /**
     * Register an additional vendor.
     */
    public function register_vendor(string $vendor, array $urls, string $tos = ''): void {
        $this->vendors[$vendor] = [
            'urls' => $urls,
            'tos'  => $tos,
        ];
    }

    /**
     * Determine if a vendor is enabled.
     */
    protected function is_vendor_enabled(string $vendor): bool {
        $options = get_option(self::OPTION_KEY, []);
        if (!is_array($options)) {
            $options = [];
        }
        if ($vendor === 'custom') {
            return !empty($this->vendors['custom']['urls'] ?? []);
        }
        return !empty($options[$vendor]);
    }

    /**
     * Are any vendors enabled?
     */
    protected function has_enabled_vendors(): bool {
        foreach (array_keys($this->vendors) as $vendor) {
            if ($this->is_vendor_enabled($vendor)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Apply or remove rewrite hooks depending on enabled vendors.
     */
    protected function maybe_apply_hooks(): void {
        if ($this->has_enabled_vendors()) {
            if (!$this->hooks_applied) {
                add_filter('script_loader_src', [$this, 'rewrite'], 10, 2);
                add_action('template_redirect', [$this, 'start_buffer']);
                add_action('shutdown', [$this, 'end_buffer']);
                $this->hooks_applied = true;
            }
        } else {
            if ($this->hooks_applied) {
                remove_filter('script_loader_src', [$this, 'rewrite'], 10);
                remove_action('template_redirect', [$this, 'start_buffer']);
                remove_action('shutdown', [$this, 'end_buffer']);
                $this->hooks_applied = false;
            }
        }
    }

    /**
     * Schedule the daily refresh event if not already scheduled.
     */
    protected function maybe_schedule_refresh(): void {
        if (!wp_next_scheduled('gm2_remote_mirror_refresh')) {
            wp_schedule_event(time(), 'daily', 'gm2_remote_mirror_refresh');
        }
    }

    /**
     * Handle option updates.
     */
    public function on_option_update($old_value, $value): void {
        $this->maybe_apply_hooks();
        // Refresh immediately on option change.
        $this->refresh_all();
    }

    /**
     * Handle custom URL option updates.
     */
    public function on_custom_urls_update($old_value, $value): void {
        $urls = is_array($value) ? $value : [];
        if (isset($this->vendors['custom'])) {
            $this->vendors['custom']['urls'] = $urls;
        } elseif (!empty($urls)) {
            $this->register_vendor('custom', $urls);
        }
        $this->maybe_apply_hooks();
        $this->refresh_all();
    }

    /**
     * Cron handler to refresh all enabled vendors.
     */
    public function refresh_all(): void {
        foreach ($this->vendors as $vendor => $data) {
            if (!$this->is_vendor_enabled($vendor)) {
                continue;
            }
            foreach ($data['urls'] as $url) {
                $this->fetch_and_cache($url, $vendor);
            }
        }
    }

    /**
     * Rewrite script src to use locally cached versions when available.
     */
    public function rewrite(string $src, string $handle): string {
        $src_host = parse_url($src, PHP_URL_HOST);
        $src_path = (string) (parse_url($src, PHP_URL_PATH) ?? '');
        foreach ($this->vendors as $vendor => $data) {
            if (!$this->is_vendor_enabled($vendor)) {
                continue;
            }
            foreach ($data['urls'] as $remote_url) {
                $remote_host = parse_url($remote_url, PHP_URL_HOST);
                $remote_path = (string) (parse_url($remote_url, PHP_URL_PATH) ?? '');
                if ($src_host === $remote_host && strpos($src_path, $remote_path) === 0) {
                    $result = $this->fetch_and_cache($remote_url, $vendor);
                    if (is_wp_error($result)) {
                        return $src;
                    }
                    $parts = parse_url($src);
                    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
                    $frag  = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
                    return $result['url'] . $query . $frag;
                }
            }
        }
        return $src;
    }

    /**
     * Start output buffering to capture hardcoded script tags.
     */
    public function start_buffer(): void {
        if ($this->buffer_level !== null) {
            return;
        }

        if (ob_get_level() === 0 && ob_start()) {
            $this->buffer_level = ob_get_level();
        }
    }

    /**
     * Process the buffer and replace hardcoded vendor scripts.
     */
    public function end_buffer(): void {
        if ($this->buffer_level === null) {
            return;
        }

        if (ob_get_level() !== $this->buffer_level) {
            $this->buffer_level = null;
            return;
        }

        $buffer = ob_get_clean();
        $this->buffer_level = null;

        if ($buffer === false) {
            return;
        }
        echo $this->replace_hardcoded_scripts($buffer);
    }

    /**
     * Replace hardcoded vendor script URLs in HTML.
     */
    protected function replace_hardcoded_scripts(string $html): string {
        foreach ($this->vendors as $vendor => $data) {
            if (!$this->is_vendor_enabled($vendor)) {
                continue;
            }
            foreach ($data['urls'] as $remote_url) {
                $pattern = '#<script([^>]+)src=["\'](' . preg_quote($remote_url, '#') . '[^"\']*)["\']([^>]*)></script>#i';
                $html = preg_replace_callback(
                    $pattern,
                    function ($matches) use ($vendor, $remote_url) {
                        $src = $matches[2];
                        $result = $this->fetch_and_cache($remote_url, $vendor);
                        if (is_wp_error($result)) {
                            return $matches[0];
                        }
                        $parts = parse_url($src);
                        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
                        $frag  = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
                        $local = $result['url'] . $query . $frag;
                        return '<script' . $matches[1] . 'src="' . $local . '"' . $matches[3] . '></script>';
                    },
                    $html
                );
            }
        }
        return $html;
    }

    /**
     * Fetch a remote script and cache it locally.
     *
     * @param string $url    Script URL to fetch.
     * @param string $vendor Vendor key for storage namespace.
     * @return array{path: string, url: string, hash: string}|\WP_Error
     */
    public function fetch_and_cache(string $url, string $vendor) {
        $filename = basename(parse_url($url, PHP_URL_PATH) ?? '');
        if (!$filename) {
            $filename = sha1($url) . '.js';
        }

        $path = $this->get_local_path($vendor, $filename);
        if (file_exists($path)) {
            return [
                'path' => $path,
                'url'  => $this->get_local_url($vendor, $filename),
                'hash' => hash_file('sha256', $path),
            ];
        }

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $type = wp_remote_retrieve_header($response, 'content-type');
        if ($code !== 200 || strpos((string) $type, 'javascript') === false) {
            return new \WP_Error('gm2_remote_mirror_invalid', 'Invalid response when fetching remote script.');
        }

        $body = wp_remote_retrieve_body($response);
        wp_mkdir_p(dirname($path));

        $bytes_written = file_put_contents($path, $body);
        if ($bytes_written === false) {
            return new \WP_Error('gm2_remote_mirror_write_failed', 'Failed to write remote script to cache.');
        }

        return [
            'path' => $path,
            'url'  => $this->get_local_url($vendor, $filename),
            'hash' => hash('sha256', $body),
        ];
    }

    /**
     * Get the absolute path for a cached file.
     */
    public function get_local_path(string $vendor, string $filename): string {
        $base = trailingslashit(WP_CONTENT_DIR) . 'cache/gm2-wordpress-suite/remote/';
        return $base . $vendor . '/' . $filename;
    }

    /**
     * Get the public URL for a cached file.
     */
    public function get_local_url(string $vendor, string $filename): string {
        $base = content_url('cache/gm2-wordpress-suite/remote/');
        return $base . $vendor . '/' . $filename;
    }

    /**
     * Retrieve the SHA-256 integrity hash for a cached file.
     */
    public function get_integrity_hash(string $vendor, string $filename): string {
        $path = $this->get_local_path($vendor, $filename);
        if (!file_exists($path)) {
            return '';
        }
        return hash_file('sha256', $path);
    }

    /**
     * Access the vendor registry.
     */
    public function get_registry(): array {
        return $this->vendors;
    }
}


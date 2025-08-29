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

    /** @var bool Tracks if rewrite hooks are applied */
    protected $hooks_applied = false;

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
            self::$instance->maybe_schedule_refresh();
            add_action('gm2_remote_mirror_refresh', [self::$instance, 'refresh_all']);
            add_action('update_option_' . self::OPTION_KEY, [self::$instance, 'on_option_update'], 10, 2);
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
        return !isset($options[$vendor]) || (bool) $options[$vendor];
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
                $this->hooks_applied = true;
            }
        } else {
            if ($this->hooks_applied) {
                remove_filter('script_loader_src', [$this, 'rewrite'], 10);
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
        foreach ($this->vendors as $vendor => $data) {
            if (!$this->is_vendor_enabled($vendor)) {
                continue;
            }
            foreach ($data['urls'] as $remote_url) {
                if (strpos($src, $remote_url) === 0) {
                    $filename = basename(parse_url($remote_url, PHP_URL_PATH) ?? '');
                    $result   = $this->fetch_and_cache($remote_url, $vendor);
                    if (is_wp_error($result)) {
                        return $src;
                    }
                    $local = $this->get_local_url($vendor, $filename);
                    $query = substr($src, strlen($remote_url));
                    return $local . $query;
                }
            }
        }
        return $src;
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
        file_put_contents($path, $body);

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


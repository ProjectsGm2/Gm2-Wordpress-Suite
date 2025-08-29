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


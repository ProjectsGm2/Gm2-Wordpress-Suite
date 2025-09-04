<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

final class AESEO_LCP_Optimizer {
    /**
     * Feature flags.
     *
     * @var array
     */
    private static $settings = [];

    /**
     * Candidate index from wp_lazy_loading_enabled.
     *
     * @var int|null
     */
    private static $candidate_index = null;

    /**
     * Counter for wp_lazy_loading_enabled calls.
     *
     * @var int
     */
    private static $lazy_count = 0;

    /**
     * Counter for wp_get_attachment_image_attributes calls.
     *
     * @var int
     */
    private static $attr_count = 0;

    /**
     * Information about the LCP image.
     *
     * @var array
     */
    private static $candidate = [];

    /**
     * Flag if processing done.
     *
     * @var bool
     */
    private static $done = false;

    /**
     * Track optimized items to avoid duplicate processing.
     *
     * @var array
     */
    private static $optimized = [];

    /**
     * Boot the optimizer by attaching hooks.
     */
    public static function boot(): void {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || is_feed()) {
            return;
        }

        self::$settings = get_option(
            'aeseo_lcp_settings',
            [
                'remove_lazy_on_lcp'       => true,
                'add_fetchpriority_high'   => true,
                'force_width_height'       => true,
                'responsive_picture_nextgen' => true,
                'add_preconnect'           => true,
                'add_preload'              => true,
            ]
        );

        add_filter('wp_lazy_loading_enabled', [ __CLASS__, 'maybe_disable_lazy' ], 10, 3);
        add_filter('wp_get_attachment_image_attributes', [ __CLASS__, 'maybe_adjust_attributes' ], 10, 3);
        add_filter('wp_get_attachment_image', [ __CLASS__, 'maybe_use_picture' ], 10, 5);
        add_action('wp_head', [ __CLASS__, 'maybe_print_links' ], 5);
    }

    /**
     * Retrieve information about the LCP candidate.
     *
     * @return array
     */
    public static function get_lcp_candidate(): array {
        return self::$candidate;
    }

    /**
     * Check whether an attachment or URL has already been optimized.
     *
     * @param int|string $attachment_id_or_url Attachment ID or URL or HTML containing data attribute.
     * @return bool
     */
    public static function is_already_optimized($attachment_id_or_url): bool {
        if (is_string($attachment_id_or_url) && strpos($attachment_id_or_url, 'data-aeseo-lcp-optimized') !== false) {
            return true;
        }

        $key = is_numeric($attachment_id_or_url)
            ? 'id_' . absint($attachment_id_or_url)
            : 'url_' . md5((string) $attachment_id_or_url);

        if (isset(self::$optimized[$key]) || get_transient('aeseo_lcp_' . $key)) {
            return true;
        }

        self::$optimized[$key] = true;
        set_transient('aeseo_lcp_' . $key, 1, HOUR_IN_SECONDS);
        return false;
    }

    /**
     * Disable lazy loading for LCP candidate.
     *
     * @param bool   $default Default decision.
     * @param string $tag     Tag name.
     * @param string $context Context.
     * @return bool
     */
    public static function maybe_disable_lazy(bool $default, string $tag, string $context): bool {
        if ($tag !== 'img' || self::$done) {
            return $default;
        }

        self::$lazy_count++;
        if (self::$candidate_index === null) {
            self::$candidate_index = self::$lazy_count;
            return !empty(self::$settings['remove_lazy_on_lcp']) ? false : $default;
        }

        return $default;
    }

    /**
     * Adjust attributes for the candidate image.
     *
     * @param array     $attr      Image attributes.
     * @param \WP_Post $attachment Attachment object.
     * @param mixed     $size      Size requested.
     * @return array
     */
    public static function maybe_adjust_attributes(array $attr, $attachment, $size): array {
        if (self::$done) {
            return $attr;
        }

        if (!empty(self::$settings['force_width_height']) && is_object($attachment) && (empty($attr['width']) || empty($attr['height']))) {
            $meta = wp_get_attachment_metadata($attachment->ID);
            if (is_array($meta)) {
                if (empty($attr['width']) && !empty($meta['width'])) {
                    $attr['width'] = sanitize_text_field((string) absint($meta['width']));
                }
                if (empty($attr['height']) && !empty($meta['height'])) {
                    $attr['height'] = sanitize_text_field((string) absint($meta['height']));
                }
            }
        }

        self::$attr_count++;
        if (self::$candidate_index !== null && self::$candidate_index === self::$attr_count) {
            $src = $attr['src'] ?? '';
            if (!$src && is_object($attachment)) {
                $src = wp_get_attachment_image_url($attachment->ID, $size);
            }

            if ($src && !self::is_already_optimized(is_object($attachment) ? $attachment->ID : $src)) {
                $attr['data-aeseo-lcp-optimized'] = '1';
                if (isset($attr['loading']) && !empty(self::$settings['remove_lazy_on_lcp'])) {
                    unset($attr['loading']);
                }
                if ($src && !empty(self::$settings['add_fetchpriority_high']) && !isset($attr['fetchpriority'])) {
                    $attr['fetchpriority'] = 'high';
                }
                self::$candidate = [
                    'source'        => 'img',
                    'attachment_id' => is_object($attachment) ? $attachment->ID : 0,
                    'url'           => $src,
                    'width'         => isset($attr['width']) ? (int) $attr['width'] : 0,
                    'height'        => isset($attr['height']) ? (int) $attr['height'] : 0,
                    'origin'        => 'wp',
                    'is_background' => false,
                ];
                self::$done = true;
            }
        }

        return $attr;
    }

    /**
     * Convert LCP image to picture element with next-gen formats.
     *
     * @param string       $html          HTML markup.
     * @param int          $attachment_id Attachment ID.
     * @param mixed        $size          Image size.
     * @param bool         $icon          Icon flag.
     * @param array|string $attr          Attributes.
     * @return string
     */
    public static function maybe_use_picture(string $html, $attachment_id, $size, $icon, $attr): string {
        if (empty(self::$settings['responsive_picture_nextgen']) || empty(self::$candidate) || self::$candidate['attachment_id'] !== (int) $attachment_id) {
            return $html;
        }

        if (self::is_already_optimized($attachment_id)) {
            return $html;
        }

        if (function_exists('gm2_queue_image_optimization')) {
            gm2_queue_image_optimization($attachment_id);
        }

        $srcset = wp_get_attachment_image_srcset($attachment_id, $size);
        if (!$srcset) {
            return $html;
        }

        $src = wp_get_attachment_image_url($attachment_id, $size);
        if (!$src) {
            return $html;
        }

        $ext = pathinfo($src, PATHINFO_EXTENSION);
        if (!$ext) {
            return $html;
        }

        $webp_srcset = self::convert_srcset_extension($srcset, $ext, 'webp');
        $avif_srcset = self::convert_srcset_extension($srcset, $ext, 'avif');

        if (!self::srcset_files_exist($webp_srcset) || !self::srcset_files_exist($avif_srcset)) {
            return $html;
        }

        return sprintf(
            '<picture><source type="image/avif" srcset="%s" /><source type="image/webp" srcset="%s" />%s</picture>',
            esc_attr($avif_srcset),
            esc_attr($webp_srcset),
            $html
        );
    }

    /**
     * Print preload and preconnect link tags if needed.
     */
    public static function maybe_print_links(): void {
        if (empty(self::$candidate['url'])) {
            return;
        }

        $url  = self::$candidate['url'];
        $host = wp_parse_url($url, PHP_URL_HOST);
        $head = ob_get_contents() ?: '';

        if (!empty(self::$settings['add_preconnect']) && $host) {
            $preconnect_hints = array_map(
                static function ($u) {
                    return wp_parse_url($u, PHP_URL_HOST);
                },
                wp_resource_hints([], 'preconnect')
            );
            $preconnect_exists = in_array($host, $preconnect_hints, true) || (
                (stripos($head, 'rel="preconnect"') !== false || stripos($head, "rel='preconnect'") !== false) &&
                stripos($head, $host) !== false
            );
            if (!$preconnect_exists) {
                printf('<link rel="preconnect" href="%s" />' . "\n", esc_url('//' . $host));
            }
        }

        if (!empty(self::$settings['add_preload'])) {
            $preloads = wp_resource_hints([], 'preload');
            $preload_exists = in_array($url, $preloads, true) || (
                (stripos($head, 'rel="preload"') !== false || stripos($head, "rel='preload'") !== false) &&
                stripos($head, $url) !== false
            );
            if (!$preload_exists) {
                printf('<link rel="preload" as="image" href="%s" fetchpriority="high" />' . "\n", esc_url($url));
            }
        }
    }

    /**
     * Swap file extensions in a srcset string.
     *
     * @param string $srcset   Original srcset.
     * @param string $from_ext Extension to replace.
     * @param string $to_ext   New extension.
     * @return string
     */
    private static function convert_srcset_extension(string $srcset, string $from_ext, string $to_ext): string {
        $sources   = array_map('trim', explode(',', $srcset));
        $converted = [];
        foreach ($sources as $source) {
            if ($source === '') {
                continue;
            }
            $parts      = preg_split('/\s+/', $source);
            $url        = $parts[0];
            $descriptor = $parts[1] ?? '';
            $url        = preg_replace('/\.' . preg_quote($from_ext, '/') . '$/', '.' . $to_ext, $url);
            $converted[] = trim($url . ' ' . $descriptor);
        }
        return implode(', ', $converted);
    }

    /**
     * Verify that at least one srcset file exists.
     *
     * @param string $srcset Srcset string.
     * @return bool
     */
    private static function srcset_files_exist(string $srcset): bool {
        $uploads = wp_get_upload_dir();
        $sources = array_map('trim', explode(',', $srcset));
        foreach ($sources as $source) {
            if ($source === '') {
                continue;
            }
            $url  = trim(explode(' ', $source)[0]);
            $path = str_replace($uploads['baseurl'], $uploads['basedir'], $url);
            if (file_exists($path)) {
                return true;
            }
        }
        return false;
    }
}

AESEO_LCP_Optimizer::boot();

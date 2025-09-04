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
     * Information about the LCP image.
     *
     * @var array
     */
    private static $candidate = [];

    /**
     * Context for the current image being processed.
     *
     * @var array
     */
    private static $current_image = [];

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

        add_filter('pre_wp_get_loading_optimization_attributes', [ __CLASS__, 'capture_image_context' ], 10, 4);
        add_filter('wp_lazy_loading_enabled', [ __CLASS__, 'maybe_disable_lazy' ], 10, 3);
        add_filter('wp_get_attachment_image_attributes', [ __CLASS__, 'maybe_adjust_attributes' ], 10, 3);
        add_filter('wp_get_attachment_image', [ __CLASS__, 'maybe_use_picture' ], 10, 5);
        add_action('wp_head', [ __CLASS__, 'maybe_print_links' ], 5);
        add_action('wp', [ __CLASS__, 'maybe_prime_candidate' ]);
        add_filter('the_content', [ __CLASS__, 'detect_from_content' ], 1);
        add_action('woocommerce_before_single_product', [ __CLASS__, 'detect_woo_product' ]);
    }

    /**
     * Retrieve information about the LCP candidate.
     *
     * @return array
     */
    public static function get_lcp_candidate(): array {
        if (empty(self::$candidate) && is_singular()) {
            $post_id = get_the_ID();
            if ($post_id) {
                // Attempt to load from cache first.
                $cached = wp_cache_get('aeseo_lcp_candidate_' . $post_id, 'aeseo');
                if (is_array($cached)) {
                    self::$candidate = $cached;
                }

                // Prime from featured image if still empty.
                if (empty(self::$candidate)) {
                    self::maybe_prime_candidate();
                }

                // Fallback to scanning the rendered content on demand.
                if (empty(self::$candidate)) {
                    $post = get_post($post_id);
                    if ($post) {
                        apply_filters('the_content', $post->post_content);
                    }
                }
            }
        }

        return self::$candidate;
    }

    /**
     * Prime candidate data from featured image or cache.
     */
    public static function maybe_prime_candidate(): void {
        if (!is_singular() || !empty(self::$candidate)) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        $cached = wp_cache_get('aeseo_lcp_candidate_' . $post_id, 'aeseo');
        if (is_array($cached)) {
            self::$candidate = $cached;
            return;
        }

        if (has_post_thumbnail($post_id)) {
            $attachment_id = get_post_thumbnail_id($post_id);
            self::set_candidate_from_attachment($attachment_id);
        }
    }

    /**
     * Detect candidate from the_content HTML.
     *
     * @param string $content Post content.
     * @return string
     */
    public static function detect_from_content(string $content): string {
        if (empty(self::$candidate)) {
            self::detect_in_html($content);
        }
        remove_filter('the_content', [ __CLASS__, __FUNCTION__ ], 1);
        return $content;
    }

    /**
     * Detect candidate on WooCommerce single product pages.
     */
    public static function detect_woo_product(): void {
        if (!empty(self::$candidate)) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        $attachment_id = get_post_thumbnail_id($post_id);
        if (!$attachment_id) {
            $gallery = get_post_meta($post_id, '_product_image_gallery', true);
            if ($gallery) {
                $ids = array_filter(array_map('absint', explode(',', (string) $gallery)));
                $attachment_id = $ids[0] ?? 0;
            }
        }

        if ($attachment_id) {
            self::set_candidate_from_attachment($attachment_id);
        }
    }

    /**
     * Parse HTML and record first image details.
     *
     * @param string $html HTML to scan.
     */
    private static function detect_in_html(string $html): void {
        if (!is_singular() || !empty(self::$candidate)) {
            return;
        }

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . shortcode_unautop($html));
        libxml_clear_errors();

        $img = $doc->getElementsByTagName('img')->item(0);
        if (!$img) {
            return;
        }

        $src    = $img->getAttribute('src');
        $width  = (int) $img->getAttribute('width');
        $height = (int) $img->getAttribute('height');
        $id     = (int) $img->getAttribute('data-id');
        if (!$id && $src) {
            $id = attachment_url_to_postid($src);
        }

        if ($src) {
            // Populate missing dimensions from attachment metadata when possible.
            if ((!$width || !$height) && $id) {
                $meta = wp_get_attachment_metadata($id);
                if (is_array($meta)) {
                    $width  = $width  ?: (int) ($meta['width'] ?? 0);
                    $height = $height ?: (int) ($meta['height'] ?? 0);
                }
            }

            self::set_candidate([
                'source'        => 'img',
                'attachment_id' => $id,
                'url'           => $src,
                'width'         => $width,
                'height'        => $height,
                'origin'        => wp_parse_url($src, PHP_URL_HOST) ?: '',
                'is_background' => false,
            ]);
        }
    }

    /**
     * Set candidate data from an attachment ID.
     *
     * @param int $attachment_id Attachment ID.
     */
    private static function set_candidate_from_attachment(int $attachment_id): void {
        $image = wp_get_attachment_image_src($attachment_id, 'full');
        if (!$image) {
            return;
        }

        $url    = $image[0];
        $width  = (int) ($image[1] ?? 0);
        $height = (int) ($image[2] ?? 0);

        self::set_candidate([
            'source'        => 'img',
            'attachment_id' => $attachment_id,
            'url'           => $url,
            'width'         => $width,
            'height'        => $height,
            'origin'        => wp_parse_url($url, PHP_URL_HOST) ?: '',
            'is_background' => false,
        ]);
    }

    /**
     * Store candidate data and cache it briefly.
     *
     * @param array $data Candidate data.
     */
    private static function set_candidate(array $data): void {
        if (!empty(self::$candidate)) {
            return;
        }

        self::$candidate = $data;

        $post_id = is_singular() ? get_the_ID() : 0;
        if ($post_id) {
            wp_cache_set('aeseo_lcp_candidate_' . $post_id, $data, 'aeseo', 60);
        }
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
     * Store context of the current image being processed.
     *
     * @param mixed        $value   Short-circuit value.
     * @param string       $tag     Tag name.
     * @param array        $attr    Tag attributes.
     * @param string|array $context Context for the element.
     * @return mixed
     */
    public static function capture_image_context($value, $tag, $attr, $context) {
        if ($tag === 'img' && is_array($attr)) {
            $id  = isset($attr['attachment_id']) ? (int) $attr['attachment_id'] : 0;
            $src = isset($attr['src']) ? (string) $attr['src'] : '';
            if (!$id && $src) {
                $id = attachment_url_to_postid($src);
            }
            self::$current_image = [
                'attachment_id' => $id,
                'src'          => $src,
            ];
        }

        return $value;
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
        if ($tag !== 'img' || self::$done || empty(self::$settings['remove_lazy_on_lcp'])) {
            return $default;
        }

        $candidate = self::get_lcp_candidate();
        if (empty($candidate)) {
            return $default;
        }

        $attachment_id = 0;
        $src           = '';

        if (is_array($context)) {
            $attachment_id = isset($context['attachment_id']) ? (int) $context['attachment_id'] : 0;
            $src           = isset($context['src']) ? (string) $context['src'] : '';
        }

        if (!$attachment_id && !$src && !empty(self::$current_image)) {
            $attachment_id = (int) (self::$current_image['attachment_id'] ?? 0);
            $src           = (string) (self::$current_image['src'] ?? '');
        }

        if (
            ($candidate['attachment_id'] && $attachment_id && $candidate['attachment_id'] === $attachment_id) ||
            ($candidate['url'] && $src && $candidate['url'] === $src)
        ) {
            return false;
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

        $candidate = self::get_lcp_candidate();
        if (!empty($candidate)) {
            $src = $attr['src'] ?? '';
            $attachment_id = is_object($attachment) ? (int) $attachment->ID : 0;
            if (!$src && $attachment_id) {
                $src = wp_get_attachment_image_url($attachment_id, $size);
            }

            $is_match = false;
            if ($candidate['attachment_id'] && $attachment_id && $candidate['attachment_id'] === $attachment_id) {
                $is_match = true;
            } elseif ($candidate['url'] && $src && $candidate['url'] === $src) {
                $is_match = true;
            }

            if ($is_match && $src && !self::is_already_optimized($attachment_id ? $attachment_id : $src)) {
                $attr['data-aeseo-lcp-optimized'] = '1';
                if (isset($attr['loading']) && !empty(self::$settings['remove_lazy_on_lcp'])) {
                    unset($attr['loading']);
                }
                if (!empty(self::$settings['add_fetchpriority_high']) && !isset($attr['fetchpriority'])) {
                    $attr['fetchpriority'] = 'high';
                }
                self::$candidate = [
                    'source'        => 'img',
                    'attachment_id' => $attachment_id,
                    'url'           => $src,
                    'width'         => isset($attr['width']) ? (int) $attr['width'] : (int) ($candidate['width'] ?? 0),
                    'height'        => isset($attr['height']) ? (int) $attr['height'] : (int) ($candidate['height'] ?? 0),
                    'origin'        => wp_parse_url($src, PHP_URL_HOST) ?: '',
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

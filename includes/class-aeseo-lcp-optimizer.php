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
     * Track whether resource hints or preload links were output.
     *
     * @var bool
     */
    private static $preconnect_added = false;

    /**
     * @var bool
     */
    private static $preload_printed = false;

    /**
     * Boot the optimizer by attaching hooks.
     */
    public static function boot(): void {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || is_feed()) {
            return;
        }

        $post_id = get_queried_object_id();
        if ($post_id && function_exists('get_post_meta')) {
            $disabled = absint(get_post_meta($post_id, '_aeseo_lcp_disable', true));
            if ($disabled && !apply_filters('aeseo_lcp_should_optimize', false, [])) {
                return;
            }
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
        add_filter('wp_img_tag_add_loading_attr', [ __CLASS__, 'maybe_unset_loading_attr' ], 10, 3);
        add_filter('wp_get_attachment_image_attributes', [ __CLASS__, 'maybe_adjust_attributes' ], 10, 3);
        add_filter('wp_get_attachment_image', [ __CLASS__, 'maybe_use_picture' ], 10, 5);
        add_filter('wp_resource_hints', [ __CLASS__, 'maybe_add_preconnect' ], 10, 2);
        add_action('wp_head', [ __CLASS__, 'detect_background_hero' ], 1);
        add_action('wp_head', [ __CLASS__, 'maybe_print_preload' ], 5);
        add_action('wp', [ __CLASS__, 'maybe_prime_candidate' ]);
        add_filter('the_content', [ __CLASS__, 'detect_from_content' ], 1);
        add_filter('the_content', [ __CLASS__, 'maybe_add_fetchpriority_to_content' ], 20);
        add_filter('render_block', [ __CLASS__, 'maybe_add_fetchpriority_to_block' ], 20, 2);
        add_action('woocommerce_before_single_product', [ __CLASS__, 'detect_woo_product' ]);
        add_filter('woocommerce_single_product_image_thumbnail_html', [ __CLASS__, 'strip_main_product_lazy' ], 10, 2);
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
                $disabled = function_exists('get_post_meta') ? absint(get_post_meta($post_id, '_aeseo_lcp_disable', true)) : 0;
                if ($disabled && !apply_filters('aeseo_lcp_should_optimize', false, [])) {
                    return [];
                }

                $override = '';
                if (function_exists('get_post_meta')) {
                    $override = wp_cache_get('aeseo_lcp_override_' . $post_id, 'aeseo');
                    if ($override === false) {
                        $override = get_post_meta($post_id, '_aeseo_lcp_override', true);
                        wp_cache_set('aeseo_lcp_override_' . $post_id, $override, 'aeseo', 60);
                    }
                }

                if ($override !== '') {
                    wp_cache_delete('aeseo_lcp_candidate_' . $post_id, 'aeseo');
                    if (is_numeric($override)) {
                        $id = absint($override);
                        if ($id) {
                            wp_cache_set('aeseo_lcp_override_' . $post_id, $id, 'aeseo', 60);
                            self::set_candidate_from_attachment($id);
                        }
                    } else {
                        $url = esc_url_raw($override);
                        wp_cache_set('aeseo_lcp_override_' . $post_id, $url, 'aeseo', 60);
                        self::set_candidate([
                            'source'        => 'img',
                            'attachment_id' => 0,
                            'url'           => $url,
                            'width'         => 0,
                            'height'        => 0,
                            'origin'        => wp_parse_url($url, PHP_URL_HOST) ?: '',
                            'is_background' => false,
                        ]);
                    }
                }

                if (empty(self::$candidate)) {
                    $cached = wp_cache_get('aeseo_lcp_candidate_' . $post_id, 'aeseo');
                    if (is_array($cached)) {
                        self::$candidate = $cached;
                    }
                }

                if (empty(self::$candidate)) {
                    self::maybe_prime_candidate();
                }

                if (empty(self::$candidate)) {
                    $post = function_exists('get_post') ? get_post($post_id) : null;
                    if ($post) {
                        apply_filters('the_content', $post->post_content);
                    }
                }
            }
        }

        self::$candidate = apply_filters('aeseo_lcp_candidate', self::$candidate);

        return self::$candidate;
    }

    /**
     * Prime candidate data from featured image or cache.
     */
    public static function maybe_prime_candidate(): void {
        if (!apply_filters('aeseo_lcp_should_optimize', true, self::$candidate)) {
            return;
        }

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
     * Attempt to detect a hero image defined via CSS background-image.
     */
    public static function detect_background_hero(): void {
        if (!empty(self::$candidate)) {
            return;
        }

        $styles = wp_styles();
        if (!($styles instanceof \WP_Styles)) {
            return;
        }

        $css = '';
        foreach ($styles->queue as $handle) {
            if (empty($styles->registered[$handle])) {
                continue;
            }
            $style = $styles->registered[$handle];

            if (!empty($style->extra['before'])) {
                $css .= is_array($style->extra['before']) ? implode(' ', $style->extra['before']) : (string) $style->extra['before'];
            }
            if (!empty($style->extra['after'])) {
                $css .= is_array($style->extra['after']) ? implode(' ', $style->extra['after']) : (string) $style->extra['after'];
            }

            $src = $style->src;
            if ($src) {
                $src_url = $src;
                if (strpos($src_url, '//') === 0) {
                    $src_url = (is_ssl() ? 'https:' : 'http:') . $src_url;
                } elseif (strpos($src_url, '/') === 0) {
                    $src_url = home_url($src_url);
                } elseif (strpos($src_url, 'http') !== 0) {
                    $src_url = trailingslashit($styles->base_url) . ltrim($src_url, '/');
                }

                $path = self::url_to_path($src_url);
                if ($path && file_exists($path)) {
                    $css .= @file_get_contents($path);
                }
            }
        }

        if ($css === '') {
            return;
        }

        $selectors = [ '.hero', '.site-hero', '.elementor-hero', '.wp-block-cover', '.slider' ];
        foreach ($selectors as $selector) {
            $pattern = '/' . preg_quote($selector, '/') . '[^{]*{[^}]*background(?:-image)?[^:]*:\s*url\(("|\')?([^"\'\)]+)\1?\)/i';
            if (!preg_match($pattern, $css, $matches)) {
                continue;
            }

            $url = trim($matches[2]);
            if ($url === '') {
                continue;
            }

            if (strpos($url, '//') === 0) {
                $url = (is_ssl() ? 'https:' : 'http:') . $url;
            } elseif (strpos($url, '/') === 0) {
                $url = home_url($url);
            }

            $attachment_id = attachment_url_to_postid($url);
            $width = 0;
            $height = 0;
            if ($attachment_id) {
                $dimensions = self::get_attachment_dimensions($attachment_id);
                $width = (int) $dimensions['width'];
                $height = (int) $dimensions['height'];
            } else {
                $path = self::url_to_path($url);
                if ($path && file_exists($path)) {
                    $size = @getimagesize($path);
                    if (is_array($size)) {
                        $width = (int) ($size[0] ?? 0);
                        $height = (int) ($size[1] ?? 0);
                    }
                }
            }

            if ($width && $height && $width < 400 && $height < 300) {
                continue;
            }

            self::set_candidate([
                'source'        => 'css-bg',
                'attachment_id' => $attachment_id,
                'url'           => $url,
                'width'         => $width,
                'height'        => $height,
                'origin'        => wp_parse_url($url, PHP_URL_HOST) ?: '',
                'is_background' => true,
            ]);
            break;
        }
    }

    /**
     * Convert a URL into a local filesystem path when possible.
     *
     * @param string $url URL to convert.
     * @return string|null
     */
    private static function url_to_path(string $url): ?string {
        $home = home_url('/');
        if (strpos($url, $home) === 0) {
            $relative = substr($url, strlen($home));
            $path     = ABSPATH . ltrim($relative, '/');
            $real     = realpath($path);
            if ($real && file_exists($real)) {
                return $real;
            }
        }
        return null;
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
            $gallery = function_exists('get_post_meta') ? get_post_meta($post_id, '_product_image_gallery', true) : '';
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
     * Remove lazy loading from the main WooCommerce product image when it matches the LCP candidate.
     *
     * @param string $html    Image HTML.
     * @param int    $post_id Product ID.
     * @return string
     */
    public static function strip_main_product_lazy(string $html, $post_id): string {
        $candidate = self::get_lcp_candidate();
        if (empty($candidate) || strpos($html, '<img') === false) {
            return $html;
        }

        if (!preg_match('/<img[^>]*>/i', $html, $img_match)) {
            return $html;
        }

        $img_tag       = $img_match[0];
        $src           = '';
        $attachment_id = 0;

        if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/', $img_tag, $m)) {
            $src = $m[1];
        }
        if (preg_match('/data-attachment-id\s*=\s*["\'](\d+)["\']/', $img_tag, $m)) {
            $attachment_id = (int) $m[1];
        }

        $is_match = false;
        if ($candidate['attachment_id'] && $attachment_id && $candidate['attachment_id'] === $attachment_id) {
            $is_match = true;
        } elseif ($candidate['url'] && $src && $candidate['url'] === $src) {
            $is_match = true;
        }

        if (!$is_match) {
            return $html;
        }

        if (self::is_already_optimized($attachment_id ? $attachment_id : $src)) {
            return $html;
        }

        $new_img_tag = preg_replace('/\sloading\s*=\s*["\']lazy["\']/', '', $img_tag);
        if (strpos($new_img_tag, 'fetchpriority') === false && !empty(self::$settings['add_fetchpriority_high'])) {
            $new_img_tag = preg_replace('/<img\b/', '<img fetchpriority="high"', $new_img_tag, 1);
        }
        if (strpos($new_img_tag, 'data-aeseo-lcp="1"') === false) {
            $new_img_tag = preg_replace('/<img\b/', '<img data-aeseo-lcp="1"', $new_img_tag, 1);
        }

        self::$done = true;

        return str_replace($img_tag, $new_img_tag, $html);
    }

    /**
     * Add fetchpriority="high" to the LCP image within a block.
     *
     * @param string $block_content Rendered block HTML.
     * @param array  $block         Block context.
     * @return string
     */
    public static function maybe_add_fetchpriority_to_block(string $block_content, array $block): string {
        if (!apply_filters('aeseo_lcp_should_optimize', true, self::$candidate)) {
            return $block_content;
        }

        return self::maybe_add_fetchpriority_html($block_content);
    }

    /**
     * Add fetchpriority="high" to the LCP image within post content.
     *
     * @param string $content Post content.
     * @return string
     */
    public static function maybe_add_fetchpriority_to_content(string $content): string {
        if (!apply_filters('aeseo_lcp_should_optimize', true, self::$candidate)) {
            return $content;
        }

        return self::maybe_add_fetchpriority_html($content);
    }

    /**
     * Inject fetchpriority="high" into existing HTML for the LCP image.
     *
     * @param string $html HTML to scan.
     * @return string
     */
    private static function maybe_add_fetchpriority_html(string $html): string {
        if (!apply_filters('aeseo_lcp_should_optimize', true, self::$candidate)) {
            return $html;
        }

        if (self::$done) {
            return $html;
        }

        if (empty(self::$settings['add_fetchpriority_high']) && empty(self::$settings['force_width_height'])) {
            return $html;
        }

        $candidate = self::get_lcp_candidate();
        if (empty($candidate) || empty($candidate['url'])) {
            return $html;
        }

        if (strpos($html, '<img') === false) {
            return $html;
        }

        // Ensure candidate dimensions are populated when possible.
        if ((!$candidate['width'] || !$candidate['height']) && !empty($candidate['attachment_id'])) {
            $dimensions = self::get_attachment_dimensions((int) $candidate['attachment_id']);
            $candidate['width']  = $candidate['width']  ?: (int) $dimensions['width'];
            $candidate['height'] = $candidate['height'] ?: (int) $dimensions['height'];
        }

        $pattern = '/<img\\b[^>]*' . preg_quote($candidate['url'], '/') . '[^>]*>/i';
        if (!preg_match($pattern, $html, $match)) {
            return $html;
        }

        if (self::is_already_optimized($candidate['attachment_id'] ? $candidate['attachment_id'] : $candidate['url'])) {
            return $html;
        }

        $img_tag     = $match[0];
        $new_img_tag = self::enhance_img_tag($img_tag, $candidate);

        $count = 0;
        $html  = str_replace($img_tag, $new_img_tag, $html, $count);

        if ($count > 0) {
            self::$done = true;
        }

        return $html;
    }

    /**
     * Add fetchpriority and missing dimensions to an img tag.
     *
     * @param string $img_tag   Original img tag.
     * @param array  $candidate LCP candidate details.
     * @return string Modified img tag.
     */
    private static function enhance_img_tag(string $img_tag, array $candidate): string {
        $attrs = [];

        if (strpos($img_tag, 'fetchpriority') === false && !empty(self::$settings['add_fetchpriority_high'])) {
            $attrs[] = 'fetchpriority="high"';
        }

        if (
            !empty(self::$settings['force_width_height']) &&
            !preg_match('/\bwidth\s*=\s*["\']\d+["\']/', $img_tag) &&
            !empty($candidate['width'])
        ) {
            $attrs[] = 'width="' . (int) $candidate['width'] . '"';
        }

        if (
            !empty(self::$settings['force_width_height']) &&
            !preg_match('/\bheight\s*=\s*["\']\d+["\']/', $img_tag) &&
            !empty($candidate['height'])
        ) {
            $attrs[] = 'height="' . (int) $candidate['height'] . '"';
        }

        if ($attrs) {
            $img_tag = preg_replace('/<img\b/', '<img ' . implode(' ', $attrs), $img_tag, 1);
        }

        return $img_tag;
    }

    /**
     * Get attachment dimensions, populating metadata if missing.
     *
     * @param int $attachment_id Attachment ID.
     * @return array
     */
    private static function get_attachment_dimensions(int $attachment_id): array {
        $cache_key = 'aeseo_att_dim_' . $attachment_id;
        $cached    = wp_cache_get($cache_key, 'aeseo');
        if (is_array($cached) && isset($cached['width'], $cached['height'])) {
            return [
                'width'  => (int) $cached['width'],
                'height' => (int) $cached['height'],
            ];
        }

        $meta   = wp_get_attachment_metadata($attachment_id) ?: [];
        $width  = (int) ($meta['width'] ?? 0);
        $height = (int) ($meta['height'] ?? 0);

        if (!$width || !$height) {
            $file = get_attached_file($attachment_id);
            if ($file && file_exists($file)) {
                $size = @getimagesize($file);
                if (is_array($size)) {
                    $updated = false;
                    if (!$width && !empty($size[0])) {
                        $width        = (int) $size[0];
                        $meta['width'] = $width;
                        $updated       = true;
                    }
                    if (!$height && !empty($size[1])) {
                        $height        = (int) $size[1];
                        $meta['height'] = $height;
                        $updated        = true;
                    }
                    if ($updated) {
                        wp_update_attachment_metadata($attachment_id, $meta);
                    }
                }
            }
        }

        $result = [
            'width'  => $width,
            'height' => $height,
        ];

        wp_cache_set($cache_key, $result, 'aeseo', HOUR_IN_SECONDS);

        return $result;
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
                $dimensions = self::get_attachment_dimensions($id);
                $width      = $width  ?: (int) $dimensions['width'];
                $height     = $height ?: (int) $dimensions['height'];
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
        if ($attachment_id <= 0 || !function_exists('wp_get_attachment_image_src')) {
            return;
        }

        $image = wp_get_attachment_image_src($attachment_id, 'full');
        if (!$image) {
            return;
        }

        $url    = $image[0];
        $width  = (int) ($image[1] ?? 0);
        $height = (int) ($image[2] ?? 0);

        if (!$width || !$height) {
            $dimensions = self::get_attachment_dimensions($attachment_id);
            $width      = $width  ?: (int) $dimensions['width'];
            $height     = $height ?: (int) $dimensions['height'];
        }

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
        if (is_string($attachment_id_or_url) && strpos($attachment_id_or_url, 'data-aeseo-lcp') !== false) {
            return true;
        }

        $key = is_numeric($attachment_id_or_url)
            ? 'id_' . absint($attachment_id_or_url)
            : 'url_' . md5((string) $attachment_id_or_url);

        if (isset(self::$optimized[$key]) || get_transient('aeseo_lcp_attr_' . $key)) {
            return true;
        }

        self::$optimized[$key] = true;
        set_transient('aeseo_lcp_attr_' . $key, 1, HOUR_IN_SECONDS);
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
        if (!apply_filters('aeseo_lcp_should_optimize', true, self::$candidate)) {
            return $default;
        }

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
     * Unset loading attribute and mark the LCP image when matched.
     *
     * @param string|bool $value   Current loading attribute value.
     * @param string      $image   HTML for the image tag.
     * @param string      $context Context in which the image is loaded.
     * @return string|bool
     */
    public static function maybe_unset_loading_attr($value, string $image, string $context) {
        if (!apply_filters('aeseo_lcp_should_optimize', true, self::$candidate)) {
            return $value;
        }

        if (self::$done || empty(self::$settings['remove_lazy_on_lcp'])) {
            return $value;
        }

        $candidate = self::get_lcp_candidate();
        if (empty($candidate)) {
            return $value;
        }

        $src          = '';
        $attachment_id = 0;

        if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/', $image, $matches)) {
            $src = $matches[1];
        }

        if (preg_match('/data-attachment-id\s*=\s*["\'](\d+)["\']/', $image, $matches)) {
            $attachment_id = (int) $matches[1];
        }

        $is_match = false;
        if ($candidate['attachment_id'] && $attachment_id && $candidate['attachment_id'] === $attachment_id) {
            $is_match = true;
        } elseif ($candidate['url'] && $src && $candidate['url'] === $src) {
            $is_match = true;
        }

        if ($is_match) {
            if (strpos($image, 'data-aeseo-lcp="1"') === false) {
                $image = preg_replace('/<img\b/', '<img data-aeseo-lcp="1"', $image, 1);
            }
            return false;
        }

        return $value;
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
        if (!apply_filters('aeseo_lcp_should_optimize', true, self::$candidate)) {
            return $attr;
        }

        if (self::$done) {
            return $attr;
        }

        if (!empty(self::$settings['force_width_height']) && is_object($attachment) && (empty($attr['width']) || empty($attr['height']))) {
            $dimensions = self::get_attachment_dimensions($attachment->ID);
            if (empty($attr['width']) && !empty($dimensions['width'])) {
                $attr['width'] = sanitize_text_field((string) absint($dimensions['width']));
            }
            if (empty($attr['height']) && !empty($dimensions['height'])) {
                $attr['height'] = sanitize_text_field((string) absint($dimensions['height']));
            }
        }

        $candidate = self::get_lcp_candidate();
        if (!empty($candidate)) {
            $src = $attr['src'] ?? '';
            $attachment_id = is_object($attachment) ? (int) $attachment->ID : 0;
            if (!$src && $attachment_id > 0 && function_exists('wp_get_attachment_image_url')) {
                $src = wp_get_attachment_image_url($attachment_id, $size);
            }

            $is_match = false;
            if ($candidate['attachment_id'] && $attachment_id && $candidate['attachment_id'] === $attachment_id) {
                $is_match = true;
            } elseif ($candidate['url'] && $src && $candidate['url'] === $src) {
                $is_match = true;
            }

            if ($is_match && $src && !self::is_already_optimized($attachment_id ? $attachment_id : $src)) {
                $attr['data-aeseo-lcp'] = '1';
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
        if (!apply_filters('aeseo_lcp_should_optimize', true, self::$candidate)) {
            return $html;
        }

        if (empty(self::$settings['responsive_picture_nextgen']) || empty(self::$candidate) || self::$candidate['attachment_id'] !== (int) $attachment_id) {
            return $html;
        }

        if (
            stripos($html, '<source') !== false &&
            (
                stripos($html, 'image/avif') !== false ||
                stripos($html, 'image/webp') !== false ||
                preg_match('/<source[^>]+\.(?:avif|webp)[^>]*>/i', $html)
            )
        ) {
            return $html;
        }

        if (self::is_already_optimized($attachment_id)) {
            return $html;
        }

        if (function_exists('gm2_queue_image_optimization')) {
            gm2_queue_image_optimization($attachment_id);
        }

        if (
            !$attachment_id ||
            !function_exists('wp_get_attachment_image_url') ||
            !function_exists('wp_get_attachment_image_srcset') ||
            !function_exists('wp_get_attachment_image_sizes')
        ) {
            return $html;
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

        $sizes_attr = wp_get_attachment_image_sizes($attachment_id, $size);
        if (!$sizes_attr) {
            $sizes_attr = '(max-width: 768px) 100vw, 1200px';
        }

        $img_tag = $html;
        if (preg_match('/<img[^>]*>/i', $html, $m)) {
            $img_tag = $m[0];
            if (strpos($img_tag, ' srcset=') !== false) {
                $img_tag = preg_replace('/srcset="[^"]*"/', 'srcset="' . esc_attr($srcset) . '"', $img_tag);
            } else {
                $img_tag = preg_replace('/<img/', '<img srcset="' . esc_attr($srcset) . '"', $img_tag, 1);
            }
            if (strpos($img_tag, ' sizes=') !== false) {
                $img_tag = preg_replace('/sizes="[^"]*"/', 'sizes="' . esc_attr($sizes_attr) . '"', $img_tag);
            } else {
                $img_tag = preg_replace('/<img/', '<img sizes="' . esc_attr($sizes_attr) . '"', $img_tag, 1);
            }
        }

        $sources = '';

        if (wp_image_editor_supports(['mime_type' => 'image/avif'])) {
            self::maybe_generate_nextgen_files($attachment_id, 'avif');
            $avif_srcset = self::convert_srcset_extension($srcset, $ext, 'avif');
            if (self::srcset_files_exist($avif_srcset)) {
                $sources .= sprintf('<source type="image/avif" srcset="%s" sizes="%s" />', esc_attr($avif_srcset), esc_attr($sizes_attr));
            }
        }

        if (wp_image_editor_supports(['mime_type' => 'image/webp'])) {
            self::maybe_generate_nextgen_files($attachment_id, 'webp');
            $webp_srcset = self::convert_srcset_extension($srcset, $ext, 'webp');
            if (self::srcset_files_exist($webp_srcset)) {
                $sources .= sprintf('<source type="image/webp" srcset="%s" sizes="%s" />', esc_attr($webp_srcset), esc_attr($sizes_attr));
            }
        }

        if ($sources === '') {
            return $img_tag;
        }

        return '<picture>' . $sources . $img_tag . '</picture>';
    }

    /**
     * Inject a preload link for the LCP image.
     */
    public static function maybe_print_preload(): void {
        if (!apply_filters('aeseo_lcp_should_optimize', true, self::$candidate)) {
            return;
        }

        if (self::$preload_printed || empty(self::$candidate['url']) || empty(self::$settings['add_preload'])) {
            return;
        }

        $url   = self::$candidate['url'];
        $head  = ob_get_contents() ?: '';
        $hints = wp_resource_hints([], 'preload');
        $exists = in_array($url, $hints, true) || (
            (stripos($head, 'rel="preload"') !== false || stripos($head, "rel='preload'") !== false) &&
            stripos($head, $url) !== false
        );

        if ($exists) {
            self::$preload_printed = true;
            return;
        }

        $attr = sprintf('rel="preload" as="image" href="%s" fetchpriority="high"', esc_url($url));

        if (!empty(self::$candidate['attachment_id'])) {
            $srcset = wp_get_attachment_image_srcset(self::$candidate['attachment_id'], 'full');
            if ($srcset) {
                $attr .= sprintf(' imagesrcset="%s"', esc_attr($srcset));
                $sizes = wp_get_attachment_image_sizes(self::$candidate['attachment_id'], 'full');
                if ($sizes) {
                    $attr .= sprintf(' imagesizes="%s"', esc_attr($sizes));
                }
            }
        }

        printf('<link %s />' . "\n", $attr); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        self::$preload_printed = true;
    }

    /**
     * Add preconnect resource hints for the LCP image origin.
     *
     * Allows customizing the hosts via the {@see 'aeseo_lcp_preconnect_hosts'} filter.
     *
     * @param array  $urls          URLs to print for resource hints.
     * @param string $relation_type The relation type the URLs are printed for.
     * @return array Filtered list of resource hints.
     */
    public static function maybe_add_preconnect(array $urls, string $relation_type): array {
        if (!apply_filters('aeseo_lcp_should_optimize', true, self::$candidate)) {
            return $urls;
        }

        if ($relation_type !== 'preconnect' || self::$preconnect_added || empty(self::$settings['add_preconnect']) || empty(self::$candidate['url'])) {
            return $urls;
        }

        $host      = wp_parse_url(self::$candidate['url'], PHP_URL_HOST);
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);

        if (!$host || $host === $site_host) {
            return $urls;
        }

        $existing_hosts = array_map(
            static function ($u) {
                return wp_parse_url($u, PHP_URL_HOST);
            },
            $urls
        );

        $hosts = apply_filters('aeseo_lcp_preconnect_hosts', ['//' . $host], self::$candidate);
        foreach ($hosts as $h) {
            if (!in_array(wp_parse_url($h, PHP_URL_HOST), $existing_hosts, true)) {
                $urls[] = $h;
            }
        }

        self::$preconnect_added = true;
        return $urls;
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

    /**
     * Generate next-gen images for the attachment if missing.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $ext           Extension to generate (webp or avif).
     */
    private static function maybe_generate_nextgen_files(int $attachment_id, string $ext): void {
        if (!in_array($ext, [ 'webp', 'avif' ], true)) {
            return;
        }

        $mime = 'image/' . $ext;
        if (!wp_image_editor_supports([ 'mime_type' => $mime ])) {
            return;
        }

        $meta = wp_get_attachment_metadata($attachment_id);
        if (!is_array($meta) || empty($meta['file'])) {
            return;
        }

        $uploads = wp_get_upload_dir();
        $base    = trailingslashit($uploads['basedir']) . trailingslashit(dirname($meta['file']));
        $files   = [ $meta['file'] ];
        if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
            foreach ($meta['sizes'] as $data) {
                if (!empty($data['file'])) {
                    $files[] = $data['file'];
                }
            }
        }

        foreach ($files as $file) {
            $src_file  = $base . $file;
            $dest_file = preg_replace('/\.[^.]+$/', '.' . $ext, $src_file);
            if (!file_exists($src_file) || file_exists($dest_file)) {
                continue;
            }

            $editor = wp_get_image_editor($src_file);
            if (is_wp_error($editor)) {
                continue;
            }

            $editor->save($dest_file, $mime);
        }
    }
}

AESEO_LCP_Optimizer::boot();

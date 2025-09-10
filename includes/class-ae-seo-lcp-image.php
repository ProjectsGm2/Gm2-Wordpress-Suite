<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists(__NAMESPACE__ . '\\AE_SEO_LCP_Image')) {
    return;
}

/**
 * Remove lazy loading from the first above-the-fold image.
 */
class AE_SEO_LCP_Image {
    /**
     * Track wp_lazy_loading_enabled calls.
     *
     * @var int
     */
    private static $lazy_count = 0;

    /**
     * Track wp_get_attachment_image_attributes calls.
     *
     * @var int
     */
    private static $attr_count = 0;

    /**
     * Index of current LCP candidate.
     *
     * @var int|null
     */
    private static $candidate = null;

    /**
     * Flag when LCP image has been processed.
     *
     * @var bool
     */
    private static $done = false;

    /**
     * URL for the LCP image.
     *
     * @var string|null
     */
    private static $lcp_url = null;

    /**
     * Hook filters.
     */
    public static function init(): void {
        add_filter('wp_lazy_loading_enabled', [ __CLASS__, 'maybe_disable_lazy' ], 10, 3);
        add_filter('wp_get_attachment_image_attributes', [ __CLASS__, 'maybe_adjust_attributes' ], 10, 3);
        add_filter('wp_get_attachment_image', [ __CLASS__, 'maybe_use_picture' ], 10, 5);
        add_filter('the_content', [ __CLASS__, 'maybe_replace_content' ], 20);
        add_action('wp_head', [ __CLASS__, 'maybe_print_links' ], 5);
    }

    /**
     * Disable lazy loading for the first image.
     *
     * @param bool   $default Default lazy loading decision.
     * @param string $tag     Tag name.
     * @param string $context Context.
     * @return bool
     */
    public static function maybe_disable_lazy(bool $default, string $tag, string $context): bool {
        if ($tag !== 'img' || self::$done) {
            return $default;
        }
        self::$lazy_count++;
        if (self::$candidate === null) {
            self::$candidate = self::$lazy_count;
            return false;
        }
        return $default;
    }

    /**
     * Determine if current image is the LCP candidate.
     *
     * @return bool
     */
    private static function is_lcp_image(): bool {
        return self::$candidate === self::$attr_count;
    }

    /**
     * Remove loading attribute for LCP image and handle opt-out.
     *
     * @param array        $attr      Image attributes.
     * @param \WP_Post    $attachment Attachment object.
    * @param string|int[] $size      Requested size.
    * @return array
     */
    public static function maybe_adjust_attributes(array $attr, $attachment, $size): array {
        if (is_object($attachment) && (empty($attr['width']) || empty($attr['height']))) {
            $meta = wp_get_attachment_metadata($attachment->ID);
            if (is_array($meta)) {
                if (empty($attr['width']) && ! empty($meta['width'])) {
                    $attr['width'] = sanitize_text_field((string) absint($meta['width']));
                }
                if (empty($attr['height']) && ! empty($meta['height'])) {
                    $attr['height'] = sanitize_text_field((string) absint($meta['height']));
                }
            }
        }
        if (self::$done) {
            return $attr;
        }
        self::$attr_count++;
        if (strpos($attr['class'] ?? '', 'gm2-hero') !== false && !isset($attr['fetchpriority'])) {
            $attr['fetchpriority'] = 'high';
        }
        if (!isset($attr['decoding'])) {
            $attr['decoding'] = 'async';
        }
        if (self::is_lcp_image()) {
            if (isset($attr['data-gm2-lcp']) && $attr['data-gm2-lcp'] === 'false') {
                $attr['loading'] = $attr['loading'] ?? 'lazy';
                self::$candidate = null;
            } else {
                unset($attr['loading']);
                if (!isset($attr['fetchpriority'])) {
                    $attr['fetchpriority'] = 'high';
                }
                if (!isset($attr['decoding'])) {
                    $attr['decoding'] = 'async';
                }
                $src = $attr['src'] ?? wp_get_attachment_image_url($attachment->ID, $size);
                if ($src) {
                    self::$lcp_url = $src;
                }
                self::$done = true;
            }
        }
        return $attr;
    }

    /**
     * During wp_head, if no existing preload/preconnect for the LCP image exists,
     * print the necessary <link> tags with deduplication using wp_resource_hints.
     */
    public static function maybe_print_links(): void {
        if (!self::$lcp_url) {
            return;
        }

        $url  = self::$lcp_url;
        $head = ob_get_contents() ?: '';

        $preloads = wp_resource_hints([], 'preload');
        $preload_exists = in_array($url, $preloads, true) || (
            (stripos($head, 'rel="preload"') !== false || stripos($head, "rel='preload'") !== false) &&
            stripos($head, $url) !== false
        );

        $host = wp_parse_url($url, PHP_URL_HOST);
        $preconnect_exists = false;
        if ($host) {
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
        }

        if ($host && ! $preconnect_exists) {
            printf('<link rel="preconnect" href="%s" />' . "\n", esc_url('//' . $host));
        }
        if (! $preload_exists) {
            printf('<link rel="preload" as="image" href="%s" fetchpriority="high" />' . "\n", esc_url($url));
        }
    }

    /**
     * Convert the LCP image markup to use a <picture> element when possible.
     *
     * @param string       $html          Image markup.
     * @param int          $attachment_id Attachment ID.
     * @param string|int[] $size          Requested size.
     * @param bool         $icon          Whether image is an icon.
     * @param array|string $attr          Image attributes.
     * @return string
     */
    public static function maybe_use_picture(string $html, $attachment_id, $size, $icon, $attr): string {
        if (!self::is_lcp_image() || post_password_required()) {
            return $html;
        }

        if (stripos($html, '<picture') !== false) {
            return $html;
        }

        $img_tag = self::add_img_attributes($html, true);

        $sources = self::build_nextgen_sources((int) $attachment_id, $img_tag);
        if ($sources === '') {
            return $img_tag;
        }

        return '<picture>' . $sources . $img_tag . '</picture>';
    }

    /**
     * Replace the LCP image inside post content with a picture element.
     */
    public static function maybe_replace_content(string $content): string {
        if (!self::$lcp_url || post_password_required()) {
            return $content;
        }

        if (strpos($content, self::$lcp_url) === false || strpos($content, '<img') === false) {
            return $content;
        }

        if (strpos($content, '<picture') !== false && preg_match('/<picture[^>]*>\s*<img[^>]*' . preg_quote(self::$lcp_url, '/') . '/i', $content)) {
            return $content;
        }

        $attachment_id = attachment_url_to_postid(self::$lcp_url);
        if (!$attachment_id) {
            return $content;
        }

        $pattern = '/<img\b[^>]*' . preg_quote(self::$lcp_url, '/') . '[^>]*>/i';
        $content = preg_replace_callback($pattern, static function ($matches) use ($attachment_id) {
            $img_tag = self::add_img_attributes($matches[0], true);
            $sources = self::build_nextgen_sources($attachment_id, $img_tag);
            if ($sources === '') {
                return $img_tag;
            }
            return '<picture>' . $sources . $img_tag . '</picture>';
        }, $content, 1);

        return $content;
    }

    /**
     * Add decoding and fetchpriority attributes to an img tag.
     */
    private static function add_img_attributes(string $img_tag, bool $fetch_high): string {
        if (strpos($img_tag, 'decoding') === false) {
            $img_tag = preg_replace('/<img/', '<img decoding="async"', $img_tag, 1);
        }
        $is_hero = strpos($img_tag, 'gm2-hero') !== false;
        if (($fetch_high || $is_hero) && strpos($img_tag, 'fetchpriority') === false) {
            $img_tag = preg_replace('/<img/', '<img fetchpriority="high"', $img_tag, 1);
        }
        return $img_tag;
    }

    /**
     * Build source tags for next-gen formats using gm2_nextgen metadata.
     */
    private static function build_nextgen_sources(int $attachment_id, string $img_tag): string {
        $meta = wp_get_attachment_metadata($attachment_id);
        if (!is_array($meta) || empty($meta['gm2_nextgen'])) {
            return '';
        }
        $sizes_attr = '';
        if (preg_match('/sizes="([^"]*)"/i', $img_tag, $m)) {
            $sizes_attr = $m[1];
        } else {
            $sizes_attr = wp_get_attachment_image_sizes($attachment_id, 'full') ?: '';
        }

        $uploads = wp_get_upload_dir();
        $baseurl = trailingslashit($uploads['baseurl']) . trailingslashit(dirname($meta['file']));

        $sources = '';
        foreach (['avif', 'webp'] as $fmt) {
            $srcset = self::nextgen_srcset_from_meta($meta, $fmt, $baseurl);
            if ($srcset) {
                $sources .= sprintf('<source type="image/%s" srcset="%s"%s />', $fmt, esc_attr($srcset), $sizes_attr ? ' sizes="' . esc_attr($sizes_attr) . '"' : '');
            }
        }
        return $sources;
    }

    /**
     * Generate a srcset string for a specific next-gen format from metadata.
     */
    private static function nextgen_srcset_from_meta(array $meta, string $format, string $baseurl): string {
        if (empty($meta['gm2_nextgen'])) {
            return '';
        }
        $items = [];
        if (!empty($meta['gm2_nextgen']['full'][$format]) && !empty($meta['width'])) {
            $items[(int) $meta['width']] = $baseurl . $meta['gm2_nextgen']['full'][$format];
        }
        if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
            foreach ($meta['sizes'] as $size => $data) {
                if (!empty($meta['gm2_nextgen'][$size][$format]) && !empty($data['width'])) {
                    $items[(int) $data['width']] = $baseurl . $meta['gm2_nextgen'][$size][$format];
                }
            }
        }
        if (empty($items)) {
            return '';
        }
        ksort($items, SORT_NUMERIC);
        $parts = [];
        foreach ($items as $width => $url) {
            $parts[] = esc_url($url) . ' ' . (int) $width . 'w';
        }
        return implode(', ', $parts);
    }

    /**
     * Swap the file extension for each item in a srcset string.
     *
     * @param string $srcset    Original srcset.
     * @param string $from_ext  Extension to replace.
     * @param string $to_ext    New extension.
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
     * Verify that at least one file in a srcset exists on disk.
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
     * @param string $ext           Extension to generate.
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

AE_SEO_LCP_Image::init();

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
        if (self::is_lcp_image()) {
            if (isset($attr['data-gm2-lcp']) && $attr['data-gm2-lcp'] === 'false') {
                $attr['loading'] = $attr['loading'] ?? 'lazy';
                self::$candidate = null;
            } else {
                unset($attr['loading']);
                if (!isset($attr['fetchpriority'])) {
                    $attr['fetchpriority'] = 'high';
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
        if (!self::is_lcp_image()) {
            return $html;
        }

        if (stripos($html, '<source') !== false && (stripos($html, 'image/avif') !== false || stripos($html, 'image/webp') !== false)) {
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

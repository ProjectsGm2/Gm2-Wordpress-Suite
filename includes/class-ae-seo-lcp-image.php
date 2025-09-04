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
     * Hook filters.
     */
    public static function init(): void {
        add_filter('wp_lazy_loading_enabled', [ __CLASS__, 'maybe_disable_lazy' ], 10, 3);
        add_filter('wp_get_attachment_image_attributes', [ __CLASS__, 'maybe_adjust_attributes' ], 10, 3);
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
                self::$done = true;
            }
        }
        return $attr;
    }
}

AE_SEO_LCP_Image::init();

<?php
namespace Gm2\NetworkPayload;

if (!defined('ABSPATH')) {
    exit;
}

class Lazyload {
    private static ?string $firstElement = null;
    private static bool $handledFirst = false;

    public static function boot(): void {
        add_filter('wp_lazy_loading_enabled', [__CLASS__, 'maybe_disable_first'], 10, 3);
        add_filter('wp_img_tag_add_loading_attr', [__CLASS__, 'filter_img_loading'], 10, 3);
        add_filter('wp_iframe_tag_add_loading_attr', [__CLASS__, 'filter_iframe_loading'], 10, 3);
        add_filter('wp_content_img_tag', [__CLASS__, 'filter_content_img_tag'], 10, 3);
        add_filter('the_content', [__CLASS__, 'filter_content_iframe_tag'], 10);
    }

    public static function maybe_disable_first(bool $default, string $tag, string $context): bool {
        if ('the_content' === $context && null === self::$firstElement && in_array($tag, ['img', 'iframe'], true)) {
            self::$firstElement = $tag;
            return false;
        }
        return $default;
    }

    public static function filter_img_loading($value, string $image, string $context) {
        if (self::is_first('img') || self::has_hero($image)) {
            return false;
        }
        return 'lazy';
    }

    public static function filter_iframe_loading($value, string $iframe, string $context) {
        if (self::is_first('iframe') || self::has_hero($iframe)) {
            return false;
        }
        return 'lazy';
    }

    public static function filter_content_img_tag(string $image, string $context, int $attachment_id): string {
        if (self::is_first('img')) {
            $image = self::prioritize($image, 'img');
            self::$handledFirst = true;
            return $image;
        }
        if (self::has_hero($image)) {
            return self::prioritize($image, 'img');
        }
        return $image;
    }

    public static function filter_content_iframe_tag(string $content): string {
        $cb = function (array $match) {
            $tag = $match[0];
            if (self::is_first('iframe')) {
                $tag = self::prioritize($tag, 'iframe');
                self::$handledFirst = true;
            } elseif (self::has_hero($tag)) {
                $tag = self::prioritize($tag, 'iframe');
            }
            return $tag;
        };
        return preg_replace_callback('/<iframe[^>]*>/i', $cb, $content);
    }

    private static function has_hero(string $html): bool {
        return (bool) preg_match('/class=["\']([^"\']*)gm2-hero/i', $html);
    }

    private static function is_first(string $tag): bool {
        return self::$firstElement === $tag && !self::$handledFirst;
    }

    private static function prioritize(string $html, string $tag): string {
        $html = preg_replace('/\sloading=["\'][^"\']*["\']/', '', $html);
        if (preg_match('/fetchpriority=/', $html)) {
            $html = preg_replace('/fetchpriority=["\'][^"\']*["\']/', 'fetchpriority="high"', $html);
        } else {
            $html = str_replace('<' . $tag, '<' . $tag . ' fetchpriority="high"', $html);
        }
        if (preg_match('/decoding=/', $html)) {
            $html = preg_replace('/decoding=["\'][^"\']*["\']/', 'decoding="async"', $html);
        } else {
            $html = str_replace('<' . $tag, '<' . $tag . ' decoding="async"', $html);
        }
        return $html;
    }
}


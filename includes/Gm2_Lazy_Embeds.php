<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lazy-load iframe and video embeds.
 */
class Gm2_Lazy_Embeds {
    /**
     * Track whether lazy embeds exist on the page.
     *
     * @var bool
     */
    private static bool $has_lazy = false;

    /**
     * Boot hooks.
     */
    public static function init(): void {
        add_filter('the_content', [ __CLASS__, 'filter_content' ], 20);
        add_action('wp_enqueue_scripts', [ __CLASS__, 'enqueue_script' ]);
    }

    /**
     * Filter post content for video and iframe tags to defer loading.
     *
     * @param string $content The post content.
     * @return string Filtered content.
     */
    public static function filter_content(string $content): string {
        if (stripos($content, '<iframe') === false && stripos($content, '<video') === false) {
            return $content;
        }

        $allow = apply_filters('gm2_lazy_embed_allowlist', [ 'urls' => [], 'classes' => [] ]);
        $allow_urls    = $allow['urls'] ?? [];
        $allow_classes = $allow['classes'] ?? [];

        $modified = false;
        $pattern = '#<(iframe|video)([^>]*)>#i';
        $content = preg_replace_callback($pattern, function ($m) use ($allow_urls, $allow_classes, &$modified) {
            $tag   = $m[1];
            $attrs = $m[2];

            if (!preg_match('/src\s*=\s*(\"|\')(.*?)\1/i', $attrs, $src_m)) {
                return $m[0];
            }
            $src = $src_m[2];

            $class_list = [];
            if (preg_match('/class\s*=\s*(\"|\')(.*?)\1/i', $attrs, $class_m)) {
                $class_list = preg_split('/\s+/', trim($class_m[2]));
            }

            $allowed = false;
            foreach ($allow_urls as $u) {
                if ($u !== '' && str_contains($src, $u)) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed && $allow_classes) {
                foreach ($class_list as $cls) {
                    if (in_array($cls, $allow_classes, true)) {
                        $allowed = true;
                        break;
                    }
                }
            }
            if ($allowed) {
                return $m[0];
            }

            $modified = true;
            $new_attrs = preg_replace('/src\s*=\s*(\"|\')(.*?)\1/i', 'data-src="$2"', $attrs);
            if (!preg_match('/\sloading\s*=/', $new_attrs)) {
                $new_attrs .= ' loading="lazy"';
            }
            return '<' . $tag . $new_attrs . '>';
        }, $content);

        if ($modified) {
            self::$has_lazy = true;
        }

        return $content;
    }

    /**
     * Enqueue lazy-load helper script when needed.
     */
    public static function enqueue_script(): void {
        if (!self::$has_lazy) {
            return;
        }
        ae_seo_register_asset('gm2-lazy-embeds', 'lazy-embeds.js');
        wp_enqueue_script('gm2-lazy-embeds');
    }
}

Gm2_Lazy_Embeds::init();

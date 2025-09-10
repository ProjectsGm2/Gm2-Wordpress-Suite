<?php
namespace Gm2\NetworkPayload;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Replace heavy video iframes with lightweight placeholders.
 */
class LiteEmbeds {
    /** Track whether lite embeds exist on the page. */
    private static bool $has_lite = false;

    /** Boot hooks for lite embeds. */
    public static function boot(): void {
        add_filter('the_content', [__CLASS__, 'filter_content'], 20);
        add_shortcode('gm2_lite_youtube', [__CLASS__, 'shortcode']);
        add_action('init', [__CLASS__, 'register_block']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_script']);
    }

    /** Filter content for YouTube/Vimeo iframes and swap with placeholders. */
    public static function filter_content(string $content): string {
        if (stripos($content, '<iframe') === false) {
            return $content;
        }
        $content = preg_replace_callback('/<iframe[^>]*><\\/iframe>/i', function ($m) {
            $tag = $m[0];
            if (preg_match('/src\\s*=\\s*(["\'])([^"\']+)\\1/i', $tag, $src_m)) {
                $placeholder = self::build_placeholder($src_m[2]);
                if ($placeholder) {
                    self::$has_lite = true;
                    return $placeholder;
                }
            }
            return $tag;
        }, $content);
        return $content;
    }

    /** Generate placeholder markup for a given embed src. */
    private static function build_placeholder(string $src): ?string {
        $poster = '';
        if (preg_match('#youtube\\.com/embed/([^?&/]+)#i', $src, $m)) {
            $id = $m[1];
            $poster = sprintf('https://i.ytimg.com/vi/%s/hqdefault.jpg', rawurlencode($id));
        } elseif (preg_match('#player\\.vimeo\\.com/video/(\\d+)#i', $src, $m)) {
            $id = $m[1];
            $poster = sprintf('https://vumbnail.com/%s.jpg', rawurlencode($id));
        } else {
            return null;
        }

        $src   = esc_url($src);
        $poster = esc_url($poster);

        return sprintf(
            '<div class="gm2-lite-embed" data-src="%s"><div class="gm2-lite-embed__poster" style="background-image:url(\'%s\')"></div><button class="gm2-lite-embed__play" aria-label="%s"></button></div>',
            $src,
            $poster,
            esc_attr__('Play video', 'gm2-wordpress-suite')
        );
    }

    /** Enqueue helper script if lite embeds were output. */
    public static function enqueue_script(): void {
        if (!self::$has_lite) {
            return;
        }
        if (function_exists('ae_seo_register_asset')) {
            ae_seo_register_asset('gm2-lite-embeds', 'lite-embeds.js');
        }
        wp_enqueue_script('gm2-lite-embeds');
    }

    /** Shortcode handler for YouTube embeds. */
    public static function shortcode($atts): string {
        $atts = shortcode_atts(['id' => ''], $atts, 'gm2_lite_youtube');
        $id = trim($atts['id']);
        if ($id === '') {
            return '';
        }
        $src = sprintf('https://www.youtube.com/embed/%s', rawurlencode($id));
        $placeholder = self::build_placeholder($src);
        if ($placeholder) {
            self::$has_lite = true;
            return $placeholder;
        }
        return '';
    }

    /** Register block equivalent to the shortcode. */
    public static function register_block(): void {
        if (!function_exists('register_block_type')) {
            return;
        }
        register_block_type('gm2/lite-youtube', [
            'render_callback' => [__CLASS__, 'render_block'],
            'attributes'      => [
                'id' => [
                    'type'    => 'string',
                    'default' => '',
                ],
            ],
        ]);
    }

    /** Render callback for the block. */
    public static function render_block(array $attributes = []): string {
        $id = $attributes['id'] ?? '';
        return self::shortcode(['id' => $id]);
    }
}


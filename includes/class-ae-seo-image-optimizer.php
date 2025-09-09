<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generic image optimizer that converts <img> tags to <picture> with next-gen sources.
 */
final class AE_SEO_Image_Optimizer {
    /**
     * Attach hooks for image optimization.
     */
    public static function boot(): void {
        add_filter('wp_get_attachment_image_tag', [ __CLASS__, 'filter_attachment_html' ], 20, 3);
        add_filter('wp_get_attachment_image', [ __CLASS__, 'filter_attachment_html' ], 20, 5);
        add_filter('the_content', [ __CLASS__, 'filter_content_images' ], 30);
    }

    /**
     * Filter attachment image HTML to output a <picture> element.
     *
     * @param string $html          Original HTML.
     * @param int    $attachment_id Attachment ID.
     * @return string
     */
    public static function filter_attachment_html(string $html, int $attachment_id): string {
        if (AESEO_LCP_Optimizer::is_already_optimized($attachment_id)) {
            return $html;
        }
        return self::pictureify($html, $attachment_id);
    }

    /**
     * Convert <img> tags within post content.
     *
     * @param string $content Post content.
     * @return string
     */
    public static function filter_content_images(string $content): string {
        return preg_replace_callback(
            '/<img\b[^>]*>/i',
            static function (array $matches) {
                $img_tag = $matches[0];
                $attachment_id = 0;
                if (preg_match('/wp-image-(\d+)/', $img_tag, $m)) {
                    $attachment_id = (int) $m[1];
                }
                $key = $attachment_id ?: $img_tag;
                if (AESEO_LCP_Optimizer::is_already_optimized($key)) {
                    return $img_tag;
                }
                return self::pictureify($img_tag, $attachment_id);
            },
            $content
        );
    }

    /**
     * Wrap an image tag in a <picture> element with next-gen sources.
     *
     * @param string $img_tag       Original img tag.
     * @param int    $attachment_id Attachment ID if known.
     * @return string
     */
    private static function pictureify(string $img_tag, int $attachment_id = 0): string {
        if (!preg_match('/src="([^"]+)"/', $img_tag, $src_match)) {
            return $img_tag;
        }
        $src = $src_match[1];
        $ext = pathinfo(parse_url($src, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);

        if (!preg_match('/srcset="([^"]+)"/', $img_tag, $srcset_match)) {
            $srcset = $attachment_id ? (string) \wp_get_attachment_image_srcset($attachment_id) : '';
            if ($srcset) {
                $img_tag = preg_replace('/<img/', '<img srcset="' . \esc_attr($srcset) . '"', $img_tag, 1);
            }
        } else {
            $srcset = $srcset_match[1];
        }
        if ($srcset === '') {
            return $img_tag;
        }

        $sizes = '';
        if (preg_match('/sizes="([^"]*)"/', $img_tag, $m_sizes)) {
            $sizes = $m_sizes[1];
        } elseif ($attachment_id) {
            $sizes = (string) \wp_get_attachment_image_sizes($attachment_id);
            if ($sizes) {
                $img_tag = preg_replace('/<img/', '<img sizes="' . \esc_attr($sizes) . '"', $img_tag, 1);
            }
        }

        $sources = '';
        if (\wp_image_editor_supports(['mime_type' => 'image/avif'])) {
            AESEO_LCP_Optimizer::maybe_generate_nextgen_files($attachment_id, 'avif');
            $avif_srcset = AESEO_LCP_Optimizer::convert_srcset_extension($srcset, $ext, 'avif');
            if (AESEO_LCP_Optimizer::srcset_files_exist($avif_srcset)) {
                $sources .= sprintf('<source type="image/avif" srcset="%s" sizes="%s" />', \esc_attr($avif_srcset), \esc_attr($sizes));
            }
        }
        if (\wp_image_editor_supports(['mime_type' => 'image/webp'])) {
            AESEO_LCP_Optimizer::maybe_generate_nextgen_files($attachment_id, 'webp');
            $webp_srcset = AESEO_LCP_Optimizer::convert_srcset_extension($srcset, $ext, 'webp');
            if (AESEO_LCP_Optimizer::srcset_files_exist($webp_srcset)) {
                $sources .= sprintf('<source type="image/webp" srcset="%s" sizes="%s" />', \esc_attr($webp_srcset), \esc_attr($sizes));
            }
        }
        if ($sources === '') {
            return $img_tag;
        }

        return '<picture>' . $sources . $img_tag . '</picture>';
    }
}

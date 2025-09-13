<?php
namespace Plugin\CLS\Dimensions;

if (!defined('ABSPATH')) {
    exit;
}

function register(): void {
    if (is_admin() || false === apply_filters('plugin_cls_dimensions_enabled', true)) {
        return;
    }
    add_filter('the_content', __NAMESPACE__ . '\\filter_content', 20);
    add_filter('wp_get_attachment_image_attributes', __NAMESPACE__ . '\\filter_image_attrs', 10, 3);
    add_filter('embed_oembed_html', __NAMESPACE__ . '\\wrap_oembed', 10, 4);
    add_filter('oembed_result', __NAMESPACE__ . '\\wrap_oembed', 10, 4);
    add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets');
}

function filter_content($content) {
    if (!class_exists('DOMDocument') || !function_exists('libxml_use_internal_errors')) {
        return $content;
    }
    if (stripos($content, '<img') === false && stripos($content, '<video') === false && stripos($content, '<iframe') === false) {
        return $content;
    }
    libxml_use_internal_errors(true);
    $doc = new \DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    foreach ($doc->getElementsByTagName('img') as $img) {
        if (!$img->hasAttribute('decoding')) {
            $img->setAttribute('decoding', 'async');
        }
        $width = $img->getAttribute('width');
        $height = $img->getAttribute('height');
        if (!$width || !$height) {
            $src = $img->getAttribute('src');
            $dims = get_image_dimensions($src);
            if ($dims) {
                [$w, $h] = $dims;
                if (!$width) {
                    $img->setAttribute('width', (string) $w);
                }
                if (!$height) {
                    $img->setAttribute('height', (string) $h);
                }
                $width = $img->getAttribute('width');
                $height = $img->getAttribute('height');
            }
        }
        if ((!$width || !$height) && $img->hasAttribute('srcset')) {
            $candidate = trim(explode(',', $img->getAttribute('srcset'))[0]);
            $url = trim(explode(' ', $candidate)[0]);
            $dims = parse_dims_from_url($url);
            if ($dims) {
                [$dw, $dh] = $dims;
                if (!$img->hasAttribute('data-w')) {
                    $img->setAttribute('data-w', (string) $dw);
                }
                if (!$img->hasAttribute('data-h')) {
                    $img->setAttribute('data-h', (string) $dh);
                }
            }
        }
    }

    foreach ($doc->getElementsByTagName('video') as $video) {
        $width = $video->getAttribute('width');
        $height = $video->getAttribute('height');
        if (!$width || !$height) {
            $poster = $video->getAttribute('poster');
            if ($poster) {
                $dims = get_image_dimensions($poster);
                if ($dims) {
                    [$w, $h] = $dims;
                    if (!$width) {
                        $video->setAttribute('width', (string) $w);
                    }
                    if (!$height) {
                        $video->setAttribute('height', (string) $h);
                    }
                    $width = $video->getAttribute('width');
                    $height = $video->getAttribute('height');
                }
            }
        }
        if (!$width || !$height) {
            if (!$video->hasAttribute('data-aspect')) {
                $video->setAttribute('data-aspect', '16/9');
            }
        }
    }

    foreach ($doc->getElementsByTagName('iframe') as $iframe) {
        if (false === apply_filters('plugin_cls_dimensions_wrap_oembed', true)) {
            continue;
        }
        $width = (int) $iframe->getAttribute('width');
        $height = (int) $iframe->getAttribute('height');
        $aspect = ($width > 0 && $height > 0) ? $width . '/' . $height : '16/9';
        $parent = $iframe->parentNode;
        if ($parent instanceof \DOMElement) {
            $class = ' ' . $parent->getAttribute('class') . ' ';
            if (strpos($class, ' wp-block-embed__wrapper ') !== false) {
                $style = $parent->getAttribute('style');
                if (strpos($style, 'aspect-ratio') === false) {
                    $parent->setAttribute('style', trim($style . ' aspect-ratio:' . $aspect));
                }
                continue;
            }
            if (strpos($class, ' cls-embed-wrap ') !== false) {
                if (!$parent->hasAttribute('data-aspect')) {
                    $parent->setAttribute('data-aspect', $aspect);
                }
                continue;
            }
        }
        $wrapper = $doc->createElement('div');
        $wrapper->setAttribute('class', 'cls-embed-wrap');
        $wrapper->setAttribute('data-aspect', $aspect);
        $parent->replaceChild($wrapper, $iframe);
        $wrapper->appendChild($iframe);
    }

    return $doc->saveHTML();
}

function filter_image_attrs($attr, $attachment, $size) {
    if (empty($attr['width']) || empty($attr['height'])) {
        $meta = wp_get_attachment_metadata($attachment->ID ?? $attachment);
        if (is_array($meta)) {
            if (empty($attr['width']) && !empty($meta['width'])) {
                $attr['width'] = $meta['width'];
            }
            if (empty($attr['height']) && !empty($meta['height'])) {
                $attr['height'] = $meta['height'];
            }
        }
    }
    if (!isset($attr['decoding'])) {
        $attr['decoding'] = 'async';
    }
    return $attr;
}

function wrap_oembed($html) {
    if (false === apply_filters('plugin_cls_dimensions_wrap_oembed', true)) {
        return $html;
    }
    if (strpos($html, 'cls-embed-wrap') !== false) {
        return $html;
    }
    if (!preg_match('/<iframe[^>]*>/i', $html)) {
        return $html;
    }
    $aspect = '16/9';
    if (preg_match('/width="(\d+)"/i', $html, $w) && preg_match('/height="(\d+)"/i', $html, $h) && (int) $h[1] > 0) {
        $aspect = $w[1] . '/' . $h[1];
    } elseif (stripos($html, 'soundcloud') !== false) {
        $aspect = '1/1';
    }
    return '<div class="cls-embed-wrap" data-aspect="' . esc_attr($aspect) . '">' . $html . '</div>';
}

function enqueue_assets(): void {
    if (is_admin()) {
        return;
    }
    wp_enqueue_style('cls-reserved', GM2_PLUGIN_URL . 'assets/css/cls-reserved.css', [], GM2_VERSION);
    wp_enqueue_script('cls-aspect-ratio', GM2_PLUGIN_URL . 'assets/js/cls-aspect-ratio.js', [], GM2_VERSION, true);
    wp_script_add_data('cls-aspect-ratio', 'defer', true);
}

function get_image_dimensions(string $src): ?array {
    $id = attachment_url_to_postid($src);
    if ($id) {
        $meta = wp_get_attachment_metadata($id);
        if (is_array($meta) && !empty($meta['width']) && !empty($meta['height'])) {
            return [(int) $meta['width'], (int) $meta['height']];
        }
        $file = get_attached_file($id);
        if ($file && file_exists($file)) {
            $info = @getimagesize($file);
            if ($info) {
                return [(int) $info[0], (int) $info[1]];
            }
        }
    } else {
        $info = @getimagesize($src);
        if ($info) {
            return [(int) $info[0], (int) $info[1]];
        }
    }
    return null;
}

function parse_dims_from_url(string $url): ?array {
    if (preg_match('/-([0-9]+)x([0-9]+)\./', $url, $m)) {
        return [(int) $m[1], (int) $m[2]];
    }
    return null;
}

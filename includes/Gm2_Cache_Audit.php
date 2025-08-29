<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Cache_Audit {
    public static function scan() {
        $scripts = wp_scripts();
        $styles  = wp_styles();

        $handles = [
            'scripts' => array_values(array_unique(array_merge(array_keys($scripts->registered), $scripts->queue))),
            'styles'  => array_values(array_unique(array_merge(array_keys($styles->registered), $styles->queue))),
        ];

        $home      = home_url();
        $response  = wp_remote_get($home);
        $body      = is_wp_error($response) ? '' : wp_remote_retrieve_body($response);
        $urls      = [];

        if ($body) {
            if (preg_match_all('#<script[^>]+src=["\']([^"\']+)["\']#i', $body, $m)) {
                foreach ($m[1] as $src) {
                    $urls[self::abs_url($src)] = 'script';
                }
            }
            if (preg_match_all('#<link[^>]+rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\']#i', $body, $m)) {
                foreach ($m[1] as $href) {
                    $urls[self::abs_url($href)] = 'style';
                }
            }
            if (preg_match_all('#<link[^>]+rel=["\'](preload|preconnect)["\'][^>]*href=["\']([^"\']+)["\'][^>]*>#i', $body, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $href = $match[2];
                    $type = 'other';
                    if (preg_match('#as=["\']([^"\']+)["\']#i', $match[0], $a)) {
                        $as = strtolower($a[1]);
                        if ($as === 'font') {
                            $type = 'font';
                        } elseif ($as === 'style') {
                            $type = 'style';
                        } elseif ($as === 'script') {
                            $type = 'script';
                        } elseif ($as === 'image') {
                            $type = 'image';
                        }
                    }
                    $urls[self::abs_url($href)] = $type;
                }
            }
            if (preg_match_all('#<img[^>]+src=["\']([^"\']+)["\']#i', $body, $m)) {
                foreach ($m[1] as $src) {
                    $urls[self::abs_url($src)] = 'image';
                }
            }
        }

        foreach ($scripts->registered as $data) {
            if (!empty($data->src)) {
                $url = self::abs_url($data->src);
                if ($url) {
                    $urls[$url] = 'script';
                }
            }
        }
        foreach ($styles->registered as $data) {
            if (!empty($data->src)) {
                $url = self::abs_url($data->src);
                if ($url) {
                    $urls[$url] = 'style';
                }
            }
        }

        $assets = [];
        foreach ($urls as $url => $type) {
            if (!$url || !preg_match('#^https?://#i', $url)) {
                continue;
            }
            $resp = wp_remote_head($url);
            $code = is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp);
            if (is_wp_error($resp) || in_array($code, [403, 405], true)) {
                $resp = wp_remote_get($url, ['headers' => ['Range' => 'bytes=0-0']]);
            }

            $headers = is_wp_error($resp) ? [] : wp_remote_retrieve_headers($resp);
            $cache_control = isset($headers['cache-control']) ? $headers['cache-control'] : '';
            $expires       = isset($headers['expires']) ? $headers['expires'] : '';
            $etag          = isset($headers['etag']) ? $headers['etag'] : '';
            $last_modified = isset($headers['last-modified']) ? $headers['last-modified'] : '';
            $content_len   = isset($headers['content-length']) ? $headers['content-length'] : '';

            $ttl = null;
            if ($cache_control && preg_match('/max-age=([0-9]+)/', $cache_control, $m)) {
                $ttl = (int) $m[1];
            } elseif ($expires) {
                $ttl = strtotime($expires) - time();
            }

            $issues = [];
            if ($cache_control === '' || $cache_control === null) {
                $issues[] = 'missing_cache_control';
            }
            if ($ttl !== null && $ttl < 604800) {
                $issues[] = 'short_max_age';
            }
            if (strpos($url, '?ver=') !== false && stripos($cache_control, 'immutable') === false) {
                $issues[] = 'missing_immutable';
            }
            if (!$etag && !$last_modified) {
                $issues[] = 'missing_validation';
            }

            $assets[] = [
                'url'            => $url,
                'type'           => $type,
                'cache_control'  => $cache_control,
                'expires'        => $expires,
                'etag'           => $etag,
                'last_modified'  => $last_modified,
                'content_length' => $content_len,
                'ttl'            => $ttl,
                'needs_attention' => !empty($issues),
                'issues'         => $issues,
            ];
        }

        return [
            'scanned_at' => current_time('mysql'),
            'handles'    => $handles,
            'assets'     => $assets,
        ];
    }

    protected static function abs_url($url) {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (strpos($url, '//') === 0) {
            $scheme = is_ssl() ? 'https:' : 'http:';
            $url = $scheme . $url;
        } elseif (!preg_match('#^https?://#i', $url)) {
            $url = wp_make_link_absolute($url, home_url('/'));
        }
        return $url;
    }
}

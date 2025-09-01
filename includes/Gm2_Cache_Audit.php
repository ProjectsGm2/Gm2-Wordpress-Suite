<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Cache_Audit {

    const OPTION_NAME = 'gm2_cache_audit_results';

    public static function init() {
        add_filter('script_loader_src', [self::class, 'replace_mapped_url']);
        add_filter('style_loader_src', [self::class, 'replace_mapped_url']);
        add_filter('wp_get_attachment_url', [self::class, 'replace_mapped_url']);
        add_action('template_redirect', [self::class, 'buffer_output'], 0);
    }

    public static function replace_mapped_url($url) {
        $map = get_option('gm2_cache_audit_local_map', []);
        if (!is_array($map) || empty($map)) {
            return $url;
        }
        return $map[$url] ?? $url;
    }

    public static function buffer_output() {
        $map = get_option('gm2_cache_audit_local_map', []);
        if (!is_array($map) || empty($map)) {
            return;
        }
        ob_start(function ($html) use ($map) {
            return str_replace(array_keys($map), array_values($map), $html);
        });
    }

    public static function get_results() {
        $results = get_option(self::OPTION_NAME, []);
        return is_array($results) ? $results : [];
    }

    public static function save_results($results) {
        update_option(self::OPTION_NAME, $results);
    }

    public static function clear_results() {
        delete_option(self::OPTION_NAME);
    }

    public static function rescan() {
        $results = static::scan();
        static::save_results($results);
        return $results;
    }

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
                    $urls[self::abs_url($src)] = [ 'type' => 'script', 'handle' => null ];
                }
            }
            if (preg_match_all('#<link[^>]+rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\']#i', $body, $m)) {
                foreach ($m[1] as $href) {
                    $urls[self::abs_url($href)] = [ 'type' => 'style', 'handle' => null ];
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
                    $urls[self::abs_url($href)] = [ 'type' => $type, 'handle' => null ];
                }
            }
            if (preg_match_all('#<img[^>]+src=["\']([^"\']+)["\']#i', $body, $m)) {
                foreach ($m[1] as $src) {
                    $urls[self::abs_url($src)] = [ 'type' => 'image', 'handle' => null ];
                }
            }
        }

        foreach ($scripts->registered as $handle => $data) {
            if (!empty($data->src)) {
                $url = self::abs_url($data->src);
                if ($url) {
                    $urls[$url] = [ 'type' => 'script', 'handle' => $handle ];
                }
            }
        }
        foreach ($styles->registered as $handle => $data) {
            if (!empty($data->src)) {
                $url = self::abs_url($data->src);
                if ($url) {
                    $urls[$url] = [ 'type' => 'style', 'handle' => $handle ];
                }
            }
        }

        $assets = [];
        foreach ($urls as $url => $info) {
            $type   = $info['type'];
            $handle = $info['handle'];
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
                'handle'         => $handle,
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

    public static function apply_fix(array $asset) {
        $url    = $asset['url'] ?? '';
        $type   = $asset['type'] ?? '';
        $handle = $asset['handle'] ?? null;
        if (!$url || !$type) {
            return new \WP_Error('invalid_asset', __('Invalid asset.', 'gm2-wordpress-suite'));
        }

        $results = static::get_results();
        if (empty($results['assets']) || !is_array($results['assets'])) {
            return new \WP_Error('asset_not_found', __('Asset not found.', 'gm2-wordpress-suite'));
        }

        $index  = null;
        foreach ($results['assets'] as $i => $stored) {
            if ($stored['url'] === $url && $stored['type'] === $type) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return new \WP_Error('asset_not_found', __('Asset not found.', 'gm2-wordpress-suite'));
        }

        $stored  = $results['assets'][$index];
        $issues  = $stored['issues'] ?? [];
        $handle  = $handle ?? ($stored['handle'] ?? null);
        if (!$handle && $type === 'script') {
            $scripts = wp_scripts();
            foreach ($scripts->registered as $h => $data) {
                if (!empty($data->src)) {
                    $registered_url = self::abs_url($data->src);
                    if ($registered_url === $url) {
                        $handle = $h;
                        break;
                    }
                }
            }
        }

        $asset_host = parse_url($url, PHP_URL_HOST);
        $home_host  = parse_url(home_url(), PHP_URL_HOST);
        $host_type  = ($asset_host && $asset_host === $home_host) ? 'same' : 'third';

        $updated = $stored;

        if ($host_type === 'third' && $type === 'script') {
            $attrs = get_option('gm2_script_attributes', []);
            if (!is_array($attrs)) {
                $attrs = [];
            }
            if (!$handle) {
                return new \WP_Error('missing_handle', __('Script handle not found.', 'gm2-wordpress-suite'));
            }
            if (empty($attrs[$handle]) || ($attrs[$handle] !== 'async' && $attrs[$handle] !== 'defer')) {
                $attrs[$handle] = 'defer';
                update_option('gm2_script_attributes', $attrs);
            }
            $verify = get_option('gm2_script_attributes', []);
            if (($verify[$handle] ?? '') !== 'defer' && ($verify[$handle] ?? '') !== 'async') {
                return new \WP_Error('fix_failed', __('Failed to set script attribute.', 'gm2-wordpress-suite'));
            }
        }

        if ($host_type !== 'third' && (in_array('short_max_age', $issues, true) || in_array('missing_cache_control', $issues, true))) {
            $apache = \Gm2\Gm2_Cache_Headers_Apache::maybe_apply();
            $nginx  = \Gm2\Gm2_Cache_Headers_Nginx::maybe_apply();

            $primary   = $apache['status'] !== 'unsupported' ? $apache : $nginx;

            if (in_array($primary['status'], ['unsupported', 'already_handled', 'not_writable'], true)) {
                $msg = '';
                if ($primary['status'] === 'unsupported') {
                    $msg = __('Unsupported server environment.', 'gm2-wordpress-suite');
                } elseif ($primary['status'] === 'already_handled') {
                    $msg = __('Cache headers already handled by CDN or server.', 'gm2-wordpress-suite');
                } elseif ($primary['status'] === 'not_writable') {
                    if (isset($primary['file'])) {
                        $msg = sprintf(__('Cannot write to %s. Please update manually.', 'gm2-wordpress-suite'), $primary['file']);
                    } else {
                        $file = ABSPATH . '.htaccess';
                        $msg = sprintf(__('Cannot write to %s. Please update manually.', 'gm2-wordpress-suite'), $file);
                    }
                    if (isset($primary['rules'])) {
                        $msg .= ' ' . sprintf(__('Rules: %s', 'gm2-wordpress-suite'), $primary['rules']);
                    }
                }
                return new \WP_Error($primary['status'], $msg);
            }

            $resp = wp_remote_head($url);
            $code = is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp);
            if (is_wp_error($resp) || in_array($code, [403, 405], true)) {
                $resp = wp_remote_get($url, ['headers' => ['Range' => 'bytes=0-0']]);
            }
            $headers = is_wp_error($resp) ? [] : wp_remote_retrieve_headers($resp);
            $cache_control = $headers['cache-control'] ?? '';
            $expires       = $headers['expires'] ?? '';
            $ttl = null;
            if ($cache_control && preg_match('/max-age=([0-9]+)/', $cache_control, $m)) {
                $ttl = (int) $m[1];
            } elseif ($expires) {
                $ttl = strtotime($expires) - time();
            }

            // Store the measured headers/TTL regardless of success so the UI can display them.
            $updated['cache_control'] = $cache_control;
            $updated['expires']       = $expires;
            $updated['ttl']           = $ttl;

            if ($ttl === null || $ttl < 604800) {
                $updated['needs_attention'] = !empty($updated['issues']);
                $results['assets'][$index]  = $updated;
                static::save_results($results);
                return new \WP_Error(
                    'ttl_unverified',
                    __('Cache headers unchanged; verify server rules.', 'gm2-wordpress-suite'),
                    [
                        'ttl'          => $ttl,
                        'cache_control'=> $cache_control,
                        'expires'      => $expires,
                    ]
                );
            }

            // Only clear related issues if the TTL verifies at a week or longer.
            $updated['issues'] = array_diff($updated['issues'], ['short_max_age', 'missing_cache_control']);
        }

        if (in_array('missing_immutable', $issues, true)) {
            update_option('gm2_pretty_versioned_urls', '1');
            if (get_option('gm2_pretty_versioned_urls') !== '1') {
                return new \WP_Error('fix_failed', __('Failed to enable versioned URLs.', 'gm2-wordpress-suite'));
            }
            if ($handle) {
                $updated['url'] = \Gm2\Versioning_MTime::update_src($url, $handle);
            }
            $updated['issues'] = array_diff($updated['issues'], ['missing_immutable']);
        }

        if ($host_type === 'third' && $type !== 'script') {
            $upload = wp_upload_dir();
            $dir = trailingslashit($upload['basedir']) . 'gm2-cache-audit/';
            wp_mkdir_p($dir);
            $filename = wp_basename(parse_url($url, PHP_URL_PATH) ?? '');
            if ($filename === '' || $filename === null) {
                $filename = md5($url);
            }
            $tmp = download_url($url);
            if (is_wp_error($tmp)) {
                return new \WP_Error('fix_failed', __('Download failed.', 'gm2-wordpress-suite'));
            }
            $local_path = $dir . $filename;
            if (!@rename($tmp, $local_path)) {
                @unlink($tmp);
                return new \WP_Error('fix_failed', __('Failed to store local copy.', 'gm2-wordpress-suite'));
            }
            $local_url = trailingslashit($upload['baseurl']) . 'gm2-cache-audit/' . $filename;
            $map = get_option('gm2_cache_audit_local_map', []);
            if (!is_array($map)) {
                $map = [];
            }
            $map[$url] = $local_url;
            update_option('gm2_cache_audit_local_map', $map);
            $updated['url'] = $local_url;

            $resp = wp_remote_head($local_url);
            $code = is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp);
            if (is_wp_error($resp) || in_array($code, [403, 405], true)) {
                $resp = wp_remote_get($local_url, ['headers' => ['Range' => 'bytes=0-0']]);
            }
            $headers = is_wp_error($resp) ? [] : wp_remote_retrieve_headers($resp);
            $cache_control = $headers['cache-control'] ?? '';
            $expires       = $headers['expires'] ?? '';
            $ttl = null;
            if ($cache_control && preg_match('/max-age=([0-9]+)/', $cache_control, $m)) {
                $ttl = (int) $m[1];
            } elseif ($expires) {
                $ttl = strtotime($expires) - time();
            }

            $updated['cache_control'] = $cache_control;
            $updated['expires']       = $expires;
            $updated['ttl']           = $ttl;

            if ($ttl !== null && $ttl >= 604800) {
                $updated['issues'] = array_diff($updated['issues'], ['short_max_age', 'missing_cache_control']);
            } else {
                $apache = \Gm2\Gm2_Cache_Headers_Apache::maybe_apply();
                $nginx  = \Gm2\Gm2_Cache_Headers_Nginx::maybe_apply();
                $primary = $apache['status'] !== 'unsupported' ? $apache : $nginx;

                if (in_array($primary['status'], ['unsupported', 'already_handled', 'not_writable'], true)) {
                    $msg = '';
                    if ($primary['status'] === 'unsupported') {
                        $msg = __('Unsupported server environment.', 'gm2-wordpress-suite');
                    } elseif ($primary['status'] === 'already_handled') {
                        $msg = __('Cache headers already handled by CDN or server.', 'gm2-wordpress-suite');
                    } elseif ($primary['status'] === 'not_writable') {
                        if (isset($primary['file'])) {
                            $msg = sprintf(__('Cannot write to %s. Please update manually.', 'gm2-wordpress-suite'), $primary['file']);
                        } else {
                            $file = ABSPATH . '.htaccess';
                            $msg = sprintf(__('Cannot write to %s. Please update manually.', 'gm2-wordpress-suite'), $file);
                        }
                        if (isset($primary['rules'])) {
                            $msg .= ' ' . sprintf(__('Rules: %s', 'gm2-wordpress-suite'), $primary['rules']);
                        }
                    }
                    return new \WP_Error($primary['status'], $msg);
                }

                $resp = wp_remote_head($local_url);
                $code = is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp);
                if (is_wp_error($resp) || in_array($code, [403, 405], true)) {
                    $resp = wp_remote_get($local_url, ['headers' => ['Range' => 'bytes=0-0']]);
                }
                $headers = is_wp_error($resp) ? [] : wp_remote_retrieve_headers($resp);
                $cache_control = $headers['cache-control'] ?? '';
                $expires       = $headers['expires'] ?? '';
                $ttl = null;
                if ($cache_control && preg_match('/max-age=([0-9]+)/', $cache_control, $m)) {
                    $ttl = (int) $m[1];
                } elseif ($expires) {
                    $ttl = strtotime($expires) - time();
                }

                $updated['cache_control'] = $cache_control;
                $updated['expires']       = $expires;
                $updated['ttl']           = $ttl;

                if ($ttl === null || $ttl < 604800) {
                    $updated['needs_attention'] = !empty($updated['issues']);
                    $results['assets'][$index]  = $updated;
                    static::save_results($results);
                    return new \WP_Error(
                        'ttl_unverified',
                        __('Cache headers unchanged; verify server rules.', 'gm2-wordpress-suite'),
                        [
                            'ttl'           => $ttl,
                            'cache_control' => $cache_control,
                            'expires'       => $expires,
                        ]
                    );
                }

                $updated['issues'] = array_diff($updated['issues'], ['short_max_age', 'missing_cache_control']);
            }
        }

        if ($handle) {
            $updated['handle'] = $handle;
        }
        $updated['needs_attention'] = !empty($updated['issues']);
        $results['assets'][$index] = $updated;
        static::save_results($results);
        return $updated;
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
            $url = \WP_Http::make_absolute_url($url, home_url('/'));
        }
        return $url;
    }
}

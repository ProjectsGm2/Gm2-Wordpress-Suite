<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Cache_Headers_Nginx {
    public static $rules = <<<'NGINX'
location ~* \.v[0-9]+\.(css|js)$ { try_files $uri $uri/ @stripver; }
location @stripver { rewrite ^/(.+)\.v[0-9]+\.(css|js)$ /$1.$2 last; }

location ~* \.(?:css|js)$ {
    expires 1y;
    add_header Cache-Control "public, max-age=31536000, immutable";
}
location ~* \.(?:woff2|svg|webp|avif|jpg|jpeg|png|gif)$ {
    expires 1y;
    add_header Cache-Control "public, max-age=31536000, immutable";
}
NGINX;

    public static function get_file_path() {
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'gm2-wordpress-suite';
        return trailingslashit($dir) . 'nginx-cache-headers.conf';
    }

    public static function is_supported_server() {
        $server = $_SERVER['SERVER_SOFTWARE'] ?? '';
        return stripos($server, 'nginx') !== false;
    }

    public static function cdn_sets_cache_headers() {
        $url = home_url('/wp-includes/js/jquery/jquery.js');
        $resp = wp_remote_head($url);
        $code = is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp);
        if (is_wp_error($resp) || in_array($code, [403, 405], true)) {
            $resp = wp_remote_get($url, ['headers' => ['Range' => 'bytes=0-0']]);
        }
        $headers = is_wp_error($resp) ? [] : wp_remote_retrieve_headers($resp);
        if (!is_array($headers)) {
            $headers = [];
        }
        foreach ($headers as $key => $value) {
            $k = strtolower($key);
            if ($k === 'cache-control') {
                return true;
            }
            if (strpos($k, 'cdn') !== false || strpos($k, 'cache') !== false) {
                return true;
            }
        }
        return false;
    }

    public static function write_rules() {
        $file = self::get_file_path();
        wp_mkdir_p(dirname($file));
        file_put_contents($file, self::$rules);
    }

    public static function rules_exist() {
        return file_exists(self::get_file_path());
    }

    public static function maybe_apply() {
        if (!self::is_supported_server()) {
            return ['status' => 'unsupported'];
        }
        if (self::cdn_sets_cache_headers()) {
            return ['status' => 'already_handled'];
        }
        $file = self::get_file_path();
        if (!wp_is_writable(dirname($file))) {
            return ['status' => 'not_writable', 'file' => $file, 'rules' => self::$rules];
        }
        self::write_rules();
        return ['status' => 'written', 'file' => $file];
    }

    public static function verify() {
        $url = home_url('/wp-includes/js/jquery/jquery.js');
        $resp = wp_remote_head($url);
        $code = is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp);
        if (is_wp_error($resp) || in_array($code, [403, 405], true)) {
            $resp = wp_remote_get($url, ['headers' => ['Range' => 'bytes=0-0']]);
        }
        $headers = is_wp_error($resp) ? [] : wp_remote_retrieve_headers($resp);
        $cc = is_array($headers) && isset($headers['cache-control']) ? strtolower($headers['cache-control']) : '';
        return strpos($cc, 'max-age=31536000') !== false && strpos($cc, 'immutable') !== false;
    }
}

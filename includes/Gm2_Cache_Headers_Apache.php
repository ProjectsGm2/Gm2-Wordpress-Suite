<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Cache_Headers_Apache {
    const MARKER = 'SEO_PLUGIN_CACHE_HEADERS';

    public static $rules = <<<HTACCESS
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css "access plus 1 year"
  ExpiresByType application/javascript "access plus 1 year"
  ExpiresByType text/javascript "access plus 1 year"
  ExpiresByType application/font-woff2 "access plus 1 year"
  ExpiresByType image/svg+xml "access plus 1 year"
  ExpiresByType image/webp "access plus 1 year"
  ExpiresByType image/avif "access plus 1 year"
  ExpiresByType image/jpeg "access plus 1 year"
  ExpiresByType image/png "access plus 1 year"
  ExpiresByType image/gif "access plus 1 year"
</IfModule>

<IfModule mod_headers.c>
  <FilesMatch "\\.(?:css|js|woff2|svg|webp|avif|jpg|jpeg|png|gif)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
  </FilesMatch>
</IfModule>
HTACCESS;

    public static function is_supported_server() {
        $server = $_SERVER['SERVER_SOFTWARE'] ?? '';
        $server = strtolower($server);
        return str_contains($server, 'apache') || str_contains($server, 'litespeed');
    }

    public static function cdn_sets_cache_headers() {
        $url = home_url('/wp-includes/js/jquery/jquery.js');
        $resp = wp_remote_head($url);
        if (is_wp_error($resp)) {
            return false;
        }
        $headers = wp_remote_retrieve_headers($resp);
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

    protected static function load_misc() {
        if (!function_exists('insert_with_markers')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }
    }

    public static function write_rules() {
        self::load_misc();
        $file = ABSPATH . '.htaccess';
        insert_with_markers($file, self::MARKER, explode("\n", self::$rules));
    }

    public static function remove_rules() {
        self::load_misc();
        $file = ABSPATH . '.htaccess';
        insert_with_markers($file, self::MARKER, []);
    }

    public static function rules_exist() {
        self::load_misc();
        $file = ABSPATH . '.htaccess';
        $lines = extract_from_markers($file, self::MARKER);
        return !empty($lines);
    }

    public static function maybe_apply() {
        if (!self::is_supported_server()) {
            return ['status' => 'unsupported'];
        }
        if (self::cdn_sets_cache_headers()) {
            return ['status' => 'already_handled'];
        }
        $file = ABSPATH . '.htaccess';
        $writable = file_exists($file) ? wp_is_writable($file) : wp_is_writable(ABSPATH);
        if (!$writable) {
            return ['status' => 'not_writable', 'rules' => self::$rules];
        }
        self::write_rules();
        return ['status' => 'written'];
    }
}

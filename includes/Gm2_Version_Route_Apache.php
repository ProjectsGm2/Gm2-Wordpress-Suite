<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Version_Route_Apache {
    const MARKER = 'SEO_PLUGIN_VERSION_ROUTE';

    public static $rules = <<<HTACCESS
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteRule ^(.+)\.v([0-9]+)\.(css|js)$ $1.$3 [L]
</IfModule>
HTACCESS;

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

    public static function maybe_apply() {
        if (!Gm2_Cache_Headers_Apache::is_supported_server()) {
            return;
        }
        $file = ABSPATH . '.htaccess';
        $writable = file_exists($file) ? wp_is_writable($file) : wp_is_writable(ABSPATH);
        if (!$writable) {
            return;
        }
        self::write_rules();
    }

    public static function remove_rules() {
        self::load_misc();
        $file = ABSPATH . '.htaccess';
        insert_with_markers($file, self::MARKER, []);
    }
}

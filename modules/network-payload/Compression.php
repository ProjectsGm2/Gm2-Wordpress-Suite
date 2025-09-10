<?php
namespace Gm2\NetworkPayload;

if (!defined('ABSPATH')) {
    exit;
}

class Compression {
    private const HTACCESS_MARKER = 'GM2_COMPRESSION';

    private static string $rules = <<<HTACCESS
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>
<IfModule mod_brotli.c>
  BrotliCompressionQuality 5
  AddOutputFilterByType BROTLI_COMPRESS text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>
HTACCESS;
    /**
     * Render compression details panel.
     */
    public static function render_panel(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['gm2_enable_compression']) && check_admin_referer('gm2_enable_compression')) {
                $res = self::enable_apache_compression();
                if (!empty($res['message'])) {
                    echo '<div class="notice notice-' . esc_attr($res['success'] ? 'success' : 'error') . '"><p>' . esc_html($res['message']) . '</p></div>';
                }
            }
            if (isset($_POST['gm2_revert_compression']) && check_admin_referer('gm2_revert_compression')) {
                $res = self::revert_apache_compression();
                if (!empty($res['message'])) {
                    echo '<div class="notice notice-' . esc_attr($res['success'] ? 'success' : 'error') . '"><p>' . esc_html($res['message']) . '</p></div>';
                }
            }
        }

        $mods_present = function_exists('apache_get_modules') && array_intersect(['mod_deflate', 'mod_brotli'], apache_get_modules());
        $backup = get_option('gm2_compression_htaccess_backup');

        echo '<h2>' . esc_html__('Compression', 'gm2-wordpress-suite') . '</h2>';

        if ($mods_present) {
            echo '<form method="post">';
            if ($backup && file_exists($backup)) {
                wp_nonce_field('gm2_revert_compression');
                echo '<p><input type="submit" name="gm2_revert_compression" class="button" value="' . esc_attr__('Revert', 'gm2-wordpress-suite') . '" /></p>';
            } else {
                wp_nonce_field('gm2_enable_compression');
                echo '<p><input type="submit" name="gm2_enable_compression" class="button button-primary" value="' . esc_attr__('Enable compression', 'gm2-wordpress-suite') . '" /></p>';
            }
            echo '</form>';
        } else {
            echo '<h3>' . esc_html__('Nginx', 'gm2-wordpress-suite') . '</h3>';
            echo '<pre><code>gzip on;\ngzip_types text/css application/javascript text/plain application/json text/xml application/xml+rss;\n</code></pre>';
            echo '<ul><li>' . esc_html__('Reload Nginx after updating the configuration.', 'gm2-wordpress-suite') . '</li><li>' . esc_html__('Verify with curl -H "Accept-Encoding: gzip" your-site', 'gm2-wordpress-suite') . '</li></ul>';
            echo '<h3>' . esc_html__('Cloudflare', 'gm2-wordpress-suite') . '</h3>';
            echo '<pre><code>' . esc_html__('Enable "Brotli" in Cloudflare Speed settings.', 'gm2-wordpress-suite') . '</code></pre>';
            echo '<ul><li>' . esc_html__('Turn on Brotli compression.', 'gm2-wordpress-suite') . '</li><li>' . esc_html__('Enable Auto Minify for CSS/JS/HTML.', 'gm2-wordpress-suite') . '</li></ul>';
        }

        $tests = [
            ['label' => __('Front Page', 'gm2-wordpress-suite'), 'url' => home_url('/')],
            ['label' => __('Plugin CSS', 'gm2-wordpress-suite'), 'url' => GM2_PLUGIN_URL . 'modules/network-payload/assets/admin.css'],
        ];

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Resource', 'gm2-wordpress-suite') . '</th>';
        echo '<th>' . esc_html__('Encoding', 'gm2-wordpress-suite') . '</th>';
        echo '<th>' . esc_html__('Transfer Size', 'gm2-wordpress-suite') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($tests as $test) {
            $info = self::probe($test['url']);
            echo '<tr><th scope="row">' . esc_html($test['label']) . '</th>';
            if ($info) {
                echo '<td>' . esc_html($info['encoding']) . '</td>';
                echo '<td>' . esc_html($info['size']) . '</td>';
            } else {
                echo '<td colspan="2">' . esc_html__('Error', 'gm2-wordpress-suite') . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function load_misc(): void {
        if (!function_exists('insert_with_markers')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }
    }

    private static function backup_htaccess(): ?string {
        $file = ABSPATH . '.htaccess';
        $timestamp = gmdate('Ymd-His');
        $backup = ABSPATH . '.htaccess.' . $timestamp;
        if (file_exists($file)) {
            if (!@copy($file, $backup)) {
                return null;
            }
        } else {
            if (false === @file_put_contents($backup, '')) {
                return null;
            }
        }
        return $backup;
    }

    public static function enable_apache_compression(): array {
        if (!function_exists('apache_get_modules')) {
            return ['success' => false, 'message' => __('Apache not detected.', 'gm2-wordpress-suite')];
        }
        $mods = apache_get_modules();
        if (!array_intersect(['mod_deflate', 'mod_brotli'], $mods)) {
            return ['success' => false, 'message' => __('Required Apache modules missing.', 'gm2-wordpress-suite')];
        }
        $file = ABSPATH . '.htaccess';
        $writable = file_exists($file) ? wp_is_writable($file) : wp_is_writable(ABSPATH);
        if (!$writable) {
            return ['success' => false, 'message' => __('.htaccess is not writable.', 'gm2-wordpress-suite')];
        }
        $backup = self::backup_htaccess();
        if (!$backup) {
            return ['success' => false, 'message' => __('Could not create backup.', 'gm2-wordpress-suite')];
        }
        update_option('gm2_compression_htaccess_backup', $backup, false);
        self::load_misc();
        insert_with_markers($file, self::HTACCESS_MARKER, explode("\n", self::$rules));
        return ['success' => true, 'message' => __('Compression enabled.', 'gm2-wordpress-suite')];
    }

    public static function revert_apache_compression(): array {
        $backup = get_option('gm2_compression_htaccess_backup');
        if (!$backup || !file_exists($backup)) {
            return ['success' => false, 'message' => __('No backup found.', 'gm2-wordpress-suite')];
        }
        $file = ABSPATH . '.htaccess';
        if (!@copy($backup, $file)) {
            return ['success' => false, 'message' => __('Could not restore backup.', 'gm2-wordpress-suite')];
        }
        unlink($backup);
        delete_option('gm2_compression_htaccess_backup');
        return ['success' => true, 'message' => __('Compression rules reverted.', 'gm2-wordpress-suite')];
    }

    /**
     * Retrieve encoding and size for a URL.
     */
    private static function probe(string $url): ?array {
        $response = wp_remote_head($url, ['timeout' => 5]);
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            $response = wp_remote_get($url, ['timeout' => 5]);
        }
        if (is_wp_error($response)) {
            return null;
        }

        $headers  = wp_remote_retrieve_headers($response);
        $encoding = isset($headers['content-encoding']) ? $headers['content-encoding'] : 'none';

        if (isset($headers['content-length'])) {
            $bytes = (int) $headers['content-length'];
        } else {
            $body  = wp_remote_retrieve_body($response);
            $bytes = strlen($body);
        }

        return [
            'encoding' => $encoding,
            'size'     => size_format($bytes, 2),
        ];
    }
}

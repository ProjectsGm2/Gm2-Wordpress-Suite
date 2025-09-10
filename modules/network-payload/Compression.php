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

    /** Register REST route used for compression testing. */
    public static function register_test_route(): void {
        register_rest_route('gm2-compression-test', '/', [
            'methods'             => \WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback'            => [__CLASS__, 'rest_test'],
        ]);
    }

    /** Return a fixed byte string to check response encoding. */
    public static function rest_test(\WP_REST_Request $request): \WP_REST_Response {
        $data = str_repeat('A', 1024);
        $resp = new \WP_REST_Response($data);
        $resp->header('Content-Type', 'application/octet-stream');
        return $resp;
    }
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

        $endpoint = rest_url('gm2-compression-test');
        $probe    = self::probe($endpoint);
        if (!$probe || 'none' === $probe['encoding']) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Compression not detected. Enable server compression or the fallback option.', 'gm2-wordpress-suite') . '</p></div>';
        }

        $tests = [
            ['label' => __('Compression Test', 'gm2-wordpress-suite'), 'url' => $endpoint, 'info' => $probe],
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
            $info = $test['info'] ?? self::probe($test['url']);
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

    private static function htaccess_path(): string {
        $path = ABSPATH . '.htaccess';
        return apply_filters('gm2_compression_htaccess_path', $path);
    }

    private static function backup_htaccess(): ?string {
        $file = self::htaccess_path();
        $timestamp = gmdate('Ymd-His');
        $backup = $file . '.' . $timestamp;
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
        $file = self::htaccess_path();
        $dir = trailingslashit(dirname($file));
        $writable = file_exists($file) ? wp_is_writable($file) : wp_is_writable($dir);
        if (!$writable) {
            return ['success' => false, 'message' => __('.htaccess is not writable.', 'gm2-wordpress-suite')];
        }
        $backup = get_option('gm2_compression_htaccess_backup');
        if (!$backup || !file_exists($backup)) {
            $backup = self::backup_htaccess();
            if (!$backup) {
                return ['success' => false, 'message' => __('Could not create backup.', 'gm2-wordpress-suite')];
            }
            update_option('gm2_compression_htaccess_backup', $backup, false);
        }
        self::load_misc();
        insert_with_markers($file, self::HTACCESS_MARKER, explode("\n", self::$rules));
        return ['success' => true, 'message' => __('Compression enabled.', 'gm2-wordpress-suite')];
    }

    public static function revert_apache_compression(): array {
        $backup = get_option('gm2_compression_htaccess_backup');
        if (!$backup || !file_exists($backup)) {
            return ['success' => false, 'message' => __('No backup found.', 'gm2-wordpress-suite')];
        }
        $file = self::htaccess_path();
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

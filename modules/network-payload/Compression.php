<?php
namespace Gm2\NetworkPayload;

if (!defined('ABSPATH')) {
    exit;
}

class Compression {
    /**
     * Render compression details panel.
     */
    public static function render_panel(): void {
        $tests = [
            ['label' => __('Front Page', 'gm2-wordpress-suite'), 'url' => home_url('/')],
            ['label' => __('Plugin CSS', 'gm2-wordpress-suite'), 'url' => GM2_PLUGIN_URL . 'modules/network-payload/assets/admin.css'],
        ];

        echo '<h2>' . esc_html__('Compression', 'gm2-wordpress-suite') . '</h2>';
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

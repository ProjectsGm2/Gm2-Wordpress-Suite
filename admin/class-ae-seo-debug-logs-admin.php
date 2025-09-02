<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class AE_SEO_Debug_Logs_Admin {
    public function run(): void {
        add_action('admin_menu', [ $this, 'add_page' ]);
    }

    public function add_page(): void {
        add_management_page(
            __('AE Debug Logs', 'gm2-wordpress-suite'),
            __('AE Debug Logs', 'gm2-wordpress-suite'),
            'manage_options',
            'ae-seo-debug-logs',
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        $file = WP_CONTENT_DIR . '/ae-seo/logs/js-optimizer.log';
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'AE Debug Logs', 'gm2-wordpress-suite' ) . '</h1>';
        if (is_readable($file)) {
            $contents = file_get_contents($file);
            if ($contents !== false) {
                echo '<pre>' . esc_html($contents) . '</pre>';
            } else {
                echo '<p>' . esc_html__( 'Unable to read log file.', 'gm2-wordpress-suite' ) . '</p>';
            }
        } else {
            echo '<p>' . esc_html__( 'Log file not found.', 'gm2-wordpress-suite' ) . '</p>';
        }
        echo '</div>';
    }
}

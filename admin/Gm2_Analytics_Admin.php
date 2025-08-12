<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Analytics_Admin {
    public function run() {
        add_action('admin_menu', [ $this, 'add_menu' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_scripts' ]);
    }

    public function add_menu() {
        add_menu_page(
            esc_html__( 'Analytics', 'gm2-wordpress-suite' ),
            esc_html__( 'Analytics', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-analytics',
            [ $this, 'display_page' ],
            'dashicons-chart-area'
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'toplevel_page_gm2-analytics' ) {
            return;
        }
        wp_enqueue_script(
            'gm2-analytics',
            GM2_PLUGIN_URL . 'admin/js/gm2-analytics.js',
            [ 'jquery' ],
            file_exists( GM2_PLUGIN_DIR . 'admin/js/gm2-analytics.js' ) ? filemtime( GM2_PLUGIN_DIR . 'admin/js/gm2-analytics.js' ) : GM2_VERSION,
            true
        );
    }

    public function display_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Analytics', 'gm2-wordpress-suite' ) . '</h1></div>';
    }
}

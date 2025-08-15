<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Custom_Posts_Admin {
    public function run() {
        add_action('admin_menu', [ $this, 'add_menu' ]);
    }

    public function add_menu() {
        add_menu_page(
            esc_html__( 'Gm2 Custom Posts', 'gm2-wordpress-suite' ),
            esc_html__( 'Gm2 Custom Posts', 'gm2-wordpress-suite' ),
            'manage_options',
            'gm2-custom-posts',
            [ $this, 'display_page' ],
            'dashicons-admin-post'
        );
    }

    public function display_page() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Permission denied', 'gm2-wordpress-suite' ) );
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Gm2 Custom Posts', 'gm2-wordpress-suite' ) . '</h1></div>';
    }
}

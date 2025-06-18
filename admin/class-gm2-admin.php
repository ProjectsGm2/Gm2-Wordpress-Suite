<?php

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Admin {

    public function run() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Gm2 Suite',
            'Gm2 Suite',
            'manage_options',
            'gm2-suite',
            [$this, 'display_admin_page'],
            'dashicons-admin-generic'
        );
    }

    public function display_admin_page() {
        echo '<div class="wrap"><h1>Gm2 WordPress Suite</h1><p>Welcome to the admin interface!</p></div>';
    }
}

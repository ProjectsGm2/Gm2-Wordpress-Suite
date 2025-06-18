<?php

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Loader {

    public function __construct() {
        // Load dependencies
        $this->load_dependencies();
    }

    private function load_dependencies() {
        require_once GM2_PLUGIN_DIR . 'admin/class-gm2-admin.php';
        require_once GM2_PLUGIN_DIR . 'public/class-gm2-public.php';
    }

    public function run() {
        $admin = new Gm2_Admin();
        $admin->run();

        $public = new Gm2_Public();
        $public->run();
    }
}

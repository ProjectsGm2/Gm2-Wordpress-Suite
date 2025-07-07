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
        require_once GM2_PLUGIN_DIR . 'admin/class-gm2-seo-admin.php';
        require_once GM2_PLUGIN_DIR . 'public/class-gm2-public.php';
        require_once GM2_PLUGIN_DIR . 'public/class-gm2-seo-public.php';
        require_once GM2_PLUGIN_DIR . 'includes/class-gm2-tariff-manager.php';
        require_once GM2_PLUGIN_DIR . 'includes/class-gm2-sitemap.php';
        require_once GM2_PLUGIN_DIR . 'includes/class-gm2-keyword-planner.php';
        require_once GM2_PLUGIN_DIR . 'includes/class-gm2-google-oauth.php';
    }

    public function run() {
        $admin = new Gm2_Admin();
        $admin->run();

        $seo_admin = new Gm2_SEO_Admin();
        $seo_admin->run();

        $public = new Gm2_Public();
        $public->run();

        $seo_public = new Gm2_SEO_Public();
        $seo_public->run();
    }
}

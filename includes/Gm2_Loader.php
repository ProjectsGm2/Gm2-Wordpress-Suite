<?php

namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Loader {

    public function __construct() {
        // Load dependencies
        $this->load_dependencies();
    }

    private function load_dependencies() {
        // Composer autoload handles loading classes
    }

    public function run() {
        $admin = new Gm2_Admin();
        $admin->run();

        $seo_admin = new Gm2_SEO_Admin();
        $seo_admin->run();

        $public = new Gm2_Public();
        $public->run();

        $qd_public = new Gm2_Quantity_Discounts_Public();
        $qd_public->run();

        $seo_public = new Gm2_SEO_Public();
        $seo_public->run();

        if (class_exists('Elementor\\Plugin')) {
            new Gm2_Elementor_SEO();
        }
    }
}

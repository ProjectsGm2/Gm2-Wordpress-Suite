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

        $enable_seo = get_option('gm2_enable_seo', '1') === '1';
        $enable_qd  = get_option('gm2_enable_quantity_discounts', '1') === '1';

        if ($enable_seo) {
            $seo_admin = new Gm2_SEO_Admin();
            $seo_admin->run();
        }

        $public = new Gm2_Public();
        $public->run();

        if ($enable_qd) {
            $qd_public = new Gm2_Quantity_Discounts_Public();
            $qd_public->run();
        }

        if ($enable_seo) {
            $seo_public = new Gm2_SEO_Public();
            $seo_public->run();
        }

        $load_elementor = function () use ($enable_seo, $enable_qd) {
            if ($enable_seo) {
                new Gm2_Elementor_SEO();
            }
            if ($enable_qd) {
                new Gm2_Elementor_Quantity_Discounts();
            }
        };

        if (did_action('elementor/loaded')) {
            $load_elementor();
        } else {
            add_action('elementor/loaded', $load_elementor);
        }
    }
}

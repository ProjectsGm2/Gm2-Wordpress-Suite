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

        if (is_admin()) {
            $wizard = new Gm2_SEO_Wizard();
            $wizard->run();

            $links = new Gm2_Link_Counts();
            $links->run();
        }

        $enable_seo = get_option('gm2_enable_seo', '1') === '1';
        $enable_qd  = get_option('gm2_enable_quantity_discounts', '1') === '1';
        $enable_ac  = get_option('gm2_enable_abandoned_carts', '1') === '1';

        if (!$enable_ac && is_admin()) {
            add_action('admin_notices', function () {
                $url = admin_url('admin.php?page=gm2');
                $msg = sprintf(
                    __('The Abandoned Carts module is currently disabled. <a href="%s">Enable it in the Gm2 settings.</a>', 'gm2-wordpress-suite'),
                    esc_url($url)
                );
                echo '<div class="notice notice-warning"><p>' . wp_kses_post($msg) . '</p></div>';
            });
        }

        if ($enable_seo) {
            $seo_admin = new Gm2_SEO_Admin();
            $seo_admin->run();
        }

        $public = new Gm2_Public();
        $public->run();

        if ($enable_ac) {
            $ac = new Gm2_Abandoned_Carts();
            $ac->run();

            // Temporarily disable Recovery Email Queue.
            // $ac_msg = new Gm2_Abandoned_Carts_Messaging();
            // $ac_msg->run();

            if (is_admin()) {
                $ac_admin = new Gm2_Abandoned_Carts_Admin();
                $ac_admin->run();
                $rc_admin = new Gm2_Recovered_Carts_Admin();
                $rc_admin->run();
            } else {
                $ac_public = new Gm2_Abandoned_Carts_Public();
                $ac_public->run();
            }
        }

        if ($enable_qd) {
            $qd_public = new Gm2_Quantity_Discounts_Public();
            $qd_public->run();
        }

        if ($enable_seo) {
            $seo_public = new Gm2_SEO_Public();
            $seo_public->run();
        }

        $gmc_rt = new Gm2_GMC_Realtime();
        $gmc_rt->run();

        if (!is_admin()) {
            $gmc_rt_public = new Gm2_GMC_Realtime_Public();
            $gmc_rt_public->run();
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

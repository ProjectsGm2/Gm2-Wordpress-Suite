<?php
/**
 * Plugin Name:       Gm2 WordPress Suite
 * Description:       A powerful suite of tools and features for WordPress, by Gm2.
 * Version:           1.6.3
 * Author:            Your Name or Team Gm2
 * Author URI:        https://yourwebsite.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gm2-wordpress-suite
 * Domain Path:       /languages
 */

defined('ABSPATH') or die('No script kiddies please!');

// Define constants
define('GM2_VERSION', '1.6.3');
define('GM2_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GM2_PLUGIN_URL', plugin_dir_url(__FILE__));

use Gm2\Gm2_Loader;
use Gm2\Gm2_SEO_Public;
use Gm2\Gm2_Sitemap;
$gm2_autoload = GM2_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($gm2_autoload)) {
    require_once $gm2_autoload;
}
require_once GM2_PLUGIN_DIR . 'includes/autoload.php';

// Include required files
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Loader.php';
require_once GM2_PLUGIN_DIR . 'public/Gm2_SEO_Public.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Sitemap.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_PageSpeed.php';

function gm2_add_weekly_schedule($schedules) {
    if (!isset($schedules['weekly'])) {
        $schedules['weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __('Once Weekly', 'gm2-wordpress-suite')
        ];
    }
    return $schedules;
}
add_filter('cron_schedules', 'gm2_add_weekly_schedule');

function gm2_activate_plugin() {
    $public = new Gm2_SEO_Public();
    $public->add_sitemap_rewrite();
    flush_rewrite_rules();
    gm2_generate_sitemap();

    $s = new Gm2_Sitemap();
    $s->ping_search_engines();

    if (!wp_next_scheduled('gm2_sitemap_ping')) {
        wp_schedule_event(time(), 'daily', 'gm2_sitemap_ping');
    }

    if (!wp_next_scheduled('gm2_pagespeed_check')) {
        wp_schedule_event(time(), 'weekly', 'gm2_pagespeed_check');
    }

    gm2_initialize_content_rules();
}
register_activation_hook(__FILE__, 'gm2_activate_plugin');

function gm2_deactivate_plugin() {
    flush_rewrite_rules();
    $timestamp = wp_next_scheduled('gm2_sitemap_ping');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'gm2_sitemap_ping');
    }
    $ts = wp_next_scheduled('gm2_pagespeed_check');
    if ($ts) {
        wp_unschedule_event($ts, 'gm2_pagespeed_check');
    }
}
register_deactivation_hook(__FILE__, 'gm2_deactivate_plugin');

function gm2_initialize_content_rules() {
    $existing = get_option('gm2_content_rules', null);
    if ($existing !== null && $existing !== false && !empty($existing)) {
        return;
    }

    $rules = [];

    $posts = ['post', 'page'];
    if (post_type_exists('product')) {
        $posts[] = 'product';
    }
    $post_defaults = [
        'Title length between 30 and 60 characters',
        'Description length between 50 and 160 characters',
        'At least one focus keyword',
        'Content has at least 300 words',
        'Focus keyword appears in first paragraph',
        'Only one H1 tag present',
        'At least one internal link',
        'At least one external link',
        'Focus keyword included in meta description',
        'SEO title is unique',
        'Meta description is unique',
    ];
    foreach ($posts as $pt) {
        $rules['post_' . $pt] = implode("\n", $post_defaults);
    }

    $taxonomies = ['category'];
    if (taxonomy_exists('product_cat')) {
        $taxonomies[] = 'product_cat';
    }
    if (taxonomy_exists('brand')) {
        $taxonomies[] = 'brand';
    }
    if (taxonomy_exists('product_brand')) {
        $taxonomies[] = 'product_brand';
    }
    $tax_defaults = [
        'Title length between 30 and 60 characters',
        'Description length between 50 and 160 characters',
        'Description has at least 150 words',
        'SEO title is unique',
        'Meta description is unique',
    ];
    foreach ($taxonomies as $tax) {
        $rules['tax_' . $tax] = implode("\n", $tax_defaults);
    }

    add_option('gm2_content_rules', $rules);
    add_option('gm2_min_internal_links', 1);
    add_option('gm2_min_external_links', 1);
}

// Initialize plugin
function gm2_init_plugin() {
    $plugin = new Gm2_Loader();
    $plugin->run();
}
add_action('plugins_loaded', 'gm2_init_plugin');

add_action('gm2_sitemap_ping', 'gm2_generate_sitemap');

function gm2_run_pagespeed_check() {
    $key = get_option('gm2_pagespeed_api_key', '');
    if (!$key) {
        return;
    }
    $helper = new \Gm2\Gm2_PageSpeed($key);
    $scores = $helper->get_scores(home_url('/'));
    if (!is_wp_error($scores)) {
        $scores['time'] = time();
        update_option('gm2_pagespeed_scores', $scores);
    }
}
add_action('gm2_pagespeed_check', 'gm2_run_pagespeed_check');


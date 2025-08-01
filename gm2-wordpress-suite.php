<?php
/**
 * Plugin Name:       Gm2 WordPress Suite
 * Description:       A powerful suite of tools and features for WordPress, by Gm2.
 * Version:           1.6.17
 * Author:            Your Name or Team Gm2
 * Author URI:        https://yourwebsite.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gm2-wordpress-suite
 * Domain Path:       /languages
 * Requires PHP:      7.3
 */

defined('ABSPATH') or die('No script kiddies please!');

// Define constants
define('GM2_VERSION', '1.6.17');
define('GM2_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GM2_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GM2_CHATGPT_LOG_FILE', GM2_PLUGIN_DIR . 'chatgpt.log');
define('GM2_CONTENT_RULES_VERSION', 2);
define('GM2_GUIDELINE_RULES_VERSION', 2);
if (!defined('GM2_GCLOUD_PROJECT_ID')) {
    $project = getenv('GM2_GCLOUD_PROJECT_ID');
    if ($project === false || $project === '') {
        $project = get_option('gm2_gcloud_project_id', '');
    }
    define('GM2_GCLOUD_PROJECT_ID', $project);
}
if (!defined('GM2_SERVICE_ACCOUNT_JSON')) {
    $json = getenv('GM2_SERVICE_ACCOUNT_JSON');
    if ($json === false || $json === '') {
        $json = get_option('gm2_service_account_json', '');
    }
    define('GM2_SERVICE_ACCOUNT_JSON', $json);
}

use Gm2\Gm2_Loader;
use Gm2\Gm2_SEO_Public;
use Gm2\Gm2_Sitemap;
use Gm2\Gm2_Abandoned_Carts;
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
require_once GM2_PLUGIN_DIR . 'includes/Gm2_SEO_Utils.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_CSV_Helper.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Abandoned_Carts.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Abandoned_Carts_Messaging.php';
require_once GM2_PLUGIN_DIR . 'admin/Gm2_Abandoned_Carts_Admin.php';
require_once GM2_PLUGIN_DIR . 'admin/class-gm2-ac-table.php';
require_once GM2_PLUGIN_DIR . 'public/Gm2_Abandoned_Carts_Public.php';

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

function gm2_add_ac_schedule($schedules) {
    $minutes = absint(apply_filters('gm2_ac_mark_abandoned_interval', (int) get_option('gm2_ac_mark_abandoned_interval', 5)));
    if ($minutes < 1) {
        $minutes = 1;
    }
    $schedules['gm2_ac_' . $minutes . '_mins'] = [
        'interval' => $minutes * MINUTE_IN_SECONDS,
        'display'  => sprintf(_n('Every %d minute', 'Every %d minutes', $minutes, 'gm2-wordpress-suite'), $minutes)
    ];
    return $schedules;
}
add_filter('cron_schedules', 'gm2_add_ac_schedule');

function gm2_activate_plugin() {
    $public = new Gm2_SEO_Public();
    $public->add_sitemap_rewrite();
    flush_rewrite_rules();
    $result = gm2_generate_sitemap();
    if (is_wp_error($result) && defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Sitemap generation failed: ' . $result->get_error_message());
    }

    $s = new Gm2_Sitemap();
    $s->ping_search_engines();

    if (!wp_next_scheduled('gm2_sitemap_ping')) {
        wp_schedule_event(time(), 'daily', 'gm2_sitemap_ping');
    }

    if (!wp_next_scheduled('gm2_pagespeed_check')) {
        wp_schedule_event(time(), 'weekly', 'gm2_pagespeed_check');
    }

    gm2_initialize_content_rules();
    gm2_initialize_guideline_rules();
    gm2_maybe_migrate_content_rules();
    gm2_maybe_migrate_guideline_rules();

    $ac = new Gm2_Abandoned_Carts();
    $ac->install();

    add_option('gm2_enable_tariff', '1');
    add_option('gm2_enable_seo', '1');
    add_option('gm2_enable_quantity_discounts', '1');
    add_option('gm2_enable_google_oauth', '1');
    add_option('gm2_enable_chatgpt', '1');
    add_option('gm2_enable_chatgpt_logging', '0');
    add_option('gm2_sitemap_path', ABSPATH . 'sitemap.xml');
    add_option('gm2_sitemap_max_urls', 1000);
    add_option('gm2_enable_abandoned_carts', '0');
    add_option('gm2_ac_mark_abandoned_interval', 5);
    add_option('gm2_setup_complete', '0');
    add_option('gm2_do_activation_redirect', '1');
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

    \Gm2\Gm2_Abandoned_Carts::clear_scheduled_event();
}
register_deactivation_hook(__FILE__, 'gm2_deactivate_plugin');

function gm2_maybe_run_setup_wizard() {
    if (get_option('gm2_do_activation_redirect') === '1') {
        delete_option('gm2_do_activation_redirect');
        if (!isset($_GET['activate-multi'])) {
            wp_safe_redirect(admin_url('index.php?page=gm2-setup-wizard'));
            exit;
        }
    }
}
add_action('admin_init', 'gm2_maybe_run_setup_wizard');

function gm2_initialize_content_rules() {
    $existing = get_option('gm2_content_rules', null);
    if ($existing !== null && $existing !== false && !empty($existing)) {
        return;
    }

    $rules = [];

    $args  = [
        'public'             => true,
        'show_ui'            => true,
        'exclude_from_search' => false,
    ];
    $posts = get_post_types($args, 'names');
    unset($posts['attachment']);
    $posts = apply_filters('gm2_supported_post_types', array_values($posts));
    $post_defaults = [
        'seo_title' => [
            'Title length between 30 and 60 characters',
            'SEO title is unique',
        ],
        'seo_description' => [
            'Description length between 50 and 160 characters',
            'Meta description is unique',
            'Focus keyword included in meta description',
        ],
        'focus_keywords' => [
            'At least one focus keyword',
        ],
        'long_tail_keywords' => [
            'Consider including long-tail keywords',
        ],
        'canonical_url' => [
            'Use a canonical URL',
        ],
        'content' => [
            'Content has at least 300 words',
            'Focus keyword appears in first paragraph',
            'Only one H1 tag present',
            'At least one internal link',
            'At least one external link',
            'Image alt text contains focus keyword',
        ],
        'general' => [],
    ];
    foreach ($posts as $pt) {
        $rules['post_' . $pt] = [];
        foreach ($post_defaults as $key => $vals) {
            $rules['post_' . $pt][$key] = implode("\n", $vals);
        }
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
        'seo_title' => [
            'Title length between 30 and 60 characters',
            'SEO title is unique',
        ],
        'seo_description' => [
            'Description length between 50 and 160 characters',
            'Meta description is unique',
        ],
        'focus_keywords' => [],
        'long_tail_keywords' => [],
        'canonical_url' => [],
        'content' => [
            'Description has at least 150 words',
        ],
        'general' => [],
    ];
    foreach ($taxonomies as $tax) {
        $rules['tax_' . $tax] = [];
        foreach ($tax_defaults as $key => $vals) {
            $rules['tax_' . $tax][$key] = implode("\n", $vals);
        }
    }

    add_option('gm2_content_rules', $rules);
    add_option('gm2_min_internal_links', 1);
    add_option('gm2_min_external_links', 1);
    add_option('gm2_tax_min_length', 150);
}

function gm2_initialize_guideline_rules() {
    $existing = get_option('gm2_guideline_rules', null);
    if ($existing !== null && $existing !== false && !empty($existing)) {
        return;
    }

    $rules = [];

    $args  = [
        'public'             => true,
        'show_ui'            => true,
        'exclude_from_search' => false,
    ];
    $posts = get_post_types($args, 'names');
    unset($posts['attachment']);
    $posts = apply_filters('gm2_supported_post_types', array_values($posts));
    $post_defaults = [
        'seo_title' => [
            'Title length between 30 and 60 characters',
            'SEO title is unique',
        ],
        'seo_description' => [
            'Description length between 50 and 160 characters',
            'Meta description is unique',
            'Focus keyword included in meta description',
        ],
        'focus_keywords' => [
            'At least one focus keyword',
        ],
        'long_tail_keywords' => [
            'Consider including long-tail keywords',
        ],
        'canonical_url' => [
            'Use a canonical URL',
        ],
        'content' => [
            'Content has at least 300 words',
            'Focus keyword appears in first paragraph',
            'Only one H1 tag present',
            'At least one internal link',
            'At least one external link',
            'Image alt text contains focus keyword',
        ],
        'general' => [],
    ];
    foreach ($posts as $pt) {
        $rules['post_' . $pt] = [];
        foreach ($post_defaults as $key => $vals) {
            $rules['post_' . $pt][$key] = implode("\n", $vals);
        }
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
        'seo_title' => [
            'Title length between 30 and 60 characters',
            'SEO title is unique',
        ],
        'seo_description' => [
            'Description length between 50 and 160 characters',
            'Meta description is unique',
        ],
        'focus_keywords' => [],
        'long_tail_keywords' => [],
        'canonical_url' => [],
        'content' => [
            'Description has at least 150 words',
        ],
        'general' => [],
    ];
    foreach ($taxonomies as $tax) {
        $rules['tax_' . $tax] = [];
        foreach ($tax_defaults as $key => $vals) {
            $rules['tax_' . $tax][$key] = implode("\n", $vals);
        }
    }

    add_option('gm2_guideline_rules', $rules);
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

function gm2_handle_ac_option_change($old, $new) {
    if ($new === '1') {
        \Gm2\Gm2_Abandoned_Carts::schedule_event();
    } else {
        \Gm2\Gm2_Abandoned_Carts::clear_scheduled_event();
    }
}
add_action('update_option_gm2_enable_abandoned_carts', 'gm2_handle_ac_option_change', 10, 2);

function gm2_handle_ac_interval_change($old, $new) {
    if (get_option('gm2_enable_abandoned_carts', '0') === '1') {
        \Gm2\Gm2_Abandoned_Carts::clear_scheduled_event();
        \Gm2\Gm2_Abandoned_Carts::schedule_event();
    }
}
add_action('update_option_gm2_ac_mark_abandoned_interval', 'gm2_handle_ac_interval_change', 10, 2);

function gm2_maybe_migrate_content_rules() {
    $current = (int) get_option('gm2_content_rules_version', 1);
    if ($current >= GM2_CONTENT_RULES_VERSION) {
        return;
    }

    $rules   = get_option('gm2_content_rules', []);
    $changed = false;

    if (is_array($rules)) {
        foreach ($rules as $base => $cats) {
            if (is_string($cats)) {
                // Previously stored as a single string without categories
                $rules[$base] = [ 'general' => $cats ];
                $changed      = true;
            } elseif (is_array($cats)) {
                $new_cats = [];
                foreach ($cats as $cat => $lines) {
                    if (is_array($lines)) {
                        $new_cats[$cat] = implode("\n", array_values($lines));
                    } else {
                        $new_cats[$cat] = (string) $lines;
                    }
                }
                $rules[$base] = $new_cats;
                $changed      = true;
            }
        }
    }

    if ($changed) {
        update_option('gm2_content_rules', $rules);
        update_option('gm2_content_rules_migrated', 1);
    }
    update_option('gm2_content_rules_version', GM2_CONTENT_RULES_VERSION);
}
add_action('plugins_loaded', 'gm2_maybe_migrate_content_rules');

function gm2_content_rules_migration_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (get_option('gm2_content_rules_migrated')) {
        echo '<div class="notice notice-warning is-dismissible"><p>' .
             esc_html__( 'Content rules have been migrated. Please review them on the SEO settings page.', 'gm2-wordpress-suite' ) .
             '</p></div>';
        delete_option('gm2_content_rules_migrated');
    }
}
add_action('admin_notices', 'gm2_content_rules_migration_notice');

function gm2_maybe_migrate_guideline_rules() {
    $current = (int) get_option('gm2_guideline_rules_version', 1);
    if ($current >= GM2_GUIDELINE_RULES_VERSION) {
        return;
    }

    $rules   = get_option('gm2_guideline_rules', []);
    $changed = false;

    if (is_array($rules)) {
        foreach ($rules as $base => $cats) {
            if (is_string($cats)) {
                $rules[$base] = [ 'general' => $cats ];
                $changed      = true;
            } elseif (is_array($cats)) {
                $new_cats = [];
                foreach ($cats as $cat => $lines) {
                    if (is_array($lines)) {
                        $new_cats[$cat] = implode("\n", array_values($lines));
                    } else {
                        $new_cats[$cat] = (string) $lines;
                    }
                }
                $rules[$base] = $new_cats;
                $changed      = true;
            }
        }
    }

    if ($changed) {
        update_option('gm2_guideline_rules', $rules);
        update_option('gm2_guideline_rules_migrated', 1);
    }
    update_option('gm2_guideline_rules_version', GM2_GUIDELINE_RULES_VERSION);
}
add_action('plugins_loaded', 'gm2_maybe_migrate_guideline_rules');

function gm2_guideline_rules_migration_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (get_option('gm2_guideline_rules_migrated')) {
        echo '<div class="notice notice-warning is-dismissible"><p>' .
             esc_html__( 'Guideline rules have been migrated. Please review them on the SEO settings page.', 'gm2-wordpress-suite' ) .
             '</p></div>';
        delete_option('gm2_guideline_rules_migrated');
    }
}
add_action('admin_notices', 'gm2_guideline_rules_migration_notice');

if (defined('WP_CLI') && WP_CLI) {
    require_once GM2_PLUGIN_DIR . 'includes/cli/class-gm2-cli.php';
}


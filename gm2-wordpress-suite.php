<?php
/**
 * Plugin Name:       Gm2 WordPress Suite
 * Description:       A powerful suite of tools and features for WordPress, by Gm2.
 * Version:           1.6.21
 * Author:            Your Name or Team Gm2
 * Author URI:        https://yourwebsite.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gm2-wordpress-suite
 * Domain Path:       /languages
 * Requires PHP:      8.0
 */

defined('ABSPATH') or die('No script kiddies please!');

// Define constants
define('GM2_VERSION', '1.6.21');
define('GM2_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GM2_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GM2_CHATGPT_LOG_FILE', GM2_PLUGIN_DIR . 'chatgpt.log');
define('GM2_CONTENT_RULES_VERSION', 2);
define('GM2_GUIDELINE_RULES_VERSION', 2);
if (!defined('GM2_ENV')) {
    $env = getenv('GM2_ENV');
    if ($env === false || $env === '') {
        $env = get_option('gm2_env', 'production');
    }
    define('GM2_ENV', $env);
}

/**
 * Retrieve the current environment for the plugin.
 *
 * @return string
 */
function gm2_get_environment() {
    return apply_filters('gm2_env', GM2_ENV);
}
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

require_once GM2_PLUGIN_DIR . 'includes/Gm2_Remote_Mirror.php';
\Gm2\Gm2_Remote_Mirror::init();

// Include required files
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Loader.php';
require_once GM2_PLUGIN_DIR . 'public/Gm2_SEO_Public.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Sitemap.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_PageSpeed.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_SEO_Utils.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_CSV_Helper.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Abandoned_Carts.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Analytics.php';
require_once GM2_PLUGIN_DIR . 'includes/gm2-custom-tables.php';
require_once GM2_PLUGIN_DIR . 'includes/gm2-custom-posts-functions.php';
require_once GM2_PLUGIN_DIR . 'includes/gm2-query-builder.php';
require_once GM2_PLUGIN_DIR . 'includes/gm2-theme-tools.php';
require_once GM2_PLUGIN_DIR . 'includes/gm2-open-in-code.php';
require_once GM2_PLUGIN_DIR . 'includes/gm2-field-renderers.php';
require_once GM2_PLUGIN_DIR . 'includes/gm2-schema-tooltips.php';
require_once GM2_PLUGIN_DIR . 'includes/gm2-editorial-comments.php';
require_once GM2_PLUGIN_DIR . 'includes/gm2-model-export.php';
require_once GM2_PLUGIN_DIR . 'includes/gm2-config-versions.php';
// Temporarily disable Recovery Email Queue.
// require_once GM2_PLUGIN_DIR . 'includes/Gm2_Abandoned_Carts_Messaging.php';
require_once GM2_PLUGIN_DIR . 'admin/Gm2_Abandoned_Carts_Admin.php';
require_once GM2_PLUGIN_DIR . 'admin/Gm2_Recovered_Carts_Admin.php';
require_once GM2_PLUGIN_DIR . 'admin/class-gm2-ac-table.php';
require_once GM2_PLUGIN_DIR . 'admin/class-gm2-bulk-ai-list-table.php';
require_once GM2_PLUGIN_DIR . 'admin/class-gm2-bulk-ai-tax-list-table.php';
require_once GM2_PLUGIN_DIR . 'admin/Gm2_Model_Export_Admin.php';
require_once GM2_PLUGIN_DIR . 'admin/gm2-config-history.php';
require_once GM2_PLUGIN_DIR . 'public/Gm2_Abandoned_Carts_Public.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_REST_Visibility.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_REST_Rate_Limiter.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_REST_Media.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_REST_Fields.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Webhooks.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Capability_Manager.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Workflow_Manager.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Audit_Log.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Ajax_Upload.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Cache_Audit.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Script_Attributes.php';
require_once GM2_PLUGIN_DIR . 'includes/functions-assets.php';
require_once GM2_PLUGIN_DIR . 'includes/class-ae-seo-js-detector.php';
require_once GM2_PLUGIN_DIR . 'includes/class-ae-seo-js-manager.php';
require_once GM2_PLUGIN_DIR . 'includes/class-ae-seo-js-controller.php';
require_once GM2_PLUGIN_DIR . 'includes/class-ae-seo-js-lazy.php';
require_once GM2_PLUGIN_DIR . 'includes/class-ae-seo-diff-serving.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Search_Console.php';
require_once GM2_PLUGIN_DIR . 'includes/render-optimizer/class-ae-seo-render-optimizer.php';
require_once GM2_PLUGIN_DIR . 'includes/Versioning_MTime.php';
require_once GM2_PLUGIN_DIR . 'admin/class-ae-seo-debug-logs-admin.php';

\Gm2\Gm2_REST_Visibility::init();
\Gm2\Gm2_REST_Rate_Limiter::init();
\Gm2\Gm2_REST_Media::init();
\Gm2\Gm2_REST_Fields::init();
\Gm2\Gm2_Webhooks::init();
\Gm2\Gm2_Ajax_Upload::init();
\Gm2\Gm2_Cache_Audit::init();
\Gm2\Gm2_Script_Attributes::init();
\Gm2\Gm2_Search_Console::init();
\Gm2\AE_SEO_JS_Detector::init();
\Gm2\AE_SEO_JS_Manager::init();
\Gm2\AE_SEO_JS_Controller::init();
\Gm2\AE_SEO_JS_Lazy::init();
\Gm2\Versioning_MTime::init();
(new \Gm2\AE_SEO_Debug_Logs_Admin())->run();
if (get_option('gm2_pretty_versioned_urls', '0') === '1') {
    \Gm2\Gm2_Version_Route_Apache::maybe_apply();
}

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

    if (!wp_next_scheduled('gm2_analytics_purge')) {
        wp_schedule_event(time(), 'daily', 'gm2_analytics_purge');
    }

    if (!wp_next_scheduled('gm2_remote_mirror_refresh')) {
        wp_schedule_event(time(), 'daily', 'gm2_remote_mirror_refresh');
    }

    gm2_initialize_content_rules();
    gm2_initialize_guideline_rules();
    gm2_maybe_migrate_content_rules();
    gm2_maybe_migrate_guideline_rules();

    gm2_custom_tables_maybe_install();

    $logger = function_exists('wc_get_logger') ? wc_get_logger() : null;
    $ac = new Gm2_Abandoned_Carts($logger);
    $ac->install();

    add_option('gm2_enable_tariff', '1');
    add_option('gm2_enable_seo', '1');
    add_option('gm2_enable_quantity_discounts', '1');
    add_option('gm2_enable_google_oauth', '1');
    add_option('gm2_enable_chatgpt', '1');
    add_option('gm2_enable_analytics', '1');
    add_option('gm2_enable_chatgpt_logging', '0');
    add_option('gm2_ac_enable_logging', '0');
    add_option('gm2_enable_custom_posts', '1');
    add_option('gm2_enable_block_templates', '0');
    add_option('gm2_enable_theme_integration', '0');
    add_option('gm2_analytics_retention_days', 30);
    add_option('gm2_sitemap_path', ABSPATH . 'sitemap.xml');
    add_option('gm2_sitemap_max_urls', 1000);
    add_option('gm2_enable_abandoned_carts', '1');
    add_option('gm2_enable_phone_login', '0');
    add_option('gm2_ac_mark_abandoned_interval', 5);
    add_option('gm2_setup_complete', '0');
    add_option('gm2_do_activation_redirect', '1');
    add_option('gm2_remote_mirror_vendors', []);
    add_option('gm2_remote_mirror_custom_urls', []);

    global $wpdb;
    $table_name = $wpdb->prefix . 'gm2_analytics_log';
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_id varchar(64) NOT NULL,
        user_id varchar(64) NOT NULL,
        url text NOT NULL,
        referrer text DEFAULT NULL,
        `timestamp` datetime NOT NULL,
        user_agent text NOT NULL,
        device varchar(20) NOT NULL,
        ip varchar(100) NOT NULL,
        event_type varchar(20) NOT NULL DEFAULT '',
        duration int NOT NULL DEFAULT 0,
        element text DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY session_id (session_id),
        KEY user_id (user_id),
        KEY `timestamp` (`timestamp`)
    ) $charset_collate;";
    dbDelta($sql);

    \Gm2\Gm2_Audit_Log::install();

    Gm2_Abandoned_Carts::schedule_event();

    gm2_maybe_add_indexes();
    \Gm2\Gm2_Cache_Headers_Apache::maybe_apply();
    \Gm2\Gm2_Cache_Headers_Nginx::maybe_apply();
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

    $ts = wp_next_scheduled('gm2_analytics_purge');
    if ($ts) {
        wp_unschedule_event($ts, 'gm2_analytics_purge');
    }

    Gm2_Abandoned_Carts::clear_scheduled_event();
}
register_deactivation_hook(__FILE__, 'gm2_deactivate_plugin');

function gm2_upgrade_analytics_log_index() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gm2_analytics_log';
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            user_id varchar(64) NOT NULL,
            url text NOT NULL,
            referrer text DEFAULT NULL,
            `timestamp` datetime NOT NULL,
            user_agent text NOT NULL,
            device varchar(20) NOT NULL,
            ip varchar(100) NOT NULL,
            event_type varchar(20) NOT NULL DEFAULT '',
            duration int NOT NULL DEFAULT 0,
            element text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY user_id (user_id),
            KEY `timestamp` (`timestamp`)
        ) $charset_collate;";
    dbDelta($sql);
}
add_action('plugins_loaded', 'gm2_upgrade_analytics_log_index');

/**
 * Ensure auxiliary database indexes exist for meta lookups and custom tables.
 *
 * Runs on activation and on plugin load to catch version upgrades.
 */
function gm2_maybe_add_indexes() {
    global $wpdb;

    if ((int) get_option('gm2_meta_indexes_version', 0) >= 2) {
        return;
    }

    $meta_tables = [
        [ 'table' => $wpdb->postmeta,   'id' => 'post_id' ],
        [ 'table' => $wpdb->usermeta,   'id' => 'user_id' ],
        [ 'table' => $wpdb->termmeta,   'id' => 'term_id' ],
        [ 'table' => $wpdb->commentmeta,'id' => 'comment_id' ],
    ];

    foreach ($meta_tables as $info) {
        $index = 'gm2_' . $info['id'] . '_meta';
        $exists = $wpdb->get_results( "SHOW INDEX FROM {$info['table']} WHERE Key_name = '$index'" );
        if (empty($exists)) {
            $wpdb->query( "ALTER TABLE {$info['table']} ADD INDEX $index ({$info['id']}, meta_key(191))" );
        }
    }

    $audit = $wpdb->prefix . 'gm2_audit_log';
    $exists = $wpdb->get_results( "SHOW INDEX FROM $audit WHERE Key_name = 'gm2_object_key'" );
    if (empty($exists)) {
        $wpdb->query( "ALTER TABLE $audit ADD INDEX gm2_object_key (object_id, meta_key(191))" );
    }

    $carts = $wpdb->prefix . 'wc_ac_carts';
    foreach ([ 'gm2_user' => 'user_id', 'gm2_abandoned_at' => 'abandoned_at', 'gm2_email' => 'email(191)' ] as $index => $cols) {
        $exists = $wpdb->get_results( "SHOW INDEX FROM $carts WHERE Key_name = '$index'" );
        if (empty($exists)) {
            $wpdb->query( "ALTER TABLE $carts ADD INDEX $index ($cols)" );
        }
    }
    $exists = $wpdb->get_results( "SHOW INDEX FROM $carts WHERE Key_name = 'ip_address'" );
    if (empty($exists)) {
        $wpdb->query( "ALTER TABLE $carts ADD INDEX ip_address (ip_address)" );
    }

    $queue = $wpdb->prefix . 'wc_ac_email_queue';
    $exists = $wpdb->get_results( "SHOW INDEX FROM $queue WHERE Key_name = 'gm2_send_at'" );
    if (empty($exists)) {
        $wpdb->query( "ALTER TABLE $queue ADD INDEX gm2_send_at (send_at, sent)" );
    }

    update_option('gm2_meta_indexes_version', 2);
}

add_action('plugins_loaded', 'gm2_maybe_add_indexes');

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

function gm2_purge_analytics_logs() {
    global $wpdb;
    $days = absint(get_option('gm2_analytics_retention_days', 30));
    if ($days > 0) {
        $table = $wpdb->prefix . 'gm2_analytics_log';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE `timestamp` < (NOW() - INTERVAL %d DAY)",
                $days
            )
        );
    }
}
add_action('gm2_analytics_purge', 'gm2_purge_analytics_logs');

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
        Gm2_Abandoned_Carts::schedule_event();
    } else {
        Gm2_Abandoned_Carts::clear_scheduled_event();
    }
}
add_action('update_option_gm2_enable_abandoned_carts', 'gm2_handle_ac_option_change', 10, 2);

function gm2_handle_ac_interval_change($old, $new) {
    if (get_option('gm2_enable_abandoned_carts', '0') === '1') {
        Gm2_Abandoned_Carts::clear_scheduled_event();
        Gm2_Abandoned_Carts::schedule_event();
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

function gm2_plugin_action_links($links) {
    $url = admin_url('admin.php?page=gm2');
    $links[] = '<a href="' . esc_url($url) . '">' . esc_html__( 'Settings', 'gm2-wordpress-suite' ) . '</a>';
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'gm2_plugin_action_links');

add_action('init', 'gm2_apply_model_lock_by_env', 1);
/**
 * Ensure the model editor is locked in production environments.
 */
function gm2_apply_model_lock_by_env() {
    $env    = gm2_get_environment();
    $locked = ($env === 'production');
    $locked = apply_filters('gm2_model_locked', $locked, $env);
    $desired = $locked ? 1 : 0;
    if ((int) get_option('gm2_model_locked') !== $desired) {
        update_option('gm2_model_locked', $desired);
    }
}

add_action('admin_bar_menu', 'gm2_add_env_to_admin_bar', 100);
/**
 * Display the current environment in the WordPress admin bar.
 *
 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
 */
function gm2_add_env_to_admin_bar($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    $env = gm2_get_environment();
    $wp_admin_bar->add_node([
        'id'    => 'gm2-env',
        'title' => sprintf('GM2 %s', esc_html($env)),
    ]);
}

if (defined('WP_CLI') && WP_CLI) {
    require_once GM2_PLUGIN_DIR . 'includes/cli/class-gm2-cli.php';
    require_once GM2_PLUGIN_DIR . 'includes/cli/class-gm2-migrate.php';
    require_once GM2_PLUGIN_DIR . 'includes/cli/class-gm2-model.php';
    require_once GM2_PLUGIN_DIR . 'includes/cli/class-gm2-schema-audit.php';
    require_once GM2_PLUGIN_DIR . 'includes/cli/class-ae-seo-critical-cli.php';
}


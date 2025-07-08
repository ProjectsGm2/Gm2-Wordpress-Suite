<?php
/**
 * Plugin Name:       Gm2 WordPress Suite
 * Description:       A powerful suite of tools and features for WordPress, by Gm2.
 * Version:           1.6.0
 * Author:            Your Name or Team Gm2
 * Author URI:        https://yourwebsite.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gm2-wordpress-suite
 * Domain Path:       /languages
 */

defined('ABSPATH') or die('No script kiddies please!');

// Define constants
define('GM2_VERSION', '1.6.0');
define('GM2_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GM2_PLUGIN_URL', plugin_dir_url(__FILE__));

use Gm2\Gm2_Loader;
use Gm2\Gm2_SEO_Public;
use Gm2\Gm2_Sitemap;
$gm2_autoload = GM2_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($gm2_autoload)) {
    require_once $gm2_autoload;
} else {
    require_once GM2_PLUGIN_DIR . 'includes/autoload.php';
}

// Include required files
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Loader.php';
require_once GM2_PLUGIN_DIR . 'public/Gm2_SEO_Public.php';
require_once GM2_PLUGIN_DIR . 'includes/Gm2_Sitemap.php';

function gm2_activate_plugin() {
    $public = new Gm2_SEO_Public();
    $public->add_sitemap_rewrite();
    flush_rewrite_rules();
    gm2_generate_sitemap();

    gm2_initialize_content_rules();
}
register_activation_hook(__FILE__, 'gm2_activate_plugin');

function gm2_deactivate_plugin() {
    flush_rewrite_rules();
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
    ];
    foreach ($taxonomies as $tax) {
        $rules['tax_' . $tax] = implode("\n", $tax_defaults);
    }

    add_option('gm2_content_rules', $rules);
}

// Initialize plugin
function gm2_init_plugin() {
    $plugin = new Gm2_Loader();
    $plugin->run();
}
add_action('plugins_loaded', 'gm2_init_plugin');


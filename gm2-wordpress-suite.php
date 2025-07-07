<?php
/**
 * Plugin Name:       Gm2 WordPress Suite
 * Description:       A powerful suite of tools and features for WordPress, by Gm2.
 * Version:           1.5.0
 * Author:            Your Name or Team Gm2
 * Author URI:        https://yourwebsite.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gm2-wordpress-suite
 * Domain Path:       /languages
 */

defined('ABSPATH') or die('No script kiddies please!');

// Define constants
define('GM2_VERSION', '1.5.0');
define('GM2_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GM2_PLUGIN_URL', plugin_dir_url(__FILE__));

use Gm2\Gm2_Loader;
use Gm2\Gm2_SEO_Public;
use Gm2\Gm2_Sitemap;

/**
 * Ensure Composer dependencies are available. If missing, attempt to run
 * `composer install` using an available Composer binary or a downloaded
 * composer.phar. Falls back to displaying an admin notice on failure.
 *
 * @return bool True when the autoloader was included successfully.
 */
function gm2_ensure_autoload() {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        return true;
    }

    gm2_try_composer_install();

    if (file_exists($autoload)) {
        require_once $autoload;
        return true;
    }

    if (!function_exists('gm2_missing_autoload_notice')) {
        function gm2_missing_autoload_notice() {
            echo '<div class="notice notice-error"><p>' .
                esc_html__('Gm2 WordPress Suite requires its Composer dependencies and was unable to install them automatically. Please run "composer install".', 'gm2-wordpress-suite') .
                '</p></div>';
        }
    }
    add_action('admin_notices', 'gm2_missing_autoload_notice');

    return false;
}

/**
 * Attempt to install Composer dependencies in the plugin directory.
 * Uses the system Composer binary when available, otherwise downloads
 * composer.phar from getcomposer.org.
 */
function gm2_try_composer_install() {
    $plugin_dir = __DIR__;

    $composer_bin = trim(shell_exec('command -v composer'));
    if ($composer_bin !== '') {
        gm2_run_command(escapeshellcmd($composer_bin) . ' install --no-dev --optimize-autoloader', $plugin_dir);
        return;
    }

    if (!function_exists('wp_remote_get')) {
        return;
    }

    $phar = $plugin_dir . '/composer.phar';
    if (!file_exists($phar)) {
        $response = wp_remote_get('https://getcomposer.org/composer-stable.phar');
        if (is_wp_error($response)) {
            return;
        }
        file_put_contents($phar, wp_remote_retrieve_body($response));
        @chmod($phar, 0755);
    }

    $php = escapeshellcmd(PHP_BINARY ?: 'php');
    gm2_run_command($php . ' ' . escapeshellarg($phar) . ' install --no-dev --optimize-autoloader', $plugin_dir);
}

/**
 * Execute a shell command from within the plugin directory.
 *
 * @param string $command
 * @param string $cwd
 * @return void
 */
function gm2_run_command($command, $cwd) {
    $original = getcwd();
    chdir($cwd);
    @exec($command);
    chdir($original);
}

if (!gm2_ensure_autoload()) {
    return;
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


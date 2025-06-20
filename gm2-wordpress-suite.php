<?php
/**
 * Plugin Name:       Gm2 WordPress Suite
 * Description:       A powerful suite of tools and features for WordPress, by Gm2.
 * Version:           1.0.0
 * Author:            Your Name or Team Gm2
 * Author URI:        https://yourwebsite.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gm2-wordpress-suite
 * Domain Path:       /languages
 */

defined('ABSPATH') or die('No script kiddies please!');

// Define constants
define('GM2_VERSION', '1.0.0');
define('GM2_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GM2_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once GM2_PLUGIN_DIR . 'includes/class-gm2-loader.php';

// Initialize plugin
function gm2_init_plugin() {
    $plugin = new Gm2_Loader();
    $plugin->run();
}
add_action('plugins_loaded', 'gm2_init_plugin');

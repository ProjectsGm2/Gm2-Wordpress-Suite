<?php
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}
$env_polyfills = getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH');
if (!defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH') && $env_polyfills && is_dir($env_polyfills)) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $env_polyfills);
}
$polyfills_path = dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills';
if (!defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH') && is_dir($polyfills_path)) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $polyfills_path);
} elseif (!defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH') && is_dir('/tmp/wordpress-develop/vendor/yoast/phpunit-polyfills')) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', '/tmp/wordpress-develop/vendor/yoast/phpunit-polyfills');
}
require_once $_tests_dir . '/includes/functions.php';
$vendor_autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendor_autoload)) {
    require_once $vendor_autoload;
}
if (!defined('GM2_GCLOUD_PROJECT_ID') && getenv('GM2_GCLOUD_PROJECT_ID')) {
    define('GM2_GCLOUD_PROJECT_ID', getenv('GM2_GCLOUD_PROJECT_ID'));
}
if (!defined('GM2_SERVICE_ACCOUNT_JSON') && getenv('GM2_SERVICE_ACCOUNT_JSON')) {
    define('GM2_SERVICE_ACCOUNT_JSON', getenv('GM2_SERVICE_ACCOUNT_JSON'));
}
if (!defined('GM2_TESTING')) {
    define('GM2_TESTING', true);
}
function _manually_load_plugin() {
    if (!class_exists('Elementor\\Base_Data_Control')) {
        eval('namespace Elementor; abstract class Base_Data_Control { public function get_type(){return "";} public function enqueue(){} public function content_template(){} protected function get_default_settings(){return [];} }');
    }
    if (!class_exists('Elementor\\Controls_Manager')) {
        eval('namespace Elementor; class Controls_Manager { const TEXT = "text"; const HIDDEN = "hidden"; const SELECT = "select"; const SELECT2 = "select2"; const NUMBER = "number"; const REPEATER = "repeater"; }');
    }
    if (!class_exists('Elementor\\Repeater')) {
        eval('namespace Elementor; class Repeater { private $controls = []; public function add_control($id, $args = []) { $this->controls[$id] = $args; } public function get_controls() { return $this->controls; } }');
    }
    if (!class_exists('Elementor\\Widget_Base')) {
        eval('namespace Elementor; abstract class Widget_Base { protected array $settings = []; public function __construct($data = [], $args = null) {} abstract public function get_name(); abstract public function get_title(); abstract public function get_icon(); public function get_categories(){ return []; } public function get_keywords(){ return []; } protected function register_controls(): void {} protected function start_controls_section($id, $args = []){} protected function end_controls_section(){} protected function add_control($id, $args = []){} protected function add_inline_editing_attributes($key, $args = []){} public function set_settings(array $settings): void { $this->settings = $settings; } public function get_settings($key = null){ if ($key === null) { return $this->settings; } return $this->settings[$key] ?? null; } public function get_settings_for_display($key = null){ return $this->get_settings($key); } }');
    }
    if (!class_exists('Elementor\\Plugin')) {
        eval('namespace Elementor; class Plugin { public static $instance; public $widgets_manager; public function __construct(){ self::$instance=$this; $this->widgets_manager=new class { public function register_control($id,$ctrl){} public function register_tag($tag){} public function register_group($n,$a=[]){} public function register($widget){} public function register_widget_type($widget){} }; } }');
        new \Elementor\Plugin();
    }
    if (!class_exists('Elementor\\Core\\DynamicTags\\Tag')) {
        eval('namespace Elementor\\Core\\DynamicTags; abstract class Tag { protected function add_control($id,$args=[]){} public function get_settings($key=null){ return null; } }');
    }
    if (!class_exists('Elementor\\Modules\\DynamicTags\\Module')) {
        eval('namespace Elementor\\Modules\\DynamicTags; class Module { const TEXT_CATEGORY="text"; const URL_CATEGORY="url"; const IMAGE_CATEGORY="image"; const MEDIA_CATEGORY="media"; const NUMBER_CATEGORY="number"; const COLOR_CATEGORY="color"; const GALLERY_CATEGORY="gallery"; const DATETIME_CATEGORY="date"; public function register_tag($tag){} public function register_group($name,$args=[]){} }');
    }
    if (!class_exists('ElementorPro\\Modules\\Forms\\Classes\\Action_Base')) {
        eval('namespace ElementorPro\\Modules\\Forms\\Classes; abstract class Action_Base { abstract public function get_name(); abstract public function get_label(); public function register_settings_section($widget) {} abstract public function run($record, $ajax_handler); public function on_export($element) {} }');
    }
    if (!class_exists('ElementorPro\\Modules\\Posts\\Widgets\\Posts')) {
        eval('namespace ElementorPro\\Modules\\Posts\\Widgets; class Posts { public function get_settings(){ return []; } }');
    }
    require dirname(__DIR__) . '/gm2-wordpress-suite.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');
require $_tests_dir . '/includes/bootstrap.php';


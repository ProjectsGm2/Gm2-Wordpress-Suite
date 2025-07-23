<?php
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
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
    require dirname(__DIR__) . '/gm2-wordpress-suite.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');
require $_tests_dir . '/includes/bootstrap.php';


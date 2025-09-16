<?php

declare(strict_types=1);

namespace Gm2\Elementor;

use Elementor\Modules\DynamicTags\Module;
use Gm2\Elementor\Controls\MetaKeySelect;
use Gm2\Elementor\Controls\PostTypeSelect;
use Gm2\Elementor\Controls\Price;
use Gm2\Elementor\Controls\TaxonomyTermMulti;
use Gm2\Elementor\Controls\Unit;
use Gm2\Elementor\DynamicTags\GM2_Dynamic_Tag_Group;
use Gm2\Elementor\Query\Filters;
use ReflectionMethod;
use WP_Error;
use WP_REST_Request;
use function __;
use function class_exists;
use function get_post;
use function is_array;
use function sanitize_text_field;
use function trim;
use function update_option;
use function update_post_meta;
use function wp_delete_post;
use function wp_insert_post;
use function wp_reset_postdata;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/Query/Filters.php';

add_action(
    'elementor/dynamic_tags/register',
    static function ($module): void {
        if (!$module instanceof Module) {
            return;
        }

        GM2_Dynamic_Tag_Group::instance()->register($module);
    }
);

if (defined('GM2_TESTING') && GM2_TESTING) {
    add_action('rest_api_init', static function (): void {
        register_rest_route('gm2-test/v1', '/widget-preview', [
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
            'callback'            => static function (WP_REST_Request $request) {
                if (!class_exists('Elementor\\Widget_Base')) {
                    eval('namespace Elementor; abstract class Widget_Base { protected array $settings = []; public function __construct($data = [], $args = null) {} abstract public function get_name(); abstract public function get_title(); abstract public function get_icon(); public function get_categories(){ return []; } public function get_keywords(){ return []; } protected function register_controls(): void {} protected function start_controls_section($id, $args = []){} protected function end_controls_section(){} protected function add_control($id, $args = []){} protected function add_inline_editing_attributes($key, $args = []){} public function set_settings(array $settings): void { $this->settings = $settings; } public function get_settings($key = null){ if ($key === null) { return $this->settings; } return $this->settings[$key] ?? null; } public function get_settings_for_display($key = null){ return $this->get_settings($key); } }');
                }

                $fieldGroups = $request->get_param('field_groups');
                if (is_array($fieldGroups)) {
                    update_option('gm2_field_groups', $fieldGroups);
                    GM2_Dynamic_Tag_Group::instance()->refresh();
                }

                $widgetName = sanitize_text_field((string) $request->get_param('widget'));
                $map = [
                    'gm2_field'         => [ 'class' => 'Gm2\\Elementor\\Widgets\\Field', 'file' => __DIR__ . '/Widgets/Field.php' ],
                    'gm2_loop_card'     => [ 'class' => 'Gm2\\Elementor\\Widgets\\LoopCard', 'file' => __DIR__ . '/Widgets/LoopCard.php' ],
                    'gm2_map'           => [ 'class' => 'Gm2\\Elementor\\Widgets\\Map', 'file' => __DIR__ . '/Widgets/Map.php' ],
                    'gm2_opening_hours' => [ 'class' => 'Gm2\\Elementor\\Widgets\\OpeningHours', 'file' => __DIR__ . '/Widgets/OpeningHours.php' ],
                ];

                if (!isset($map[$widgetName])) {
                    return new WP_Error('gm2_invalid_widget', __('Unknown widget.', 'gm2-wordpress-suite'), [ 'status' => 400 ]);
                }

                $definition = $map[$widgetName];
                if (!class_exists($definition['class'])) {
                    require_once $definition['file'];
                }

                if (!class_exists($definition['class'])) {
                    return new WP_Error('gm2_widget_unavailable', __('Widget unavailable.', 'gm2-wordpress-suite'), [ 'status' => 400 ]);
                }

                $settings = $request->get_param('settings');
                $widget   = new $definition['class']();
                if (method_exists($widget, 'set_settings') && is_array($settings)) {
                    $widget->set_settings($settings);
                }

                $postConfig = $request->get_param('post');
                $postId     = 0;
                if (is_array($postConfig)) {
                    $postType = sanitize_text_field($postConfig['post_type'] ?? 'post');
                    $postId   = wp_insert_post([
                        'post_type'   => $postType,
                        'post_title'  => 'GM2 Widget Preview',
                        'post_status' => 'publish',
                    ]);

                    if ($postId > 0 && isset($postConfig['meta']) && is_array($postConfig['meta'])) {
                        foreach ($postConfig['meta'] as $key => $value) {
                            if (!is_string($key)) {
                                continue;
                            }
                            update_post_meta($postId, $key, $value);
                        }
                    }
                }

                $original = $GLOBALS['post'] ?? null;
                if ($postId > 0) {
                    $GLOBALS['post'] = get_post($postId);
                }

                $method = new ReflectionMethod($widget, 'render');
                $method->setAccessible(true);
                ob_start();
                $method->invoke($widget);
                $html = (string) ob_get_clean();

                if ($postId > 0) {
                    wp_delete_post($postId, true);
                }

                $GLOBALS['post'] = $original;
                wp_reset_postdata();

                return [ 'html' => $html ];
            },
        ]);
    });
}

Filters::register();

PostTypeSelect::register();
TaxonomyTermMulti::register();
MetaKeySelect::register();
Unit::register();
Price::register();

add_action(
    'elementor/widgets/register',
    static function ($manager): void {
        if (!class_exists('Elementor\\Widget_Base')) {
            return;
        }

        $widgets = [
            'Gm2\\Elementor\\Widgets\\Field'         => __DIR__ . '/Widgets/Field.php',
            'Gm2\\Elementor\\Widgets\\LoopCard'      => __DIR__ . '/Widgets/LoopCard.php',
            'Gm2\\Elementor\\Widgets\\Map'           => __DIR__ . '/Widgets/Map.php',
            'Gm2\\Elementor\\Widgets\\OpeningHours' => __DIR__ . '/Widgets/OpeningHours.php',
        ];

        foreach ($widgets as $class => $path) {
            if (!class_exists($class)) {
                require_once $path;
            }

            if (!class_exists($class)) {
                continue;
            }

            $widget = new $class();

            if (method_exists($manager, 'register')) {
                $manager->register($widget);
                continue;
            }

            if (method_exists($manager, 'register_widget_type')) {
                $manager->register_widget_type($widget);
            }
        }
    }
);

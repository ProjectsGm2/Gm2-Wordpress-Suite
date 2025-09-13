<?php
namespace Gm2\Elementor;

use Elementor\Base_Data_Control;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elementor control for selecting GM2 field keys via AJAX.
 */
class GM2_Field_Key_Control extends Base_Data_Control {
    /** Control type identifier. */
    const TYPE = 'gm2-field-key';

    /**
     * Register hooks.
     */
    public static function init() {
        add_action('wp_ajax_gm2_field_keys', [__CLASS__, 'ajax_field_keys']);
        add_action('elementor/controls/register', [__CLASS__, 'register_control']);
    }

    /**
     * Register the control with Elementor.
     *
     * @param \Elementor\Controls_Manager $controls_manager
     */
    public static function register_control($controls_manager) {
        $controls_manager->register_control(self::TYPE, new self());
    }

    /**
     * Control type.
     */
    public function get_type() {
        return self::TYPE;
    }

    /**
     * Enqueue assets.
     */
    public function enqueue() {
        wp_enqueue_script(
            'gm2-field-key-control',
            GM2_PLUGIN_URL . 'public/js/gm2-field-key-control.js',
            ['jquery', 'elementor-controls'],
            defined('GM2_VERSION') ? GM2_VERSION : false,
            true
        );
        wp_localize_script('gm2-field-key-control', 'gm2FieldKey', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gm2_field_keys'),
        ]);
    }

    /**
     * Render control content.
     */
    public function content_template() {
        ?>
        <div class="gm2-field-key-control">
            <select data-setting="{{ data.name }}"></select>
        </div>
        <?php
    }

    /**
     * Default settings.
     */
    protected function get_default_settings() {
        return [ 'label_block' => true ];
    }

    /**
     * AJAX callback returning available field keys for a post type.
     */
    public static function ajax_field_keys() {
        check_ajax_referer('gm2_field_keys', 'nonce');
        $post_type = sanitize_key($_POST['post_type'] ?? '');
        $groups    = get_option('gm2_field_groups', []);
        $fields    = [];
        if (is_array($groups)) {
            foreach ($groups as $group) {
                $scope   = $group['scope'] ?? '';
                $objects = $group['objects'] ?? [];
                if ($scope === 'post_type' && $objects && !in_array($post_type, $objects, true)) {
                    continue;
                }
                if (!empty($group['fields']) && is_array($group['fields'])) {
                    foreach ($group['fields'] as $key => $field) {
                        self::collect_field($fields, $key, $field);
                    }
                }
            }
        }
        wp_send_json_success($fields);
    }

    /**
     * Recursively flatten fields.
     *
     * @param array  $store
     * @param string $path
     * @param array  $field
     * @param string $prefix
     */
    private static function collect_field(&$store, $path, $field, $prefix = '') {
        $label       = $field['label'] ?? $path;
        $display     = $prefix ? $prefix . ' › ' . $label : $label; // › is a single right-pointing angle quote
        $store[$path] = $display;
        if (!empty($field['fields']) && is_array($field['fields'])) {
            foreach ($field['fields'] as $sub_key => $sub_field) {
                self::collect_field($store, $path . '.' . $sub_key, $sub_field, $display);
            }
        }
        if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
            foreach ($field['sub_fields'] as $sub_key => $sub_field) {
                self::collect_field($store, $path . '.0.' . $sub_key, $sub_field, $display . ' [0]');
            }
        }
    }
}

GM2_Field_Key_Control::init();

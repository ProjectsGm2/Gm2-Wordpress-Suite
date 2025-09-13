<?php
namespace Gm2\Elementor;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elementor dynamic tag exposing GM2 custom field values.
 */
class GM2_Dynamic_Tag extends Tag {
    /**
     * Bootstraps the dynamic tag registration.
     */
    public static function init() {
        // Register group and tag with Elementor when dynamic tags are loaded.
        add_action('elementor/dynamic_tags/register_tags', [__CLASS__, 'register']);
        add_action('elementor/dynamic_tags/register_tags', [__CLASS__, 'register_group'], 5);
    }

    /**
     * Register the dynamic tag with Elementor.
     *
     * @param \Elementor\Modules\DynamicTags\Module $dynamic_tags
     */
    public static function register($dynamic_tags) {
        $dynamic_tags->register_tag(__CLASS__);
    }

    /**
     * Register the GM2 dynamic tag group.
     *
     * @param \Elementor\Modules\DynamicTags\Module $dynamic_tags
     */
    public static function register_group($dynamic_tags) {
        $dynamic_tags->register_group('gm2', [
            'title' => __('GM2 Fields', 'gm2-wordpress-suite'),
        ]);
    }

    /**
     * Unique tag name.
     */
    public function get_name() {
        return 'gm2-field';
    }

    /**
     * Tag title shown in Elementor UI.
     */
    public function get_title() {
        return __('GM2 Field', 'gm2-wordpress-suite');
    }

    /**
     * Group for tag listing.
     */
    public function get_group() {
        return 'gm2';
    }

    /**
     * Determine Elementor category based on field type.
     *
     * @return array
     */
    public function get_categories() {
        $fields = self::get_fields();
        $key    = $this->get_settings('field');
        $type   = $fields[$key]['type'] ?? '';
        return self::categories_for_type($type);
    }

    /**
     * Register controls for the dynamic tag.
     */
    protected function register_controls() {
        $this->add_control('field', [
            'label' => __('Field', 'gm2-wordpress-suite'),
            'type'  => GM2_Field_Key_Control::TYPE,
        ]);

        $this->add_control('fallback', [
            'label' => __('Fallback', 'gm2-wordpress-suite'),
            'type'  => Controls_Manager::TEXT,
        ]);
    }

    /**
     * Return the field value for Elementor rendering.
     *
     * @param array $options
     * @return mixed
     */
    public function get_value( array $options = [] ) {
        $key      = $this->get_settings('field');
        $fallback = $this->get_settings('fallback');
        if (!$key) {
            return $fallback;
        }
        $value = self::resolve_field_value($key);
        if ($value === '' || $value === null) {
            return $fallback;
        }
        return $value;
    }

    /**
     * Resolve a field path into a value using dot notation for nested fields.
     *
     * @param string $path
     * @return mixed
     */
    private static function resolve_field_value($path) {
        $parts = explode('.', $path);
        $base  = array_shift($parts);
        $value = \gm2_field($base, '');
        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return '';
            }
        }
        return $value;
    }

    /** @var array|null Cache of flattened fields. */
    private static $fields_cache = null;

    /**
     * Retrieve flattened field definitions for control options.
     *
     * @return array
     */
    private static function get_fields() {
        if (self::$fields_cache !== null) {
            return self::$fields_cache;
        }
        $groups = get_option('gm2_field_groups', []);
        $fields = [];
        if (is_array($groups)) {
            foreach ($groups as $group) {
                if (!empty($group['fields']) && is_array($group['fields'])) {
                    foreach ($group['fields'] as $key => $field) {
                        self::collect_field($fields, $key, $field, $group['label'] ?? '');
                    }
                }
            }
        }
        self::$fields_cache = $fields;
        return $fields;
    }

    /**
     * Recursively collect fields including group and repeater subfields.
     *
     * @param array  $store
     * @param string $path
     * @param array  $field
     * @param string $group_label
     * @param string $prefix
     */
    private static function collect_field(&$store, $path, $field, $group_label = '', $prefix = '') {
        $label = $field['label'] ?? $path;
        $full  = $group_label ? $group_label . ': ' . $label : $label;
        if ($prefix !== '') {
            $full = $prefix . ' › ' . $label;
        }
        $store[$path] = [
            'label' => $full,
            'type'  => $field['type'] ?? 'text',
        ];
        if (!empty($field['fields']) && is_array($field['fields'])) {
            foreach ($field['fields'] as $sub_key => $sub_field) {
                self::collect_field($store, $path . '.' . $sub_key, $sub_field, $group_label, $full);
            }
        }
        if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
            foreach ($field['sub_fields'] as $sub_key => $sub_field) {
                self::collect_field($store, $path . '.0.' . $sub_key, $sub_field, $group_label, $full . ' [0]');
            }
        }
    }

    /**
     * Map a GM2 field type to Elementor dynamic tag categories.
     *
     * @param string $type
     * @return array
     */
    private static function categories_for_type($type) {
        switch ($type) {
            case 'url':
            case 'link':
                return [ Module::URL_CATEGORY ];
            case 'image':
            case 'media':
            case 'video':
            case 'audio':
            case 'file':
                return [ Module::MEDIA_CATEGORY ];
            case 'gallery':
                return [ Module::GALLERY_CATEGORY ];
            default:
                return [ Module::TEXT_CATEGORY ];
        }
    }
}

GM2_Dynamic_Tag::init();

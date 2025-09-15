<?php
namespace Gm2\Integrations\Elementor;

use Elementor\Controls_Manager;
use Gm2\Elementor\GM2_Field_Key_Control;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Translate Elementor query controls into WP_Query args.
 */
class GM2_CP_Elementor_Query {
    /**
     * Register Elementor query hook.
     */
    public static function register() {
        add_action('elementor_pro/posts/query/gm2_cp', [__CLASS__, 'apply_query'], 10, 2);
        add_action('elementor/element/posts/section_query/before_section_end', [__CLASS__, 'add_controls'], 10, 2);
    }

    /**
     * Add custom query controls.
     */
    public static function add_controls($element, $args) {
        $post_type_options = self::get_post_type_options();
        $taxonomy_options  = self::get_taxonomy_options();
        $terms_options     = self::get_terms_options(array_keys($taxonomy_options));

        $element->add_control('gm2_cp_post_type', [
            'label'       => __('Post Types', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'options'     => $post_type_options,
            'label_block' => true,
            'condition'   => [ 'query_id' => 'gm2_cp' ],
        ]);

        $element->add_control('gm2_cp_taxonomy', [
            'label'       => __('Taxonomy', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::SELECT,
            'options'     => $taxonomy_options,
            'label_block' => true,
            'condition'   => [ 'query_id' => 'gm2_cp' ],
        ]);

        $element->add_control('gm2_cp_terms', [
            'label'       => __('Terms', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'options'     => $terms_options,
            'label_block' => true,
            'condition'   => [
                'query_id'         => 'gm2_cp',
                'gm2_cp_taxonomy!' => '',
            ],
        ]);

        $element->add_control('gm2_cp_meta_key', [
            'label'     => __('GM2 Field Key', 'gm2-wordpress-suite'),
            'type'      => GM2_Field_Key_Control::TYPE,
            'condition' => [ 'query_id' => 'gm2_cp' ],
        ]);

        $element->add_control('gm2_cp_meta_compare', [
            'label'     => __('Meta Compare', 'gm2-wordpress-suite'),
            'type'      => Controls_Manager::SELECT,
            'options'   => self::get_meta_compare_options(),
            'condition' => [
                'query_id'           => 'gm2_cp',
                'gm2_cp_meta_key!'   => '',
            ],
        ]);

        $element->add_control('gm2_cp_meta_type', [
            'label'     => __('Meta Type', 'gm2-wordpress-suite'),
            'type'      => Controls_Manager::SELECT,
            'options'   => self::get_meta_type_options(),
            'condition' => [
                'query_id'           => 'gm2_cp',
                'gm2_cp_meta_key!'   => '',
            ],
        ]);

        $element->add_control('gm2_cp_meta_value', [
            'label'       => __('Meta Value', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'condition'   => [
                'query_id'           => 'gm2_cp',
                'gm2_cp_meta_key!'   => '',
            ],
        ]);

        $element->add_control('gm2_cp_date_after', [
            'label'       => __('Date After', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::DATE_TIME,
            'picker_options' => [ 'enableTime' => false ],
            'condition'   => [ 'query_id' => 'gm2_cp' ],
        ]);

        $element->add_control('gm2_cp_date_before', [
            'label'       => __('Date Before', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::DATE_TIME,
            'picker_options' => [ 'enableTime' => false ],
            'condition'   => [ 'query_id' => 'gm2_cp' ],
        ]);

        $element->add_control('gm2_cp_price_min', [
            'label'     => __('Minimum Price', 'gm2-wordpress-suite'),
            'type'      => Controls_Manager::NUMBER,
            'condition' => [ 'query_id' => 'gm2_cp' ],
        ]);

        $element->add_control('gm2_cp_price_max', [
            'label'     => __('Maximum Price', 'gm2-wordpress-suite'),
            'type'      => Controls_Manager::NUMBER,
            'condition' => [ 'query_id' => 'gm2_cp' ],
        ]);

        $element->add_control('gm2_cp_price_key', [
            'label'       => __('Price Meta Key', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'condition'   => [ 'query_id' => 'gm2_cp' ],
        ]);

        $element->add_control('gm2_cp_geo_lat', [
            'label'     => __('Latitude', 'gm2-wordpress-suite'),
            'type'      => Controls_Manager::NUMBER,
            'condition' => [ 'query_id' => 'gm2_cp' ],
        ]);

        $element->add_control('gm2_cp_geo_lng', [
            'label'     => __('Longitude', 'gm2-wordpress-suite'),
            'type'      => Controls_Manager::NUMBER,
            'condition' => [ 'query_id' => 'gm2_cp' ],
        ]);

        $element->add_control('gm2_cp_geo_radius', [
            'label'     => __('Radius (km)', 'gm2-wordpress-suite'),
            'type'      => Controls_Manager::NUMBER,
            'condition' => [ 'query_id' => 'gm2_cp' ],
        ]);

        $element->add_control('gm2_cp_geo_lat_key', [
            'label'       => __('Latitude Meta Key', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'condition'   => [ 'query_id' => 'gm2_cp' ],
        ]);

        $element->add_control('gm2_cp_geo_lng_key', [
            'label'       => __('Longitude Meta Key', 'gm2-wordpress-suite'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'condition'   => [ 'query_id' => 'gm2_cp' ],
        ]);
    }

    /**
     * Apply custom query arguments from widget settings.
     *
     * @param \WP_Query                                 $query   Query instance.
     * @param \ElementorPro\Modules\Posts\Widgets\Posts $widget Widget instance.
     */
    public static function apply_query($query, $widget) {
        $settings = $widget->get_settings();
        $args     = [];

        // Post type selection.
        if (!empty($settings['gm2_cp_post_type'])) {
            $args['post_type'] = array_map('sanitize_key', (array) $settings['gm2_cp_post_type']);
        }

        // Taxonomy terms.
        if (!empty($settings['gm2_cp_taxonomy']) && !empty($settings['gm2_cp_terms'])) {
            $args['tax_query'][] = [
                'taxonomy' => sanitize_key($settings['gm2_cp_taxonomy']),
                'field'    => 'term_id',
                'terms'    => array_map('absint', (array) $settings['gm2_cp_terms']),
            ];
        }

        // Meta comparisons.
        if (!empty($settings['gm2_cp_meta_key'])) {
            $meta = [
                'key' => sanitize_text_field($settings['gm2_cp_meta_key']),
            ];
            if ($settings['gm2_cp_meta_value'] !== '') {
                $meta['value'] = sanitize_text_field($settings['gm2_cp_meta_value']);
            }
            if (!empty($settings['gm2_cp_meta_compare'])) {
                $meta['compare'] = strtoupper($settings['gm2_cp_meta_compare']);
            }
            if (!empty($settings['gm2_cp_meta_type'])) {
                $meta['type'] = sanitize_text_field($settings['gm2_cp_meta_type']);
            }
            $args['meta_query'][] = $meta;
        }

        // Date range.
        if (!empty($settings['gm2_cp_date_after']) || !empty($settings['gm2_cp_date_before'])) {
            $date = ['inclusive' => true];
            if (!empty($settings['gm2_cp_date_after'])) {
                $date['after'] = sanitize_text_field($settings['gm2_cp_date_after']);
            }
            if (!empty($settings['gm2_cp_date_before'])) {
                $date['before'] = sanitize_text_field($settings['gm2_cp_date_before']);
            }
            $args['date_query'][] = $date;
        }

        // Price range.
        if ($settings['gm2_cp_price_min'] !== '' || $settings['gm2_cp_price_max'] !== '') {
            $min   = $settings['gm2_cp_price_min'] !== '' ? floatval($settings['gm2_cp_price_min']) : null;
            $max   = $settings['gm2_cp_price_max'] !== '' ? floatval($settings['gm2_cp_price_max']) : null;
            $range = array_filter([$min, $max], static function ($v) {
                return $v !== null;
            });
            if ($range) {
                $compare = 'BETWEEN';
                if (count($range) === 1) {
                    $compare = $min !== null ? '>=' : '<=';
                }
                $args['meta_query'][] = [
                    'key'     => sanitize_key($settings['gm2_cp_price_key'] ?? '_price'),
                    'value'   => $range,
                    'compare' => $compare,
                    'type'    => 'NUMERIC',
                ];
            }
        }

        // Geodistance via bounding box around coordinates.
        if (
            $settings['gm2_cp_geo_lat'] !== '' &&
            $settings['gm2_cp_geo_lng'] !== '' &&
            $settings['gm2_cp_geo_radius'] !== ''
        ) {
            $lat     = floatval($settings['gm2_cp_geo_lat']);
            $lng     = floatval($settings['gm2_cp_geo_lng']);
            $radius  = floatval($settings['gm2_cp_geo_radius']);
            $lat_key = sanitize_key($settings['gm2_cp_geo_lat_key'] ?? 'gm2_geo_lat');
            $lng_key = sanitize_key($settings['gm2_cp_geo_lng_key'] ?? 'gm2_geo_lng');

            $lat_range = [$lat - ($radius / 111.045), $lat + ($radius / 111.045)];
            $lng_range = [$lng - ($radius / (111.045 * cos(deg2rad($lat)))), $lng + ($radius / (111.045 * cos(deg2rad($lat))))];

            $args['meta_query'][] = [
                'key'     => $lat_key,
                'value'   => $lat_range,
                'compare' => 'BETWEEN',
                'type'    => 'DECIMAL',
            ];
            $args['meta_query'][] = [
                'key'     => $lng_key,
                'value'   => $lng_range,
                'compare' => 'BETWEEN',
                'type'    => 'DECIMAL',
            ];
        }

        // Merge with existing query vars.
        foreach ($args as $key => $value) {
            $existing = $query->get($key);
            if (is_array($existing) && is_array($value)) {
                $query->set($key, array_merge($existing, $value));
            } else {
                $query->set($key, $value);
            }
        }
    }

    /**
     * Fetch public post types for the control options.
     *
     * @return array<string, string>
     */
    protected static function get_post_type_options() {
        $post_types = get_post_types(['public' => true], 'objects');
        $options    = [];

        foreach ($post_types as $type => $object) {
            $label = $object->labels->singular_name ?? $object->labels->name ?? $type;
            $options[$type] = $label;
        }

        return $options;
    }

    /**
     * Fetch public taxonomies for the control options.
     *
     * @return array<string, string>
     */
    protected static function get_taxonomy_options() {
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $options    = [];

        foreach ($taxonomies as $taxonomy => $object) {
            $label = $object->labels->singular_name ?? $object->labels->name ?? $taxonomy;
            $options[$taxonomy] = $label;
        }

        return $options;
    }

    /**
     * Fetch term options for configured taxonomies.
     *
     * @param array<int|string> $taxonomies List of taxonomies.
     *
     * @return array<int, string>
     */
    protected static function get_terms_options($taxonomies) {
        if (empty($taxonomies)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomies,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        $options = [];

        foreach ($terms as $term) {
            $taxonomy = get_taxonomy($term->taxonomy);
            $label    = $taxonomy && isset($taxonomy->labels->singular_name)
                ? $taxonomy->labels->singular_name
                : $term->taxonomy;

            $options[$term->term_id] = sprintf('%s: %s', $label, $term->name);
        }

        return $options;
    }

    /**
     * Supported meta comparison operators.
     *
     * @return array<string, string>
     */
    protected static function get_meta_compare_options() {
        return [
            '='           => __('Equal (=)', 'gm2-wordpress-suite'),
            '!='          => __('Not equal (!=)', 'gm2-wordpress-suite'),
            '>'           => __('Greater than (>)', 'gm2-wordpress-suite'),
            '>='          => __('Greater than or equal (>=)', 'gm2-wordpress-suite'),
            '<'           => __('Less than (<)', 'gm2-wordpress-suite'),
            '<='          => __('Less than or equal (<=)', 'gm2-wordpress-suite'),
            'LIKE'        => __('Like', 'gm2-wordpress-suite'),
            'NOT LIKE'    => __('Not like', 'gm2-wordpress-suite'),
            'IN'          => __('In', 'gm2-wordpress-suite'),
            'NOT IN'      => __('Not in', 'gm2-wordpress-suite'),
            'BETWEEN'     => __('Between', 'gm2-wordpress-suite'),
            'NOT BETWEEN' => __('Not between', 'gm2-wordpress-suite'),
            'EXISTS'      => __('Exists', 'gm2-wordpress-suite'),
            'NOT EXISTS'  => __('Not exists', 'gm2-wordpress-suite'),
        ];
    }

    /**
     * Supported meta types.
     *
     * @return array<string, string>
     */
    protected static function get_meta_type_options() {
        return [
            ''         => __('Default', 'gm2-wordpress-suite'),
            'NUMERIC'  => __('Numeric', 'gm2-wordpress-suite'),
            'DECIMAL'  => __('Decimal', 'gm2-wordpress-suite'),
            'SIGNED'   => __('Signed', 'gm2-wordpress-suite'),
            'UNSIGNED' => __('Unsigned', 'gm2-wordpress-suite'),
            'BINARY'   => __('Binary', 'gm2-wordpress-suite'),
            'CHAR'     => __('Char', 'gm2-wordpress-suite'),
            'DATE'     => __('Date', 'gm2-wordpress-suite'),
            'DATETIME' => __('Date & Time', 'gm2-wordpress-suite'),
            'TIME'     => __('Time', 'gm2-wordpress-suite'),
        ];
    }
}
GM2_CP_Elementor_Query::register();

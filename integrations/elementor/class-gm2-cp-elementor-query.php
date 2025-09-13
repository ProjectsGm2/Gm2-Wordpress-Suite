<?php
namespace Gm2\Integrations\Elementor;

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
        $element->add_control('gm2_cp_meta_key', [
            'label' => __('GM2 Field Key', 'gm2-wordpress-suite'),
            'type'  => GM2_Field_Key_Control::TYPE,
            'condition' => [ 'query_id' => 'gm2_cp' ],
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
}
GM2_CP_Elementor_Query::register();

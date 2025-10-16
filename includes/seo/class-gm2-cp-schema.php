<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Output JSON-LD for custom post types using field mappings.
 */
class Gm2_CP_Schema {
    /**
     * Register hooks.
     */
    public static function init(): void {
        add_action('wp_head', [__CLASS__, 'singular_schema'], 19);
        add_action('wp_head', [__CLASS__, 'archive_schema'], 19);
    }

    /**
     * Output schema for singular views.
     */
    public static function singular_schema(): void {
        if (!is_singular()) {
            return;
        }
        $post = get_queried_object();
        if (!$post || empty($post->post_type)) {
            return;
        }
        $map = self::get_map($post->post_type);
        if (!$map) {
            return;
        }
        $schema = self::build_schema($map['type'], $map['map'], $post->ID);
        if (!$schema) {
            return;
        }
        $schema = apply_filters('gm2_cp_schema_data', $schema, $post->ID, $post->post_type, 'singular');
        if (apply_filters('gm2_seo_cp_schema', false, $schema, [ 'context' => 'singular', 'id' => $post->ID, 'post_type' => $post->post_type ])) {
            return;
        }
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . "</script>\n";
    }

    /**
     * Output ItemList schema for archives.
     */
    public static function archive_schema(): void {
        if (!is_post_type_archive()) {
            return;
        }
        $post_type = get_query_var('post_type');
        if (is_array($post_type)) {
            $post_type = reset($post_type);
        }
        if (!$post_type) {
            $post_type = get_post_type();
        }
        if (!$post_type) {
            return;
        }
        $map = self::get_map($post_type);
        if (!$map) {
            return;
        }
        global $wp_query;
        $items = [];
        foreach ($wp_query->posts as $index => $post) {
            $item_schema = self::build_schema($map['type'], $map['map'], $post->ID);
            if (!$item_schema) {
                continue;
            }
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'item'     => $item_schema,
            ];
        }
        if (!$items) {
            return;
        }
        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'itemListElement' => $items,
        ];
        $schema = apply_filters('gm2_cp_schema_archive_data', $schema, $post_type);
        if (apply_filters('gm2_seo_cp_schema', false, $schema, [ 'context' => 'archive', 'post_type' => $post_type ])) {
            return;
        }
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . "</script>\n";
    }

    /**
     * Retrieve mapping for a post type.
     *
     * @param string $post_type
     * @return array|null
     */
    private static function get_map(string $post_type): ?array {
        $maps = get_option('gm2_cp_schema_map', []);
        if (!is_array($maps) || empty($maps[$post_type]['type']) || empty($maps[$post_type]['map'])) {
            return null;
        }
        return $maps[$post_type];
    }

    /**
     * Build schema array from mapping and field values.
     *
     * @param string $type Schema type.
     * @param array  $map  Property to field key map.
     * @param int    $post_id Post ID.
     * @return array|null
     */
    private static function build_schema(string $type, array $map, int $post_id): ?array {
        $schema   = [
            '@context' => 'https://schema.org',
            '@type'    => $type,
        ];
        $has_data = false;
        foreach ($map as $prop => $field_key) {
            $value = gm2_field($field_key, '', $post_id);
            if ($value === '' || $value === null) {
                continue;
            }
            self::assign($schema, $prop, $value);
            $has_data = true;
        }
        return $has_data ? $schema : null;
    }

    /**
     * Assign a value to a nested property path.
     */
    private static function assign(array &$schema, string $path, $value): void {
        $segments = explode('.', $path);
        $ref =& $schema;
        foreach ($segments as $seg) {
            if ($seg === '') {
                continue;
            }
            if (is_numeric($seg)) {
                $idx = (int) $seg;
                if (!isset($ref[$idx]) || !is_array($ref[$idx])) {
                    $ref[$idx] = [];
                }
                $ref =& $ref[$idx];
                continue;
            }
            if (!isset($ref[$seg]) || !is_array($ref[$seg])) {
                $ref[$seg] = [];
            }
            if (!isset($ref[$seg]['@type'])) {
                $t = self::nested_type($seg);
                if ($t) {
                    $ref[$seg]['@type'] = $t;
                }
            }
            $ref =& $ref[$seg];
        }
        if (is_array($ref) && is_array($value)) {
            $is_list = function_exists('wp_is_numeric_array') ? wp_is_numeric_array($value) : self::is_list($value);
            if (isset($ref['@type']) && $is_list) {
                $type = $ref['@type'];
                $ref = array_map(
                    static function ($item) use ($type) {
                        if (!is_array($item)) {
                            return $item;
                        }
                        if (!isset($item['@type'])) {
                            $item['@type'] = $type;
                        }
                        return $item;
                    },
                    $value
                );
                return;
            }
            $ref = array_replace($ref, $value);
            return;
        }
        $ref = $value;
    }

    /**
     * Map nested property names to schema types.
     */
    private static function nested_type(string $segment): ?string {
        return match ($segment) {
            'address' => 'PostalAddress',
            'baseSalary' => 'MonetaryAmount',
            'courseInstance' => 'CourseInstance',
            'geo' => 'GeoCoordinates',
            'hiringOrganization' => 'Organization',
            'jobLocation' => 'Place',
            'location' => 'Place',
            'offers' => 'Offer',
            'openingHoursSpecification' => 'OpeningHoursSpecification',
            'organizer' => 'Organization',
            'virtualLocation' => 'VirtualLocation',
            default => null,
        };
    }

    /**
     * Determine if an array uses sequential numeric keys.
     */
    private static function is_list(array $value): bool {
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }
}

Gm2_CP_Schema::init();

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

            $value = self::normalizeSchemaValue($prop, $value);
            if ($value === '' || $value === null) {
                continue;
            }

            self::assign($schema, $prop, $value);
            $has_data = true;
        }
        return $has_data ? $schema : null;
    }

    /**
     * Normalize mapped values for specific schema properties.
     */
    private static function normalizeSchemaValue(string $property, mixed $value): mixed
    {
        if (self::propertyMatches($property, 'eventAttendanceMode')) {
            return self::normalizeEventAttendanceMode($value);
        }

        return $value;
    }

    /**
     * Determine if a property path targets a specific property.
     */
    private static function propertyMatches(string $property, string $target): bool
    {
        if ($property === $target) {
            return true;
        }

        $suffix = '.' . $target;

        return substr($property, -strlen($suffix)) === $suffix;
    }

    /**
     * Normalize EventAttendanceMode values to canonical schema URLs.
     */
    private static function normalizeEventAttendanceMode(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        $normalized = strtolower($value);
        $map = [
            'online'    => 'https://schema.org/OnlineEventAttendanceMode',
            'offline'   => 'https://schema.org/OfflineEventAttendanceMode',
            'inperson'  => 'https://schema.org/OfflineEventAttendanceMode',
            'in-person' => 'https://schema.org/OfflineEventAttendanceMode',
            'onsite'    => 'https://schema.org/OfflineEventAttendanceMode',
            'hybrid'    => 'https://schema.org/MixedEventAttendanceMode',
            'mixed'     => 'https://schema.org/MixedEventAttendanceMode',
        ];

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        if (preg_match('/EventAttendanceMode$/', $value)) {
            return 'https://schema.org/' . $value;
        }

        return $value;
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
            'applicationContact' => 'ContactPoint',
            'baseSalary' => 'MonetaryAmount',
            'courseInstance' => 'CourseInstance',
            'geo' => 'GeoCoordinates',
            'hiringOrganization' => 'Organization',
            'provider' => 'Organization',
            'jobLocation' => 'Place',
            'location' => 'Place',
            'offers' => 'Offer',
            'openingHoursSpecification' => 'OpeningHoursSpecification',
            'organizer' => 'Organization',
            'priceSpecification' => 'PriceSpecification',
            'value' => 'QuantitativeValue',
            'virtualLocation' => 'VirtualLocation',
        default => null,
        };
    }
}

Gm2_CP_Schema::init();

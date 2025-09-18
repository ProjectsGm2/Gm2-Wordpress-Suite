<?php

declare(strict_types=1);

namespace Gm2\Elementor\Query;

use WP_Query;

use function add_action;
use function current_time;
use function deg2rad;
use function cos;
use function sanitize_title;
use function sanitize_text_field;

/**
 * Registers Elementor query filters for GM2 content types.
 */
class Filters
{
    private const DEFAULT_EVENTS_PER_PAGE     = 6;
    private const DEFAULT_JOBS_PER_PAGE       = 10;
    private const DEFAULT_PROPERTIES_PER_PAGE = 12;
    private const DEFAULT_DIRECTORY_PER_PAGE  = 12;
    private const DEFAULT_COURSES_PER_PAGE    = 9;

    /**
     * Bootstrap the filter callbacks.
     */
    public static function register(): void
    {
        add_action('elementor/query/gm2_upcoming_events', [self::class, 'handleUpcomingEvents'], 10, 2);
        add_action('elementor/query/gm2_past_events', [self::class, 'handlePastEvents'], 10, 2);
        add_action('elementor/query/gm2_open_jobs', [self::class, 'handleOpenJobs'], 10, 2);
        add_action('elementor/query/gm2_properties_sale', [self::class, 'handlePropertiesForSale'], 10, 2);
        add_action('elementor/query/gm2_properties_rent', [self::class, 'handlePropertiesForRent'], 10, 2);
        add_action('elementor/query/gm2_properties_nearby', [self::class, 'handlePropertiesNearby'], 10, 2);
        add_action('elementor/query/gm2_directory_nearby', [self::class, 'handleDirectoryNearby'], 10, 2);
        add_action('elementor/query/gm2_directory_by_category', [self::class, 'handleDirectoryByCategory'], 10, 2);
        add_action('elementor/query/gm2_courses_active', [self::class, 'handleActiveCourses'], 10, 2);
    }

    /**
     * Upcoming events are ordered by start date and limited to future events.
     */
    public static function handleUpcomingEvents($query, $widget = null): void
    {
        if (!$query instanceof WP_Query) {
            return;
        }

        self::ensurePostType($query, 'event');
        self::ensureStatus($query, 'publish');
        self::ensurePagination($query, self::DEFAULT_EVENTS_PER_PAGE);
        self::applySearch($query, ['gm2_event_search', 'gm2_search']);

        $now = current_time('mysql');
        self::appendMetaQuery($query, [
            'key'     => 'start_date',
            'value'   => $now,
            'compare' => '>=',
            'type'    => 'DATETIME',
        ]);

        self::ensureMetaOrdering($query, 'start_date', 'meta_value', 'ASC');
    }

    /**
     * Past events are sorted by most recent first and limited to dates before today.
     */
    public static function handlePastEvents($query, $widget = null): void
    {
        if (!$query instanceof WP_Query) {
            return;
        }

        self::ensurePostType($query, 'event');
        self::ensureStatus($query, 'publish');
        self::ensurePagination($query, self::DEFAULT_EVENTS_PER_PAGE);
        self::applySearch($query, ['gm2_event_search', 'gm2_search']);

        $now = current_time('mysql');
        self::appendMetaQuery($query, [
            'key'     => 'start_date',
            'value'   => $now,
            'compare' => '<',
            'type'    => 'DATETIME',
        ]);

        self::ensureMetaOrdering($query, 'start_date', 'meta_value', 'DESC');
    }

    /**
     * Open jobs default to the current listings in descending publish order.
     */
    public static function handleOpenJobs($query, $widget = null): void
    {
        if (!$query instanceof WP_Query) {
            return;
        }

        self::ensurePostType($query, 'job');
        self::ensureStatus($query, 'publish');
        self::ensurePagination($query, self::DEFAULT_JOBS_PER_PAGE);
        self::applySearch($query, ['gm2_job_search', 'gm2_search']);

        self::appendMetaQuery($query, [
            'key'   => 'job_status',
            'value' => 'open',
        ]);

        self::ensureOrdering($query, 'date', 'DESC');
    }

    /**
     * Properties for sale filter by taxonomy and price order.
     */
    public static function handlePropertiesForSale($query, $widget = null): void
    {
        if (!$query instanceof WP_Query) {
            return;
        }

        self::preparePropertyQuery($query, 'for-sale');
    }

    /**
     * Properties for rent filter by taxonomy and price order.
     */
    public static function handlePropertiesForRent($query, $widget = null): void
    {
        if (!$query instanceof WP_Query) {
            return;
        }

        self::preparePropertyQuery($query, 'for-rent');
    }

    /**
     * Nearby properties use a geo bounding box to limit results.
     */
    public static function handlePropertiesNearby($query, $widget = null): void
    {
        if (!$query instanceof WP_Query) {
            return;
        }

        self::ensurePostType($query, 'property');
        self::ensureStatus($query, 'publish');
        self::ensurePagination($query, self::DEFAULT_PROPERTIES_PER_PAGE);
        self::applySearch($query, ['gm2_property_search', 'gm2_search']);

        $lat    = self::floatQueryVar($query, 'gm2_lat');
        $lng    = self::floatQueryVar($query, 'gm2_lng');
        $radius = self::floatQueryVar($query, 'gm2_radius');

        if ($lat !== null && $lng !== null && $radius !== null && $radius > 0) {
            $latRange = $radius / 111.045;
            $lngRange = $radius / (111.045 * max(cos(deg2rad($lat)), 0.00001));

            self::appendMetaQuery($query, [
                'key'     => 'latitude',
                'value'   => [$lat - $latRange, $lat + $latRange],
                'compare' => 'BETWEEN',
                'type'    => 'DECIMAL',
            ]);

            self::appendMetaQuery($query, [
                'key'     => 'longitude',
                'value'   => [$lng - $lngRange, $lng + $lngRange],
                'compare' => 'BETWEEN',
                'type'    => 'DECIMAL',
            ]);
        }

        self::ensureOrdering($query, 'title', 'ASC');
    }

    /**
     * Nearby directory listings use a geo bounding box to limit results.
     */
    public static function handleDirectoryNearby($query, $widget = null): void
    {
        if (!$query instanceof WP_Query) {
            return;
        }

        self::ensurePostType($query, 'listing');
        self::ensureStatus($query, 'publish');
        self::ensurePagination($query, self::DEFAULT_DIRECTORY_PER_PAGE);
        self::applySearch($query, ['gm2_directory_search', 'gm2_search']);

        $lat    = self::floatQueryVar($query, 'gm2_lat');
        $lng    = self::floatQueryVar($query, 'gm2_lng');
        $radius = self::floatQueryVar($query, 'gm2_radius');

        if ($lat !== null && $lng !== null && $radius !== null && $radius > 0) {
            $latRange = $radius / 111.045;
            $lngRange = $radius / (111.045 * max(cos(deg2rad($lat)), 0.00001));

            self::appendMetaQuery($query, [
                'key'     => 'latitude',
                'value'   => [$lat - $latRange, $lat + $latRange],
                'compare' => 'BETWEEN',
                'type'    => 'DECIMAL',
            ]);

            self::appendMetaQuery($query, [
                'key'     => 'longitude',
                'value'   => [$lng - $lngRange, $lng + $lngRange],
                'compare' => 'BETWEEN',
                'type'    => 'DECIMAL',
            ]);
        }

        self::ensureOrdering($query, 'title', 'ASC');
    }

    /**
     * Directory listings filtered by taxonomy slugs.
     */
    public static function handleDirectoryByCategory($query, $widget = null): void
    {
        if (!$query instanceof WP_Query) {
            return;
        }

        self::ensurePostType($query, 'listing');
        self::ensureStatus($query, 'publish');
        self::ensurePagination($query, self::DEFAULT_DIRECTORY_PER_PAGE);
        self::applySearch($query, ['gm2_directory_search', 'gm2_search']);

        $terms = self::normaliseTaxonomyTerms($query->get('gm2_listing_category'));

        if (empty($terms)) {
            $terms = self::normaliseTaxonomyTerms($query->get('gm2_directory_category'));
        }

        if (empty($terms)) {
            $terms = self::normaliseTaxonomyTerms($query->get('listing_category'));
        }

        if (!empty($terms)) {
            self::appendTaxQuery($query, [
                'taxonomy' => 'listing_category',
                'field'    => 'slug',
                'terms'    => $terms,
            ]);
        }

        self::ensureOrdering($query, 'title', 'ASC');
    }

    /**
     * Active courses are published entries with an "active" status flag.
     */
    public static function handleActiveCourses($query, $widget = null): void
    {
        if (!$query instanceof WP_Query) {
            return;
        }

        self::ensurePostType($query, 'course');
        self::ensureStatus($query, 'publish');
        self::ensurePagination($query, self::DEFAULT_COURSES_PER_PAGE);
        self::applySearch($query, ['gm2_course_search', 'gm2_search']);

        self::appendMetaQuery($query, [
            'key'   => 'status',
            'value' => 'active',
        ]);

        self::ensureOrdering($query, 'date', 'DESC');
    }

    /**
     * Apply shared property filtering logic.
     */
    private static function preparePropertyQuery(WP_Query $query, string $statusSlug): void
    {
        self::ensurePostType($query, 'property');
        self::ensureStatus($query, 'publish');
        self::ensurePagination($query, self::DEFAULT_PROPERTIES_PER_PAGE);
        self::applySearch($query, ['gm2_property_search', 'gm2_search']);

        self::appendTaxQuery($query, [
            'taxonomy' => 'property_status',
            'field'    => 'slug',
            'terms'    => [$statusSlug],
        ]);

        self::ensureMetaOrdering($query, 'price', 'meta_value_num', 'ASC');
    }

    /**
     * Ensure a post type when one has not been explicitly set.
     */
    private static function ensurePostType(WP_Query $query, string $postType): void
    {
        $current = $query->get('post_type');
        if (empty($current) || $current === 'any') {
            $query->set('post_type', $postType);
        }
    }

    /**
     * Ensure a post status when one has not been explicitly set.
     */
    private static function ensureStatus(WP_Query $query, string $status): void
    {
        if (!$query->get('post_status')) {
            $query->set('post_status', $status);
        }
    }

    /**
     * Ensure pagination defaults to a sensible amount.
     */
    private static function ensurePagination(WP_Query $query, int $default): void
    {
        $perPage = (int) $query->get('posts_per_page');
        if ($perPage <= 0) {
            $query->set('posts_per_page', $default);
        }
    }

    /**
     * Append a meta query clause.
     */
    private static function appendMetaQuery(WP_Query $query, array $clause): void
    {
        $metaQuery = $query->get('meta_query');
        if (!is_array($metaQuery)) {
            $metaQuery = [];
        }

        $metaQuery[] = $clause;

        if (count($metaQuery) > 1 && !isset($metaQuery['relation'])) {
            $metaQuery['relation'] = 'AND';
        }

        $query->set('meta_query', $metaQuery);
    }

    /**
     * Append a taxonomy query clause.
     */
    private static function appendTaxQuery(WP_Query $query, array $clause): void
    {
        $taxQuery = $query->get('tax_query');
        if (!is_array($taxQuery)) {
            $taxQuery = [];
        }

        $taxQuery[] = $clause;

        if (count($taxQuery) > 1 && !isset($taxQuery['relation'])) {
            $taxQuery['relation'] = 'AND';
        }

        $query->set('tax_query', $taxQuery);
    }

    /**
     * Ensure ordering using a standard post field.
     */
    private static function ensureOrdering(WP_Query $query, $orderby, string $order): void
    {
        if (!$query->get('orderby')) {
            $query->set('orderby', $orderby);
        }

        if (!$query->get('order')) {
            $query->set('order', $order);
        }
    }

    /**
     * Ensure ordering using a specific meta key.
     */
    private static function ensureMetaOrdering(WP_Query $query, string $metaKey, $orderby, string $order): void
    {
        if (!$query->get('meta_key')) {
            $query->set('meta_key', $metaKey);
        }

        self::ensureOrdering($query, $orderby, $order);
    }

    /**
     * Apply a search term if the query has been given a custom key.
     */
    private static function applySearch(WP_Query $query, array $keys): void
    {
        if ($query->get('s')) {
            return;
        }

        foreach ($keys as $key) {
            $value = $query->get($key);
            if (is_string($value) && $value !== '') {
                $query->set('s', sanitize_text_field($value));
                break;
            }
        }
    }

    /**
     * Pull a float value out of the query vars.
     */
    private static function floatQueryVar(WP_Query $query, string $key): ?float
    {
        $value = $query->get($key);
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * Normalise taxonomy slug input from query vars to an array of sanitized terms.
     *
     * @param mixed $value Raw query variable value.
     */
    private static function normaliseTaxonomyTerms($value): array
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }

            $value = array_map('trim', explode(',', $value));
        }

        if (!is_array($value)) {
            return [];
        }

        $terms = [];
        foreach ($value as $term) {
            if (!is_string($term)) {
                continue;
            }

            $term = sanitize_title($term);
            if ($term !== '') {
                $terms[] = $term;
            }
        }

        return array_values(array_unique($terms));
    }
}

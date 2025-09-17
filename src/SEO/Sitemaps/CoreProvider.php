<?php

namespace Gm2\SEO\Sitemaps;

use WP_Post;
use WP_Sitemaps_Provider;
use WP_Term;

if (!defined('ABSPATH')) {
    return;
}

/**
 * Integrates GM2 content with the core XML sitemaps API introduced in WordPress 5.5.
 *
 * @see https://make.wordpress.org/core/2020/07/22/wordpress-5-5-introduces-xml-sitemaps/
 * @see https://developer.wordpress.org/reference/classes/wp_sitemaps_provider/
 */
final class CoreProvider
{
    /**
     * Tracks whether the provider has already registered its hooks.
     */
    private static bool $registered = false;

    /**
     * Bootstrap the provider by scheduling registration on {@see init}.
     */
    public static function bootstrap(): void
    {
        add_action('init', [__CLASS__, 'register'], 20);
    }

    /**
     * Register GM2 specific providers and filters with the sitemap server.
     */
    public static function register(): void
    {
        if (self::$registered || !self::isSitemapsAvailable() || !self::isEnabled()) {
            return;
        }

        self::$registered = true;

        self::registerProviders();
        self::hookFilters();
    }

    /**
     * Determine if the core sitemap server is available.
     */
    private static function isSitemapsAvailable(): bool
    {
        return function_exists('wp_sitemaps_get_server') && class_exists('\\WP_Sitemaps_Provider');
    }

    /**
     * Determine if sitemap output is enabled for the site.
     */
    private static function isEnabled(): bool
    {
        return get_option('gm2_sitemap_enabled', '1') === '1';
    }

    /**
     * Register lightweight providers to signal GM2 sitemap support to core.
     */
    private static function registerProviders(): void
    {
        $postProvider = new NullProvider('gm2-posts', 'post');
        $taxProvider  = new NullProvider('gm2-taxonomies', 'term');

        self::registerProvider('gm2-posts', $postProvider);
        self::registerProvider('gm2-taxonomies', $taxProvider);
    }

    /**
     * Register a provider with the core sitemap registry.
     */
    private static function registerProvider(string $name, WP_Sitemaps_Provider $provider): void
    {
        if (function_exists('wp_sitemaps_register_provider')) {
            wp_sitemaps_register_provider($name, $provider);
            return;
        }

        if (function_exists('wp_register_sitemap_provider')) {
            wp_register_sitemap_provider($name, $provider);
        }
    }

    /**
     * Hook core sitemap filters so GM2 content is exposed by the default providers.
     */
    private static function hookFilters(): void
    {
        add_filter('wp_sitemaps_post_types', [__CLASS__, 'filterPostTypes']);
        add_filter('wp_sitemaps_taxonomies', [__CLASS__, 'filterTaxonomies']);
        add_filter('wp_sitemaps_posts_entry', [__CLASS__, 'filterPostEntry'], 10, 3);
        add_filter('wp_sitemaps_posts_query_args', [__CLASS__, 'filterPostQueryArgs'], 10, 2);
        add_filter('wp_sitemaps_taxonomies_entry', [__CLASS__, 'filterTaxonomyEntry'], 10, 4);
        add_filter('wp_sitemaps_taxonomies_query_args', [__CLASS__, 'filterTaxonomyQueryArgs'], 10, 2);
        add_filter('wp_sitemaps_taxonomies_pre_max_num_pages', [__CLASS__, 'filterTaxonomyMaxPages'], 10, 2);
    }

    /**
     * Append GM2 post types to the default posts provider.
     *
     * @param array<string, \WP_Post_Type> $postTypes
     * @return array<string, \WP_Post_Type>
     */
    public static function filterPostTypes(array $postTypes): array
    {
        if (!self::isEnabled()) {
            return $postTypes;
        }

        foreach (self::getSupportedPostTypes() as $postType) {
            if (isset($postTypes[$postType])) {
                continue;
            }

            $object = get_post_type_object($postType);
            if (!$object || !is_post_type_viewable($object)) {
                continue;
            }

            $postTypes[$postType] = $object;
        }

        return $postTypes;
    }

    /**
     * Append GM2 taxonomies to the default taxonomy provider.
     *
     * @param array<string, \WP_Taxonomy> $taxonomies
     * @return array<string, \WP_Taxonomy>
     */
    public static function filterTaxonomies(array $taxonomies): array
    {
        if (!self::isEnabled()) {
            return $taxonomies;
        }

        foreach (self::getSupportedTaxonomies() as $taxonomy) {
            if (isset($taxonomies[$taxonomy])) {
                continue;
            }

            $object = get_taxonomy($taxonomy);
            if (!$object || !is_taxonomy_viewable($object)) {
                continue;
            }

            $taxonomies[$taxonomy] = $object;
        }

        return $taxonomies;
    }

    /**
     * Ensure sitemap queries skip unwanted statuses while remaining filterable.
     *
     * @param array  $args     Query args supplied to {@see WP_Query}.
     * @param string $postType Current post type being queried.
     * @return array
     */
    public static function filterPostQueryArgs(array $args, string $postType): array
    {
        if (!self::isEnabled() || !in_array($postType, self::getSupportedPostTypes(), true)) {
            return $args;
        }

        $statuses = isset($args['post_status']) ? (array) $args['post_status'] : ['publish'];

        if (in_array('any', $statuses, true)) {
            $statuses = get_post_stati(['internal' => false]);
        }

        /**
         * Filter the list of post statuses that should be skipped when building sitemap queries.
         *
         * @param string[] $statuses_to_skip Statuses that must be removed from the sitemap query.
         * @param string   $postType        The current post type name.
         */
        $skip = apply_filters('gm2_sitemaps_skip_statuses', ['draft', 'pending', 'future', 'private'], $postType);
        $skip = array_map('sanitize_key', array_filter((array) $skip));

        if (!empty($skip)) {
            $statuses = array_values(array_diff($statuses, $skip));
        }

        if (empty($statuses)) {
            $statuses = ['publish'];
        }

        $args['post_status'] = $statuses;

        return $args;
    }

    /**
     * Populate last modified and image data for GM2 posts.
     *
     * @param array   $entry    Sitemap entry data.
     * @param WP_Post $post     The current post object.
     * @param string  $postType Current post type name.
     * @return array
     */
    public static function filterPostEntry(array $entry, WP_Post $post, string $postType): array
    {
        if (!self::isEnabled() || !in_array($postType, self::getSupportedPostTypes(), true)) {
            return $entry;
        }

        $entry['lastmod'] = get_post_modified_time('c', true, $post);

        $image = get_the_post_thumbnail_url($post, 'full');
        if ($image) {
            $imageEntry = [
                'loc' => $image,
            ];

            $caption = self::resolveAddress($post->ID);
            if ($caption !== '') {
                $imageEntry['caption'] = $caption;
                $imageEntry['title']   = $caption;
            }

            $entry['images']   = isset($entry['images']) && is_array($entry['images']) ? $entry['images'] : [];
            $entry['images'][] = $imageEntry;
        }

        return $entry;
    }

    /**
     * Populate last modified data for GM2 terms and provide a hook for term images.
     *
     * @param array        $entry    Sitemap entry data.
     * @param int          $termId   Term ID.
     * @param string       $taxonomy Taxonomy name.
     * @param WP_Term|null $term     Optional term object (added in WordPress 6.0).
     * @return array
     */
    public static function filterTaxonomyEntry(array $entry, int $termId, string $taxonomy, ?WP_Term $term = null): array
    {
        if (!self::isEnabled() || !in_array($taxonomy, self::getSupportedTaxonomies(), true)) {
            return $entry;
        }

        $lastModified = self::getTermLastModified($termId, $taxonomy);
        if ($lastModified !== '') {
            $entry['lastmod'] = $lastModified;
        }

        $image = self::getTermImageUrl($termId);
        if ($image) {
            $entry['images']   = isset($entry['images']) && is_array($entry['images']) ? $entry['images'] : [];
            $entry['images'][] = ['loc' => $image];
        }

        return $entry;
    }

    /**
     * Adjust taxonomy queries when large term sets need additional pagination.
     *
     * @param array  $args     Query args supplied to {@see WP_Term_Query}.
     * @param string $taxonomy The taxonomy name currently being queried.
     * @return array
     */
    public static function filterTaxonomyQueryArgs(array $args, string $taxonomy): array
    {
        if (!self::isEnabled()) {
            return $args;
        }

        $map = self::getTaxonomySplitSizes();
        if (!isset($map[$taxonomy])) {
            return $args;
        }

        $chunk = $map[$taxonomy];
        if ($chunk > 0) {
            $args['number'] = min($chunk, (int) wp_sitemaps_get_max_urls('term'));
        }

        return $args;
    }

    /**
     * Ensure taxonomy pagination honours the configured split sizes.
     *
     * @param int|null $maxPages Existing value supplied by core.
     * @param string   $taxonomy Taxonomy name.
     * @return int|null
     */
    public static function filterTaxonomyMaxPages($maxPages, string $taxonomy)
    {
        if (!self::isEnabled()) {
            return $maxPages;
        }

        $map = self::getTaxonomySplitSizes();
        if (!isset($map[$taxonomy]) || $map[$taxonomy] <= 0) {
            return $maxPages;
        }

        $chunkSize = (int) $map[$taxonomy];

        $count = wp_count_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
        ]);

        if (is_wp_error($count)) {
            return $maxPages;
        }

        return (int) ceil((int) $count / max(1, $chunkSize));
    }

    /**
     * Retrieve the GM2-supported post types.
     *
     * @return string[]
     */
    private static function getSupportedPostTypes(): array
    {
        $args  = [
            'public'              => true,
            'show_ui'             => true,
            'exclude_from_search' => false,
        ];
        $types = get_post_types($args, 'names');
        unset($types['attachment']);

        /**
         * Filter the list of GM2 supported post types.
         *
         * @param string[] $postTypes List of supported post type names.
         */
        $types = apply_filters('gm2_supported_post_types', array_values($types));

        return array_values(array_unique(array_map('sanitize_key', (array) $types)));
    }

    /**
     * Retrieve the GM2-supported taxonomies.
     *
     * @return string[]
     */
    private static function getSupportedTaxonomies(): array
    {
        $taxonomies = ['category'];
        if (taxonomy_exists('product_cat')) {
            $taxonomies[] = 'product_cat';
        }
        if (taxonomy_exists('brand')) {
            $taxonomies[] = 'brand';
        }
        if (taxonomy_exists('product_brand')) {
            $taxonomies[] = 'product_brand';
        }

        /**
         * Filter the list of GM2 supported taxonomies.
         *
         * @param string[] $taxonomies List of supported taxonomy names.
         */
        $taxonomies = apply_filters('gm2_supported_taxonomies', $taxonomies);

        return array_values(array_unique(array_map('sanitize_key', (array) $taxonomies)));
    }

    /**
     * Resolve an address string for the given post using existing helpers.
     */
    private static function resolveAddress(int $postId): string
    {
        $value = \gm2_field('address', '', $postId);

        if (is_array($value)) {
            if (isset($value['address']) && is_array($value['address']) && class_exists('\\GM2_Field_Geospatial')) {
                $formatted = \GM2_Field_Geospatial::format_address($value['address']);
                if ($formatted !== '') {
                    return $formatted;
                }
            }

            $parts = array_filter(array_map('trim', $value));
            if (!empty($parts)) {
                return implode(', ', $parts);
            }
        }

        $parts = [];
        foreach (['address', 'city', 'region', 'state', 'province', 'postal_code', 'zip', 'country'] as $key) {
            $field = \gm2_field($key, '', $postId);
            if (is_string($field) && trim($field) !== '') {
                $parts[] = trim($field);
            }
        }

        $parts = array_values(array_unique(array_filter($parts)));

        return $parts ? implode(', ', $parts) : '';
    }

    /**
     * Retrieve a formatted last modified time for the supplied term.
     */
    private static function getTermLastModified(int $termId, string $taxonomy): string
    {
        $metaKeys = ['gm2_last_modified', '_gm2_last_modified', 'gm2_updated_at', '_gm2_updated_at'];
        foreach ($metaKeys as $key) {
            $value = get_term_meta($termId, $key, true);
            if (is_string($value) && $value !== '') {
                $timestamp = strtotime($value);
                if ($timestamp) {
                    return gmdate('c', $timestamp);
                }
            }
        }

        return '';
    }

    /**
     * Attempt to locate a representative image URL for the supplied term.
     */
    private static function getTermImageUrl(int $termId): string
    {
        $image = \gm2_field('image', '', $termId, 'term');
        if (is_numeric($image)) {
            $src = wp_get_attachment_url((int) $image);
            if (is_string($src)) {
                return $src;
            }
        }

        if (is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        $thumbnailId = get_term_meta($termId, '_thumbnail_id', true);
        if (is_numeric($thumbnailId)) {
            $src = wp_get_attachment_url((int) $thumbnailId);
            if (is_string($src)) {
                return $src;
            }
        }

        return '';
    }

    /**
     * Retrieve sanitized taxonomy split sizes configured via {@see gm2_sitemaps_split_taxonomies}.
     *
     * @return array<string, int>
     */
    private static function getTaxonomySplitSizes(): array
    {
        /**
         * Filter the number of taxonomy terms displayed per sitemap page.
         *
         * Returning an associative array keyed by taxonomy name allows large datasets to be split
         * across multiple sitemap pages.
         *
         * @param array<string,int> $splits Mapping of taxonomy name to the desired chunk size.
         */
        $map = apply_filters('gm2_sitemaps_split_taxonomies', []);
        if (!is_array($map)) {
            return [];
        }

        $sanitized = [];
        foreach ($map as $taxonomy => $size) {
            $taxonomy = sanitize_key((string) $taxonomy);
            $size     = (int) $size;
            if ($taxonomy !== '' && $size > 0) {
                $sanitized[$taxonomy] = $size;
            }
        }

        return $sanitized;
    }
}

/**
 * Minimal provider used to announce GM2 sitemap participation to the core registry.
 */
final class NullProvider extends WP_Sitemaps_Provider
{
    public function __construct(string $name, string $objectType)
    {
        $this->name        = $name;
        $this->object_type = $objectType;
    }

    /** @inheritDoc */
    public function get_url_list($page_num, $object_subtype = ''): array
    {
        return [];
    }

    /** @inheritDoc */
    public function get_max_num_pages($object_subtype = ''): int
    {
        return 0;
    }

    /** @inheritDoc */
    public function get_object_subtypes(): array
    {
        return [];
    }
}

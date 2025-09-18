<?php

declare(strict_types=1);

namespace Gm2\Performance;

use WP_Query;

use function add_action;
use function add_filter;
use function apply_filters;
use function array_keys;
use function array_unique;
use function array_values;
use function delete_transient;
use function get_post;
use function get_post_type;
use function get_transient;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_object;
use function sanitize_key;
use function sanitize_text_field;
use function set_transient;
use function wp_cache_delete;
use function wp_cache_get;
use function wp_cache_set;
use function wp_is_post_autosave;
use function wp_is_post_revision;
use function wp_json_encode;

/**
 * Provides deterministic query caching backed by the WordPress object cache.
 *
 * Consumers can opt-out of caching by filtering {@see QueryCache::BYPASS_FILTER}:
 *
 * <code>
 * add_filter(
 *     'gm2_query_cache_bypass',
 *     static fn (bool $bypass, array $args, array $context): bool => true,
 *     10,
 *     3
 * );
 * </code>
 */
class QueryCache
{
    public const GROUP        = 'gm2_query_cache';
    private const INDEX_GROUP = 'gm2_query_cache_index';
    private const TRANSIENT_PREFIX = 'gm2_qc_';
    private const INDEX_TTL   = DAY_IN_SECONDS;
    private const DEFAULT_EXPIRATION = 10 * MINUTE_IN_SECONDS;
    public const BYPASS_FILTER = 'gm2_query_cache_bypass';

    private static bool $bootstrapped = false;

    /**
     * Bootstraps cache filters and invalidation listeners.
     */
    public static function init(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        self::$bootstrapped = true;

        add_filter('posts_pre_query', [self::class, 'maybeServeFromCache'], 10, 2);
        add_filter('the_posts', [self::class, 'maybePrimeCache'], 10, 2);

        add_action('save_post', [self::class, 'handlePostChange'], 10, 3);
        add_action('deleted_post', [self::class, 'handlePostDeletion']);
        add_action('trashed_post', [self::class, 'handlePostDeletion']);

        add_action('set_object_terms', [self::class, 'handleSetObjectTerms'], 10, 6);
        add_action('created_term', [self::class, 'handleTermChange'], 10, 3);
        add_action('edited_term', [self::class, 'handleTermChange'], 10, 3);
        add_action('delete_term', [self::class, 'handleTermDeletion'], 10, 5);
    }

    /**
     * Attach caching metadata to a query arguments array.
     */
    public static function configureArgs(array $args, array $context = [], ?int $expiration = null): array
    {
        self::init();
        unset($args['gm2_query_cache']);

        $config = self::buildConfig($args, $context, $expiration);
        if ($config !== null) {
            $args['gm2_query_cache'] = $config;
        }

        return $args;
    }

    /**
     * Attach caching metadata to a {@see WP_Query} instance.
     */
    public static function prepareQuery(WP_Query $query, array $context = [], ?int $expiration = null): void
    {
        self::init();
        $vars = $query->query_vars;
        unset($vars['gm2_query_cache']);

        $config = self::buildConfig($vars, $context, $expiration);
        if ($config !== null) {
            $query->set('gm2_query_cache', $config);
        } else {
            $query->set('gm2_query_cache', null);
        }
        $query->set('gm2_query_cache_hit', false);
    }

    /**
     * Retrieve a cached payload by key.
     */
    public static function get(string $key): ?array
    {
        $payload = wp_cache_get($key, self::GROUP);
        if ($payload === false) {
            if (self::useTransients()) {
                $payload = get_transient(self::TRANSIENT_PREFIX . $key);
            }
        }

        return is_array($payload) ? $payload : null;
    }

    /**
     * Invalidate caches for the supplied post type.
     */
    public static function invalidatePostType(string $postType): void
    {
        $postType = sanitize_key($postType);
        if ($postType === '') {
            return;
        }

        self::invalidateTags(['post_type:' . $postType, 'post_type:any']);
    }

    /**
     * Invalidate caches for the supplied taxonomy.
     */
    public static function invalidateTaxonomy(string $taxonomy): void
    {
        $taxonomy = sanitize_key($taxonomy);
        if ($taxonomy === '') {
            return;
        }

        self::invalidateTags(['taxonomy:' . $taxonomy]);
    }

    /**
     * Filter callback invoked prior to running a query.
     */
    public static function maybeServeFromCache($posts, WP_Query $query)
    {
        $config = self::getConfig($query);
        if ($config === null) {
            return $posts;
        }

        if (apply_filters(self::BYPASS_FILTER, false, $query->query_vars, $config['context'])) {
            return $posts;
        }

        $cached = self::get($config['key']);
        if ($cached === null) {
            return null;
        }

        if (!isset($cached['posts']) || !is_array($cached['posts'])) {
            return null;
        }

        $query->posts       = $cached['posts'];
        $query->post_count  = (int) ($cached['post_count'] ?? count($cached['posts']));
        $query->found_posts = (int) ($cached['found_posts'] ?? $query->post_count);
        $query->max_num_pages = (int) ($cached['max_num_pages'] ?? 0);
        $query->set('gm2_query_cache_hit', true);

        return $cached['posts'];
    }

    /**
     * Filter callback invoked after a query has executed.
     */
    public static function maybePrimeCache(array $posts, WP_Query $query): array
    {
        $config = self::getConfig($query);
        if ($config === null) {
            return $posts;
        }

        if ($query->get('gm2_query_cache_hit')) {
            return $posts;
        }

        if (apply_filters(self::BYPASS_FILTER, false, $query->query_vars, $config['context'])) {
            return $posts;
        }

        $payload = [
            'posts'         => $posts,
            'post_count'    => (int) $query->post_count,
            'found_posts'   => (int) $query->found_posts,
            'max_num_pages' => (int) $query->max_num_pages,
            'tags'          => $config['tags'],
        ];

        self::store($config['key'], $payload, $config['tags'], $config['expiration']);

        return $posts;
    }

    /**
     * Handles cache invalidation on post save operations.
     */
    public static function handlePostChange(int $postId, $post, bool $update): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        $postType = $post->post_type ?? get_post_type($postId);
        if ($postType) {
            self::invalidatePostType($postType);
        }
    }

    /**
     * Handles cache invalidation when a post is deleted or trashed.
     */
    public static function handlePostDeletion(int $postId): void
    {
        $post = get_post($postId);
        $postType = $post && isset($post->post_type) ? $post->post_type : get_post_type($postId);
        if ($postType) {
            self::invalidatePostType($postType);
        }
    }

    /**
     * Handles cache invalidation when object terms change.
     */
    public static function handleSetObjectTerms($objectId, $terms, $ttIds, $taxonomy, $append, $oldTtIds): void
    {
        $taxonomy = sanitize_key((string) $taxonomy);
        if ($taxonomy !== '') {
            self::invalidateTaxonomy($taxonomy);
        }

        $postType = get_post_type((int) $objectId);
        if ($postType) {
            self::invalidatePostType($postType);
        }
    }

    /**
     * Handles cache invalidation on term create/edit operations.
     */
    public static function handleTermChange($termId, $termTaxonomyId, $taxonomy): void
    {
        $taxonomy = sanitize_key((string) $taxonomy);
        if ($taxonomy === '') {
            return;
        }

        self::invalidateTaxonomy($taxonomy);
    }

    /**
     * Handles cache invalidation when a term is deleted.
     */
    public static function handleTermDeletion($term, $ttId, $taxonomy, $deletedTerm = null, $objectIds = null): void
    {
        $taxonomy = sanitize_key((string) $taxonomy);
        if ($taxonomy === '') {
            return;
        }

        self::invalidateTaxonomy($taxonomy);
    }

    private static function buildConfig(array $args, array $context, ?int $expiration): ?array
    {
        $context = self::normaliseValue($context);

        if (apply_filters(self::BYPASS_FILTER, false, $args, $context)) {
            return null;
        }

        if ($expiration === null) {
            $expiration = (int) apply_filters('gm2_query_cache_expiration', self::DEFAULT_EXPIRATION, $args, $context);
        }

        $key  = self::generateKey($args, $context);
        $tags = self::deriveTags($args, $context);

        return [
            'key'        => $key,
            'context'    => $context,
            'tags'       => $tags,
            'expiration' => max(0, (int) $expiration),
        ];
    }

    /**
     * Generate a deterministic cache key for the given arguments and context.
     */
    public static function generateKey(array $args, array $context = []): string
    {
        $data = [
            'args'    => self::normaliseValue($args),
            'context' => self::normaliseValue($context),
        ];

        return md5(wp_json_encode($data));
    }

    private static function deriveTags(array $args, array $context): array
    {
        $tags = [];

        if (isset($args['post_type'])) {
            $postTypes = $args['post_type'];
            if (!is_array($postTypes)) {
                $postTypes = [$postTypes];
            }

            if (empty($postTypes)) {
                $postTypes = ['any'];
            }

            foreach ($postTypes as $type) {
                $type = sanitize_key((string) $type);
                if ($type === '') {
                    continue;
                }
                $tags[] = 'post_type:' . $type;
            }
        } else {
            $tags[] = 'post_type:any';
        }

        if (isset($args['tax_query']) && is_array($args['tax_query'])) {
            foreach ($args['tax_query'] as $clause) {
                if (!is_array($clause)) {
                    continue;
                }
                $taxonomy = $clause['taxonomy'] ?? '';
                $taxonomy = sanitize_key((string) $taxonomy);
                if ($taxonomy === '') {
                    continue;
                }
                $tags[] = 'taxonomy:' . $taxonomy;
            }
        }

        return array_values(array_unique($tags));
    }

    private static function getConfig(WP_Query $query): ?array
    {
        $config = $query->get('gm2_query_cache');
        if (!is_array($config) || empty($config['key'])) {
            return null;
        }

        return $config;
    }

    private static function store(string $key, array $payload, array $tags, int $expiration): void
    {
        wp_cache_set($key, $payload, self::GROUP, $expiration);

        if (self::useTransients()) {
            set_transient(self::TRANSIENT_PREFIX . $key, $payload, $expiration);
        }

        self::indexKey($key, $tags);
    }

    private static function indexKey(string $key, array $tags): void
    {
        foreach (array_unique($tags) as $tag) {
            if ($tag === '') {
                continue;
            }

            $index = self::getIndex($tag);
            $index[$key] = true;
            self::persistIndex($tag, $index);
        }
    }

    private static function invalidateTags(array $tags): void
    {
        foreach (array_unique($tags) as $tag) {
            if ($tag === '') {
                continue;
            }

            $index = self::getIndex($tag);
            if (empty($index)) {
                continue;
            }

            foreach (array_keys($index) as $key) {
                $payload = self::get($key);
                $entryTags = is_array($payload) ? ($payload['tags'] ?? []) : [];
                self::deleteEntry($key, $entryTags);
            }

            self::persistIndex($tag, []);
        }
    }

    private static function deleteEntry(string $key, array $tags = []): void
    {
        wp_cache_delete($key, self::GROUP);

        if (self::useTransients()) {
            delete_transient(self::TRANSIENT_PREFIX . $key);
        }

        foreach (array_unique($tags) as $tag) {
            if ($tag === '') {
                continue;
            }
            $index = self::getIndex($tag);
            if (isset($index[$key])) {
                unset($index[$key]);
                self::persistIndex($tag, $index);
            }
        }
    }

    private static function getIndex(string $tag): array
    {
        $index = wp_cache_get($tag, self::INDEX_GROUP);
        if ($index === false) {
            $index = [];
            if (self::useTransients()) {
                $stored = get_transient(self::indexTransientKey($tag));
                if (is_array($stored)) {
                    $index = $stored;
                }
            }
        }

        return is_array($index) ? $index : [];
    }

    private static function persistIndex(string $tag, array $index): void
    {
        if (empty($index)) {
            wp_cache_delete($tag, self::INDEX_GROUP);
            if (self::useTransients()) {
                delete_transient(self::indexTransientKey($tag));
            }
            return;
        }

        wp_cache_set($tag, $index, self::INDEX_GROUP, self::INDEX_TTL);
        if (self::useTransients()) {
            set_transient(self::indexTransientKey($tag), $index, self::INDEX_TTL);
        }
    }

    private static function indexTransientKey(string $tag): string
    {
        return self::TRANSIENT_PREFIX . 'idx_' . md5($tag);
    }

    private static function normaliseValue($value)
    {
        if (is_object($value)) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            $normalised = [];
            foreach ($value as $key => $data) {
                $key = is_string($key) ? sanitize_key($key) : $key;
                $normalised[$key] = self::normaliseValue($data);
            }

            if (self::isAssociative($normalised)) {
                ksort($normalised);
            } else {
                $normalised = array_values($normalised);
            }

            return $normalised;
        }

        if (is_bool($value) || $value === null) {
            return $value;
        }

        if (is_numeric($value)) {
            return 0 + $value;
        }

        return sanitize_text_field((string) $value);
    }

    private static function isAssociative(array $value): bool
    {
        $keys = array_keys($value);
        return $keys !== array_keys($keys);
    }

    private static function useTransients(): bool
    {
        return (bool) apply_filters('gm2_query_cache_use_transients', false);
    }
}

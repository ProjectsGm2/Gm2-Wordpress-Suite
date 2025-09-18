<?php

declare(strict_types=1);

namespace Gm2\Performance;

use function add_filter;
use function get_option;

/**
 * Bridges admin toggles with the deterministic query cache.
 */
class QueryCacheManager
{
    public const OPTION_ENABLED = 'gm2_perf_query_cache_enabled';
    public const OPTION_FORCE_TRANSIENTS = 'gm2_perf_query_cache_use_transients';

    public static function init(): void
    {
        add_filter(QueryCache::BYPASS_FILTER, [__CLASS__, 'maybe_disable'], 5);
        add_filter('gm2_query_cache_use_transients', [__CLASS__, 'maybe_force_transients'], 5);
    }

    public static function maybe_disable(bool $bypass): bool
    {
        if (!self::is_enabled()) {
            return true;
        }

        return $bypass;
    }

    public static function maybe_force_transients(bool $useTransients): bool
    {
        if (self::force_transients()) {
            return true;
        }

        return $useTransients;
    }

    public static function is_enabled(): bool
    {
        return get_option(self::OPTION_ENABLED, '1') === '1';
    }

    public static function force_transients(): bool
    {
        return get_option(self::OPTION_FORCE_TRANSIENTS, '0') === '1';
    }
}

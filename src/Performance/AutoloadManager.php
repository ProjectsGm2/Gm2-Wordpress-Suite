<?php

declare(strict_types=1);

namespace Gm2\Performance;

use function apply_filters;

/**
 * Centralises autoload defaults for plugin options.
 */
class AutoloadManager
{
    private const FILTER = 'gm2_performance_autoload_disabled_options';

    /**
     * Options that should default to autoload = 'no'.
     *
     * @var array<int, string>
     */
    private const NO_AUTOLOAD = [
        'ae_css_settings',
        'ae_js_compat_overrides',
        'ae_js_dequeue_allowlist',
        'ae_js_dequeue_denylist',
        'ae_seo_ro_critical_css_exclusions',
        'ae_seo_ro_critical_css_map',
        'gm2_content_rules',
        'gm2_description_templates',
        'gm2_guideline_rules',
        'gm2_remote_mirror_custom_urls',
        'gm2_remote_mirror_vendors',
        'gm2_script_attributes',
        'gm2_title_templates',
        'gm2_defer_js_overrides',
    ];

    /**
     * Determine the preferred autoload flag for an option.
     */
    public static function get_autoload_flag(string $option): string
    {
        return self::should_disable_autoload($option) ? 'no' : 'yes';
    }

    /**
     * Retrieve the managed options that should disable autoloading.
     *
     * @return array<int, string>
     */
    public static function get_no_autoload_options(): array
    {
        $options = apply_filters(self::FILTER, self::NO_AUTOLOAD);
        $options = is_array($options) ? $options : self::NO_AUTOLOAD;
        $options = array_filter($options, static fn ($value) => is_string($value) && $value !== '');

        return array_values(array_unique($options));
    }

    private static function should_disable_autoload(string $option): bool
    {
        return in_array($option, self::get_no_autoload_options(), true);
    }
}

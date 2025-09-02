<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple request-level diagnostics logger for optimizer classes.
 */
class AE_SEO_Optimizer_Diagnostics {
    /**
     * Stored logs grouped by optimizer type.
     *
     * @var array
     */
    private static $logs = [];

    /**
     * Add a diagnostic entry.
     *
     * @param string $type  Optimizer type.
     * @param array  $entry Data with handle, bundle and reason.
     * @return void
     */
    public static function add(string $type, array $entry): void {
        $logs = self::get();
        if (!isset($logs[$type])) {
            $logs[$type] = [];
        }
        $logs[$type][] = $entry;
        self::$logs = $logs;
        set_transient('ae_seo_optimizer_diagnostics', $logs, 0);
    }

    /**
     * Retrieve all logs for the current request.
     *
     * @return array
     */
    public static function get(): array {
        if (empty(self::$logs)) {
            $stored = get_transient('ae_seo_optimizer_diagnostics');
            self::$logs = is_array($stored) ? $stored : [];
        }
        return self::$logs;
    }

    /**
     * Clear stored logs.
     *
     * @return void
     */
    public static function clear(): void {
        self::$logs = [];
        delete_transient('ae_seo_optimizer_diagnostics');
    }

    /**
     * Map a reason code to a human readable, translatable label.
     *
     * @param string $code Reason code stored in diagnostics.
     * @return string Translated label for display.
     */
    public static function reason_label(string $code): string {
        $map = [
            'request_excluded'   => __('Request excluded', 'gm2-wordpress-suite'),
            'feed_or_404'        => __('Skipped on feed or 404', 'gm2-wordpress-suite'),
            'pattern'            => __('Matched exclusion pattern', 'gm2-wordpress-suite'),
            'denylist'           => __('Handle on denylist', 'gm2-wordpress-suite'),
            'preload_or_noasync' => __('Already preloaded or opted out', 'gm2-wordpress-suite'),
            'processed'          => __('Processed asynchronously', 'gm2-wordpress-suite'),
            'feature_disabled'   => __('Feature disabled', 'gm2-wordpress-suite'),
            'module'             => __('Module or nomodule script', 'gm2-wordpress-suite'),
            'deny_domain'        => __('Domain on denylist', 'gm2-wordpress-suite'),
            'allow_domain'       => __('Domain allowlisted', 'gm2-wordpress-suite'),
            'respect_footer'     => __('Respecting footer placement', 'gm2-wordpress-suite'),
            'not_allowlisted'    => __('Not on allowlist', 'gm2-wordpress-suite'),
            'existing_attribute' => __('Existing async/defer attribute', 'gm2-wordpress-suite'),
            'async'              => __('Added async attribute', 'gm2-wordpress-suite'),
            'defer'              => __('Added defer attribute', 'gm2-wordpress-suite'),
            'blocking'           => __('Left blocking', 'gm2-wordpress-suite'),
            'none'               => __('No attribute applied', 'gm2-wordpress-suite'),
            'not_registered'     => __('Handle not registered', 'gm2-wordpress-suite'),
            'excluded'           => __('Explicitly excluded', 'gm2-wordpress-suite'),
            'integrity'          => __('Has integrity or crossorigin', 'gm2-wordpress-suite'),
            'missing'            => __('File missing', 'gm2-wordpress-suite'),
            'filesize'           => __('Could not determine file size', 'gm2-wordpress-suite'),
            'file_limit'         => __('File exceeds size limit', 'gm2-wordpress-suite'),
            'bundle_cap'         => __('Bundle size cap reached', 'gm2-wordpress-suite'),
            'external'           => __('External file not combined', 'gm2-wordpress-suite'),
            'not_enough_files'   => __('Not enough files to combine', 'gm2-wordpress-suite'),
            'build_failed'       => __('Failed to build combined file', 'gm2-wordpress-suite'),
            'combined'           => __('Combined into bundle', 'gm2-wordpress-suite'),
            'group_mismatch'     => __('Script group mismatch', 'gm2-wordpress-suite'),
        ];

        return $map[$code] ?? $code;
    }
}

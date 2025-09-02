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
}

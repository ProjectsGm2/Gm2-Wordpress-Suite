<?php

declare(strict_types=1);

namespace Gm2\Performance;

use wpdb;

use function absint;
use function array_map;
use function is_array;

/**
 * Provides aggregated metrics for autoloaded WordPress options.
 */
class AutoloadInspector
{
    /**
     * Retrieve counts and total sizes grouped by autoload flag.
     *
     * @return array<string, array<string, int>>
     */
    public static function get_totals(): array
    {
        global $wpdb;

        $table  = self::get_options_table($wpdb);
        $sql    = "SELECT autoload, COUNT(*) AS count, SUM(LENGTH(option_value)) AS bytes FROM {$table} GROUP BY autoload";
        $rows   = $wpdb->get_results($sql, ARRAY_A);
        $totals = [
            'yes'         => [ 'count' => 0, 'bytes' => 0 ],
            'no'          => [ 'count' => 0, 'bytes' => 0 ],
            'total_bytes' => 0,
        ];

        foreach ((array) $rows as $row) {
            $autoload = ($row['autoload'] ?? '') === 'yes' ? 'yes' : 'no';
            $count    = absint($row['count'] ?? 0);
            $bytes    = absint($row['bytes'] ?? 0);

            $totals[$autoload]['count'] = $count;
            $totals[$autoload]['bytes'] = $bytes;
            $totals['total_bytes']     += $bytes;
        }

        return $totals;
    }

    /**
     * Locate autoloaded options whose payload exceeds the provided threshold.
     *
     * @param int    $threshold Minimum number of bytes.
     * @param int    $limit     Maximum rows to return.
     * @param string $autoload  Autoload flag to inspect.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_heavy_options(int $threshold = 50000, int $limit = 20, string $autoload = 'yes'): array
    {
        global $wpdb;

        $threshold = max(0, $threshold);
        $limit     = max(1, $limit);
        $autoload  = $autoload === 'no' ? 'no' : 'yes';

        $table = self::get_options_table($wpdb);
        $sql   = $wpdb->prepare(
            "SELECT option_name, autoload, LENGTH(option_value) AS bytes
             FROM {$table}
             WHERE autoload = %s
               AND LENGTH(option_value) >= %d
             ORDER BY bytes DESC
             LIMIT %d",
            $autoload,
            $threshold,
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(
            static function (array $row): array {
                return [
                    'option_name' => (string) ($row['option_name'] ?? ''),
                    'autoload'    => (string) ($row['autoload'] ?? ''),
                    'bytes'       => absint($row['bytes'] ?? 0),
                ];
            },
            $rows
        );
    }

    /**
     * Convert a byte count to a human readable string.
     */
    public static function format_bytes(int $bytes): string
    {
        $bytes = max(0, $bytes);
        if ($bytes >= 1048576) {
            return sprintf('%.2f MB', $bytes / 1048576);
        }
        if ($bytes >= 1024) {
            return sprintf('%.2f KB', $bytes / 1024);
        }

        return sprintf('%d B', $bytes);
    }

    private static function get_options_table(wpdb $wpdb): string
    {
        return $wpdb->options;
    }
}

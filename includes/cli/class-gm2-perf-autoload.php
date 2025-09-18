<?php
namespace Gm2;

use Gm2\Performance\AutoloadInspector;
use Gm2\Performance\AutoloadManager;

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * Inspect autoloaded wp_options payloads.
 */
class Gm2_Perf_Autoload_CLI extends \WP_CLI_Command
{
    private const DEFAULT_THRESHOLD = 50000;
    private const DEFAULT_LIMIT = 20;

    /**
     * List options whose payload exceeds the configured threshold.
     *
     * ## OPTIONS
     *
     * [--threshold=<bytes>]
     * : Minimum payload size in bytes. Defaults to 50000 (≈50 KB).
     *
     * [--limit=<number>]
     * : Maximum number of options to list. Defaults to 20.
     *
     * [--autoload=<flag>]
     * : Autoload flag to inspect (yes or no). Defaults to yes.
     *
     * [--format=<format>]
     * : Render output in a particular format. See WP-CLI docs for available formats.
     *
     * ## EXAMPLES
     *
     *     wp gm2 perf autoload --threshold=75000 --limit=5
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args): void
    {
        $threshold = isset($assoc_args['threshold']) ? (int) $assoc_args['threshold'] : self::DEFAULT_THRESHOLD;
        $limit     = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : self::DEFAULT_LIMIT;
        $autoload  = isset($assoc_args['autoload']) ? strtolower((string) $assoc_args['autoload']) : 'yes';

        $threshold = max(0, $threshold);
        $limit     = max(1, $limit);
        $autoload  = $autoload === 'no' ? 'no' : 'yes';

        $rows = AutoloadInspector::get_heavy_options($threshold, $limit, $autoload);
        if (empty($rows)) {
            \WP_CLI::success(sprintf(
                /* translators: 1: autoload flag, 2: size threshold in bytes. */
                __('No %1$s options exceed %2$d bytes.', 'gm2-wordpress-suite'),
                $autoload === 'no' ? __('non-autoloaded', 'gm2-wordpress-suite') : __('autoloaded', 'gm2-wordpress-suite'),
                $threshold
            ));
            return;
        }

        $rows = array_map(
            static fn (array $row): array => [
                'option_name' => (string) ($row['option_name'] ?? ''),
                'autoload'    => (string) ($row['autoload'] ?? ''),
                'bytes'       => (int) ($row['bytes'] ?? 0),
                'size'        => AutoloadInspector::format_bytes((int) ($row['bytes'] ?? 0)),
            ],
            $rows
        );

        $formatter = new \WP_CLI\Formatter($assoc_args, ['option_name', 'autoload', 'bytes', 'size']);
        $formatter->display_items($rows);
    }

    /**
     * Display aggregate counts and payload sizes grouped by autoload flag.
     *
     * ## EXAMPLES
     *
     *     wp gm2 perf autoload totals
     *
     * @subcommand totals
     * @when after_wp_load
     */
    public function totals($args, $assoc_args): void
    {
        $totals = AutoloadInspector::get_totals();
        $rows   = [
            [
                'autoload' => 'yes',
                'count'    => (int) ($totals['yes']['count'] ?? 0),
                'bytes'    => (int) ($totals['yes']['bytes'] ?? 0),
                'size'     => AutoloadInspector::format_bytes((int) ($totals['yes']['bytes'] ?? 0)),
            ],
            [
                'autoload' => 'no',
                'count'    => (int) ($totals['no']['count'] ?? 0),
                'bytes'    => (int) ($totals['no']['bytes'] ?? 0),
                'size'     => AutoloadInspector::format_bytes((int) ($totals['no']['bytes'] ?? 0)),
            ],
        ];

        $formatter = new \WP_CLI\Formatter($assoc_args, ['autoload', 'count', 'bytes', 'size']);
        $formatter->display_items($rows);

        $totalBytes = (int) ($totals['total_bytes'] ?? 0);
        \WP_CLI::line(sprintf(
            /* translators: %s: formatted number of bytes. */
            __('Total option payload: %s.', 'gm2-wordpress-suite'),
            AutoloadInspector::format_bytes($totalBytes)
        ));

        if ($totalBytes >= 800000) {
            \WP_CLI::warning(__('Autoloaded options exceed 800 KB. Consider moving large payloads to autoload = "no".', 'gm2-wordpress-suite'));
        } elseif ($totalBytes >= 500000) {
            \WP_CLI::warning(__('Autoloaded options exceed 500 KB. Review heavy rows to avoid cache misses.', 'gm2-wordpress-suite'));
        }
    }

    /**
     * List options that default to autoload = "no" via the manager.
     *
     * ## EXAMPLES
     *
     *     wp gm2 perf autoload managed
     *
     * @subcommand managed
     * @when after_wp_load
     */
    public function managed($args, $assoc_args): void
    {
        $options = AutoloadManager::get_no_autoload_options();
        if (empty($options)) {
            \WP_CLI::warning(__('No managed autoload exclusions are registered.', 'gm2-wordpress-suite'));
            return;
        }

        foreach ($options as $option) {
            \WP_CLI::line($option);
        }
    }
}

\WP_CLI::add_command('gm2 perf autoload', __NAMESPACE__ . '\\Gm2_Perf_Autoload_CLI');

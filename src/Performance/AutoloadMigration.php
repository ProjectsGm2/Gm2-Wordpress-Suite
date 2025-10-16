<?php

declare(strict_types=1);

namespace Gm2\Performance;

use wpdb;

use function add_action;
use function array_fill;
use function count;
use function get_option;
use function implode;
use function update_option;
use function wp_cache_delete;

/**
 * Updates existing options to use the preferred autoload flags.
 */
class AutoloadMigration
{
    private const OPTION = 'gm2_autoload_migration_version';
    private const VERSION = 1;

    public static function init(): void
    {
        add_action('plugins_loaded', [__CLASS__, 'maybe_run'], 5);
    }

    public static function maybe_run(): void
    {
        $version = (int) get_option(self::OPTION, 0);
        if ($version >= self::VERSION) {
            return;
        }

        self::run_migration();

        update_option(self::OPTION, self::VERSION, false);
    }

    public static function run_migration(): void
    {
        global $wpdb;

        $options = AutoloadManager::get_no_autoload_options();
        if (empty($options)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($options), '%s'));
        $table        = self::get_options_table($wpdb);
        $sql          = $wpdb->prepare(
            "UPDATE {$table} SET autoload = 'no' WHERE option_name IN ({$placeholders}) AND autoload <> 'no'",
            ...$options
        );

        $updated = (int) $wpdb->query($sql);
        if ($updated > 0) {
            self::clear_autoload_cache();
        }
    }

    private static function get_options_table(wpdb $wpdb): string
    {
        return $wpdb->options;
    }

    private static function clear_autoload_cache(): void
    {
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
    }
}

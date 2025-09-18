<?php

declare(strict_types=1);

use Gm2\Performance\AutoloadMigration;
use ReflectionClass;

class AutoloadMigrationTest extends WP_UnitTestCase
{
    /**
     * @var array<int, string>
     */
    private array $trackedOptions = [];

    protected function tearDown(): void
    {
        foreach ($this->trackedOptions as $option) {
            delete_option($option);
        }
        delete_option('gm2_autoload_migration_version');
        remove_all_filters('gm2_performance_autoload_disabled_options');
        parent::tearDown();
    }

    public function test_run_migration_updates_autoload_flags(): void
    {
        global $wpdb;

        $option = 'gm2_migration_option';
        $this->trackOption($option);

        add_filter(
            'gm2_performance_autoload_disabled_options',
            static function (array $options) use ($option): array {
                $options[] = $option;
                return $options;
            }
        );

        add_option($option, 'payload', '', 'yes');

        $this->assertSame(
            'yes',
            $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option))
        );

        AutoloadMigration::run_migration();

        $this->assertSame(
            'no',
            $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option))
        );
    }

    public function test_maybe_run_sets_version_and_updates_autoload(): void
    {
        global $wpdb;

        $option = 'gm2_migration_option_versioned';
        $this->trackOption($option);

        add_filter(
            'gm2_performance_autoload_disabled_options',
            static function (array $options) use ($option): array {
                $options[] = $option;
                return $options;
            }
        );

        add_option($option, 'payload', '', 'yes');

        delete_option('gm2_autoload_migration_version');

        AutoloadMigration::maybe_run();

        $version = get_option('gm2_autoload_migration_version');
        $this->assertSame(self::getMigrationVersion(), (int) $version);
        $this->assertSame(
            'no',
            $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option))
        );
    }

    public function test_maybe_run_skips_when_version_is_current(): void
    {
        global $wpdb;

        $option = 'gm2_migration_option_skip';
        $this->trackOption($option);

        add_filter(
            'gm2_performance_autoload_disabled_options',
            static function (array $options) use ($option): array {
                $options[] = $option;
                return $options;
            }
        );

        add_option($option, 'payload', '', 'yes');

        update_option('gm2_autoload_migration_version', self::getMigrationVersion(), false);

        AutoloadMigration::maybe_run();

        $this->assertSame(
            'yes',
            $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option))
        );
    }

    private static function getMigrationVersion(): int
    {
        $reflection = new ReflectionClass(AutoloadMigration::class);
        $constant   = $reflection->getReflectionConstant('VERSION');

        return (int) ($constant ? $constant->getValue() : 0);
    }

    private function trackOption(string $option): void
    {
        $this->trackedOptions[] = $option;
    }
}

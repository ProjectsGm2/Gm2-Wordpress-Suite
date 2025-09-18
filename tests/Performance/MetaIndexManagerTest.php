<?php

declare(strict_types=1);

use Gm2\Performance\MetaIndexManager;

final class MetaIndexManagerTest extends WP_UnitTestCase
{
    public function test_generate_sql_for_numeric_meta_keys(): void
    {
        global $wpdb;

        $manager = new MetaIndexManager();
        $create  = $manager->generateCreateStatement('price');
        $drop    = $manager->generateDropStatement('price');

        $expectedCreate = sprintf(
            'CREATE INDEX `%s` ON `%s` (meta_key, (CAST(meta_value AS DECIMAL(18,2))))',
            'gm2_meta_price_idx',
            $wpdb->postmeta
        );
        $expectedDrop = sprintf(
            'DROP INDEX `%s` ON `%s`',
            'gm2_meta_price_idx',
            $wpdb->postmeta
        );

        $this->assertSame($expectedCreate, $create);
        $this->assertSame($expectedDrop, $drop);
    }

    public function test_filters_can_disable_meta_key(): void
    {
        $callback = static function (array $keys): array {
            return array_diff($keys, ['job_status']);
        };

        add_filter(MetaIndexManager::FILTER_META_KEYS, $callback, 10, 1);

        try {
            $manager = new MetaIndexManager(new MetaIndexManagerTest_FakeWpdb());
            $this->assertNull($manager->getDefinition('job_status'));
        } finally {
            remove_filter(MetaIndexManager::FILTER_META_KEYS, $callback, 10);
        }
    }

    public function test_create_reports_existing_indexes(): void
    {
        $wpdb = new MetaIndexManagerTest_FakeWpdb(['gm2_meta_start_date_idx']);
        $manager = new MetaIndexManager($wpdb);

        $result = $manager->create('start_date');

        $this->assertSame('exists', $result['status']);
        $creates = array_filter(
            $wpdb->queries,
            static fn(string $query): bool => strpos($query, 'CREATE INDEX') === 0
        );
        $this->assertSame([], array_values($creates));
    }

    public function test_drop_reports_missing_indexes(): void
    {
        $wpdb = new MetaIndexManagerTest_FakeWpdb();
        $manager = new MetaIndexManager($wpdb);

        $result = $manager->drop('latitude');

        $this->assertSame('missing', $result['status']);
    }

    public function test_create_returns_error_message_from_wpdb(): void
    {
        $wpdb = new MetaIndexManagerTest_FakeWpdb([], true);
        $manager = new MetaIndexManager($wpdb);

        $result = $manager->create('course_status');

        $this->assertSame('error', $result['status']);
        $this->assertSame('Simulated failure', $result['message']);
    }
}

final class MetaIndexManagerTest_FakeWpdb
{
    public string $postmeta = 'wp_postmeta';
    public string $prefix   = 'wp_';
    public string $last_error = '';
    public array $queries = [];

    /** @var array<int, string> */
    private array $existing;
    private bool $failOnQuery;

    public function __construct(array $existing = [], bool $failOnQuery = false)
    {
        $this->existing    = $existing;
        $this->failOnQuery = $failOnQuery;
    }

    public function prepare(string $query, ...$args): string
    {
        foreach ($args as $arg) {
            $query = preg_replace('/%s/', "'" . addslashes((string) $arg) . "'", $query, 1);
        }

        return $query;
    }

    public function get_results(string $query, $output = null): array
    {
        $this->queries[] = $query;

        foreach ($this->existing as $name) {
            if (strpos($query, $name) !== false) {
                return [['Key_name' => $name]];
            }
        }

        return [];
    }

    public function query(string $query)
    {
        $this->queries[] = $query;

        if ($this->failOnQuery) {
            $this->last_error = 'Simulated failure';

            return false;
        }

        if (preg_match('/CREATE INDEX `([^`]+)`/', $query, $matches) === 1) {
            if (!in_array($matches[1], $this->existing, true)) {
                $this->existing[] = $matches[1];
            }
        } elseif (preg_match('/DROP INDEX `([^`]+)`/', $query, $matches) === 1) {
            $this->existing = array_values(array_filter(
                $this->existing,
                static fn(string $name): bool => $name !== $matches[1]
            ));
        }

        return 1;
    }
}

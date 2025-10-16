<?php

declare(strict_types=1);

namespace Gm2\Performance;

use RuntimeException;

use function addslashes;
use function apply_filters;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function implode;
use function is_array;
use function is_object;
use function method_exists;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strtolower;
use function trim;

final class MetaIndexManager
{
    public const FILTER_META_KEYS      = 'gm2_performance_meta_index_keys';
    public const FILTER_DEFINITIONS    = 'gm2_performance_meta_index_definitions';
    public const FILTER_META_TABLE     = 'gm2_performance_meta_index_table';

    /**
     * Database connection used to inspect and manipulate indexes.
     *
     * @var object
     */
    private object $wpdb;

    public function __construct(?object $wpdb = null)
    {
        if ($wpdb !== null) {
            $this->wpdb = $wpdb;

            return;
        }

        global $wpdb;
        if (!is_object($wpdb)) {
            throw new RuntimeException('The global $wpdb instance is not available.');
        }

        $this->wpdb = $wpdb;
    }

    /**
     * Retrieve the default set of meta keys that receive composite indexes.
     */
    public function getDefaultMetaKeys(): array
    {
        return [
            'start_date',
            'end_date',
            'price',
            'latitude',
            'longitude',
            'course_status',
            'job_status',
        ];
    }

    /**
     * Determine which table should receive the indexes.
     */
    public function getMetaTable(): string
    {
        $table = '';
        if (isset($this->wpdb->postmeta) && $this->wpdb->postmeta !== '') {
            $table = (string) $this->wpdb->postmeta;
        }

        if ($table === '') {
            $prefix = isset($this->wpdb->prefix) ? (string) $this->wpdb->prefix : 'wp_';
            $table  = $prefix . 'postmeta';
        }

        $table = apply_filters(self::FILTER_META_TABLE, $table, $this->wpdb);

        return (string) $table;
    }

    /**
     * Retrieve the filtered list of meta keys slated for indexing.
     */
    public function getMetaKeys(): array
    {
        $keys = apply_filters(self::FILTER_META_KEYS, $this->getDefaultMetaKeys(), $this->wpdb);
        if (!is_array($keys)) {
            $keys = [];
        }

        $keys = array_map(static fn($key): string => (string) $key, $keys);
        $keys = array_filter($keys, static fn(string $key): bool => $key !== '');

        return array_values(array_unique($keys));
    }

    /**
     * Retrieve the normalized index definitions.
     */
    public function getDefinitions(): array
    {
        $table       = $this->getMetaTable();
        $definitions = [];

        foreach ($this->getMetaKeys() as $metaKey) {
            $definitions[$metaKey] = $this->buildDefinition($metaKey, $table);
        }

        $definitions = apply_filters(self::FILTER_DEFINITIONS, $definitions, $table, $this->wpdb);
        if (!is_array($definitions)) {
            return [];
        }

        return $definitions;
    }

    /**
     * Retrieve a specific index definition for a meta key.
     */
    public function getDefinition(string $metaKey): ?array
    {
        $definitions = $this->getDefinitions();

        return $definitions[$metaKey] ?? null;
    }

    /**
     * Generate the CREATE INDEX statement for a meta key.
     */
    public function generateCreateStatement(string $metaKey): ?string
    {
        $definition = $this->getDefinition($metaKey);

        return $definition['create_sql'] ?? null;
    }

    /**
     * Generate the DROP INDEX statement for a meta key.
     */
    public function generateDropStatement(string $metaKey): ?string
    {
        $definition = $this->getDefinition($metaKey);

        return $definition['drop_sql'] ?? null;
    }

    /**
     * Provide a status overview of each registered index.
     */
    public function describe(): array
    {
        $rows = [];
        foreach ($this->getDefinitions() as $metaKey => $definition) {
            $rows[$metaKey] = [
                'meta_key'   => $metaKey,
                'index_name' => $definition['name'],
                'table'      => $definition['table'],
                'expression' => $definition['expression'],
                'exists'     => $this->indexExists($definition),
            ];
        }

        return $rows;
    }

    /**
     * Create the index for a meta key when it does not already exist.
     */
    public function create(string $metaKey): array
    {
        $definition = $this->getDefinition($metaKey);
        if ($definition === null) {
            return ['status' => 'unknown'];
        }

        if ($this->indexExists($definition)) {
            return ['status' => 'exists'];
        }

        $result = $this->wpdbQuery($definition['create_sql']);
        if ($result === false) {
            return [
                'status'  => 'error',
                'message' => $this->getLastError() ?: 'Failed to create index.',
            ];
        }

        return ['status' => 'created'];
    }

    /**
     * Drop the index for a meta key when it exists.
     */
    public function drop(string $metaKey): array
    {
        $definition = $this->getDefinition($metaKey);
        if ($definition === null) {
            return ['status' => 'unknown'];
        }

        if (!$this->indexExists($definition)) {
            return ['status' => 'missing'];
        }

        $result = $this->wpdbQuery($definition['drop_sql']);
        if ($result === false) {
            return [
                'status'  => 'error',
                'message' => $this->getLastError() ?: 'Failed to drop index.',
            ];
        }

        return ['status' => 'dropped'];
    }

    private function buildDefinition(string $metaKey, string $table): array
    {
        $indexName  = $this->buildIndexName($metaKey);
        $expression = $this->expressionForMetaKey($metaKey);

        $create = sprintf(
            'CREATE INDEX %s ON %s (meta_key, %s)',
            $this->quoteIdentifier($indexName),
            $this->quoteIdentifier($table),
            $expression
        );

        $drop = sprintf(
            'DROP INDEX %s ON %s',
            $this->quoteIdentifier($indexName),
            $this->quoteIdentifier($table)
        );

        return [
            'key'        => $metaKey,
            'name'       => $indexName,
            'table'      => $table,
            'expression' => $expression,
            'create_sql' => $create,
            'drop_sql'   => $drop,
        ];
    }

    private function buildIndexName(string $metaKey): string
    {
        $sanitized = preg_replace('/[^a-z0-9_]+/', '_', strtolower($metaKey)) ?? '';
        $sanitized = trim($sanitized, '_');

        if ($sanitized === '') {
            $sanitized = 'meta';
        }

        return 'gm2_meta_' . $sanitized . '_idx';
    }

    private function expressionForMetaKey(string $metaKey): string
    {
        switch ($metaKey) {
            case 'start_date':
            case 'end_date':
                return '(CAST(meta_value AS DATETIME))';
            case 'price':
                return '(CAST(meta_value AS DECIMAL(18,2)))';
            case 'latitude':
            case 'longitude':
                return '(CAST(meta_value AS DECIMAL(10,6)))';
            case 'course_status':
            case 'job_status':
                return 'meta_value(32)';
            default:
                return 'meta_value(191)';
        }
    }

    private function indexExists(array $definition): bool
    {
        if (!method_exists($this->wpdb, 'get_results')) {
            return false;
        }

        $sql = sprintf(
            'SHOW INDEX FROM %s WHERE Key_name = %%s',
            $this->quoteIdentifier($definition['table'])
        );
        $prepared = $this->wpdbPrepare($sql, $definition['name']);
        $results  = $this->wpdb->get_results($prepared, 'ARRAY_A');

        return !empty($results);
    }

    private function wpdbPrepare(string $query, string ...$args): string
    {
        if (method_exists($this->wpdb, 'prepare')) {
            return $this->wpdb->prepare($query, ...$args);
        }

        foreach ($args as $arg) {
            $query = preg_replace('/%s/', "'" . addslashes($arg) . "'", $query, 1);
        }

        return $query;
    }

    /**
     * @return mixed
     */
    private function wpdbQuery(string $query)
    {
        if (!method_exists($this->wpdb, 'query')) {
            return false;
        }

        return $this->wpdb->query($query);
    }

    private function quoteIdentifier(string $identifier): string
    {
        $parts = explode('.', $identifier);
        foreach ($parts as &$part) {
            $part = '`' . str_replace('`', '``', $part) . '`';
        }

        return implode('.', $parts);
    }

    private function getLastError(): string
    {
        return (string) ($this->wpdb->last_error ?? '');
    }
}

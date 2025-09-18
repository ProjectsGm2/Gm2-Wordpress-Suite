# Composite meta indexes

The suite can register composite indexes that combine `meta_key` with a typed
representation of `meta_value`. These indexes speed up meta queries that filter
by a key **and** perform comparisons on the stored value. Common examples are
range checks on `_gm2_start_date`, numeric comparisons on `_gm2_price`, or
geospatial lookups on `_gm2_latitude`/`_gm2_longitude`.

## Why add composite indexes?

WordPress stores meta data in the `wp_postmeta` table where the `meta_value`
column is a `LONGTEXT`. Without an index MySQL has to scan the entire table for
each query. Creating a composite index on `(meta_key, CAST(meta_value AS ...))`
allows the database to seek directly to the matching rows and dramatically
reduces response times for faceted searches, REST API filters and background
jobs that sort or filter by course dates, job price ranges and similar values.

The default manager registers indexes for:

- `start_date` and `end_date` as `CAST(meta_value AS DATETIME)`
- `price` as `CAST(meta_value AS DECIMAL(18,2))`
- `latitude` and `longitude` as `CAST(meta_value AS DECIMAL(10,6))`
- `course_status` and `job_status` using a shortened `meta_value(32)` prefix

Sites can add or remove keys via the
`gm2_performance_meta_index_keys` filter and can override the SQL definition for
any key with the `gm2_performance_meta_index_definitions` filter. Use
`gm2_performance_meta_index_table` to point the manager at a different meta
table.

## When to avoid or drop an index

Indexes trade faster reads for slower writes. Each insert, update or delete of
the indexed meta keys must also update the index tree. For sites with frequent
writes or large import jobs you may wish to disable indexes temporarily:

```php
add_filter( 'gm2_performance_meta_index_keys', function ( $keys ) {
    return array_diff( $keys, [ 'latitude', 'longitude' ] );
} );
```

Alternatively drop the index before bulk imports and recreate it afterwards with
the CLI commands below.

## WP-CLI commands

Use the new `gm2 perf indexes` namespace to inspect and manage indexes:

```bash
wp gm2 perf indexes list
wp gm2 perf indexes list --key=price
wp gm2 perf indexes create --key=start_date --yes
wp gm2 perf indexes drop --key=latitude --yes
```

The commands return the current status, ask for confirmation before destructive
changes and surface the filters listed above so you can audit site-specific
configuration.

## Testing index SQL

If you add new keys or customise the SQL ensure the resulting `CREATE INDEX`
statement matches what your MySQL version supports. The
`Gm2\Performance\MetaIndexManager` class exposes helper methods like
`generateCreateStatement()` and `generateDropStatement()` that you can call from
unit tests or wp shell to verify the generated SQL before deploying to
production.

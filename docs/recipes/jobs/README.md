# Jobs Recipe

Job board example using schema tooltips.

See the [Schema Mapping and SEO guide](../../schema-mapping-and-seo.md) for mapping presets, JSON-LD examples and SEO hooks.

```php
register_post_type( 'gm2_job', [
    'label' => 'Job',
    'public' => true,
] );

echo gm2_schema_tooltip( 'Job location', 'Location' );
$admin = new Gm2_Custom_Posts_Admin();
$admin->register_help(
    'gm2_job',
    '<p>Job admin tips.</p>',
    [ 'input[name="gm2_salary"]' => 'Annual salary.' ]
);
```

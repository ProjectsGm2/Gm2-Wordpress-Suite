# Real Estate Recipe

Example snippet for real estate listings.

See the [Schema Mapping and SEO guide](../../schema-mapping-and-seo.md) for mapping presets, JSON-LD examples and SEO hooks.

```php
register_post_type( 'gm2_property', [
    'label' => 'Property',
    'public' => true,
] );

echo gm2_schema_tooltip( 'Property price', 'Price' );
$admin = new Gm2_Custom_Posts_Admin();
$admin->register_help(
    'gm2_property',
    '<p>Property admin tips.</p>',
    [ 'input[name="gm2_price"]' => 'Listing price.' ]
);
```

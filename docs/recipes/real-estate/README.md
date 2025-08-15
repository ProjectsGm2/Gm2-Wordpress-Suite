# Real Estate Recipe

Example snippet for real estate listings.

```php
register_post_type( 'gm2_property', [
    'label' => 'Property',
    'public' => true,
] );

echo gm2_schema_tooltip( 'Property price', 'Price' );
```

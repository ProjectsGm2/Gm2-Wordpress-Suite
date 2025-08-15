# Jobs Recipe

Job board example using schema tooltips.

```php
register_post_type( 'gm2_job', [
    'label' => 'Job',
    'public' => true,
] );

echo gm2_schema_tooltip( 'Job location', 'Location' );
```

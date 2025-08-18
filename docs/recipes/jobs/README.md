# Jobs Recipe

Job board example using schema tooltips.

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

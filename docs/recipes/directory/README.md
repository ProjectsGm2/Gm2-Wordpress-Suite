# Directory Recipe

Minimal example showing how to register a Directory custom post type with schema annotations.

```php
register_post_type( 'gm2_directory', [
    'label' => 'Directory',
    'public' => true,
    'template' => [
        [ 'core/image', [] ],
        [ 'core/paragraph', [ 'placeholder' => 'Add business details...' ] ],
    ],
    'template_lock' => 'insert',
] );

echo gm2_schema_tooltip( 'Business address', 'Address' );
```

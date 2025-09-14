# Courses Recipe

Course listing with Open in Code output.

See the [Schema Mapping and SEO guide](../../schema-mapping-and-seo.md) for mapping presets, JSON-LD examples and SEO hooks.

```php
register_post_type( 'gm2_course', [
    'label' => 'Course',
    'public' => true,
] );

echo gm2_render_open_in_code( '<?php // course php ?>', json_encode( ['type' => 'course'] ) );
echo gm2_schema_tooltip( 'Course name', 'Name' );
```

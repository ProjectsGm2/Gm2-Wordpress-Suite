# Courses Recipe

Course listing with Open in Code output.

```php
register_post_type( 'gm2_course', [
    'label' => 'Course',
    'public' => true,
] );

echo gm2_render_open_in_code( '<?php // course php ?>', json_encode( ['type' => 'course'] ) );
echo gm2_schema_tooltip( 'Course name', 'Name' );
```

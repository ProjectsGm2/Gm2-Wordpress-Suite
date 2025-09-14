# Events Recipe

Registers an Event post type and demonstrates Open in Code for generated schema.

See the [Schema Mapping and SEO guide](../../schema-mapping-and-seo.md) for mapping presets, JSON-LD examples and SEO hooks.

```php
register_post_type( 'gm2_event', [
    'label' => 'Event',
    'public' => true,
] );

echo gm2_render_open_in_code( '<?php // event php ?>', json_encode( ['type' => 'event'] ) );
echo gm2_schema_tooltip( 'Event venue', 'Venue' );
```

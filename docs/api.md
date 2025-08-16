# Gm2 WordPress Suite API

This document describes helper functions and JavaScript utilities with typed signatures and usage examples.

## PHP Hooks and Functions

### `gm2_schema_tooltip( string $schema, string $label ): string`
Wraps a label with inline schema information. The schema text is exposed as a tooltip in the UI.

```php
echo gm2_schema_tooltip( 'Product\\nname', 'Product Name' );
```

### `gm2_render_open_in_code( string $php_code, string $json_code ): string`
Outputs an **Open in Code** button with PHP/JSON blocks and copy or download options.

```php
$php  = "<?php echo 'Hello';";
$json = json_encode( ['hello' => 'world'] );

echo gm2_render_open_in_code( $php, $json );
```

### `gm2_resolve_default( array $field, int $object_id = 0, string $context_type = 'post' ): mixed`
Resolves a field's default value. Besides static values and callbacks, template
strings may contain tokens such as `{post_id}` or `{date:Y-m-d}`. Date tokens are
rendered using the site's timezone.

```php
$field = [ 'default_template' => 'Published on {date:Y-m-d}' ];
$value = gm2_resolve_default( $field );
```

### Field serialization
Field definitions support a `serialize` key that determines how values are
exposed through the REST API and webhooks. Supported modes are:

- `raw` – return the stored value.
- `rendered` – pass the value through `the_content` filter.
- `media` – treat the value as an attachment ID and return the
  `wp_prepare_attachment_for_js()` array.

```php
$field = [
    'label'     => 'Summary',
    'type'      => 'text',
    'serialize' => 'rendered',
];
```

## JavaScript APIs

### `gm2-schema-tooltips`
Elements with a `data-schema` attribute automatically display the schema text via a native tooltip.

```html
<span class="gm2-schema-field" data-schema="Price including tax">Price</span>
```

### `gm2-open-in-code`
Provides copy-to-clipboard and download buttons for code blocks rendered by `gm2_render_open_in_code()`.

```js
// Behaviour is attached to markup produced by the PHP helper; no direct API is exposed.
```

### `Gm2_Custom_Posts_Admin::register_help( string $screen, string $content, array $tooltips = [] ): void`
Registers contextual help for an admin screen. The `$tooltips` array maps CSS selectors to messages that appear as native tooltips. A "Help" tab is added automatically.

```php
$admin = new Gm2_Custom_Posts_Admin();
$admin->register_help(
    'toplevel_page_gm2-custom-posts',
    __( 'Manage custom post types and taxonomies.', 'gm2-wordpress-suite' ),
    [ 'input[name="pt_slug"]' => __( 'Unique identifier for the post type.', 'gm2-wordpress-suite' ) ]
);
```

### `gm2-help`
Elements targeted by the help registry receive their message as a tooltip.

```js
// Tooltips are applied automatically based on localized data.
```


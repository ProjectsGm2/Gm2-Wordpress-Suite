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


# Fields and Validation

This document covers the available field types, how to apply conditional logic and location rules, and the hooks that allow developers to customize sanitization and validation.

## Field Types and Arguments

Each field is defined by a `slug`, `type` and `label`. Additional keys vary by field type:

| Type | Key Arguments |
| ---- | ------------- |
| `text`, `textarea`, `number` | `default`, `placeholder`, `min`, `max` |
| `select`, `radio`, `checkbox` | `choices` (array of value ⇒ label pairs) |
| `media`, `gallery`, `file` | `mime_types`, `return_format` |
| `group`, `repeater`, `flexible` | `fields` (nested definitions), `min`, `max` |
| `date`, `datetime`, `time` | `format`, `return_format` |
| `url`, `email`, `phone` | none (validated automatically) |
| Presentation types (`gradient`, `icon`, `badge`, `rating`) | see [extra-fields.md](extra-fields.md) |

Field definitions also support optional `instructions`, `description`, and `admin_class` keys for additional context and styling.

## Conditional Logic

Display logic is handled through a `conditions` array on individual fields. Each group defines a relation and a list of conditions with `target`, `operator`, and `value` keys. The field is shown when any group passes.

```php
[
    'slug' => 'cta_text',
    'type' => 'text',
    'label' => 'CTA Text',
    'conditions' => [
        [
            'relation' => 'AND',
            'conditions' => [
                [
                    'target' => 'enable_cta',
                    'operator' => '=',
                    'value' => '1',
                ],
            ],
        ],
    ],
]
```

Legacy `conditional` keys are converted automatically, mapping `field` and `value` to the modern structure.

## Location Rules

Field groups can be restricted to specific contexts using `location` rules. Each rule compares a context value—such as `post_type`, `template`, or `taxonomy`—against a target value:

```php
[
    [
        'relation' => 'AND',
        'rules' => [
            [ 'param' => 'post_type', 'operator' => '==', 'value' => 'product' ],
            [ 'param' => 'template', 'operator' => '!=', 'value' => 'landing.php' ],
        ],
    ],
]
```

A group passes when all its rules evaluate true. The field group renders when any group passes.

## REST Visibility

Meta fields are registered with `show_in_rest` enabled by default so values appear in the REST API. Set `'show_in_rest' => false` within a field's `args` to hide it:

```php
[
    'slug'  => 'internal_notes',
    'type'  => 'textarea',
    'label' => 'Internal Notes',
    'args'  => [ 'show_in_rest' => false ],
]
```

## Hooks

### `gm2_cp_register_field_type`
Fires after a field type class is registered. Receive the type slug and class name to modify or inspect registration.

```php
add_action( 'gm2_cp_register_field_type', function ( $type, $class ) {
    // $type is the field type key, $class is the implementing class.
} );
```

### `gm2_cp_field_sanitize_{$type}`
Filters the sanitized value for every field of a given type.

```php
add_filter( 'gm2_cp_field_sanitize_text', function ( $value, $field ) {
    return wp_strip_all_tags( $value );
}, 10, 2 );
```

### `gm2_cp_field_sanitize_{$slug}`
Filters the sanitized value before saving. Runs after the type-level filter above so individual slugs can override shared logic.

```php
add_filter( 'gm2_cp_field_sanitize_custom_slug', function ( $value, $field ) {
    return trim( $value );
}, 10, 2 );
```

### `gm2_cp_field_validate_{$type}`
Runs after sanitization to enforce custom rules. Throw a `WP_Error` to halt saving.

```php
add_action( 'gm2_cp_field_validate_text', function ( $value, $field, $object_id ) {
    if ( strlen( $value ) > 100 ) {
        throw new WP_Error( 'too_long', 'Text is limited to 100 characters.' );
    }
}, 10, 3 );
```

These hooks allow bespoke field types, sanitization routines, and validation rules across the suite.


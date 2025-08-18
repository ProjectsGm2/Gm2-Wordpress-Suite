# Field Definition Schema

Each field definition used by `gm2_render_field_group()` accepts the following keys:

- `label` – Human‑readable label displayed with the field.
- `slug` – Unique key used to store the value.
- `type` – Field type such as `text`, `textarea`, `select`, `checkbox`,
  or design helpers like `gradient`, `icon`, `badge` and `rating`.
- `default` – Optional default value.
- `description` – Short description shown in field listings.
- `instructions` – Additional help text rendered below the field.
- `placeholder` – Placeholder text applied to the input element.
- `admin_class` – Extra CSS classes applied to the input when editing in the admin.
- `tab` – Name of the tab grouping the field. Tabs are rendered as a navigation bar and only the active tab's fields are visible.
- `accordion` – Name of the accordion group the field belongs to. Accordion headers toggle visibility of their fields.

These keys allow field groups to provide richer guidance and layout controls within the admin interface.

## Flexible Content Field

The `flexible` field type supports multiple row layouts, each with its own set of
nested fields. Configure via a `layouts` array where the keys are layout slugs
and each layout provides a `label` and `fields` definition:

```php
[
    'slug'   => 'content_blocks',
    'type'   => 'flexible',
    'layouts' => [
        'hero' => [
            'label'  => 'Hero',
            'fields' => [
                'title' => [ 'type' => 'text', 'label' => 'Title' ],
                'image' => [ 'type' => 'media', 'label' => 'Image' ],
            ],
        ],
        'cta' => [
            'label'  => 'Call to Action',
            'fields' => [
                'content' => [ 'type' => 'textarea', 'label' => 'Content' ],
            ],
        ],
    ],
]
```

Each saved value is an array of rows with a `layout` key and the values for the
fields defined in that layout.

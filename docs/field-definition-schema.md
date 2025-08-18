# Field Definition Schema

Each field definition used by `gm2_render_field_group()` accepts the following keys:

- `label` – Human‑readable label displayed with the field.
- `slug` – Unique key used to store the value.
- `type` – Field type such as `text`, `textarea`, `select` or `checkbox`.
- `default` – Optional default value.
- `description` – Short description shown in field listings.
- `instructions` – Additional help text rendered below the field.
- `placeholder` – Placeholder text applied to the input element.
- `admin_class` – Extra CSS classes applied to the input when editing in the admin.
- `tab` – Name of the tab grouping the field. Tabs are rendered as a navigation bar and only the active tab's fields are visible.
- `accordion` – Name of the accordion group the field belongs to. Accordion headers toggle visibility of their fields.

These keys allow field groups to provide richer guidance and layout controls within the admin interface.

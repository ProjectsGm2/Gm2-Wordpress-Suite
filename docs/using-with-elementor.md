# Using with Elementor

The Gm2 WordPress Suite integrates with Elementor to expose custom field data
and advanced queries directly inside the page builder. This guide covers the
dynamic tag, query controls and template helpers available when building
Elementor layouts.

## Dynamic Tags

GM2 registers a **GM2 Field** dynamic tag that reads values from any registered
field group.

1. In Elementor choose a control that supports dynamic data.
2. Select **GM2 Field** from the dynamic tags menu under the "GM2" group.
3. Pick a field key. Nested fields use dot notation such as `address.city` or
   `slides.0.image` for repeater items.
4. Optionally supply a fallback value to display when the field is empty.

Available tag types are mapped to Elementor categories so the tag only appears
where it is supported:

- **Text** – strings and numbers.
- **URL** – link fields.
- **Media** – single images or files.
- **Gallery** – arrays of images.

## Query Builder

Elementor Pro's Posts widget gains a **GM2 CP** query ID that converts additional
controls into `WP_Query` arguments. Key controls include:

- **Post Type** – choose the post type to display.
- **Taxonomy** – select terms to build a `tax_query`.
- **Meta Key/Compare/Value** – add custom `meta_query` conditions.
- **Price Min/Max** – restrict results to a numeric range.
- **Date After/Before** – filter by publish date.
- **Latitude/Longitude/Radius** – query posts within a geodistance box.

### Sample Configurations

1. **Events in New York**
   - Post Type: `event`
   - Taxonomy: `location` terms `12`, `34`
   - Date After: `2024-01-01`
   - Latitude: `40.7128`, Longitude: `-74.0060`, Radius: `25`

2. **Products in Stock**
   - Post Type: `product`
   - Meta Key: `_stock`, Compare: `>`, Value: `0`
   - Price Min: `10`, Price Max: `100`

These options allow complex queries without writing PHP.

## Template Helper Functions

Use the following helpers to print field values inside PHP or Twig/Blade
templates:

```php
// Simple value with a default.
echo gm2_field('hero_title', 'Coming Soon');

// Render an <img> tag for a media field.
 echo gm2_field_image('hero_image', 'large');

// Format a price.
echo gm2_field_currency('price');

// Telephone link.
echo gm2_field_phone_link('support_phone');

// Map link for an address field.
echo gm2_field_map_link('address');
```

These helpers resolve defaults, handle formatting and fallbacks, and work with
posts, terms, users and options.

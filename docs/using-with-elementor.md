# Using with Elementor

The Gm2 WordPress Suite integrates with Elementor to expose custom field data
and advanced queries directly inside the page builder. This guide covers the
dynamic tag, query controls and template helpers available when building
Elementor layouts.

## Dynamic Tags

GM2 exposes two dynamic tag groups inside Elementor so you can read custom
field values anywhere dynamic content is supported.

### GM2 Fields Group

The **GM2 Field** tag automatically adapts to the chosen field type and lives
under the **GM2 Fields** group.

1. Choose any Elementor control that allows dynamic data.
2. Select **GM2 Field** from the dynamic tags list.
3. Use the **Field** control to pick a key. Nested items use dot notation such
   as `address.city` or `slides.0.image`.
4. Supply a **Fallback** value to render when the field is empty.

The tag advertises the correct Elementor categories so it only appears for
compatible widgets (Text, URL, Media and Gallery contexts).

### Gm2 CP Fields Group

Custom post fields also register dedicated tags under the **Gm2 CP Fields**
group. Each tag returns the correct structure for Elementor's built-in
controls:

- **GM2 CP Text** – text, textarea and number strings.
- **GM2 CP URL** – outputs a URL array (`['url' => 'https://…']`).
- **GM2 CP Image** – returns attachment ID and URL for image controls.
- **GM2 CP Media** – works with audio, video and file widgets.
- **GM2 CP Number** – numeric values with optional fallbacks.
- **GM2 CP Color** – hex or rgba color strings.
- **GM2 CP Gallery** – arrays of image IDs/URLs.
- **GM2 CP Date** – date or datetime strings.

All CP tags share the **Field Key** selector (with dot notation support) and a
**Fallback** control so you can handle empty values gracefully.

## Widgets

In addition to dynamic tags, the suite provides Elementor widgets that render
GM2 data without manual templating.

### GM2 Field widget

The **GM2 Field** widget mirrors the dynamic tag controls so you can drop a
single field into layouts that expect native widgets. Choose a post type,
select a field, and optionally override the HTML tag or fallback text. The
widget formats each field type automatically (images produce `<img>` tags,
links render anchors, and WYSIWYG content is printed as sanitized HTML).【F:src/Elementor/Widgets/Field.php†L20-L129】【F:src/Elementor/Widgets/AbstractFieldWidget.php†L30-L209】

### GM2 Loop Card widget

Use the **GM2 Loop Card** widget inside Elementor loop templates to assemble a
complete card from multiple fields. It supports configurable layouts, featured
images, title/subtitle sources, body text, meta rows, and an optional button
link. Each value is sanitized and formatted according to its field type so the
card stays consistent across posts.【F:src/Elementor/Widgets/LoopCard.php†L18-L278】

### GM2 Map widget

The **GM2 Map** widget accepts geopoint or address fields, builds a provider URL
using `{{lat}}`, `{{lng}}`, or `{{query}}` tokens, and renders either an embed
iframe or a link. You can change the provider template, tweak the embed height,
or supply fallback text when no coordinates exist.【F:src/Elementor/Widgets/Map.php†L18-L154】

### GM2 Opening Hours widget

The **GM2 Opening Hours** widget normalizes repeater-based schedules (day,
start, and end values) or plain text fields into a definition list. Closed days
use a customizable label and times are formatted with the site time settings
when possible, falling back to sanitized strings when parsing fails.【F:src/Elementor/Widgets/OpeningHours.php†L18-L192】

## Query Builder

Elementor Pro's Posts, Loop Grid and Archive Posts widgets gain a **GM2 CP**
query ID. Selecting it reveals additional controls that translate directly into
`WP_Query` arguments:

- **Post Types** – choose one or more public post types.
- **Taxonomy** – pick the taxonomy used for term filtering.
- **Terms** – select specific term IDs once a taxonomy is chosen.
- **GM2 Field Key** – select a field for meta comparisons via the Field Key
  control.
- **Meta Compare** – comparison operator (e.g. `=`, `BETWEEN`, `LIKE`).
- **Meta Type** – specify how to cast values (`NUMERIC`, `DATE`, etc.).
- **Meta Value** – value used with the selected comparison.
- **Date After / Date Before** – inclusive publish date range pickers.
- **Minimum Price / Maximum Price** – numeric price filters using the configured
  meta key.
- **Price Meta Key** – override the price meta key (defaults to `_price`).
- **Latitude / Longitude / Radius (km)** – centre point and radius for
  geospatial searches.
- **Latitude Meta Key / Longitude Meta Key** – override default coordinate keys
  (`gm2_geo_lat`/`gm2_geo_lng`).

### Sample Configurations

1. **Events in New York (Posts widget)**
   - Post Types: `event`
   - Taxonomy: `location`
   - Terms: IDs `12`, `34`
   - Date After: `2024-01-01`
   - Latitude: `40.7128`, Longitude: `-74.0060`, Radius (km): `25`

2. **Products in Stock (Loop Grid)**
   - Set your Loop Grid template to display the product card layout.
   - Under Query → Query ID choose **GM2 CP**.
   - Post Types: `product`
   - GM2 Field Key: `_stock`
   - Meta Compare: `>`
   - Meta Value: `0`
   - Minimum Price: `10`, Maximum Price: `100`
   - Price Meta Key: `_price`

3. **Team Members by Department (Archive Posts)**
   - Query ID: **GM2 CP**
   - Post Types: `team_member`
   - Taxonomy: `department`
   - Terms: IDs `3`, `7`

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

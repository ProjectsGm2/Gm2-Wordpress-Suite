# Directory Preset

## Custom Post Type

- **Slug:** `listing`
- **Purpose:** Local business listings with support for classic details (title, long-form description, featured image, and custom fields) published under `/listings/` with an archive page and store icon in the menu.
- **Editor Template:** Block editor template locks insertion and opens with a grouped layout containing a heading, intro paragraph, and an Elementor Template shortcode placeholder so sites can swap in a custom Elementor design without rebuilding the structure.

## Taxonomies

| Taxonomy | Hierarchical | Purpose |
| --- | --- | --- |
| `listing_category` | Yes | Categorises listings into business types for browsing and filtering. |
| `listing_location` | No | Tags listings with cities or neighbourhoods for location-based filtering. |

## Field Groups

### Listing Details

| Field | Type | Validation & Notes |
| --- | --- | --- |
| `phone` | Text | Optional. Validated with the pattern `^\+?[0-9\-\s]+$` and exposed in the REST API. |
| `address` | Text | Required address line stored in REST for mapping and schema usage. |
| `website` | URL | Optional URL stored in REST for call-to-action links. |

## Schema Mapping

- **Schema Type:** `LocalBusiness`
- **Mappings:**
  - `telephone` ← `phone`
  - `address.streetAddress` ← `address`
  - `url` ← `website`

## Elementor Notes

The template encourages pairing native blocks with Elementor by leaving `[elementor-template id=""]` in place. Replace the blank ID with a saved Elementor template to render a richer hero while the locked group preserves consistent structure across listings.

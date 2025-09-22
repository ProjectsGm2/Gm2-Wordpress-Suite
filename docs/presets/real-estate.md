# Real Estate Preset

## Custom Post Type

- **Slug:** `property`
- **Purpose:** Lists property inventory with long-form descriptions and featured media under `/properties/`, surfaced in the admin with the home icon.
- **Editor Template:** Provides a locked block group with a headline, intro paragraph, and Elementor Template shortcode so editors can swap in a bespoke Elementor hero while retaining consistent structure.

## Taxonomies

| Taxonomy | Hierarchical | Purpose |
| --- | --- | --- |
| `property_type` | Yes | Segments listings by property type (e.g., apartment, commercial) for archive filtering. |

## Field Groups

### Property Details

| Field | Type | Validation & Notes |
| --- | --- | --- |
| `price` | Number | Required, minimum `0`, exposed in REST for feeds and schema. |
| `address` | Text | Required street address, REST exposed for mapping and schema. |
| `bedrooms` | Number | Optional count with a minimum of `0`, exposed in REST. |

## Schema Mapping

- **Schema Type:** `RealEstateListing`
- **Source:** [`presets/real-estate/blueprint.json`](../../presets/real-estate/blueprint.json) (`seo_mappings → property`)

| Schema property | GM2 field slug |
| --- | --- |
| `price` | `price` |
| `priceCurrency` | `price_currency` |
| `address.streetAddress` | `address` |
| `address.addressLocality` | `city` |
| `address.addressRegion` | `region` |
| `address.postalCode` | `postal_code` |
| `address.addressCountry` | `country` |
| `geo.latitude` | `latitude` |
| `geo.longitude` | `longitude` |
| `numberOfRooms` | `bedrooms` |
| `numberOfBathroomsTotal` | `bathrooms` |
| `floorSize.value` | `floor_size` |
| `floorSize.unitText` | `floor_size_unit` |
| `identifier` | `mls_id` |
| `yearBuilt` | `year_built` |

## Elementor Notes

Populate the `[elementor-template id=""]` shortcode with a saved Elementor template to drive the hero or gallery while the locked block group keeps the base layout intact across property pages.

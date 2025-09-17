# Events Preset

## Custom Post Type

- **Slug:** `event`
- **Purpose:** Publishes event details with support for long-form content, featured imagery, and excerpts under `/events/` with a calendar icon in the admin menu.
- **Editor Template:** Uses a locked block group containing a heading, summary paragraph, and Elementor Template shortcode placeholder so editors can drop in a saved Elementor hero layout while preserving baseline structure.

## Taxonomies

| Taxonomy | Hierarchical | Purpose |
| --- | --- | --- |
| `event_category` | Yes | Hierarchical categories organize events in archives (e.g., Workshop, Webinar). |
| `event_tag` | No | Flexible tagging surfaces keywords like `Networking` or `Virtual` for filtering. |

## Field Groups

### Event Details

| Field | Type | Validation & Notes |
| --- | --- | --- |
| `start_date` | DateTime | Required start timestamp exposed in REST responses. |
| `end_date` | DateTime | Required end timestamp exposed in REST responses. |
| `location` | Text | Required venue or meeting details, also exposed through REST for maps. |

## Schema Mapping

- **Schema Type:** `Event`
- **Mappings:**
  - `startDate` ← `start_date`
  - `endDate` ← `end_date`
  - `eventStatus` ← `status`
  - `eventAttendanceMode` ← `attendance_mode`
  - `location.name` ← `location`
  - `location.address.streetAddress` ← `location`
  - `virtualLocation.url` ← `virtual_event_url`
  - `onlineEventUrl` ← `virtual_event_url`
  - `organizer` ← `organizer`
  - `offers` ← `ticket_offers`

The `location` text feeds the `Place` name and street address, while linking an organizer post fills the `Organization` node. Adding rows to the `ticket_offers` repeater produces `Offer` objects with price, currency, and optional purchase URLs, and supplying a virtual event link populates a `VirtualLocation` alongside `onlineEventUrl` for all-online or hybrid sessions.

## Elementor Notes

Embed an Elementor design by filling in the `[elementor-template id=""]` placeholder. The locked group keeps the core heading and summary in place while letting Elementor control the hero or schedule layout.

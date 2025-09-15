# Courses Preset

## Custom Post Type

- **Slug:** `course`
- **Purpose:** Publishes course overviews with long-form descriptions and featured imagery at `/courses/` with the learn-more icon in the admin sidebar.
- **Editor Template:** Locks in a grouped block pattern (heading, summary paragraph, Elementor Template shortcode) so course pages stay consistent while allowing Elementor to power the hero.

## Taxonomies

| Taxonomy | Hierarchical | Purpose |
| --- | --- | --- |
| `course_category` | No | Buckets courses into subjects or programs for filtering the archive. |

## Field Groups

### Course Details

| Field | Type | Validation & Notes |
| --- | --- | --- |
| `provider` | Text | Required provider name exposed in REST. |
| `course_code` | Text | Required code validated against `^[A-Z0-9-]+$` and exposed in REST. |
| `course_url` | URL | Optional canonical course link exposed in REST. |

## Schema Mapping

- **Schema Type:** `Course`
- **Mappings:**
  - `provider` ← `provider`
  - `courseCode` ← `course_code`
  - `url` ← `course_url`

## Elementor Notes

Enter the saved Elementor template ID in `[elementor-template id=""]` to inject a tailored hero or CTA layout while the locked group guarantees consistent base content for every course.

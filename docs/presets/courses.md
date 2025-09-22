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
- **Source:** [`presets/courses/blueprint.json`](../../presets/courses/blueprint.json) (`seo_mappings → course`)

| Schema property | GM2 field slug |
| --- | --- |
| `provider.name` | `provider` |
| `provider.alternateName` | `organization_name` |
| `provider.department` | `department` |
| `provider.url` | `website` |
| `provider.email` | `contact_email` |
| `provider.telephone` | `contact_phone` |
| `courseCode` | `course_code` |
| `url` | `course_url` |
| `coursePrerequisites` | `prerequisites` |
| `timeRequired` | `duration_iso` |
| `courseInstance.courseMode` | `modality` |
| `courseInstance.eventAttendanceMode` | `modality` |
| `courseInstance.startDate` | `start_date_time` |
| `courseInstance.endDate` | `end_date_time` |
| `courseInstance.duration` | `duration_iso` |
| `courseInstance.location.name` | `provider` |
| `courseInstance.location.url` | `website` |
| `courseInstance.location.email` | `contact_email` |
| `courseInstance.location.telephone` | `contact_phone` |
| `courseInstance.location.timeZone` | `start_time_zone` |
| `courseInstance.offers.price` | `amount` |
| `courseInstance.offers.priceCurrency` | `currency` |
| `courseInstance.offers.url` | `course_url` |

Shared fields like `modality`, `provider`, and `course_url` deliberately power both the parent `Course` object and nested `CourseInstance` data so schedule details stay in sync across schema consumers.

## Elementor Notes

Enter the saved Elementor template ID in `[elementor-template id=""]` to inject a tailored hero or CTA layout while the locked group guarantees consistent base content for every course.

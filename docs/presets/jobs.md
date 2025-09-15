# Jobs Preset

## Custom Post Type

- **Slug:** `job`
- **Purpose:** Promotes job postings with detailed descriptions under `/jobs/` using the businessperson icon in the admin menu.
- **Editor Template:** Starts with a locked block group (heading, summary paragraph, Elementor Template shortcode) so teams can blend core content with a reusable Elementor layout.

## Taxonomies

| Taxonomy | Hierarchical | Purpose |
| --- | --- | --- |
| `job_category` | No | Groups roles by department or discipline for filtering job archives. |

## Field Groups

### Job Details

| Field | Type | Validation & Notes |
| --- | --- | --- |
| `date_posted` | Date | Required posting date exposed in REST feeds. |
| `employment_type` | Text | Required employment type (full-time, contract, etc.) exposed in REST. |
| `company` | Text | Required hiring organisation name available through REST. |
| `apply_url` | URL | Optional application link exposed in REST. |

## Schema Mapping

- **Schema Type:** `JobPosting`
- **Mappings:**
  - `datePosted` ← `date_posted`
  - `employmentType` ← `employment_type`
  - `hiringOrganization` ← `company`
  - `url` ← `apply_url`

## Elementor Notes

Swap the blank ID in `[elementor-template id=""]` for a saved Elementor layout to control the hero or call-to-action while the locked block group preserves consistent structure across job entries.

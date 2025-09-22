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
| `job_status` | Select | Required open/closed flag exposed in REST so queries can hide filled roles. |
| `employment_type` | Text | Required employment type (full-time, contract, etc.) exposed in REST. |
| `company` | Text | Required hiring organisation name available through REST. |
| `apply_url` | URL | Optional application link exposed in REST. |

## Schema Mapping

- **Schema Type:** `JobPosting`
- **Source:** [`presets/jobs/blueprint.json`](../../presets/jobs/blueprint.json) (`seo_mappings → job`)

| Schema property | GM2 field slug |
| --- | --- |
| `datePosted` | `date_posted` |
| `validThrough` | `valid_through` |
| `employmentType` | `employment_type` |
| `jobLocationType` | `job_location_type` |
| `jobLocation.name` | `job_location_name` |
| `jobLocation.address.streetAddress` | `job_street` |
| `jobLocation.address.addressLocality` | `job_city` |
| `jobLocation.address.addressRegion` | `job_region` |
| `jobLocation.address.postalCode` | `job_postal_code` |
| `jobLocation.address.addressCountry` | `job_country` |
| `jobLocation.geo.latitude` | `job_latitude` |
| `jobLocation.geo.longitude` | `job_longitude` |
| `baseSalary.currency` | `salary_currency` |
| `baseSalary.value.minValue` | `salary_min` |
| `baseSalary.value.maxValue` | `salary_max` |
| `baseSalary.value.unitText` | `salary_unit_text` |
| `offers.0.priceCurrency` | `salary_currency` |
| `offers.0.price` | `salary_min` |
| `offers.0.priceSpecification.priceCurrency` | `salary_currency` |
| `offers.0.priceSpecification.price` | `salary_min` |
| `offers.0.priceSpecification.minPrice` | `salary_min` |
| `offers.0.priceSpecification.maxPrice` | `salary_max` |
| `offers.0.priceSpecification.unitText` | `salary_unit_text` |
| `offers.0.url` | `apply_url` |
| `jobBenefits` | `job_benefits` |
| `educationRequirements` | `education_level` |
| `experienceRequirements` | `experience_requirements` |
| `applicationContact.email` | `apply_email` |
| `applicationContact.url` | `apply_url` |
| `hiringOrganization` | `company` |
| `url` | `apply_url` |

Salary values intentionally feed both the `baseSalary` object and the first `Offer` node to satisfy job board aggregators that prefer complete `Offer` pricing data alongside structured salary ranges.

## Elementor Notes

Swap the blank ID in `[elementor-template id=""]` for a saved Elementor layout to control the hero or call-to-action while the locked block group preserves consistent structure across job entries.

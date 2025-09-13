# Gm2 CP – Status & Gaps

## Feature Checklist

| Acceptance Criteria | Status | Notes |
|---------------------|--------|-------|
| CPT & Taxonomy Builder (UI + API) with export/import of JSON blueprints | Present | Admin builder and CLI export/import functions |
| Field Groups & Field Types engine, validation, conditional logic, REST visibility, Elementor Dynamic Tags | Partial | Field engine exists with many types and REST visibility but lacks Elementor dynamic tags |
| Elementor integration (dynamic tags for custom fields, query controls, template helpers, loop compatibility) | Partial | Contains Elementor widgets and SEO panel; missing dynamic tags and query controls |
| SEO/schema mapping layer per CPT, JSON-LD output for Directory, Event, RealEstateListing, JobPosting, Course, meta title/description templates, canonical/OG/Twitter | Partial | SEO meta and schema templates present; CPT-specific schema types missing |
| Preset “Blueprints” for Directory, Events, Real Estate, Jobs, Courses | Missing | Recipes documented but no bundled presets |
| Frontend submission + moderation flow; Elementor Forms action for "Create/Update Post" | Missing | No frontend submission module located |
| Import/Export (CSV/JSON) + WP-CLI | Present | Model import/export functions and CLI commands |
| Roles & capabilities per CPT; custom statuses; email notifications on submission/state changes | Partial | Capability and workflow managers exist; submission/state email flows not packaged |
| Performance & security (nonces, sanitize/escape, prepared SQL, caching) | Partial | Secure patterns found but full audit not completed |
| Multisite & i18n (wp-i18n ready strings); WPML/Polylang compatibility notes | Partial | Multisite-aware loader and translation template; no WPML/Polylang notes |
| Tests (PHPUnit + Playwright/JS), PHPCS | Partial | Extensive PHP and JS tests; PHPCS integration absent |
| Docs (README, Admin user guide, Dev hooks reference) | Present | README and documentation in `docs/` |

## Milestones

- **M1 – Elementor Field Integration**
  - Add dynamic tag & query control support for custom fields.
  - Provide archive/single template helpers and loop compatibility.

- **M2 – Schema & Blueprint Presets**
  - Implement schema mappings for Directory, Event, RealEstateListing, JobPosting, Course.
  - Ship JSON blueprints for Directory, Events, Real Estate, Jobs, Courses.

- **M3 – Frontend Submission & Workflow**
  - Build frontend submission with moderation and Elementor Form actions.
  - Expand workflow manager for submission notifications and custom statuses.

- **M4 – Polish & Compliance**
  - Document WPML/Polylang compatibility.
  - Integrate PHPCS and finalize performance/security audits.
  - Update documentation with hook references and user guides.


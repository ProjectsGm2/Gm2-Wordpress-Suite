# Blueprint Presets

Detailed breakdowns of the bundled blueprint presets, including custom post type slugs, taxonomies, field groups, schema mappings and Elementor guidance:

- [Directory](directory.md)
- [Events](events.md)
- [Real Estate](real-estate.md)
- [Jobs](jobs.md)
- [Courses](courses.md)

## Applying presets in the admin

The **Blueprint Preset Wizard** is available from **Gm2 Custom Posts → Overview**. It replaces the legacy preset dropdown and provides a richer preview and confirmation flow:

1. Open the wizard and select a preset to review its post types, taxonomies, field groups, default terms, block templates and bundled Elementor layouts.
2. Check the Elementor templates you want to import. Templates are disabled when the preset does not ship a bundle; a warning appears when Elementor is not active.
3. Click **Apply preset**. If existing definitions are detected you will be prompted to confirm the overwrite before the blueprint is applied via the `PresetManager`.
4. When Elementor templates are selected the wizard imports the JSON bundles into the `elementor_library` post type after the blueprint is installed.

The wizard is powered by `admin/Presets/Wizard.php` and the React implementation in `admin/js/preset-wizard.js`. It uses AJAX nonces and capability checks to ensure only authorised users can run blueprints or import Elementor assets.

## Modifying presets

### Understand the blueprint layout

Each preset ships with a `blueprint.json` file under `presets/<preset>/` that mirrors the structure validated by `presets/schema.json` and surfaced through the Preset Wizard.【F:README.md†L63-L70】【F:presets/schema.json†L1-L90】 The most important sections are:

- **`post_types`** – registers each custom post type, including labels, supports, rewrite settings, block templates and SEO defaults. For example, the Directory preset defines the `listing` post type with a locked block pattern and SEO metadata scaffold.【F:presets/directory/blueprint.json†L2-L70】
- **`taxonomies`** – associates hierarchical and flat taxonomies with those post types. The Directory preset ships `listing_category`, `listing_amenity` and `listing_location` configurations ready to attach to `listing`.【F:presets/directory/blueprint.json†L72-L111】
- **`field_groups`** and **`fields.groups`** – duplicate structures that describe ACF-style field groups. `field_groups` is consumed by the Preset Manager, while `fields.groups` feeds the Field API; add, rename or remove fields in both arrays to keep them in sync.【F:presets/directory/blueprint.json†L113-L280】【F:presets/directory/blueprint.json†L305-L475】
- **`relationships`, `default_terms` and schema/SEO mappings** – wire relational lookups, seed starter terms and map field values to schema.org definitions so downstream tooling keeps working.【F:presets/directory/blueprint.json†L476-L583】
- **Elementor bundles** – blueprints reference template metadata inside `templates` while the matching Elementor JSON lives beside the preset (for example `presets/directory/templates/listing-single.json` and `presets/directory/elementor/directory-hero.json`). Update the blueprint file paths when you add or rename Elementor JSON exports.【F:presets/directory/blueprint.json†L584-L603】【F:presets/directory/elementor/directory-hero.json†L1-L48】

Use a JSON-aware editor or `wp gm2 blueprint import` to validate changes against the schema so malformed data never reaches production.【F:readme.txt†L43-L50】

### Common tweaks

#### Change labels or copy

1. Open the relevant `presets/<preset>/blueprint.json` file (e.g. `presets/directory/blueprint.json`).【F:presets/directory/blueprint.json†L2-L7】
2. Locate the target `labels` array inside the post type or taxonomy you want to rename.
3. Update `name`, `singular_name`, menu labels or rewrite `slug` values as needed.
4. Save the file and rerun the Preset Wizard or CLI import to apply the new copy.

#### Add a custom field

1. In `field_groups`, append a field definition with a unique `key`, human-readable `label`, machine `name`, data `type` and any validation rules. The snippet below adds an Instagram URL to the Directory preset’s **Listing Details** group.【F:presets/directory/blueprint.json†L113-L280】

   ```json
   {
     "key": "field_instagram",
     "label": "Instagram",
     "name": "instagram",
     "type": "url",
     "instructions": "Link to the business Instagram profile.",
     "expose_in_rest": true
   }
   ```

2. Mirror the same object in `fields.groups` so the Field API and REST schema stay aligned.【F:presets/directory/blueprint.json†L305-L475】
3. If the new field feeds structured data or Elementor widgets, update `schema_mappings`, `seo_mappings` or bundled templates to surface it.【F:presets/directory/blueprint.json†L282-L603】

#### Remove a default taxonomy

1. Delete the taxonomy entry from the `taxonomies` object (for example remove `listing_amenity` from the Directory preset).【F:presets/directory/blueprint.json†L86-L111】
2. Remove any seeded terms under `default_terms` that use the same taxonomy key.【F:presets/directory/blueprint.json†L525-L541】
3. Review field conditions, Elementor queries and schema mappings to ensure nothing references the removed taxonomy before re-importing the blueprint.【F:presets/directory/blueprint.json†L282-L603】

### Deploying changes

- **Manual edits:** Commit the updated `blueprint.json` and any affected Elementor JSON to version control so teammates can review the diff. Pair edits with the schema for linting and keep mirrored field sections in sync to avoid runtime mismatches.【F:presets/schema.json†L1-L188】【F:presets/directory/blueprint.json†L113-L603】
- **WP-CLI import/export:** Export production changes for review or import local edits with `wp gm2 blueprint export` and `wp gm2 blueprint import`. Both commands validate the JSON against the same schema used by the wizard, catching mistakes early.【F:README.md†L63-L68】【F:readme.txt†L43-L50】
- **Version control workflow:** Treat CLI exports as artifacts—check them into your feature branch, run code review, and tag releases so you can roll back to a known `blueprint.json` or Elementor bundle if necessary.【F:README.md†L63-L70】【F:presets/directory/elementor/directory-hero.json†L1-L48】

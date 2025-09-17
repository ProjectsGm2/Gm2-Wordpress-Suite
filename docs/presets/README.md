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

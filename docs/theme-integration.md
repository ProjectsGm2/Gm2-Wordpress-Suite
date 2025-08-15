# Theme Integration

Enabling the **Theme Integration** option under **Gm2 → Dashboard** writes simple Twig and Blade template files to the plugin's `theme-integration/` directory. Each file lists the registered field names from your field groups using `gm2_field()` calls.

## Steps
1. Go to **Gm2 → Dashboard** and check **Theme Integration**.
2. Visit any page to trigger generation. Templates appear under `wp-content/plugins/gm2-wordpress-suite/theme-integration/`.
3. Copy the generated `.twig` or `.blade.php` files into your theme (`templates/` or `resources/views/`) and customize as needed.

These files provide a starting point for integrating field groups into Timber (Twig) or Blade based themes.

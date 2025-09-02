# Gm2 WordPress Suite

This repository contains the development version of the Gm2 WordPress Suite plugin. For plugin installation steps and feature documentation see [readme.txt](readme.txt).

The suite includes an optional **Pretty Versioned URLs** feature that rewrites `file.css?ver=123` to `file.v123.css` and supplies matching Apache and Nginx rules.

## Building the Plugin

Generate a production-ready ZIP package with all dependencies using:

```bash
bash bin/build-plugin.sh
```

This script creates a `gm2-wordpress-suite.zip` file that includes the plugin and its bundled dependencies.

### Rebuilding Optimizer assets

The Render Optimizer JavaScript is bundled with Rollup. After modifying files in `assets/src/optimizer`, regenerate the distributable scripts with:

```bash
npm run build
# or
make build
```

The generated files in `assets/dist` should be committed to version control.

## AI Providers

The suite can generate content using multiple AI services. Select **ChatGPT**, **Gemma**, or **Llama** from the **Gm2 → AI Settings** page and enter the corresponding API key and optional endpoint. The chosen provider is used throughout the plugin for AI-powered features.

### Running Gemma Locally

The plugin can invoke a local Gemma binary instead of a remote API.

**Requirements**

- The Gemma inference binary installed on the server (defaults to `/usr/local/bin/gemma`).
- A downloaded Gemma model file accessible to PHP.

**Configuration**

1. Open **Gm2 → AI Settings** and choose **Gemma (Local)**.
2. Provide the full paths to the model and inference binary.
3. Save the settings.

**Performance considerations**

Running Gemma locally is CPU/GPU and memory intensive. Each request executes the binary and blocks until it finishes, so low-powered or shared hosts may experience slowdowns. Use dedicated hardware or offload heavy tasks to background jobs if possible.

## Running Tests

The PHPUnit tests rely on the official WordPress test suite. Before running the tests you must install the suite using the helper script provided in `bin`.

### Prerequisites

 - **PHP** 8.0 or higher
- **Composer** for installing PHPUnit
- **Node.js** and **npm** for running JavaScript tests

Install PHPUnit globally with Composer if it is not already available:

```bash
composer global require phpunit/phpunit
```

Make sure `~/.composer/vendor/bin` (or your global Composer bin directory) is on your `PATH`.
Run `npm install` once to install the JavaScript test dependencies.

### Installing the WordPress test suite

Run the following command once using your database credentials:

```bash
bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
```

This downloads WordPress and configures the test database. By default the script
places the suite in `/tmp/wordpress-tests-lib`. If you want to install it in a
different location, set the `WP_TESTS_DIR` environment variable before running
the script and when executing the tests so that
`tests/bootstrap.php` can locate the files:

```bash
export WP_TESTS_DIR=/path/to/wordpress-tests-lib
bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
```

If the directory does not exist when `phpunit` runs, the bootstrap script will
fail with an error similar to:

```
Fatal error: Uncaught Error: Failed opening required '/tmp/wordpress-tests-lib/includes/functions.php'
```

### Running the tests

After the test suite is installed, execute:

```bash
phpunit
```

JavaScript tests live in `tests/js` and are executed with:

```bash
# Install dependencies if you haven't already
npm install

# Run the test suite
npm test
```

The Makefile includes a `test` target which automatically checks for the test suite and runs PHPUnit and the Jest tests. When invoking this target you must supply your database credentials via the `DB_NAME`, `DB_USER`, and `DB_PASS` environment variables:

```bash
make test DB_NAME=wp_test DB_USER=root DB_PASS=pass
```

## Automatic Asset Versioning

Local stylesheets and scripts loaded through WordPress are automatically
versioned with their file modification time. This ensures browsers receive
updated assets whenever a file changes.

To skip this behavior for a specific handle, pass `['no_auto_version' => true]`
as the final argument when calling `wp_register_style()` or
`wp_register_script()`. The original `ver` value will then be preserved.

## Script Attributes

Control how front-end scripts load by assigning **Blocking**, **Defer**, or
**Async** attributes per handle. Handles default to `defer`, but the plugin
walks WordPress dependencies: a handle becomes blocking if any dependency is
blocking, and it loses its attribute when a dependency is marked `async` or
otherwise non-deferred.

Use the presets on **SEO → Performance → Script Loading**:

* **Defer all third-party** – marks WordPress core handles as blocking and
  defers everything else.
* **Conservative** – only sets core handles to blocking, letting other scripts
  fall back to the default `defer`.

Scripts that call `document.write` or expect synchronous execution should stay
blocking. Deferring these scripts can break page output or tracking snippets.

## Render Optimizer

The Render Optimizer groups several front-end performance features behind **SEO → Performance → Render Optimizer**. Enable modules individually to:

* Inline critical CSS and preload full stylesheets.
* Defer or async scripts with an enable toggle plus handle and domain allow/deny lists.
* Serve modern and legacy JavaScript bundles using `<script type="module" crossorigin="anonymous">` and `<script nomodule crossorigin="anonymous">`. Differential serving is enabled by default (`ae_seo_ro_enable_diff_serving`), and module scripts remain blocking even when JS deferral is active.
* Combine and minify local CSS and JS assets with per-type toggles, size caps and exclusion lists.

### Critical CSS

Inline above-the-fold styles and load remaining CSS asynchronously.

**Strategies**

* `per_home_archive_single` – store snippets for the home page, archives and each post type.
* `per_url_cache` – hash the current URL to map CSS per page.

**Async methods**

* `preload_onload` (default) outputs a `<link rel="preload">` tag that switches to `rel="stylesheet"` and includes a `<noscript>` fallback.
* `media_print` loads stylesheets with `media="print"` and changes to `all` on load.

**Exclusions**

Provide handles or patterns to skip. Editor, dashicons, admin-bar and WooCommerce inline styles are ignored automatically.

### Critical CSS CLI Build

Generate and refresh critical CSS from the command line:

```bash
wp ae-seo critical:build
```

The command queues home, archive and recent single URLs in the `ae_seo_ro_critical_job` transient and invokes the Node `critical` package via `shell_exec`. Install the dependency globally with:

```bash
npm i -g critical
```

Each run populates the `ae_seo_ro_critical_css_map` option and never executes during front‑end requests. If snippets become outdated, clear the transient or delete the map—using the **Purge Critical CSS** button or WP‑CLI—to force a rebuild.

### JavaScript Deferral

Toggle script deferral on or off and maintain allow and deny lists for specific handles and hostnames. List analytics domains like `www.googletagmanager.com` or `www.google.com/recaptcha` to always load asynchronously. The **Respect in footer** option keeps footer scripts at the bottom unless allowlisted. Inline blocks are parsed to detect dependencies automatically and jQuery remains blocking when early inline usage is detected.

### Combination and Minification

Toggle CSS and JS combination independently. Local files under the per‑file size limit are merged until a bundle cap is reached, and handles, hostnames or regex patterns in the exclusion lists remain separate. Generated bundles are stored in `wp-content/uploads/ae-seo/optimizer/`, and a **Purge Combined Files** button removes them. Combining assets may cause compatibility issues and offers little benefit on HTTP/2 or HTTP/3 servers.

**Purge workflow**

Use the **Purge Critical CSS** and **Purge JS Map** buttons on the Render Optimizer screen to rebuild caches after changing themes or script settings. After purging, clear any page, opcode or CDN caches and acceptance-test the site: load key pages, check the browser console and verify forms, logins and checkout flows work as expected.

To confirm differential serving, open the site in a modern browser and ensure only the `optimizer-modern.js` module bundle executes. Test again in an older or emulated legacy browser and verify only `optimizer-legacy.js` runs.

### Runtime Filters

The Render Optimizer can be toggled at runtime with two filters:

* `ae_seo_ro_enable_for_logged_in` &mdash; return `true` to process optimizer features for logged-in users instead of skipping them. This allows administrators to see the front end exactly as visitors do.
* `ae_seo_ro_skip_url` &mdash; receives the current URL and lets you bypass optimization on matching paths, such as preview pages.

### AJAX Purge Buttons

Four buttons on the Render Optimizer screen issue AJAX requests to clear caches: **Purge & Rebuild Critical CSS**, **Purge & Rebuild JS Map**, **Purge Combined Assets**, and **Clear Diagnostics**. Each action verifies the caller has the `manage_options` capability and returns an escaped confirmation message. Purging Critical CSS also resets the JS map and combined asset cache, while purging the JS map clears its dependencies. Purging combined assets flushes generated bundles and related maps, and clearing diagnostics removes any logged entries.

### Optimizer Diagnostics Panel

The diagnostic table lists request-level logs for optimizer decisions with columns for type, handle, bundle and reason. Use it to determine why a script or style was bundled, deferred or skipped. All output is escaped for safety, and the **Clear Diagnostics** button (also requiring `manage_options`) resets the log so a fresh page load repopulates it.

The optimizer automatically disables its features when popular optimization plugins like WP&nbsp;Rocket, Autoptimize or Perfmatters are active. If WordPress's `is_plugin_active()` helper isn't available, the conflict check is skipped to avoid errors. Only one optimization plugin should run at a time.

After adjusting the source files in `assets/src/optimizer`, rebuild the distributed scripts as noted in [Rebuilding Optimizer assets](#rebuilding-optimizer-assets).

## Cache Headers

On Apache or LiteSpeed the plugin automatically inserts long-lived caching
rules into `.htaccess` using the `SEO_PLUGIN_CACHE_HEADERS` marker. Generated
directives enable `Expires` and `Cache-Control` headers for common static assets
like stylesheets, scripts, images and fonts. If the file is not writable the
rules are returned so they can be added manually.

## Remote Script Mirroring

The **Remote Mirror** feature caches third-party tracking scripts locally and rewrites
their URLs on the front end. Enable vendors like Facebook Pixel or Google gtag with
simple checkboxes, view the SHA-256 hash for each mirrored file to use in SRI
attributes, and rely on the built-in daily refresh to keep copies current.

## Sitemap Path Option

The plugin stores the generated XML sitemap at `sitemap.xml` in the WordPress
root directory. You can change this location by setting the `gm2_sitemap_path`
option on the **SEO → Sitemap** settings page.

Use the `gm2_sitemap_max_urls` option to limit how many URLs are written to each
sitemap file. When a file reaches this number the plugin creates additional
files like `sitemap-1.xml` and `sitemap-2.xml` and updates the index at
`sitemap.xml`.

## WP-CLI Commands

The suite exposes a `gm2` command group when run under WP-CLI.

Generate the sitemap:

```bash
wp gm2 sitemap generate
```

Clear cached AI data and AI provider logs:

```bash
wp gm2 ai clear
```

Generate PHP code for saved models:

```bash
wp gm2 model generate [--mu-plugin] [--php=<file>] [--json=<file>]
```

This writes `register_post_type()`, `register_taxonomy()` and field bootstrap
code to disk. Use `--mu-plugin` to output to the `mu-plugins` directory. The
generated PHP and JSON are also surfaced via `gm2_render_open_in_code()` for
easy download in the admin UI.

## Google Merchant Centre Real-Time Updates

The plugin tracks real-time product updates for Google Merchant Centre. Price,
availability and inventory changes are cached and exposed via the REST
endpoint `/gm2/v1/gmc/realtime`. The front-end script polls this endpoint and
dispatches a `gm2GmcRealtimeUpdate` event with the latest data.

Developers can customize which fields are monitored by filtering the
`gm2_gmc_realtime_fields` list:

```php
add_filter( 'gm2_gmc_realtime_fields', function( $fields ) {
    $fields[] = 'sale_price';
    return $fields;
} );
```

## JSON-LD Template Placeholders

JSON-LD templates support the following placeholder tokens. These are replaced
with dynamic values when schemas are generated:

- `{{title}}` – Post or term title
- `{{permalink}}` – Permalink URL
- `{{description}}` – SEO description or excerpt
- `{{image}}` – Featured image URL
- `{{price}}` – Product price
- `{{price_currency}}` – Currency code
- `{{availability}}` – Stock availability URL
- `{{sku}}` – Product SKU
- `{{brand}}` – Brand name
- `{{rating}}` – Review rating value

Use these placeholders within the JSON-LD template editor on the SEO settings
page to insert dynamic content.

## Bulk AI Review

The **Bulk AI Review** page lists posts with AI-generated SEO Title, Description,
Focus Keywords and Long Tail Keywords suggestions. Use the **Export CSV** button
to download a `gm2-bulk-ai.csv` file containing these columns.

## Bulk AI for Taxonomies

The **Bulk AI Taxonomies** page under **Gm2 → Bulk AI Taxonomies** lists terms
from supported taxonomies like categories and WooCommerce product categories.
Select multiple terms to generate AI SEO titles and descriptions in bulk then
apply the suggestions with one click.

Use the checkboxes **Only terms missing SEO Title** and **Only terms missing Description** to limit the list to terms missing those fields. The plugin remembers your selections.

By default this page requires the `manage_categories` capability. Developers can
override the required capability using the following filter:

```php
add_filter( 'gm2_bulk_ai_tax_capability', function() { return 'edit_posts'; } );
```

Click the **Export CSV** button to download matching terms via
`admin-post.php?action=gm2_bulk_ai_tax_export`. The resulting
`gm2-bulk-ai-tax.csv` file includes `term_id`, `name`, `seo_title`,
`description` and `taxonomy` columns.

### Scheduling and Cancelling Batches

Selected terms can also be queued for background processing. Use **Schedule Batch**
to process the items via WP-Cron or **Cancel Batch** to clear any pending jobs.

## Abandoned Carts Module

Enable this feature from **Gm2 → Dashboard** to create two database tables used
for tracking carts and queuing recovery emails. The cart table stores the cart
contents along with the shopper's email address, IP, detected location and
device type, the first and last URLs visited, cart total, total browsing time,
and how many times the shopper returned to the cart. A small JavaScript file
captures the email as soon as it is entered on the checkout page so the address
is available even if the customer never completes the order. It also records
when a cart becomes active or abandoned as the visitor browses the site.

The script listens for `beforeunload`, `visibilitychange` and `pagehide` events
to mark a cart abandoned when the last browser tab closes. Some older browsers
may ignore these events or block background requests, which can prevent the
notification from reaching WordPress.

The admin screen under **Gm2 → Abandoned Carts** displays a table of carts and
their status—active or abandoned—showing the IP address, email, location, device,
products, cart value, entry and exit URLs, browsing time, and revisit count.
Opening a cart's activity log reveals a per-visit trail with the returning IP
address, entry and exit pages, and timestamps for each session. Recovery emails
are planned to be queued and processed by WP&nbsp;Cron via the `gm2_ac_process_queue`
action, but this feature is currently disabled.

## Cache Audit

The **Cache Audit** screen under **SEO → Performance → Cache Audit** scans the
homepage and all enqueued scripts and styles to analyze caching headers for
scripts, styles, images, fonts and other resources. Each asset is requested via
`HEAD` to record its TTL, `Cache-Control`, `ETag`, `Last-Modified` and size.
Assets are flagged as **Needs Attention** when they lack a `Cache-Control`
header, use a `max-age` under seven days, include a versioned URL without
`immutable`, or omit both `ETag` and `Last-Modified` headers.

Filter the table by asset type, host or status, click **Re-scan** to refresh
results, or **Export CSV** to download `gm2-cache-audit.csv`. On multisite, a
network admin can switch sites from a dropdown and audit each site
individually. Access requires the `manage_options` capability (`manage_network`
for multisite) and the last scan is stored in the
`gm2_cache_audit_results` option with a `scanned_at` timestamp.

A small panel on this screen offers quick copy-ready checks:

```
curl -I https://example.com/wp-includes/js/jquery/jquery.min.js
```

Verify the `Cache-Control` header matches expectations. For repeat-view testing,
open your browser DevTools with **Disable cache** unchecked, perform a hard
reload, and confirm the file loads from disk cache.

## JavaScript Manager and Auto-Dequeue (Beta)

AE_SEO_JS_Detector builds a transient map of registered scripts and records the page type, widgets and enqueued handles for each front-end request. AE_SEO_JS_Controller reads that context and dequeues scripts not seen for the current URL.

Settings live under **SEO → Performance → JavaScript** to enable the manager, lazy-loading, script replacements, debug logging, handle allow and deny lists and an optional safe-mode query parameter. Per-page auto-dequeue remains in beta—test on staging and use the allowlist, denylist or `?aejs=off` parameter if a handle is removed incorrectly.

The **SEO → Script Usage** page lists discovered script handles with counts per template so you can accept or override which templates require each script before relying on auto-dequeue.

## SEO Performance CLI

Run `wp seo-perf` commands to audit a site and manage caching headers.

- `wp seo-perf audit` – Runs an AI-powered audit and prints JSON
  recommendations.
- `wp seo-perf apply-htaccess` – Writes cache headers into `.htaccess`.
- `wp seo-perf generate-nginx` – Generates an Nginx include file with
  caching rules.
- `wp seo-perf clear-markers` – Removes `.htaccess` markers and the Nginx
  file.

### Usage

```bash
wp seo-perf audit > audit.json
wp seo-perf apply-htaccess
wp seo-perf generate-nginx
wp seo-perf clear-markers
```

`audit` outputs a JSON object describing issues and recommendations, for
example:

```json
{
  "issues": ["Missing Cache-Control on images"],
  "recommendations": ["Add long-lived headers for static assets"]
}
```

All commands are multisite-aware. Pass `--url=<site>` to target a specific
site.

### Exit codes

| Command | Code | Meaning |
|---------|------|---------|
| `audit` | 0 | Success |
|         | 1 | AI utilities missing or underlying error code |
| `apply-htaccess` | 0 | Success |
|         | 1 | Unsupported server |
|         | 2 | CDN already sets headers |
|         | 3 | `.htaccess` not writable |
|         | 4 | Unknown result |
| `generate-nginx` | 0 | Success |
|         | 1 | Unsupported server |
|         | 2 | CDN already sets headers |
|         | 3 | Directory not writable |
|         | 4 | Unknown result |
| `clear-markers` | 0 | Success |
|                | 1 | Could not remove generated file |

WP-CLI prints an error message and exits with the code above. Review the
message to resolve permission or server issues before retrying.




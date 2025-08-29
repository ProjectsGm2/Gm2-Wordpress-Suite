# Gm2 WordPress Suite

This repository contains the development version of the Gm2 WordPress Suite plugin. For plugin installation steps and feature documentation see [readme.txt](readme.txt).

## Building the Plugin

Generate a production-ready ZIP package with all dependencies using:

```bash
bash bin/build-plugin.sh
```

This script creates a `gm2-wordpress-suite.zip` file that includes the plugin and its bundled dependencies.

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




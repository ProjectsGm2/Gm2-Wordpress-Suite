# Gm2 WordPress Suite

This repository contains the development version of the Gm2 WordPress Suite plugin. For plugin installation steps and feature documentation see [readme.txt](readme.txt).

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

Clear cached AI data and ChatGPT logs:

```bash
wp gm2 ai clear
```

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

Selected terms can also be queued for background processing. Use **Schedule Batch**
to process the items via WP-Cron or **Cancel Batch** to clear any pending jobs.

## Abandoned Carts Module

Enable this feature from **Gm2 → Dashboard** to create two database tables used
for tracking carts and queuing recovery emails. The cart table stores the cart
contents along with the shopper's email address, IP, detected location and
device type, the first and last URLs visited, and the cart total. A small
JavaScript file captures the email as soon as it is entered on the checkout
page so the address is available even if the customer never completes the
order.

The admin screen under **Gm2 → Abandoned Carts** displays a table of abandoned
carts showing the IP address, email, location, device, products, cart value,
entry and exit URLs, and the abandonment time. Recovery emails are added to a
queue which is processed hourly by WP&nbsp;Cron via the `gm2_ac_process_queue`
action. Developers can hook `gm2_ac_send_message` to send the actual email
content. The cart timeout in minutes can be configured on the same settings
page.




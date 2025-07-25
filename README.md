# Gm2 WordPress Suite

This repository contains the development version of the Gm2 WordPress Suite plugin. For plugin installation steps and feature documentation see [readme.txt](readme.txt).

## Running Tests

The PHPUnit tests rely on the official WordPress test suite. Before running the tests you must install the suite using the helper script provided in `bin`.

### Prerequisites

- **PHP** 7.3 or higher
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

## Abandoned Carts Module

When enabled from the Gm2 dashboard, the plugin tracks WooCommerce carts and
sends recovery emails after a configurable timeout. Configure the timeout and
message schedule under **Gm2 → Abandoned Carts**.




# Contributing

This project includes a PHPUnit test suite that relies on the WordPress test library.

## Installation

1. Install development dependencies using [Composer](https://getcomposer.org):

   ```bash
   composer install
   ```

   This command installs packages under the `vendor/` directory. On production
   systems you can run `composer install --no-dev` to omit development-only
   packages.

2. Set up the WordPress testing suite. This script installs WordPress and configures the test environment:

   ```bash
   bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
   ```

    Replace the placeholders with your local MySQL credentials. Run the script once before executing the tests.

## WordPress Test Suite

The PHPUnit tests depend on the WordPress testing suite. The installation script above downloads it automatically. You can also check out the suite manually, for example:

```bash
svn co https://develop.svn.wordpress.org/trunk/tests/phpunit $WP_TESTS_DIR
```

Set the `WP_TESTS_DIR` environment variable to the directory where the suite resides. PHPUnit tests cannot run without this directory. If the variable is not set, `tests/bootstrap.php` falls back to `/tmp/wordpress-tests-lib`.

## Running Tests

After the dependencies and test suite are installed, run the tests. Ensure that
`WP_TESTS_DIR` points to the installed suite, for example:

```bash
WP_TESTS_DIR=/path/to/wordpress-tests-lib composer run test
```

This command executes the PHPUnit tests located in the `tests/` directory.

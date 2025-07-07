# Contributing

This project includes a PHPUnit test suite that relies on the WordPress test library.

## Installation

1. Install development dependencies using [Composer](https://getcomposer.org):

   ```bash
   composer install
   ```

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

Set the `WP_TESTS_DIR` environment variable to the directory where the suite resides. PHPUnit tests cannot run without this directory.

## Running Tests

After the dependencies and test suite are installed, run:

```bash
composer run test
```

This will execute the PHPUnit tests located in the `tests/` directory.

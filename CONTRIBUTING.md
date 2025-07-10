# Contributing

This project includes a PHPUnit test suite that relies on the WordPress test library. PHPUnit is expected to be installed globally, so the `phpunit` command is available on your system path.

## Installation

1. Install PHPUnit through your package manager so the `phpunit` command is available globally.
2. Set up the WordPress testing suite. This script downloads WordPress and configures the test environment:

   ```bash
   bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
   ```

   Replace the placeholders with your local MySQL credentials. Run the script once before executing the tests.

## WordPress Test Suite

The PHPUnit tests depend on the WordPress testing suite. The installation script above downloads it automatically from GitHub. You can also fetch the suite manually if you prefer:

```bash
curl -L https://codeload.github.com/WordPress/wordpress-develop/tar.gz/refs/heads/trunk | \
  tar -xz --strip-components=1 wordpress-develop-*/tests/phpunit -C "$WP_TESTS_DIR"
```

Set the `WP_TESTS_DIR` environment variable to the directory where the suite resides. PHPUnit tests cannot run without this directory. If the variable is not set, `tests/bootstrap.php` falls back to `/tmp/wordpress-tests-lib`.

## Running Tests

After the test suite is installed, run the tests. Ensure that
`WP_TESTS_DIR` points to the installed suite, for example:

```bash
WP_TESTS_DIR=/path/to/wordpress-tests-lib phpunit
```

This command executes the PHPUnit tests located in the `tests/` directory.

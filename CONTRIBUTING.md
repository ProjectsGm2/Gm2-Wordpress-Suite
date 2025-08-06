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

After the test suite is installed, run the tests. Ensure that `WP_TESTS_DIR`
points to the installed suite if you used a custom location. You can invoke the
Makefile or run PHPUnit directly, for example:

```bash
make test
# or
WP_TESTS_DIR=/path/to/wordpress-tests-lib phpunit
```

This command executes the PHPUnit tests located in the `tests/` directory.

JavaScript unit tests live in `tests/js` and use Jest. Install the Node
dependencies once and execute the suite with:

```bash
npm install
npm test
```

### Database credentials

Before running the test suite via `make test`, export your database connection
details as environment variables:

```
export DB_NAME=<database>
export DB_USER=<user>
export DB_PASS=<password>
export DB_HOST=<host>
```

These variables are required so that `bin/install-wp-tests.sh` can create the
database used by WordPress. If they are omitted, the Makefile will invoke the
script with missing parameters and you'll encounter an error.

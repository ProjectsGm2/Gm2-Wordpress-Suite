# Gm2 WordPress Suite

This repository contains the development version of the Gm2 WordPress Suite plugin. For plugin installation steps and feature documentation see [readme.txt](readme.txt).

## Running Tests

The PHPUnit tests rely on the official WordPress test suite. Before running the tests you must install the suite using the helper script provided in `bin`.

### Prerequisites

- **PHP** 7.4 or higher
- **Composer** for installing PHPUnit

Install PHPUnit globally with Composer if it is not already available:

```bash
composer global require phpunit/phpunit
```

Make sure `~/.composer/vendor/bin` (or your global Composer bin directory) is on your `PATH`.

### Installing the WordPress test suite

Run the following command once using your database credentials:

```bash
bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
```

This downloads WordPress and configures the test database. The script uses `$WP_TESTS_DIR` or defaults to `/tmp/wordpress-tests-lib`.

### Running the tests

After the test suite is installed, execute:

```bash
phpunit
```

The Makefile includes a `test` target which automatically checks for the test suite and runs PHPUnit:

```bash
make test
```




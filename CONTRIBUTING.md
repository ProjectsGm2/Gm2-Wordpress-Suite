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

## Running Tests

After the dependencies and test suite are installed, run:

```bash
composer run test
```

This will execute the PHPUnit tests located in the `tests/` directory.

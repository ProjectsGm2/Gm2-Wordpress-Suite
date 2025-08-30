.PHONY: test install-tests check-tests docs build

WP_TESTS_DIR ?= $(TMPDIR)/wordpress-tests-lib

check-tests:
	@if [ ! -f "$(WP_TESTS_DIR)/includes/functions.php" ]; then \
	        if [ -z "$(DB_NAME)" ] || [ -z "$(DB_USER)" ] || [ -z "$(DB_PASS)" ]; then \
	                echo "Database credentials must be supplied via DB_NAME, DB_USER and DB_PASS."; \
	                echo "Example: make test DB_NAME=wp_test DB_USER=root DB_PASS=pass"; \
	                exit 1; \
	        fi; \
	        echo "WordPress test suite not found. Installing..."; \
	        $(MAKE) install-tests; \
	fi

test: check-tests
	phpunit
	npm test

install-tests:
        bash bin/install-wp-tests.sh $(DB_NAME) $(DB_USER) $(DB_PASS) $(DB_HOST) $(WP_VERSION)


build:
        npm run build

docs:
        node bin/generate-hooks-docs.js

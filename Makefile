.PHONY: test install-tests check-tests

WP_TESTS_DIR ?= $(TMPDIR)/wordpress-tests-lib

check-tests:
	@if [ ! -f "$(WP_TESTS_DIR)/includes/functions.php" ]; then \
		echo "WordPress test suite not found. Installing..."; \
		$(MAKE) install-tests; \
	fi

test: check-tests
	phpunit
	npm test

install-tests:
	bash bin/install-wp-tests.sh $(DB_NAME) $(DB_USER) $(DB_PASS) $(DB_HOST) $(WP_VERSION)

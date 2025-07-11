.PHONY: test install-tests

test:
	phpunit

install-tests:
	bash bin/install-wp-tests.sh $(DB_NAME) $(DB_USER) $(DB_PASS) $(DB_HOST) $(WP_VERSION)

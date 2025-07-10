#!/usr/bin/env bash

if [ $# -lt 3 ]; then
    echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
    exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress/}

download() {
    if [ "$(command -v curl)" ]; then
        curl -sL "$1" -o "$2"
    elif [ "$(command -v wget)" ]; then
        wget -nv -O "$2" "$1"
    fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
    WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
    if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
        WP_TESTS_TAG="tags/${WP_VERSION%??}"
    else
        WP_TESTS_TAG="tags/$WP_VERSION"
    fi
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
    WP_TESTS_TAG="trunk"
else
    LATEST_VERSION=$(curl -s https://api.github.com/repos/WordPress/WordPress/tags?per_page=1 | grep -o '"name": "[^"]*"' | head -1 | sed 's/"name": "\([^"]*\)"/\1/')
    if [[ -z "$LATEST_VERSION" ]]; then
        echo "Latest WordPress version could not be found"
        exit 1
    fi
    WP_VERSION=$LATEST_VERSION
    WP_TESTS_TAG="tags/$LATEST_VERSION"
fi

set -ex

install_wp() {
    if [ -d "$WP_CORE_DIR" ]; then
        return
    fi

    mkdir -p "$WP_CORE_DIR"

    local DOWNLOAD_TAG
    if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
        DOWNLOAD_TAG="master"
    else
        DOWNLOAD_TAG="$WP_VERSION"
    fi

    download "https://codeload.github.com/WordPress/WordPress/tar.gz/$DOWNLOAD_TAG" "$TMPDIR/wordpress.tar.gz"
    tar --strip-components=1 -xzf "$TMPDIR/wordpress.tar.gz" -C "$WP_CORE_DIR"

    download https://raw.githubusercontent.com/markoheijnen/wp-mysqli/master/db.php "$WP_CORE_DIR/wp-content/db.php"
}

install_test_suite() {
    if [[ $(uname -s) == 'Darwin' ]]; then
        local ioption='-i .bak'
    else
        local ioption='-i'
    fi

    if [ ! -d "$WP_TESTS_DIR" ]; then
        mkdir -p "$WP_TESTS_DIR"
        local REF
        if [[ $WP_TESTS_TAG == branches/* ]]; then
            REF="refs/heads/${WP_TESTS_TAG#branches/}"
        elif [[ $WP_TESTS_TAG == tags/* ]]; then
            REF="refs/tags/${WP_TESTS_TAG#tags/}"
        else
            REF="refs/heads/$WP_TESTS_TAG"
        fi
        download "https://codeload.github.com/WordPress/wordpress-develop/tar.gz/$REF" "$TMPDIR/wordpress-develop.tar.gz"
        mkdir -p "$TMPDIR/wordpress-develop"
        tar --strip-components=1 -xzf "$TMPDIR/wordpress-develop.tar.gz" -C "$TMPDIR/wordpress-develop"
        cp -R "$TMPDIR/wordpress-develop/tests/phpunit/includes" "$WP_TESTS_DIR"
        cp -R "$TMPDIR/wordpress-develop/tests/phpunit/data" "$WP_TESTS_DIR"
    fi

    if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
        download "https://raw.githubusercontent.com/WordPress/wordpress-develop/${WP_TESTS_TAG#*/}/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
        WP_CORE_DIR=$(echo "$WP_CORE_DIR" | sed "s:/\+$::")
        sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR/wp-tests-config.php"
        sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
        sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
        sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
        sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
    fi
}

install_db() {
    if [ ${SKIP_DB_CREATE} = "true" ]; then
        return 0
    fi

    local PARTS=(${DB_HOST//:/ })
    local DB_HOSTNAME=${PARTS[0]}
    local DB_SOCK_OR_PORT=${PARTS[1]}
    local EXTRA=""

    if ! [ -z $DB_HOSTNAME ] ; then
        if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
            EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
        elif ! [ -z $DB_SOCK_OR_PORT ] ; then
            EXTRA=" --socket=$DB_SOCK_OR_PORT"
        elif ! [ -z $DB_HOSTNAME ] ; then
            EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
        fi
    fi

    mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

install_wp
install_test_suite
install_db

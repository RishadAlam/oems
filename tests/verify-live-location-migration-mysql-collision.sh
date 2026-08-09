#!/bin/sh

# Regression harness: a failed verifier CREATE DATABASE must not remove a
# pre-existing disposable collision target that contains sentinel data.
set -eu

mysql_host="${OEMS_MIGRATION_TEST_HOST:-127.0.0.1}"
mysql_port="${OEMS_MIGRATION_TEST_PORT:-3306}"
mysql_user="${OEMS_MIGRATION_TEST_USER:-root}"
mysql_password="${OEMS_MIGRATION_TEST_PASSWORD:-}"
database="oems_live_location_collision_$$_$(date +%s)"
database_owned=false

mysql_run() {
    MYSQL_PWD="$mysql_password" mysql --protocol=TCP --host="$mysql_host" --port="$mysql_port" --user="$mysql_user" "$@"
}

cleanup() {
    if [ "$database_owned" = true ]; then
        mysql_run --execute="DROP DATABASE IF EXISTS \`$database\`" >/dev/null 2>&1 || true
    fi
}

trap cleanup 0 1 2 3 15

mysql_run --execute="CREATE DATABASE \`$database\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
database_owned=true
mysql_run "$database" --execute="CREATE TABLE sentinel (marker VARCHAR(32) NOT NULL); INSERT INTO sentinel (marker) VALUES ('survives')"

if OEMS_MIGRATION_TEST_MYSQL=1 \
    OEMS_MIGRATION_TEST_HOST="$mysql_host" \
    OEMS_MIGRATION_TEST_PORT="$mysql_port" \
    OEMS_MIGRATION_TEST_USER="$mysql_user" \
    OEMS_MIGRATION_TEST_PASSWORD="$mysql_password" \
    OEMS_MIGRATION_TEST_DATABASE="$database" \
    sh tests/verify-live-location-migration-mysql.sh >/dev/null 2>&1; then
    echo 'Expected verifier database creation to fail for the pre-existing collision target.' >&2
    exit 1
fi

marker="$(mysql_run --batch --skip-column-names "$database" --execute='SELECT marker FROM sentinel')"

if [ "$marker" != 'survives' ]; then
    echo 'The failed verifier removed or changed the pre-existing sentinel database.' >&2
    exit 1
fi

echo "Native MySQL collision cleanup regression passed for $database; sentinel survived and harness cleanup is automatic."

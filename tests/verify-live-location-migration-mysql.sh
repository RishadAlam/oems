#!/bin/sh

# Opt-in native MySQL verification. It imports the populated 90cb666 baseline
# into a unique disposable database, runs the migration twice, and removes only
# a database it created on every exit path.
set -eu

if [ "${OEMS_MIGRATION_TEST_MYSQL:-}" != "1" ]; then
    echo 'Set OEMS_MIGRATION_TEST_MYSQL=1 to run the disposable native MySQL migration verification.' >&2
    exit 2
fi

mysql_host="${OEMS_MIGRATION_TEST_HOST:-127.0.0.1}"
mysql_port="${OEMS_MIGRATION_TEST_PORT:-3306}"
mysql_user="${OEMS_MIGRATION_TEST_USER:-root}"
mysql_password="${OEMS_MIGRATION_TEST_PASSWORD:-}"
database="${OEMS_MIGRATION_TEST_DATABASE:-oems_live_location_verify_$$_$(date +%s)}"
database_owned=false

case "$database" in
    oems_live_location_*) ;;
    *)
        echo 'The native verifier only accepts disposable oems_live_location_* database names.' >&2
        exit 2
        ;;
esac

case "$database" in
    *[!a-z0-9_]*|'')
        echo 'The native verifier database name may contain only lowercase letters, digits, and underscores.' >&2
        exit 2
        ;;
esac

mysql_run() {
    MYSQL_PWD="$mysql_password" mysql --protocol=TCP --host="$mysql_host" --port="$mysql_port" --user="$mysql_user" "$@"
}

cleanup() {
    if [ "$database_owned" = true ]; then
        mysql_run --execute="DROP DATABASE IF EXISTS \`$database\`" >/dev/null 2>&1 || true
    fi
}

trap cleanup 0 1 2 3 15

expect() {
    actual="$1"
    expected="$2"
    description="$3"

    if [ "$actual" != "$expected" ]; then
        echo "Expected $description to be $expected; received $actual." >&2
        exit 1
    fi
}

mysql_run --execute="CREATE DATABASE \`$database\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
database_owned=true
git show 90cb666:database/schema.sql | mysql_run "$database" >/dev/null
git show 90cb666:database/seed.sql | mysql_run "$database" >/dev/null
git show 90cb666:database/demo_seed.sql | mysql_run "$database" >/dev/null

scalar() {
    mysql_run --batch --skip-column-names "$database" --execute="$1"
}

events_before="$(scalar 'SELECT COUNT(*) FROM events')"
partial_venue_id="$(scalar 'SELECT id FROM venues ORDER BY id ASC LIMIT 1')"
mysql_run "$database" --execute="UPDATE venues SET latitude = 23.8123456, longitude = NULL WHERE id = $partial_venue_id"
partial_identity_before="$(scalar "SELECT CONCAT_WS('|', name, address_line, city, country, COALESCE(postal_code, ''), COALESCE(capacity, '')) FROM venues WHERE id = $partial_venue_id")"
expect "$(scalar 'SELECT COUNT(*) FROM venues WHERE (latitude IS NULL) <> (longitude IS NULL)')" '1' 'representative legacy partial coordinate pair'

mysql_run "$database" < database/migrations/2026-08-09-live-location.sql >/dev/null
mysql_run "$database" < database/migrations/2026-08-09-live-location.sql >/dev/null

public_defaults_query="SELECT COUNT(*) FROM events WHERE location_visibility = 'public'"
event_columns_query="SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = 'events' AND COLUMN_NAME IN ('location_visibility', 'arrival_notes')"
coordinate_index_query="SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = 'venues' AND INDEX_NAME = 'idx_venues_coordinates'"
coordinate_check_query="SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '$database' AND TABLE_NAME = 'venues' AND CONSTRAINT_NAME = 'chk_venues_coordinate_pair' AND CONSTRAINT_TYPE = 'CHECK'"
cache_table_query="SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = 'geocoding_cache'"

expect "$(scalar 'SELECT COUNT(*) FROM events')" "$events_before" 'preserved baseline event count'
expect "$(scalar "$public_defaults_query")" "$events_before" 'public event visibility defaults'
expect "$(scalar "$event_columns_query")" '2' 'live location event columns'
expect "$(scalar "$coordinate_index_query")" '2' 'venue coordinate index columns'
expect "$(scalar "$coordinate_check_query")" '1' 'venue coordinate pair check'
expect "$(scalar "$cache_table_query")" '1' 'geocoding cache table'
expect "$(scalar 'SELECT COUNT(*) FROM venues WHERE (latitude IS NULL) <> (longitude IS NULL)')" '0' 'reconciled partial coordinate pairs'
expect "$(scalar "SELECT CONCAT_WS('|', name, address_line, city, country, COALESCE(postal_code, ''), COALESCE(capacity, '')) FROM venues WHERE id = $partial_venue_id")" "$partial_identity_before" 'partial venue non-coordinate data preservation'
expect "$(scalar "SELECT CONCAT(COALESCE(latitude, 'NULL'), ':', COALESCE(longitude, 'NULL')) FROM venues WHERE id = $partial_venue_id")" 'NULL:NULL' 'privacy-preserving partial coordinate reconciliation'

mysql_run "$database" --execute="INSERT INTO geocoding_cache (query_hash, normalized_query, provider, response_json, expires_at) VALUES ('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Dhaka', 'native-test', JSON_OBJECT(), '2026-12-31 00:00:00')"

if mysql_run "$database" --execute="INSERT INTO geocoding_cache (query_hash, normalized_query, provider, response_json, expires_at) VALUES ('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Dhaka again', 'native-test', JSON_OBJECT(), '2026-12-31 00:00:00')" >/dev/null 2>&1; then
    echo 'Expected duplicate geocoding cache query hash to be rejected.' >&2
    exit 1
fi

if mysql_run "$database" --execute="INSERT INTO venues (name, address_line, city, country, latitude) VALUES ('Broken native venue', '1 Test Road', 'Dhaka', 'Bangladesh', 23.8)" >/dev/null 2>&1; then
    echo 'Expected a venue with only one coordinate to be rejected.' >&2
    exit 1
fi

echo "Native MySQL migration verification passed for $database; cleanup is automatic."

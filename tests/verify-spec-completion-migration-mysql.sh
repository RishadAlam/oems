#!/bin/sh

# Opt-in native MySQL verification. Imports the populated pre-completion
# baseline into a unique disposable database, runs the migration repeatedly,
# and removes only the database created by this script.
set -eu

if [ "${OEMS_MIGRATION_TEST_MYSQL:-}" != "1" ]; then
    echo 'Set OEMS_MIGRATION_TEST_MYSQL=1 to run the specification-completion migration verifier.' >&2
    exit 2
fi

mysql_host="${OEMS_MIGRATION_TEST_HOST:-127.0.0.1}"
mysql_port="${OEMS_MIGRATION_TEST_PORT:-3306}"
mysql_user="${OEMS_MIGRATION_TEST_USER:-root}"
mysql_password="${OEMS_MIGRATION_TEST_PASSWORD:-}"
database="${OEMS_MIGRATION_TEST_DATABASE:-oems_spec_completion_$$_$(date +%s)}"
database_owned=false

case "$database" in
    oems_spec_completion_*) ;;
    *)
        echo 'The verifier only accepts disposable oems_spec_completion_* database names.' >&2
        exit 2
        ;;
esac

case "$database" in
    *[!a-z0-9_]*|'')
        echo 'The verifier database name may contain only lowercase letters, digits, and underscores.' >&2
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

scalar() {
    mysql_run --batch --skip-column-names "$database" --execute="$1"
}

mysql_run --execute="CREATE DATABASE \`$database\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
database_owned=true
git show db075591:database/schema.sql | mysql_run "$database" >/dev/null
git show db075591:database/seed.sql | mysql_run "$database" >/dev/null
git show db075591:database/demo_seed.sql | mysql_run "$database" >/dev/null

events_before="$(scalar 'SELECT COUNT(*) FROM events')"
users_before="$(scalar 'SELECT COUNT(*) FROM users')"
registrations_before="$(scalar 'SELECT COUNT(*) FROM registrations')"
payments_before="$(scalar 'SELECT COUNT(*) FROM payments')"
tickets_before="$(scalar 'SELECT COUNT(*) FROM tickets')"

mysql_run "$database" < database/migrations/2026-08-10-spec-completion.sql >/dev/null
mysql_run "$database" < database/migrations/2026-08-10-spec-completion.sql >/dev/null

expect "$(scalar "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = 'event_announcements'")" '1' 'announcement table'
expect "$(scalar "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = 'event_announcements' AND INDEX_NAME = 'idx_event_announcements_event_sent'")" '2' 'announcement event and sent-time index columns'
expect "$(scalar "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '$database' AND TABLE_NAME = 'event_announcements' AND CONSTRAINT_TYPE = 'FOREIGN KEY'")" '2' 'announcement event and sender foreign keys'
expect "$(scalar "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = 'event_announcements' AND COLUMN_NAME = 'audience'")" "enum('confirmed')" 'confirmed-only announcement audience'
expect "$(scalar 'SELECT COUNT(*) FROM events')" "$events_before" 'preserved event count'
expect "$(scalar 'SELECT COUNT(*) FROM users')" "$users_before" 'preserved user count'
expect "$(scalar 'SELECT COUNT(*) FROM registrations')" "$registrations_before" 'preserved registration count'
expect "$(scalar 'SELECT COUNT(*) FROM payments')" "$payments_before" 'preserved payment count'
expect "$(scalar 'SELECT COUNT(*) FROM tickets')" "$tickets_before" 'preserved ticket count'

event_id="$(scalar 'SELECT id FROM events ORDER BY id ASC LIMIT 1')"
mysql_run "$database" --execute="INSERT INTO event_announcements (event_id, sent_by, subject, message, audience, recipient_count, request_key, sent_at) VALUES ($event_id, 1, 'Native migration check', 'Existing data remains intact.', 'confirmed', 0, REPEAT('a', 64), NOW())"

if mysql_run "$database" --execute="INSERT INTO event_announcements (event_id, sent_by, subject, message, audience, recipient_count, request_key, sent_at) VALUES ($event_id, 1, 'Replay', 'Must not duplicate.', 'confirmed', 0, REPEAT('a', 64), NOW())" >/dev/null 2>&1; then
    echo 'Expected duplicate announcement request key to be rejected.' >&2
    exit 1
fi

if mysql_run "$database" --execute="SET SESSION sql_mode = 'STRICT_ALL_TABLES'; INSERT INTO event_announcements (event_id, sent_by, subject, message, audience, recipient_count, request_key, sent_at) VALUES ($event_id, 1, 'Wrong audience', 'Must fail.', 'all', 0, REPEAT('b', 64), NOW())" >/dev/null 2>&1; then
    echo 'Expected unsupported announcement audience to be rejected.' >&2
    exit 1
fi

if mysql_run "$database" --execute="INSERT INTO event_announcements (event_id, sent_by, subject, message, audience, recipient_count, request_key, sent_at) VALUES (999999999, 1, 'Unknown event', 'Must fail.', 'confirmed', 0, REPEAT('c', 64), NOW())" >/dev/null 2>&1; then
    echo 'Expected unknown announcement event to be rejected.' >&2
    exit 1
fi

if mysql_run "$database" --execute="INSERT INTO event_announcements (event_id, sent_by, subject, message, audience, recipient_count, request_key, sent_at) VALUES ($event_id, 999999999, 'Unknown sender', 'Must fail.', 'confirmed', 0, REPEAT('d', 64), NOW())" >/dev/null 2>&1; then
    echo 'Expected unknown announcement sender to be rejected.' >&2
    exit 1
fi

mysql_run "$database" < database/migrations/2026-08-10-spec-completion.sql >/dev/null
expect "$(scalar 'SELECT COUNT(*) FROM event_announcements')" '1' 'preserved announcement after repeated migration'

echo "Native MySQL specification-completion migration verification passed for $database; cleanup is automatic."

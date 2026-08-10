#!/bin/sh

# Opt-in native MySQL verification. It imports the populated pre-Week-3
# baseline into a unique disposable database, runs the migration twice, and
# removes only the database created by this script.
set -eu

if [ "${OEMS_MIGRATION_TEST_MYSQL:-}" != "1" ]; then
    echo 'Set OEMS_MIGRATION_TEST_MYSQL=1 to run the Week 3 migration verifier.' >&2
    exit 2
fi

mysql_host="${OEMS_MIGRATION_TEST_HOST:-127.0.0.1}"
mysql_port="${OEMS_MIGRATION_TEST_PORT:-3306}"
mysql_user="${OEMS_MIGRATION_TEST_USER:-root}"
mysql_password="${OEMS_MIGRATION_TEST_PASSWORD:-}"
database="${OEMS_MIGRATION_TEST_DATABASE:-oems_week3_verify_$$_$(date +%s)}"
database_owned=false

case "$database" in
    oems_week3_*) ;;
    *) echo 'The verifier only accepts disposable oems_week3_* database names.' >&2; exit 2 ;;
esac
case "$database" in
    *[!a-z0-9_]*|'') echo 'The verifier database name may contain only lowercase letters, digits, and underscores.' >&2; exit 2 ;;
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

scalar() {
    mysql_run --batch --skip-column-names "$database" --execute="$1"
}

expect() {
    if [ "$1" != "$2" ]; then
        echo "Expected $3 to be $2; received $1." >&2
        exit 1
    fi
}

mysql_run --execute="CREATE DATABASE \`$database\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
database_owned=true
git show e349a4d:database/schema.sql | mysql_run "$database" >/dev/null
git show e349a4d:database/seed.sql | mysql_run "$database" >/dev/null
git show e349a4d:database/demo_seed.sql | mysql_run "$database" >/dev/null

users_before="$(scalar 'SELECT COUNT(*) FROM users')"
events_before="$(scalar 'SELECT COUNT(*) FROM events')"
registrations_before="$(scalar 'SELECT COUNT(*) FROM registrations')"
mysql_run "$database" --execute="INSERT INTO newsletter (email, status, subscribed_at) VALUES ('legacy-week3@example.com', 'subscribed', NOW())"

mysql_run "$database" < database/migrations/2026-08-10-week-3-operations.sql >/dev/null
mysql_run "$database" < database/migrations/2026-08-10-week-3-operations.sql >/dev/null

expect "$(scalar 'SELECT COUNT(*) FROM users')" "$users_before" 'preserved users'
expect "$(scalar 'SELECT COUNT(*) FROM events')" "$events_before" 'preserved events'
expect "$(scalar 'SELECT COUNT(*) FROM registrations')" "$registrations_before" 'preserved registrations'
expect "$(scalar "SELECT COUNT(*) FROM newsletter WHERE email = 'legacy-week3@example.com' AND status = 'subscribed'")" '1' 'preserved legacy subscription'
expect "$(scalar "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME IN ('mail_outbox', 'newsletter_campaigns')")" '2' 'Week 3 tables'
expect "$(scalar "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = 'newsletter' AND COLUMN_NAME IN ('confirmation_token_hash', 'confirmation_expires_at', 'confirmed_at', 'unsubscribe_token_hash')")" '4' 'newsletter lifecycle columns'
expect "$(scalar "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = 'coupon_usage' AND INDEX_NAME = 'uq_coupon_usage_coupon_user'")" '2' 'coupon user uniqueness columns'
expect "$(scalar "SELECT COUNT(*) FROM settings WHERE (\`group\`, \`key\`) IN (('mail', 'outbox_max_attempts'), ('mail', 'reminder_lead_hours'), ('operations', 'maintenance_mode'), ('operations', 'backup_retention')) AND is_public = 0")" '4' 'private Week 3 settings'

mysql_run "$database" --execute="INSERT INTO mail_outbox (template, recipient_email, payload, idempotency_key, status, attempts, available_at) VALUES ('event_reminder', 'native@example.com', JSON_OBJECT('event_id', 1), REPEAT('a', 64), 'queued', 0, NOW())"
if mysql_run "$database" --execute="INSERT INTO mail_outbox (template, recipient_email, payload, idempotency_key, status, attempts, available_at) VALUES ('event_reminder', 'replay@example.com', JSON_OBJECT(), REPEAT('a', 64), 'queued', 0, NOW())" >/dev/null 2>&1; then
    echo 'Expected duplicate outbox idempotency key to be rejected.' >&2
    exit 1
fi

mysql_run "$database" --execute="INSERT INTO newsletter (email, status, confirmation_token_hash, confirmation_expires_at, subscribed_at) VALUES ('pending-week3@example.com', 'pending', REPEAT('b', 64), DATE_ADD(NOW(), INTERVAL 1 DAY), NOW())"
if mysql_run "$database" --execute="INSERT INTO newsletter (email, status, subscribed_at) VALUES ('broken-week3@example.com', 'pending', NOW())" >/dev/null 2>&1; then
    echo 'Expected a pending subscription without confirmation evidence to be rejected.' >&2
    exit 1
fi

mysql_run "$database" < database/migrations/2026-08-10-week-3-operations.sql >/dev/null
expect "$(scalar 'SELECT COUNT(*) FROM mail_outbox')" '1' 'preserved outbox job after replayed migration'
expect "$(scalar "SELECT COUNT(*) FROM newsletter WHERE email IN ('legacy-week3@example.com', 'pending-week3@example.com')")" '2' 'preserved subscriptions after replayed migration'

echo "Native MySQL Week 3 migration verification passed for $database; cleanup is automatic."

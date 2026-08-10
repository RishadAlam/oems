#!/bin/sh

# Opt-in native MySQL verification. Imports the populated Week 3 release into
# a unique disposable database, runs the Week 4 migration repeatedly, and
# removes only the database created by this script.
set -eu

if [ "${OEMS_WEEK4_TEST_MYSQL:-}" != "1" ]; then
    echo 'Set OEMS_WEEK4_TEST_MYSQL=1 to run the Week 4 migration verifier.' >&2
    exit 2
fi

mysql_host="${OEMS_WEEK4_TEST_HOST:-127.0.0.1}"
mysql_port="${OEMS_WEEK4_TEST_PORT:-3306}"
mysql_user="${OEMS_WEEK4_TEST_USER:-root}"
mysql_password="${OEMS_WEEK4_TEST_PASSWORD:-}"
database="${OEMS_WEEK4_TEST_DATABASE:-oems_week4_$$_$(date +%s)}"
database_owned=false

case "$database" in
    oems_week4_*) ;;
    *) echo 'The verifier accepts only disposable oems_week4_* database names.' >&2; exit 2 ;;
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

git show 8db8537:database/schema.sql | mysql_run "$database" >/dev/null
git show 8db8537:database/seed.sql | mysql_run "$database" >/dev/null
git show 8db8537:database/demo_seed.sql | mysql_run "$database" >/dev/null

events_before="$(scalar 'SELECT COUNT(*) FROM events')"
users_before="$(scalar 'SELECT COUNT(*) FROM users')"
registrations_before="$(scalar 'SELECT COUNT(*) FROM registrations')"
payments_before="$(scalar 'SELECT COUNT(*) FROM payments')"
tickets_before="$(scalar 'SELECT COUNT(*) FROM tickets')"

waitlist_user="$(scalar "SELECT users.id FROM users WHERE users.deleted_at IS NULL AND users.role_id = 3 AND NOT EXISTS (SELECT 1 FROM registrations WHERE registrations.user_id = users.id AND registrations.event_id = (SELECT id FROM events ORDER BY id LIMIT 1)) ORDER BY users.id LIMIT 1")"
waitlist_event="$(scalar 'SELECT id FROM events ORDER BY id LIMIT 1')"
mysql_run "$database" --execute="INSERT INTO registrations (event_id, user_id, registration_number, status, amount, currency, registered_at) VALUES ($waitlist_event, $waitlist_user, 'OEMS-WEEK4-MIGRATION-WAIT', 'waitlisted', 0.00, 'BDT', '2026-08-10 10:00:00')"
registrations_before=$((registrations_before + 1))

mysql_run "$database" < database/migrations/2026-08-10-week-4-growth-experience.sql >/dev/null
mysql_run "$database" < database/migrations/2026-08-10-week-4-growth-experience.sql >/dev/null

expect "$(scalar "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = 'events' AND COLUMN_NAME = 'waitlist_enabled'")" '1' 'event waitlist toggle'
expect "$(scalar "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = 'registrations' AND COLUMN_NAME IN ('waitlisted_at', 'promoted_at')")" '2' 'registration waitlist timestamps'
expect "$(scalar "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = 'registrations' AND INDEX_NAME = 'idx_registrations_event_waitlist'")" '4' 'four-column waitlist queue index'
expect "$(scalar "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME IN ('event_certificates', 'blog_posts')")" '2' 'Week 4 tables'
expect "$(scalar "SELECT waitlisted_at = registered_at FROM registrations WHERE registration_number = 'OEMS-WEEK4-MIGRATION-WAIT'")" '1' 'legacy waitlist queue timestamp reconciliation'
expect "$(scalar 'SELECT COUNT(*) FROM events')" "$events_before" 'preserved event count'
expect "$(scalar 'SELECT COUNT(*) FROM users')" "$users_before" 'preserved user count'
expect "$(scalar 'SELECT COUNT(*) FROM registrations')" "$registrations_before" 'preserved registration count'
expect "$(scalar 'SELECT COUNT(*) FROM payments')" "$payments_before" 'preserved payment count'
expect "$(scalar 'SELECT COUNT(*) FROM tickets')" "$tickets_before" 'preserved ticket count'

registration_id="$(scalar 'SELECT id FROM registrations ORDER BY id LIMIT 1')"
participant_id="$(scalar "SELECT user_id FROM registrations WHERE id = $registration_id")"
mysql_run "$database" --execute="INSERT INTO event_certificates (registration_id, participant_id, certificate_number, verification_token_hash, pdf_path, status, issued_at) VALUES ($registration_id, $participant_id, 'OEMS-WEEK4-CERT-1', REPEAT('a', 64), 'certificates/week4.pdf', 'valid', NOW())"

if mysql_run "$database" --execute="INSERT INTO event_certificates (registration_id, participant_id, certificate_number, verification_token_hash, pdf_path, status, issued_at) VALUES ($registration_id, $participant_id, 'OEMS-WEEK4-CERT-2', REPEAT('b', 64), 'certificates/duplicate.pdf', 'valid', NOW())" >/dev/null 2>&1; then
    echo 'Expected duplicate certificate registration to be rejected.' >&2
    exit 1
fi

mysql_run "$database" < database/demo_seed.sql >/dev/null
mysql_run "$database" < database/demo_seed.sql >/dev/null
expect "$(scalar "SELECT COUNT(*) FROM blog_posts WHERE slug = 'how-to-choose-an-event-worth-your-time' AND status = 'published' AND deleted_at IS NULL")" '1' 'repeatable published demo Blog post'
expect "$(scalar 'SELECT COUNT(*) FROM event_certificates')" '1' 'preserved certificate after repeated seed'

mysql_run "$database" < database/migrations/2026-08-10-week-4-growth-experience.sql >/dev/null
expect "$(scalar 'SELECT COUNT(*) FROM event_certificates')" '1' 'preserved certificate after third migration'

echo "Native MySQL Week 4 migration verification passed for $database; cleanup is automatic."

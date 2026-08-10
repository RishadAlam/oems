#!/bin/sh

set -eu

if [ "${OEMS_BACKUP_RESTORE_MYSQL:-}" != "1" ]; then
    echo 'Set OEMS_BACKUP_RESTORE_MYSQL=1 to run the backup restore verifier.' >&2
    exit 2
fi

archive="${OEMS_BACKUP_ARCHIVE:-}"
mysql_host="${OEMS_BACKUP_TEST_HOST:-127.0.0.1}"
mysql_port="${OEMS_BACKUP_TEST_PORT:-3306}"
mysql_user="${OEMS_BACKUP_TEST_USER:-root}"
mysql_password="${OEMS_BACKUP_TEST_PASSWORD:-}"
database="${OEMS_BACKUP_TEST_DATABASE:-oems_restore_$$_$(date +%s)}"
database_owned=false

case "$database" in
    oems_restore_*) ;;
    *) echo 'The verifier only accepts disposable oems_restore_* database names.' >&2; exit 2 ;;
esac
case "$database" in
    *[!a-z0-9_]*|'') echo 'The verifier database name may contain only lowercase letters, digits, and underscores.' >&2; exit 2 ;;
esac
if [ ! -f "$archive" ]; then
    echo 'A readable OEMS_BACKUP_ARCHIVE is required.' >&2
    exit 2
fi

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
gzip -dc "$archive" | mysql_run "$database"

scalar() {
    mysql_run --batch --skip-column-names "$database" --execute="$1"
}

tables=$(scalar 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')
users=$(scalar 'SELECT COUNT(*) FROM users')
events=$(scalar 'SELECT COUNT(*) FROM events')
[ "$tables" -ge 35 ]
[ "$users" -ge 3 ]
[ "$events" -ge 1 ]
for table in mail_outbox coupons newsletter_campaigns event_reminders; do
    [ "$(scalar "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$table'")" = '1' ]
done

echo "Backup restore verification passed: tables=$tables users=$users events=$events"

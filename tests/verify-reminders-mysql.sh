#!/bin/sh

set -eu

if [ "${OEMS_REMINDER_TEST_MYSQL:-}" != "1" ]; then
    echo 'Set OEMS_REMINDER_TEST_MYSQL=1 to run the reminder verifier.' >&2
    exit 2
fi

mysql_host="${OEMS_REMINDER_TEST_HOST:-127.0.0.1}"
mysql_port="${OEMS_REMINDER_TEST_PORT:-3306}"
mysql_user="${OEMS_REMINDER_TEST_USER:-root}"
mysql_password="${OEMS_REMINDER_TEST_PASSWORD:-}"
database="${OEMS_REMINDER_TEST_DATABASE:-oems_reminders_$$_$(date +%s)}"
database_owned=false

case "$database" in oems_reminders_*) ;; *) echo 'The verifier accepts only disposable oems_reminders_* database names.' >&2; exit 2 ;; esac
case "$database" in *[!a-z0-9_]*|'') echo 'The verifier database name is invalid.' >&2; exit 2 ;; esac

mysql_run() { MYSQL_PWD="$mysql_password" mysql --protocol=TCP --host="$mysql_host" --port="$mysql_port" --user="$mysql_user" "$@"; }
cleanup() { if [ "$database_owned" = true ]; then mysql_run --execute="DROP DATABASE IF EXISTS \`$database\`" >/dev/null 2>&1 || true; fi; }
trap cleanup 0 1 2 3 15
mysql_run --execute="CREATE DATABASE \`$database\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
database_owned=true
mysql_run "$database" < database/schema.sql >/dev/null
mysql_run "$database" < database/seed.sql >/dev/null
mysql_run "$database" < database/demo_seed.sql >/dev/null

DB_HOST="$mysql_host" DB_PORT="$mysql_port" DB_DATABASE="$database" DB_USERNAME="$mysql_user" DB_PASSWORD="$mysql_password" php tests/verify-reminders-mysql.php

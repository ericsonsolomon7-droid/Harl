#!/bin/sh
set -e

PORT="${PORT:-8080}"
sed -i "s/8080/$PORT/g" /etc/apache2/sites-available/000-default.conf

export DB_HOST="${DB_HOST:-${MYSQLHOST:-${MYSQL_HOST:-127.0.0.1}}}"
export DB_PORT="${DB_PORT:-${MYSQLPORT:-${MYSQL_PORT:-3306}}}"
export DB_DATABASE="${DB_DATABASE:-${MYSQLDATABASE:-${MYSQL_DATABASE:-bookstore_db}}}"
export DB_USERNAME="${DB_USERNAME:-${MYSQLUSER:-${MYSQL_USER:-root}}}"
export DB_PASSWORD="${DB_PASSWORD:-${MYSQLPASSWORD:-${MYSQL_PASSWORD:-}}}"

if [ -z "${DB_DATABASE:-}" ] || [ "$DB_DATABASE" = "bookstore_db" ]; then
  if [ -n "${MYSQL_URL:-}" ] || [ -n "${MYSQLDATABASE_URL:-}" ]; then
    railway_database_url="${MYSQL_URL:-${MYSQLDATABASE_URL:-}}"
    DB_DATABASE=$(printf '%s' "$railway_database_url" | sed 's#^.*/##' | sed 's/[?].*$//')
    export DB_DATABASE
  fi
fi

if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "127.0.0.1" ]; then
  max_attempts=30
  attempt=1
  while ! mysqladmin ping -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" --password="$DB_PASSWORD" --silent >/dev/null 2>&1; do
    if [ "$attempt" -ge "$max_attempts" ]; then
      echo "MySQL did not become ready in time" >&2
      break
    fi
    attempt=$((attempt + 1))
    sleep 2
  done

  table_count=$(mysql -N -B -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" --password="$DB_PASSWORD" "$DB_DATABASE" \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users';" 2>/dev/null || printf '0')

  if [ "$table_count" = "0" ]; then
    sed '/^CREATE DATABASE/d;/^USE /d' /var/www/html/database/bookstore.sql \
      | mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" --password="$DB_PASSWORD" "$DB_DATABASE"
  fi
fi

apache2-foreground

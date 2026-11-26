#!/bin/sh
set -e

PORT="${PORT:-8080}"
sed -i "s/8080/$PORT/g" /etc/apache2/sites-available/000-default.conf

railway_database_url="${MYSQL_URL:-${MYSQLDATABASE_URL:-${DATABASE_URL:-}}}"
railway_host=""
railway_port=""
railway_database=""
railway_username=""
railway_password=""

if [ -n "$railway_database_url" ]; then
  railway_host=$(URL="$railway_database_url" php -r '$u=parse_url(getenv("URL")); echo $u["host"] ?? "";')
  railway_port=$(URL="$railway_database_url" php -r '$u=parse_url(getenv("URL")); echo $u["port"] ?? "3306";')
  railway_database=$(URL="$railway_database_url" php -r '$u=parse_url(getenv("URL")); echo ltrim($u["path"] ?? "", "/");')
  railway_username=$(URL="$railway_database_url" php -r '$u=parse_url(getenv("URL")); echo $u["user"] ?? "";')
  railway_password=$(URL="$railway_database_url" php -r '$u=parse_url(getenv("URL")); echo $u["pass"] ?? "";')
fi

export DB_HOST="${DB_HOST:-${MYSQLHOST:-${MYSQL_HOST:-${railway_host:-127.0.0.1}}}}"
export DB_PORT="${DB_PORT:-${MYSQLPORT:-${MYSQL_PORT:-${railway_port:-3306}}}}"
export DB_DATABASE="${DB_DATABASE:-${MYSQLDATABASE:-${MYSQL_DATABASE:-${railway_database:-bookstore_db}}}}"
export DB_USERNAME="${DB_USERNAME:-${MYSQLUSER:-${MYSQL_USER:-${railway_username:-root}}}}"
export DB_PASSWORD="${DB_PASSWORD:-${MYSQLPASSWORD:-${MYSQL_PASSWORD:-${railway_password:-}}}}"

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

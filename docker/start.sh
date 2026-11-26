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
  railway_host=$(URL="$railway_database_url" php -r '$u=parse_url(getenv("URL")); echo isset($u["host"]) ? $u["host"] : "";')
  railway_port=$(URL="$railway_database_url" php -r '$u=parse_url(getenv("URL")); echo isset($u["port"]) ? $u["port"] : "3306";')
  railway_database=$(URL="$railway_database_url" php -r '$u=parse_url(getenv("URL")); echo isset($u["path"]) ? ltrim($u["path"], "/") : "";')
  railway_username=$(URL="$railway_database_url" php -r '$u=parse_url(getenv("URL")); echo isset($u["user"]) ? $u["user"] : "";')
  railway_password=$(URL="$railway_database_url" php -r '$u=parse_url(getenv("URL")); echo isset($u["pass"]) ? $u["pass"] : "";')
fi

export DB_HOST="${DB_HOST:-${MYSQLHOST:-${MYSQL_HOST:-${railway_host:-127.0.0.1}}}}"
export DB_PORT="${DB_PORT:-${MYSQLPORT:-${MYSQL_PORT:-${railway_port:-3306}}}}"
export DB_DATABASE="${DB_DATABASE:-${MYSQLDATABASE:-${MYSQL_DATABASE:-${railway_database:-bookstore_db}}}}"
export DB_USERNAME="${DB_USERNAME:-${MYSQLUSER:-${MYSQL_USER:-${railway_username:-root}}}}"
export DB_PASSWORD="${DB_PASSWORD:-${MYSQLPASSWORD:-${MYSQL_PASSWORD:-${railway_password:-}}}}"

initialize_database() {
  if [ -z "$DB_HOST" ] || [ "$DB_HOST" = "127.0.0.1" ]; then
    return
  fi

  export MYSQL_PWD="$DB_PASSWORD"
  max_attempts=60
  attempt=1
  ping_error=""

  while ! mysqladmin ping --connect-timeout=5 -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" --silent >/dev/null 2>&1; do
    if [ "$attempt" -eq 1 ]; then
      ping_error=$(mysqladmin ping --connect-timeout=5 -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" 2>&1 || true)
    fi
    if [ "$attempt" -ge "$max_attempts" ]; then
      echo "MySQL did not become ready after $((max_attempts * 2)) seconds; host=$DB_HOST port=$DB_PORT database=$DB_DATABASE" >&2
      if [ -n "$ping_error" ]; then
        echo "MySQL ping error: $ping_error" >&2
      fi
      return
    fi
    attempt=$((attempt + 1))
    sleep 2
  done

  table_count=$(mysql --connect-timeout=5 -N -B -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE" \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('users', 'categories', 'books');" 2>/dev/null || printf '0')

  if [ "$table_count" != "3" ]; then
    echo "Initializing Harl database schema in $DB_DATABASE"
    if ! sed '/^CREATE DATABASE/d;/^USE /d' /var/www/html/database/bookstore.sql \
      | mysql --connect-timeout=10 -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE"; then
      echo "Harl database schema initialization failed" >&2
    fi
  fi

  core_tables=$(mysql --connect-timeout=5 -N -B -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE" \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('users', 'categories', 'books');" 2>/dev/null || printf '0')
  echo "Harl core database tables ready: $core_tables/3"
}

initialize_database &
exec apache2-foreground

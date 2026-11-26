<?php
declare(strict_types=1);

namespace Bookstore\Config;

use PDO;
use RuntimeException;

function loadLocalEnvironment(): void
{
    $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Unable to read the local environment file.');
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $key = trim(substr($line, 0, $separator));
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/D', $key) || getenv($key) !== false) {
            continue;
        }

        $value = trim(substr($line, $separator + 1));
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

function database(): PDO
{
    loadLocalEnvironment();

    $host = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: (getenv('MYSQL_HOST') ?: ''));
    $port = getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: (getenv('MYSQL_PORT') ?: '3306'));
    $name = getenv('DB_DATABASE') ?: (getenv('MYSQLDATABASE') ?: (getenv('MYSQL_DATABASE') ?: ''));
    $username = getenv('DB_USERNAME') ?: (getenv('MYSQLUSER') ?: (getenv('MYSQL_USER') ?: ''));
    $password = getenv('DB_PASSWORD') ?: (getenv('MYSQLPASSWORD') ?: (getenv('MYSQL_PASSWORD') ?: ''));

    $connectionUrl = getenv('DATABASE_URL') ?: (getenv('MYSQL_URL') ?: getenv('MYSQLDATABASE_URL'));
    if (is_string($connectionUrl) && $connectionUrl !== '') {
        $connection = parse_url($connectionUrl);
        if ($connection !== false) {
            $host = $host ?: (string) ($connection['host'] ?? '');
            $port = getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: (getenv('MYSQL_PORT') ?: (string) ($connection['port'] ?? '3306')));
            $name = $name ?: ltrim((string) ($connection['path'] ?? ''), '/');
            $username = $username ?: (string) ($connection['user'] ?? '');
            $password = $password ?: (string) ($connection['pass'] ?? '');
        }
    }

    $host = $host ?: '127.0.0.1';
    $name = $name ?: 'bookstore_db';
    $username = $username ?: 'root';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

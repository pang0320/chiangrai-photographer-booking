<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$dbHost = getenv('DB_HOST');
if (!$dbHost) {
    $dbHost = '127.0.0.1';
}

$dbName = getenv('DB_NAME');
if (!$dbName) {
    $dbName = 'chiangrai_photographer_booking';
}

$dbUser = getenv('DB_USER');
if (!$dbUser) {
    $dbUser = 'root';
}

$dbPass = getenv('DB_PASS');
if ($dbPass === false) {
    $dbPass = '';
}

$dbCharset = getenv('DB_CHARSET');
if (!$dbCharset) {
    $dbCharset = 'utf8mb4';
}

$dbPort = getenv('DB_PORT');
if (!$dbPort) {
    $dbPort = '3306';
}

define('DB_HOST', $dbHost);
define('DB_PORT', (int)$dbPort);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_CHARSET', $dbCharset);

function parse_db_endpoint(string $endpoint, int $defaultPort): array
{
    $endpoint = trim($endpoint);
    if ($endpoint === '') {
        return ['', $defaultPort];
    }

    if (strpos($endpoint, ':') !== false && substr_count($endpoint, ':') === 1) {
        [$host, $port] = explode(':', $endpoint, 2);
        $port = (int)$port;
        if ($port <= 0) {
            $port = $defaultPort;
        }
        return [$host, $port];
    }

    return [$endpoint, $defaultPort];
}

function db_connection_candidates(): array
{
    $candidates = [];
    $primary = DB_HOST . ':' . DB_PORT;
    $candidates[] = $primary;

    $fallbacks = getenv('DB_FALLBACK_HOSTS');
    if ($fallbacks === false || trim($fallbacks) === '') {
        $fallbacks = 'host.docker.internal:3307,127.0.0.1:3306,127.0.0.1:3307,localhost:3306,localhost:3307';
    }

    foreach (explode(',', $fallbacks) as $fallback) {
        $fallback = trim($fallback);
        if ($fallback !== '') {
            $candidates[] = $fallback;
        }
    }

    return array_values(array_unique($candidates));
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $lastException = null;
    foreach (db_connection_candidates() as $endpoint) {
        [$host, $port] = parse_db_endpoint($endpoint, DB_PORT);
        if ($host === '') {
            continue;
        }

        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 3,
            ]);

            return $pdo;
        } catch (PDOException $exception) {
            $lastException = $exception;
        }
    }

    if ($lastException instanceof PDOException) {
        throw $lastException;
    }

    throw new PDOException('Unable to connect database: no valid database host configured');
}

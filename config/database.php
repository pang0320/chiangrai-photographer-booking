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

define('DB_HOST', $dbHost);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_CHARSET', $dbCharset);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

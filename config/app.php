<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');

$scheme = 'http';
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $scheme = 'https';
}
$host = 'localhost:8000';
if (!empty($_SERVER['HTTP_HOST'])) {
    $host = $_SERVER['HTTP_HOST'];
}
$isSecure = $scheme === 'https';
$enforceHttps = getenv('ENFORCE_HTTPS') === '1';

if ($enforceHttps && !$isSecure && PHP_SAPI !== 'cli') {
    $redirectUri = '/';
    if (isset($_SERVER['REQUEST_URI'])) {
        $redirectUri = $_SERVER['REQUEST_URI'];
    }
    header('Location: https://' . $host . $redirectUri, true, 301);
    exit;
}

if ($isSecure && PHP_SAPI !== 'cli') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

define('APP_NAME', 'Chiang Rai Photographer Booking');
$appUrl = getenv('APP_URL');
if (!$appUrl) {
    $appUrl = $scheme . '://' . $host;
}
define('APP_URL', $appUrl);
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads');
define('UPLOAD_URL', APP_URL . '/assets/uploads');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);
define('CSRF_PROTECTION', getenv('CSRF_PROTECTION') === '1');
define('PAYMENT_DISCLAIMER', 'เว็บไซต์เป็นเพียงตัวกลางในการค้นหาและติดต่อช่างภาพเท่านั้น ไม่ได้เป็นตัวกลางรับชำระเงิน');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

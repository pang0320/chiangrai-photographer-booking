<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';

define('APP_NAME', 'Chiang Rai Photographer Booking');
define('APP_URL', getenv('APP_URL') ?: $scheme . '://' . $host);
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads');
define('UPLOAD_URL', APP_URL . '/assets/uploads');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);
define('PAYMENT_DISCLAIMER', 'เว็บไซต์เป็นเพียงตัวกลางในการค้นหาและติดต่อช่างภาพเท่านั้น ไม่ได้เป็นตัวกลางรับชำระเงิน');

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

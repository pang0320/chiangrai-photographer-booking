<?php
declare(strict_types=1);

/**
 * สร้างหรือดึง CSRF Token จาก session
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * สร้าง hidden input field สำหรับ CSRF Token
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * ตรวจสอบความถูกต้องของ CSRF Token ในคำขอ POST
 */
function verify_csrf(): void
{
    if (!defined('CSRF_PROTECTION') || CSRF_PROTECTION !== true) {
        return;
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
}

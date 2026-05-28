<?php
declare(strict_types=1);

/**
 * เก็บข้อความแจ้งเตือนลง session (Flash Message)
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * ดึงข้อความแจ้งเตือนทั้งหมดออกมาและล้างค่าใน session
 */
function flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

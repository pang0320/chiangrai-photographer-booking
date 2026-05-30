<?php
declare(strict_types=1);

/**
 * เก็บข้อความแจ้งเตือนลง session (Flash Message)
 * ใช้สำหรับอำนวยความสะดวกในการทำงานเกี่ยวกับ เก็บข้อความแจ้งเตือนลง session (Flash Message)
 * @param string $type ประเภท/หมวดหมู่
 * @param string $message รายละเอียดการแจ้งเตือน
 * @return void ไม่มีการคืนค่า
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * ดึงข้อความแจ้งเตือนทั้งหมดออกมาและล้างค่าใน session
 * ใช้สำหรับอำนวยความสะดวกในการทำงานเกี่ยวกับ ดึงข้อความแจ้งเตือนทั้งหมดออกมาและล้างค่าใน session
 * @return array ชุดข้อมูล (Array)
 */
function flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

function upload_image(array $file, string $folder): ?string
{
    if (empty($file['name']) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ');
    }
    if ((int)$file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('ไฟล์ต้องมีขนาดไม่เกิน 5MB');
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('อนุญาตเฉพาะ jpg, jpeg, png, webp');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, (string)$file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowedMime, true)) {
        throw new RuntimeException('ชนิดไฟล์ไม่ถูกต้อง');
    }

    $dir = UPLOAD_PATH . '/' . trim($folder, '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $target = $dir . '/' . $name;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new RuntimeException('ไม่สามารถบันทึกไฟล์ได้');
    }

    return trim($folder, '/') . '/' . $name;
}


<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function upload_image(array $file, string $folder): ?string
{
    if (empty($file['name']) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $originalName = (string)$file['name'];
    $tmpName = (string)($file['tmp_name'] ?? '');
    $safeFolder = trim($folder, '/');
    $allowedFolders = ['avatars', 'covers', 'portfolios', 'reviews', 'articles', 'banners'];
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $blockedExt = ['php', 'phtml', 'phar', 'exe', 'js', 'html', 'htm', 'svg', 'sh', 'bat', 'cmd', 'com', 'scr', 'cgi', 'pl', 'asp', 'aspx', 'jsp'];
    $allowedMime = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    if (!in_array($safeFolder, $allowedFolders, true)) {
        upload_security_reject('invalid_upload_folder', [
            'folder' => $folder,
            'file_name' => $originalName,
        ]);
        throw new RuntimeException('โฟลเดอร์อัปโหลดไม่ถูกต้อง');
    }

    if (strpos($safeFolder, '..') !== false || strpos($safeFolder, '\\') !== false) {
        upload_security_reject('upload_folder_traversal', [
            'folder' => $folder,
            'file_name' => $originalName,
        ]);
        throw new RuntimeException('โฟลเดอร์อัปโหลดไม่ถูกต้อง');
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        upload_security_reject('upload_error', [
            'file_name' => $originalName,
            'error_code' => (int)$file['error'],
        ]);

        if (in_array((int)$file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new RuntimeException(UPLOAD_IMAGE_HELP_TEXT);
        }

        throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ กรุณาตรวจสอบไฟล์อีกครั้ง');
    }

    if ((int)$file['size'] > MAX_UPLOAD_SIZE) {
        upload_security_reject('upload_file_too_large', [
            'file_name' => $originalName,
            'file_size' => (int)$file['size'],
            'max_size' => MAX_UPLOAD_SIZE,
        ]);
        throw new RuntimeException(UPLOAD_IMAGE_HELP_TEXT);
    }

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        upload_security_reject('invalid_uploaded_file', [
            'file_name' => $originalName,
            'tmp_name' => $tmpName,
        ]);
        throw new RuntimeException('ไฟล์อัปโหลดไม่ถูกต้อง');
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        upload_security_reject('blocked_upload_extension', [
            'file_name' => $originalName,
            'extension' => $ext,
        ]);
        throw new RuntimeException(UPLOAD_IMAGE_HELP_TEXT);
    }

    $nameParts = explode('.', strtolower($originalName));
    foreach ($nameParts as $namePart) {
        if (in_array($namePart, $blockedExt, true)) {
            upload_security_reject('dangerous_upload_filename', [
                'file_name' => $originalName,
                'matched_extension' => $namePart,
            ]);
            throw new RuntimeException('ชื่อไฟล์มีนามสกุลที่ไม่อนุญาต');
        }
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        upload_security_reject('finfo_unavailable', [
            'file_name' => $originalName,
        ]);
        throw new RuntimeException('ไม่สามารถตรวจสอบชนิดไฟล์ได้');
    }

    $mime = finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    if (!is_string($mime) || !in_array($mime, $allowedMime[$ext], true)) {
        upload_security_reject('invalid_upload_mime', [
            'file_name' => $originalName,
            'extension' => $ext,
            'mime' => $mime,
        ]);
        throw new RuntimeException('ชนิดไฟล์ไม่ถูกต้อง');
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
        upload_security_reject('invalid_image_binary', [
            'file_name' => $originalName,
            'mime' => $mime,
        ]);
        throw new RuntimeException('ไฟล์รูปภาพไม่ถูกต้อง');
    }

    if (!isset($imageInfo['mime']) || !in_array((string)$imageInfo['mime'], $allowedMime[$ext], true)) {
        upload_security_reject('image_mime_mismatch', [
            'file_name' => $originalName,
            'extension' => $ext,
            'finfo_mime' => $mime,
            'image_mime' => $imageInfo['mime'] ?? '',
        ]);
        throw new RuntimeException('ข้อมูลรูปภาพไม่ตรงกับชนิดไฟล์');
    }

    $dir = UPLOAD_PATH . '/' . $safeFolder;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($tmpName, $target)) {
        upload_security_reject('move_uploaded_file_failed', [
            'file_name' => $originalName,
            'target_folder' => $safeFolder,
        ]);
        throw new RuntimeException('ไม่สามารถบันทึกไฟล์ได้');
    }

    @chmod($target, 0644);

    return $safeFolder . '/' . $name;
}

function upload_security_reject(string $reason, array $context = []): void
{
    $context['reason'] = $reason;
    $context['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
    $context['user_agent'] = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    if (function_exists('logSecurityEvent')) {
        logSecurityEvent('upload_security_rejected', $context);
        return;
    }

    try {
        $stmt = db()->prepare('INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, user_agent, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            'upload_security_rejected',
            'security',
            null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            substr((string)json_encode($context, JSON_UNESCAPED_UNICODE), 0, 2000),
        ]);
    } catch (Throwable $e) {
        error_log('Upload security log failed: ' . $e->getMessage());
    }
}

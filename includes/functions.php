<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/upload.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 64);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user !== null) {
        return $user ?: null;
    }
    $stmt = db()->prepare('SELECT u.*, r.name AS role_name, r.display_name AS role_display FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: false;
    return $user ?: null;
}

function role_id(string $role): int
{
    $stmt = db()->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
    $stmt->execute([$role]);
    return (int)($stmt->fetchColumn() ?: 0);
}

function requireLogin(): void
{
    $user = current_user();

    if (!$user) {
        flash('warning', 'กรุณาเข้าสู่ระบบก่อน');
        redirect('/login.php');
    }

    if ($user['status'] === 'suspended') {
        log_activity('blocked_suspended_user', 'users', (int)$user['id'], 'Suspended user attempted to access protected page');
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }

        session_destroy();
        session_start();
        flash('error', 'บัญชีของคุณถูกระงับ กรุณาติดต่อผู้ดูแลระบบ');
        redirect('/login.php');
    }
}

function requireRole($roles): void
{
    requireLogin();
    $roles = is_array($roles) ? $roles : [$roles];
    $user = current_user();
    if (!$user || !in_array($user['role_name'], $roles, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function dashboard_path(string $role): string
{
    if ($role === 'admin') {
        return '/admin/dashboard.php';
    }
    if ($role === 'photographer') {
        return '/photographer/dashboard.php';
    }
    return '/customer/dashboard.php';
}

function setting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string)$value;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value, created_at, updated_at) VALUES (?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');
    $stmt->execute([$key, $value]);
}

function db_fetch_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_fetch_value(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function slugify(string $text): string
{
    $text = trim(mb_strtolower($text, 'UTF-8'));
    $text = preg_replace('/[^\pL\pN]+/u', '-', $text);
    $text = trim((string)$text, '-');
    return $text !== '' ? $text : bin2hex(random_bytes(4));
}

function unique_slug(string $table, string $base, ?int $ignoreId = null): string
{
    $slug = slugify($base);
    $candidate = $slug;
    $i = 2;
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = ?";
        $params = [$candidate];
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        $stmt = db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }
        $candidate = $slug . '-' . $i++;
    }
}

function log_activity(string $action, string $table = '', ?int $recordId = null, string $description = ''): void
{
    $user = current_user();
    $stmt = db()->prepare('INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, user_agent, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $user['id'] ?? null,
        $action,
        $table ?: null,
        $recordId,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        $description,
    ]);
}

function notify_user(int $userId, string $title, string $message, string $type = 'info', ?int $relatedId = null): void
{
    $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())');
    $stmt->execute([$userId, $title, $message, $type, $relatedId]);
}

function unread_notifications_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function recent_notifications(int $userId, int $limit = 20): array
{
    $limit = max(1, min(50, $limit));
    $stmt = db()->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT {$limit}");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function is_login_blocked(string $email): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip_address = ? AND success = 0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
    $stmt->execute([$email, client_ip()]);
    return (int)$stmt->fetchColumn() >= 5;
}

function record_login_attempt(string $email, bool $success): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (email, ip_address, success, user_agent, attempted_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([
        $email,
        client_ip(),
        $success ? 1 : 0,
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
}

function clear_failed_login_attempts(string $email): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE email = ? AND ip_address = ?');
    $stmt->execute([$email, client_ip()]);
}

function photographer_profile_by_user(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM photographer_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function photographer_id_for_user(int $userId): int
{
    $profile = photographer_profile_by_user($userId);
    return $profile ? (int)$profile['id'] : 0;
}

function public_image(?string $path, string $fallback): string
{
    if (!$path) {
        return $fallback;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if (!is_file(UPLOAD_PATH . '/' . ltrim($path, '/'))) {
        return $fallback;
    }
    return '/assets/uploads/' . ltrim($path, '/');
}

function time_slot_label(string $slot): string
{
    $map = ['morning' => 'เช้า', 'afternoon' => 'บ่าย', 'evening' => 'เย็น', 'full_day' => 'เต็มวัน'];
    return $map[$slot] ?? $slot;
}

function booking_status_label(string $status): string
{
    $map = [
        'pending' => 'รอการตอบรับ',
        'accepted' => 'ตอบรับแล้ว',
        'rejected' => 'ปฏิเสธ',
        'cancelled' => 'ยกเลิก',
        'confirmed' => 'นัดหมายสำเร็จ',
        'completed' => 'เสร็จสิ้น',
        'approved' => 'อนุมัติแล้ว',
        'suspended' => 'ระงับ',
        'visible' => 'แสดง',
        'hidden' => 'ซ่อน',
        'published' => 'เผยแพร่',
        'draft' => 'ฉบับร่าง',
    ];
    return $map[$status] ?? $status;
}

function status_badge(string $status): string
{
    $colors = [
        'active' => 'bg-emerald-100 text-emerald-700',
        'pending' => 'bg-amber-100 text-amber-700',
        'approved' => 'bg-emerald-100 text-emerald-700',
        'accepted' => 'bg-sky-100 text-sky-700',
        'confirmed' => 'bg-indigo-100 text-indigo-700',
        'completed' => 'bg-emerald-100 text-emerald-700',
        'rejected' => 'bg-rose-100 text-rose-700',
        'cancelled' => 'bg-slate-200 text-slate-700',
        'suspended' => 'bg-rose-100 text-rose-700',
        'visible' => 'bg-emerald-100 text-emerald-700',
        'hidden' => 'bg-slate-200 text-slate-700',
        'published' => 'bg-emerald-100 text-emerald-700',
        'draft' => 'bg-slate-200 text-slate-700',
    ];
    $icons = [
        'active' => 'fa-circle-check',
        'pending' => 'fa-hourglass-half',
        'approved' => 'fa-circle-check',
        'accepted' => 'fa-calendar-check',
        'confirmed' => 'fa-check',
        'completed' => 'fa-circle-check',
        'rejected' => 'fa-circle-xmark',
        'cancelled' => 'fa-ban',
        'suspended' => 'fa-ban',
        'visible' => 'fa-eye',
        'hidden' => 'fa-eye-slash',
        'published' => 'fa-circle-check',
        'draft' => 'fa-pen',
    ];
    $icon = 'fa-circle-info';
    if (isset($icons[$status])) {
        $icon = $icons[$status];
    }
    return '<span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ' . ($colors[$status] ?? 'bg-slate-100 text-slate-700') . '"><i class="fa-solid ' . h($icon) . '"></i>' . h(booking_status_label($status)) . '</span>';
}

function generate_booking_code(): string
{
    return 'CRB' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
}

function add_booking_status_log(int $bookingId, ?string $oldStatus, string $newStatus, ?int $changedBy, string $note = ''): void
{
    $stmt = db()->prepare('INSERT INTO booking_status_logs (booking_id, old_status, new_status, changed_by, note, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$bookingId, $oldStatus, $newStatus, $changedBy, $note]);
}

function can_book_slot(int $photographerId, string $date, string $slot, ?int $excludeBookingId = null): bool
{
    $stmt = db()->prepare('SELECT id FROM photographer_availability WHERE photographer_id = ? AND available_date = ? AND time_slot = ? AND status = "available" LIMIT 1');
    $stmt->execute([$photographerId, $date, $slot]);
    if (!$stmt->fetchColumn()) {
        return false;
    }

    $sql = 'SELECT id
            FROM bookings
            WHERE photographer_id = ?
              AND booking_date = ?
              AND status IN ("pending","accepted","confirmed")
              AND deleted_at IS NULL
              AND (time_slot = ? OR time_slot = "full_day" OR ? = "full_day")';
    $params = [$photographerId, $date, $slot, $slot];
    if ($excludeBookingId) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeBookingId;
    }
    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return !$stmt->fetchColumn();
}

function update_photographer_rating(int $photographerId): void
{
    $stmt = db()->prepare('SELECT AVG(rating_overall) avg_rating, COUNT(*) total FROM reviews WHERE photographer_id = ? AND status = "visible" AND deleted_at IS NULL');
    $stmt->execute([$photographerId]);
    $row = $stmt->fetch() ?: ['avg_rating' => 0, 'total' => 0];
    $up = db()->prepare('UPDATE photographer_profiles SET average_rating = ?, total_reviews = ?, updated_at = NOW() WHERE id = ?');
    $up->execute([round((float)$row['avg_rating'], 2), (int)$row['total'], $photographerId]);
}

function update_photographer_response_stats(int $photographerId): void
{
    $totalRequests = (int)db_fetch_value('SELECT COUNT(*) FROM bookings WHERE photographer_id = ? AND deleted_at IS NULL', [$photographerId]);
    $respondedRequests = (int)db_fetch_value('SELECT COUNT(*) FROM bookings WHERE photographer_id = ? AND status IN ("accepted","rejected","confirmed","completed") AND deleted_at IS NULL', [$photographerId]);
    $responseRate = 0;
    if ($totalRequests > 0) {
        $responseRate = round(($respondedRequests / $totalRequests) * 100, 2);
    }

    $averageHours = db_fetch_value('SELECT AVG(TIMESTAMPDIFF(MINUTE, b.created_at, l.created_at)) / 60
                                    FROM bookings b
                                    JOIN booking_status_logs l ON l.booking_id = b.id
                                    WHERE b.photographer_id = ?
                                      AND l.new_status IN ("accepted","rejected")
                                      AND b.deleted_at IS NULL', [$photographerId]);
    if ($averageHours === false || $averageHours === null) {
        $averageHours = 0;
    }

    $stmt = db()->prepare('UPDATE photographer_profiles SET response_rate = ?, average_response_hours = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$responseRate, round((float)$averageHours, 2), $photographerId]);
}

function paginate(int $total, int $page, int $perPage, string $baseUrl): string
{
    $pages = (int)ceil($total / $perPage);
    if ($pages <= 1) {
        return '';
    }
    $html = '<div class="mt-8 flex flex-wrap gap-2">';
    for ($i = 1; $i <= $pages; $i++) {
        $url = $baseUrl . (strpos($baseUrl, '?') === false ? '?' : '&') . 'page=' . $i;
        $class = $i === $page ? 'bg-red-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50';
        $html .= '<a class="rounded-xl border px-4 py-2 text-sm font-semibold ' . $class . '" href="' . h($url) . '"><i class="fa-solid fa-file-lines mr-1"></i>' . $i . '</a>';
    }
    return $html . '</div>';
}

function table_count(string $table, string $where = '1=1'): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function favorite_count(int $photographerId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM favorite_photographers WHERE photographer_id = ?');
    $stmt->execute([$photographerId]);
    return (int)$stmt->fetchColumn();
}

function is_favorite_photographer(int $customerId, int $photographerId): bool
{
    $stmt = db()->prepare('SELECT id FROM favorite_photographers WHERE customer_id = ? AND photographer_id = ? LIMIT 1');
    $stmt->execute([$customerId, $photographerId]);
    return (bool)$stmt->fetchColumn();
}

function toggle_favorite_photographer(int $customerId, int $photographerId): bool
{
    if (is_favorite_photographer($customerId, $photographerId)) {
        $stmt = db()->prepare('DELETE FROM favorite_photographers WHERE customer_id = ? AND photographer_id = ?');
        $stmt->execute([$customerId, $photographerId]);
        return false;
    }

    $stmt = db()->prepare('INSERT INTO favorite_photographers (customer_id, photographer_id, created_at) VALUES (?, ?, NOW())');
    $stmt->execute([$customerId, $photographerId]);
    return true;
}

function record_search_log(string $keyword, int $districtId, int $categoryId): void
{
    $user = current_user();
    $userId = null;
    if ($user) {
        $userId = (int)$user['id'];
    }
    $districtValue = null;
    if ($districtId > 0) {
        $districtValue = $districtId;
    }
    $categoryValue = null;
    if ($categoryId > 0) {
        $categoryValue = $categoryId;
    }
    $stmt = db()->prepare('INSERT INTO search_logs (user_id, keyword, district_id, category_id, search_date, ip_address, created_at) VALUES (?, ?, ?, ?, CURDATE(), ?, NOW())');
    $stmt->execute([$userId, $keyword, $districtValue, $categoryValue, client_ip()]);
}

function record_recently_viewed(int $userId, int $photographerId): void
{
    $stmt = db()->prepare('INSERT INTO recently_viewed_photographers (user_id, photographer_id, viewed_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE viewed_at = NOW()');
    $stmt->execute([$userId, $photographerId]);
}

function photographer_completion_percent(int $photographerId): int
{
    $profile = db_fetch_all('SELECT * FROM photographer_profiles WHERE id = ? LIMIT 1', [$photographerId]);
    if (!$profile) {
        return 0;
    }
    $p = $profile[0];
    $checks = [];
    $checks[] = !empty($p['profile_image']);
    $checks[] = !empty($p['cover_image']);
    $checks[] = trim((string)$p['bio']) !== '';
    $checks[] = trim((string)$p['phone_public']) !== '' || trim((string)$p['line_id']) !== '';
    $checks[] = (int)db_fetch_value('SELECT COUNT(*) FROM photographer_service_areas WHERE photographer_id = ? AND is_active = 1', [$photographerId]) > 0;
    $checks[] = (int)db_fetch_value('SELECT COUNT(*) FROM photographer_services WHERE photographer_id = ? AND is_active = 1', [$photographerId]) > 0;
    $checks[] = (int)db_fetch_value('SELECT COUNT(*) FROM photographer_portfolios WHERE photographer_id = ? AND deleted_at IS NULL', [$photographerId]) >= 5;
    $checks[] = (int)db_fetch_value('SELECT COUNT(*) FROM photographer_availability WHERE photographer_id = ? AND available_date >= CURDATE() AND status = "available"', [$photographerId]) > 0;

    $done = 0;
    foreach ($checks as $check) {
        if ($check) {
            $done++;
        }
    }
    return (int)round(($done / count($checks)) * 100);
}

function report_status_label(string $status): string
{
    $map = [
        'pending' => 'รอตรวจสอบ',
        'reviewed' => 'ตรวจสอบแล้ว',
        'resolved' => 'แก้ไขแล้ว',
        'rejected' => 'ไม่รับรายงาน',
    ];
    if (isset($map[$status])) {
        return $map[$status];
    }
    return $status;
}

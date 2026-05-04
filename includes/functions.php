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
    if (!current_user()) {
        flash('warning', 'กรุณาเข้าสู่ระบบก่อน');
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
        'pending' => 'รอตอบรับ',
        'accepted' => 'ตอบรับแล้ว',
        'rejected' => 'ปฏิเสธ',
        'cancelled' => 'ยกเลิก',
        'confirmed' => 'ยืนยันงาน',
        'completed' => 'เสร็จสิ้น',
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
    return '<span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ' . ($colors[$status] ?? 'bg-slate-100 text-slate-700') . '">' . h(booking_status_label($status)) . '</span>';
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

    $sql = 'SELECT id FROM bookings WHERE photographer_id = ? AND booking_date = ? AND time_slot = ? AND status IN ("pending","accepted","confirmed") AND deleted_at IS NULL';
    $params = [$photographerId, $date, $slot];
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

function paginate(int $total, int $page, int $perPage, string $baseUrl): string
{
    $pages = (int)ceil($total / $perPage);
    if ($pages <= 1) {
        return '';
    }
    $html = '<div class="mt-8 flex flex-wrap gap-2">';
    for ($i = 1; $i <= $pages; $i++) {
        $url = $baseUrl . (strpos($baseUrl, '?') === false ? '?' : '&') . 'page=' . $i;
        $class = $i === $page ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50';
        $html .= '<a class="rounded-xl border px-4 py-2 text-sm font-semibold ' . $class . '" href="' . h($url) . '">' . $i . '</a>';
    }
    return $html . '</div>';
}

function table_count(string $table, string $where = '1=1'): int
{
    $stmt = db()->query("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    return (int)$stmt->fetchColumn();
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/security.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function request_cache_remember(string $key, callable $resolver)
{
    static $cache = [];

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $cache[$key] = $resolver();
    return $cache[$key];
}

function app_cache_file(string $key): string
{
    return rtrim(CACHE_PATH, '/\\') . '/' . sha1($key) . '.cache';
}

function cache_remember(string $key, int $ttlSeconds, callable $resolver)
{
    if (!CACHE_ENABLED || $ttlSeconds <= 0) {
        return $resolver();
    }

    $file = app_cache_file($key);
    if (is_file($file) && (time() - filemtime($file)) <= $ttlSeconds) {
        $payload = file_get_contents($file);
        if ($payload !== false) {
            $value = @unserialize($payload, ['allowed_classes' => false]);
            if ($value !== false || $payload === serialize(false)) {
                return $value;
            }
        }
    }

    $value = $resolver();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    if (is_dir($dir) && is_writable($dir)) {
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, serialize($value), LOCK_EX) !== false) {
            @rename($tmp, $file);
        }
    }

    return $value;
}

function cache_forget(string $key): void
{
    $file = app_cache_file($key);
    if (is_file($file)) {
        @unlink($file);
    }
}

function cache_clear_all(): void
{
    $dir = rtrim(CACHE_PATH, '/\\');
    if (!is_dir($dir)) {
        return;
    }

    foreach (glob($dir . '/*.cache') ?: [] as $file) {
        @unlink($file);
    }
}

function redirect_with_intended(string $path): void
{
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if ($requestUri !== '' && strpos($requestUri, '/login.php') !== 0) {
        $_SESSION['intended_url'] = $requestUri;
    }

    redirect($path);
}

function clean_context_path(?string $path = null): string
{
    if ($path === null) {
        $path = (string)($_SERVER['REQUEST_URI'] ?? '/');
    }

    $parsedPath = parse_url($path, PHP_URL_PATH);
    if (!$parsedPath) {
        $parsedPath = '/';
    }

    if ($parsedPath[0] !== '/') {
        $parsedPath = '/' . $parsedPath;
    }

    return $parsedPath;
}

function clean_context_set(string $path, array $params): void
{
    $path = clean_context_path($path);
    $cleanParams = [];

    foreach ($params as $key => $value) {
        if (!is_string($key) || $key === '') {
            continue;
        }

        if (is_array($value)) {
            $cleanParams[$key] = array_values(array_map('strval', $value));
            continue;
        }

        $cleanParams[$key] = (string)$value;
    }

    if (!isset($_SESSION['clean_page_context']) || !is_array($_SESSION['clean_page_context'])) {
        $_SESSION['clean_page_context'] = [];
    }

    $_SESSION['clean_page_context'][$path] = $cleanParams;
}

function clean_context_get(?string $path = null): array
{
    $path = clean_context_path($path);
    if (isset($_SESSION['clean_page_context'][$path]) && is_array($_SESSION['clean_page_context'][$path])) {
        return $_SESSION['clean_page_context'][$path];
    }

    return [];
}

function clean_context_init(array $allowedKeys, ?string $path = null): array
{
    $path = clean_context_path($path);
    $incoming = [];

    if (is_post() && isset($_POST['__context_nav'])) {
        verify_csrf();
        foreach ($allowedKeys as $key) {
            if (isset($_POST[$key])) {
                $incoming[$key] = $_POST[$key];
            }
        }
        clean_context_set($path, $incoming);
        redirect($path);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        foreach ($allowedKeys as $key) {
            if (isset($_GET[$key])) {
                $incoming[$key] = $_GET[$key];
            }
        }

        if ($incoming) {
            clean_context_set($path, $incoming);
            redirect($path);
        }
    }

    return clean_context_get($path);
}

function clean_context_value(array $context, string $key, $default = '')
{
    if (array_key_exists($key, $context)) {
        return $context[$key];
    }

    return $default;
}

function clean_redirect(string $path, array $params = []): void
{
    clean_context_set($path, $params);
    redirect(clean_context_path($path));
}

function clean_context_inputs(array $params): string
{
    $html = '<input type="hidden" name="__context_nav" value="1">';
    $html .= csrf_field();

    foreach ($params as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $item) {
                $html .= '<input type="hidden" name="' . h($key) . '[]" value="' . h($item) . '">';
            }
            continue;
        }

        $html .= '<input type="hidden" name="' . h($key) . '" value="' . h($value) . '">';
    }

    return $html;
}

function clean_context_button(string $path, array $params, string $content, string $buttonClass = '', string $formClass = 'inline', string $formAttrs = ''): string
{
    return '<form method="post" action="' . h(clean_context_path($path)) . '" class="' . h($formClass) . '" ' . $formAttrs . '>'
        . clean_context_inputs($params)
        . '<button type="submit" class="' . h($buttonClass) . '">' . $content . '</button>'
        . '</form>';
}

function clean_context_button_from_url(string $url, string $content, string $buttonClass = '', string $formClass = 'inline', string $formAttrs = ''): string
{
    $path = clean_context_path($url);
    $params = [];
    $query = parse_url($url, PHP_URL_QUERY);

    if ($query) {
        parse_str($query, $params);
    }

    return clean_context_button($path, $params, $content, $buttonClass, $formClass, $formAttrs);
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 64);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function auth_session_expired(): bool
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['last_activity_at'])) {
        return false;
    }

    return (time() - (int)$_SESSION['last_activity_at']) >= SESSION_TIMEOUT_SECONDS;
}

function clear_auth_session(bool $restart = false): void
{
    $_SESSION = [];
    $canModifyHeaders = !headers_sent();

    if ($canModifyHeaders && ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    if ($restart && $canModifyHeaders) {
        session_start();
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    if (auth_session_expired()) {
        clear_auth_session(true);
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
    return (int)request_cache_remember('role_id:' . $role, function () use ($role) {
        $stmt = db()->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
        $stmt->execute([$role]);
        return (int)($stmt->fetchColumn() ?: 0);
    });
}

function requireLogin(): void
{
    if (auth_session_expired()) {
        clear_auth_session(true);
        flash('warning', 'ไม่ได้ใช้งานเกิน 20 นาที กรุณาเข้าสู่ระบบใหม่');
        redirect('/login.php');
    }

    $user = current_user();

    if (!$user) {
        flash('warning', 'กรุณาเข้าสู่ระบบก่อน');
        redirect_with_intended('/login.php');
    }

    if ($user['status'] === 'suspended') {
        log_activity('blocked_suspended_user', 'users', (int)$user['id'], 'Suspended user attempted to access protected page');
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }

        clear_auth_session(true);
        flash('error', 'บัญชีของคุณถูกระงับ กรุณาติดต่อผู้ดูแลระบบ');
        redirect('/login.php');
    }

    $_SESSION['last_activity_at'] = time();
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

function user_workspace_path(array $user): string
{
    $role = (string)($user['role_name'] ?? '');

    if ($role === 'photographer') {
        $profile = photographer_profile_by_user((int)($user['id'] ?? 0));

        if ($profile) {
            if (photographer_completion_percent((int)$profile['id']) < 100) {
                return '/photographer/onboarding.php';
            }
        }
    }

    return dashboard_path($role);
}

function user_workspace_label(array $user): string
{
    $role = (string)($user['role_name'] ?? '');

    if ($role === 'photographer') {
        $profile = photographer_profile_by_user((int)($user['id'] ?? 0));

        if ($profile) {
            if (photographer_completion_percent((int)$profile['id']) < 100) {
                return 'ตั้งค่าโปรไฟล์';
            }
        }
    }

    return 'เมนูของฉัน';
}

function user_workspace_icon(array $user): string
{
    $role = (string)($user['role_name'] ?? '');

    if ($role === 'photographer') {
        $profile = photographer_profile_by_user((int)($user['id'] ?? 0));

        if ($profile) {
            if (photographer_completion_percent((int)$profile['id']) < 100) {
                return 'fa-list-check';
            }
        }
    }

    return 'fa-gauge';
}

function setting(string $key, string $default = ''): string
{
    return (string)request_cache_remember('setting:' . $key . ':' . $default, function () use ($key, $default) {
        $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string)$value;
    });
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

function db_fetch_all_cached(string $cacheKey, int $ttlSeconds, string $sql, array $params = []): array
{
    return cache_remember($cacheKey . ':' . sha1($sql . serialize($params)), $ttlSeconds, function () use ($sql, $params) {
        return db_fetch_all($sql, $params);
    });
}

function db_fetch_value_cached(string $cacheKey, int $ttlSeconds, string $sql, array $params = [])
{
    return cache_remember($cacheKey . ':' . sha1($sql . serialize($params)), $ttlSeconds, function () use ($sql, $params) {
        return db_fetch_value($sql, $params);
    });
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
    if ($userId <= 0) {
        return;
    }

    $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message, type, related_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())');
    $stmt->execute([$userId, $title, $message, $type, $relatedId]);
}

function notify_admins(string $title, string $message, string $type = 'info', ?int $relatedId = null): void
{
    $admins = db_fetch_all('SELECT u.id
                            FROM users u
                            JOIN roles r ON r.id = u.role_id
                            WHERE r.name = "admin"
                              AND u.status = "active"
                              AND u.deleted_at IS NULL');
    foreach ($admins as $admin) {
        notify_user((int)$admin['id'], $title, $message, $type, $relatedId);
    }
}

function required_mark(): string
{
    return '<span class="text-red-600" aria-label="จำเป็น">*</span>';
}

function text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

function is_new_content(?string $date, int $days = 7): bool
{
    $date = trim((string)$date);
    if ($date === '' || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return false;
    }

    try {
        $created = new DateTime($date);
        $limit = new DateTime('-' . max(1, $days) . ' days');
        return $created >= $limit;
    } catch (Exception $exception) {
        return false;
    }
}

function new_content_badge(?string $date, int $days = 7): string
{
    if (!is_new_content($date, $days)) {
        return '';
    }

    return '<span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700"><i class="fa-solid fa-bolt"></i>ใหม่</span>';
}

function ranking_order_sql(string $alias = 'p', ?string $completedExpression = null): string
{
    $alias = preg_replace('/[^A-Za-z0-9_]/', '', $alias);
    if ($alias === '') {
        $alias = 'p';
    }

    if ($completedExpression === null || trim($completedExpression) === '') {
        $completedExpression = '(SELECT COUNT(*) FROM bookings b_rank WHERE b_rank.photographer_id = ' . $alias . '.id AND b_rank.status = "completed" AND b_rank.deleted_at IS NULL)';
    }

    return $alias . '.average_rating DESC, '
        . $alias . '.total_reviews DESC, '
        . $completedExpression . ' DESC, '
        . $alias . '.response_rate DESC, '
        . $alias . '.is_verified DESC, '
        . $alias . '.profile_views DESC, '
        . $alias . '.id ASC';
}

function notification_target_url(array $notification, array $user): string
{
    $type = (string)($notification['type'] ?? 'info');
    $relatedId = (int)($notification['related_id'] ?? 0);
    $role = (string)($user['role_name'] ?? '');

    if ($type === 'booking' && $relatedId > 0) {
        if ($role === 'customer') {
            return '/customer/booking_detail.php?id=' . $relatedId;
        }
        if ($role === 'photographer') {
            return '/photographer/booking_detail.php?id=' . $relatedId;
        }
        if ($role === 'admin') {
            return '/admin/bookings.php';
        }
    }

    if ($type === 'review') {
        if ($role === 'photographer') {
            return '/photographer/reviews.php';
        }
        if ($role === 'customer') {
            return '/customer/reviews.php';
        }
        if ($role === 'admin') {
            return '/admin/reviews.php';
        }
    }

    if (in_array($type, ['photographer_verified', 'photographer_rejected', 'photographer_approved', 'photographer_suspended'], true)) {
        if ($role === 'photographer') {
            return '/photographer/profile.php';
        }
        if ($role === 'admin') {
            return '/admin/photographers.php';
        }
    }

    if ($type === 'account') {
        if ($role === 'customer') {
            return '/customer/profile.php';
        }
        if ($role === 'photographer') {
            return '/photographer/profile.php';
        }
        if ($role === 'admin') {
            return '/admin/users.php';
        }
    }

    if ($type === 'report') {
        if ($role === 'admin') {
            return '/admin/reports_moderation.php';
        }
        if ($role === 'customer') {
            return '/customer/reports.php';
        }
    }

    if ($type === 'article' && $relatedId > 0) {
        return '/blog.php';
    }

    return $role === 'customer' ? '/customer/notifications.php' : '/notifications.php';
}

function unread_notifications_count(int $userId): int
{
    return (int)request_cache_remember('unread_notifications_count:' . $userId, function () use ($userId) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    });
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
    return request_cache_remember('photographer_profile_by_user:' . $userId, function () use ($userId) {
        $stmt = db()->prepare('SELECT * FROM photographer_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
        return $profile ?: null;
    });
}

function photographer_id_for_user(int $userId): int
{
    $profile = photographer_profile_by_user($userId);
    return $profile ? (int)$profile['id'] : 0;
}

function user_avatar_url(array $user): string
{
    $role = (string)($user['role_name'] ?? '');
    $fallback = '/assets/uploads/seed/photo-1494790108377-be9c29b29330.jpg';
    $imagePath = (string)($user['avatar'] ?? '');

    if ($role === 'photographer') {
        $fallback = '/assets/uploads/seed/photo-1500648767791-00dcc994a43e.jpg';
        $profile = photographer_profile_by_user((int)($user['id'] ?? 0));
        if ($profile && !empty($profile['profile_image'])) {
            $imagePath = (string)$profile['profile_image'];
        }
    }

    if ($role === 'admin') {
        $fallback = '/assets/uploads/seed/photo-1519345182560-3f2917c472ef.jpg';
    }

    return public_image($imagePath, $fallback);
}

function public_image(?string $path, string $fallback): string
{
    if (!$path) {
        return normalize_local_image_fallback($fallback);
    }
    if (preg_match('#^https?://#i', $path)) {
        if (preg_match('#photo-[A-Za-z0-9_-]+#', $path, $matches)) {
            $seedPath = 'seed/' . $matches[0] . '.jpg';
            if (is_file_cached(UPLOAD_PATH . '/' . $seedPath)) {
                return '/assets/uploads/' . $seedPath;
            }
        }
        return normalize_local_image_fallback($fallback);
    }
    if (!is_file_cached(UPLOAD_PATH . '/' . ltrim($path, '/'))) {
        return normalize_local_image_fallback($fallback);
    }
    return '/assets/uploads/' . ltrim($path, '/');
}

function normalize_local_image_fallback(string $fallback): string
{
    if (preg_match('#^https?://#i', $fallback)) {
        if (preg_match('#photo-[A-Za-z0-9_-]+#', $fallback, $matches)) {
            $seedPath = 'seed/' . $matches[0] . '.jpg';
            if (is_file_cached(UPLOAD_PATH . '/' . $seedPath)) {
                return '/assets/uploads/' . $seedPath;
            }
        }
        return '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg';
    }

    if (strpos($fallback, '/assets/uploads/') === 0) {
        return $fallback;
    }

    if (is_file_cached(UPLOAD_PATH . '/' . ltrim($fallback, '/'))) {
        return '/assets/uploads/' . ltrim($fallback, '/');
    }

    return $fallback;
}

function is_file_cached(string $path): bool
{
    return (bool)request_cache_remember('is_file:' . $path, function () use ($path) {
        return is_file($path);
    });
}

function time_slot_label(string $slot): string
{
    $map = ['morning' => 'เช้า', 'afternoon' => 'บ่าย', 'evening' => 'เย็น', 'full_day' => 'เต็มวัน'];
    return $map[$slot] ?? $slot;
}

function parse_be_date_to_iso(?string $value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $matches)) {
        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];

        if ($year >= 2400) {
            $year -= 543;
        }

        if (checkdate($month, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }

    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $value, $matches)) {
        $day = (int)$matches[1];
        $month = (int)$matches[2];
        $year = (int)$matches[3];

        if ($year >= 2400) {
            $year -= 543;
        }

        if (checkdate($month, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }

    if (preg_match('/^(\d{4})[\/\.](\d{1,2})[\/\.](\d{1,2})$/', $value, $matches)) {
        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];

        if ($year >= 2400) {
            $year -= 543;
        }

        if (checkdate($month, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }

    return '';
}

function format_be_date(?string $value): string
{
    $value = trim((string)$value);

    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    try {
        $date = new DateTime($value);
    } catch (Exception $exception) {
        return $value;
    }

    $year = (int)$date->format('Y') + 543;
    return $date->format('d/m/') . $year;
}

function format_be_datetime(?string $value): string
{
    $value = trim((string)$value);

    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    try {
        $date = new DateTime($value);
    } catch (Exception $exception) {
        return $value;
    }

    $year = (int)$date->format('Y') + 543;
    return $date->format('d/m/') . $year . $date->format(' H:i');
}

function current_be_year(): int
{
    return (int)date('Y') + 543;
}

function be_date_input_value(?string $value): string
{
    $isoDate = parse_be_date_to_iso($value);

    if ($isoDate === '') {
        return '';
    }

    return format_be_date($isoDate);
}

function be_date_input(string $name, ?string $value = '', string $classes = '', bool $required = false, string $placeholder = 'วว/ดด/พ.ศ.'): string
{
    $isoDate = parse_be_date_to_iso($value);
    $calendarNames = ['available_date', 'booking_date'];

    if (in_array($name, $calendarNames, true)) {
        $statuses = [];
        if (isset($GLOBALS['calendar_date_statuses'][$name]) && is_array($GLOBALS['calendar_date_statuses'][$name])) {
            $statuses = $GLOBALS['calendar_date_statuses'][$name];
        }

        $label = $name === 'booking_date' ? 'วันที่ต้องการจ้าง' : 'วันที่ต้องการจ้าง';
        if (isset($GLOBALS['calendar_date_labels'][$name]) && is_string($GLOBALS['calendar_date_labels'][$name])) {
            $label = $GLOBALS['calendar_date_labels'][$name];
        }

        return calendar_date_input($name, $isoDate, $statuses, $required, $label);
    }

    $id = 'be_date_' . bin2hex(random_bytes(4));
    $requiredAttribute = '';

    if ($required) {
        $requiredAttribute = ' required';
    }

    return '<input type="text" data-be-date-visible data-target="' . h($id) . '" value="' . h(be_date_input_value($isoDate)) . '" placeholder="' . h($placeholder) . '" autocomplete="off" inputmode="numeric" class="' . h($classes) . '"' . $requiredAttribute . '>'
        . '<input type="hidden" id="' . h($id) . '" name="' . h($name) . '" value="' . h($isoDate) . '">';
}

function calendar_date_input(string $name, ?string $value = '', array $dateStatuses = [], bool $required = false, string $label = 'วันที่ต้องการจ้าง'): string
{
    $isoDate = parse_be_date_to_iso($value);
    $id = 'calendar_date_' . bin2hex(random_bytes(4));
    $requiredAttribute = $required ? ' required' : '';
    $statusJson = h(json_encode($dateStatuses, JSON_UNESCAPED_UNICODE));
    $defaultStatus = 'available';
    if (isset($GLOBALS['calendar_date_default_status'][$name]) && is_string($GLOBALS['calendar_date_default_status'][$name])) {
        $defaultStatus = $GLOBALS['calendar_date_default_status'][$name];
    }
    $selectableStatuses = [];
    if (isset($GLOBALS['calendar_date_selectable_statuses'][$name]) && is_array($GLOBALS['calendar_date_selectable_statuses'][$name])) {
        $selectableStatuses = $GLOBALS['calendar_date_selectable_statuses'][$name];
    }
    $selectableJson = h(json_encode($selectableStatuses, JSON_UNESCAPED_UNICODE));
    $showLegend = !empty($dateStatuses);
    if (isset($GLOBALS['calendar_date_show_legend'][$name])) {
        $showLegend = (bool)$GLOBALS['calendar_date_show_legend'][$name];
    }

    $legendHtml = '';
    if ($showLegend) {
        $legendHtml = '<div class="calendar-date-legend"><span><i class="calendar-dot calendar-dot-available"></i>ว่าง</span><span><i class="calendar-dot calendar-dot-unavailable"></i>ไม่ว่าง</span><span><i class="calendar-dot calendar-dot-booked"></i>ถูกจอง</span><span><i class="calendar-dot calendar-dot-pending"></i>รอตอบรับ</span></div>';
    }

    return '<div class="calendar-date" data-calendar-date data-target="' . h($id) . '" data-statuses="' . $statusJson . '" data-default-status="' . h($defaultStatus) . '" data-selectable-statuses="' . $selectableJson . '">'
        . '<input type="hidden" id="' . h($id) . '" name="' . h($name) . '" value="' . h($isoDate) . '"' . $requiredAttribute . '>'
        . '<button type="button" class="calendar-date-trigger" data-calendar-trigger>'
        . '<span><i class="fa-solid fa-calendar-days"></i><span><b>' . h($label) . '</b><small data-calendar-selected>' . h($isoDate ? format_be_date($isoDate) : 'เลือกวันที่') . '</small></span></span><i class="fa-solid fa-chevron-down"></i>'
        . '</button>'
        . '<div class="calendar-date-popover" data-calendar-popover>'
        . '<div class="calendar-date-header">'
        . '<div><p class="calendar-date-label">' . h($label) . '</p><p class="calendar-date-selected" data-calendar-selected-popover>' . h($isoDate ? format_be_date($isoDate) : 'ยังไม่ได้เลือกวันที่') . '</p></div>'
        . '<div class="calendar-date-controls"><button type="button" data-calendar-prev aria-label="เดือนก่อนหน้า"><i class="fa-solid fa-chevron-left"></i></button><button type="button" data-calendar-next aria-label="เดือนถัดไป"><i class="fa-solid fa-chevron-right"></i></button></div>'
        . '</div>'
        . '<div class="calendar-date-month" data-calendar-month></div>'
        . '<div class="calendar-date-weekdays"><span>อา</span><span>จ</span><span>อ</span><span>พ</span><span>พฤ</span><span>ศ</span><span>ส</span></div>'
        . '<div class="calendar-date-grid" data-calendar-grid></div>'
        . $legendHtml
        . '</div></div>';
}

function booking_status_label(string $status): string
{
    $map = [
        'pending' => 'รอการตอบรับ',
        'accepted' => 'ตอบรับแล้ว',
        'rejected' => 'ปฏิเสธ',
        'cancelled' => 'ยกเลิกโดยลูกค้า',
        'confirmed' => 'ยืนยันงาน',
        'completed' => 'เสร็จสิ้น',
        'approved' => 'อนุมัติแล้ว',
        'suspended' => 'ระงับ',
        'visible' => 'แสดง',
        'hidden' => 'ซ่อน',
        'published' => 'เผยแพร่',
        'draft' => 'ฉบับร่าง',
        'active' => 'ใช้งาน',
        'unavailable' => 'ไม่ว่าง',
        'available' => 'ว่าง',
        'booked' => 'ถูกจองแล้ว',
        'reviewed' => 'ตรวจสอบแล้ว',
        'resolved' => 'แก้ไขแล้ว',
    ];
    return $map[$status] ?? $status;
}

function role_display_name(string $role): string
{
    $map = [
        'customer' => 'ลูกค้า',
        'photographer' => 'ช่างภาพ',
        'admin' => 'ผู้ดูแลระบบ',
    ];

    return $map[$role] ?? $role;
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
        'available' => 'bg-emerald-100 text-emerald-700',
        'unavailable' => 'bg-slate-200 text-slate-700',
        'booked' => 'bg-indigo-100 text-indigo-700',
        'reviewed' => 'bg-sky-100 text-sky-700',
        'resolved' => 'bg-emerald-100 text-emerald-700',
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
        'available' => 'fa-circle-check',
        'unavailable' => 'fa-circle-minus',
        'booked' => 'fa-calendar-check',
        'reviewed' => 'fa-eye',
        'resolved' => 'fa-check',
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

function booking_status_timeline_icon(string $status): string
{
    $icons = [
        'pending' => 'fa-hourglass-half',
        'accepted' => 'fa-calendar-check',
        'confirmed' => 'fa-handshake',
        'completed' => 'fa-circle-check',
        'rejected' => 'fa-circle-xmark',
        'cancelled' => 'fa-ban',
    ];

    return $icons[$status] ?? 'fa-clock-rotate-left';
}

function booking_status_timeline_tone(string $status): string
{
    $tones = [
        'pending' => 'timeline-tone-pending',
        'accepted' => 'timeline-tone-accepted',
        'confirmed' => 'timeline-tone-confirmed',
        'completed' => 'timeline-tone-completed',
        'rejected' => 'timeline-tone-rejected',
        'cancelled' => 'timeline-tone-cancelled',
    ];

    return $tones[$status] ?? 'timeline-tone-default';
}

function booking_status_timeline_html(array $logs): string
{
    if (!$logs) {
        return '<div class="booking-timeline-empty">'
            . '<i class="fa-solid fa-clipboard-list"></i>'
            . '<h3>ยังไม่มีประวัติสถานะ</h3>'
            . '<p>เมื่อมีการเปลี่ยนสถานะ ระบบจะแสดงเป็นเส้นเวลาที่นี่</p>'
            . '</div>';
    }

    $total = count($logs);
    $html = '<div class="booking-timeline-scroll" aria-label="ประวัติสถานะแบบเส้นเวลา">';
    $html .= '<div class="booking-timeline" style="--timeline-count:' . (int)$total . '">';

    foreach ($logs as $index => $log) {
        $newStatus = (string)($log['new_status'] ?? '');
        $oldStatus = (string)($log['old_status'] ?? '');
        $oldStatusText = $oldStatus !== '' ? booking_status_label($oldStatus) : 'เริ่มต้น';
        $newStatusText = booking_status_label($newStatus);
        $changedByName = !empty($log['name']) ? (string)$log['name'] : 'ระบบ';
        $note = trim((string)($log['note'] ?? ''));
        $stepNumber = $index + 1;
        $isLatest = $stepNumber === $total;
        $tone = booking_status_timeline_tone($newStatus);
        $icon = booking_status_timeline_icon($newStatus);

        $html .= '<article class="booking-timeline-item ' . h($tone) . ($isLatest ? ' is-latest' : '') . '" style="--step-index:' . (int)$stepNumber . '" tabindex="0">';
        $html .= '<div class="booking-timeline-marker" aria-hidden="true"><span>' . (int)$stepNumber . '</span><i class="fa-solid ' . h($icon) . '"></i></div>';
        $html .= '<div class="booking-timeline-card">';
        $html .= '<div class="booking-timeline-head">';
        $html .= '<div>';
        $html .= '<p class="booking-timeline-kicker">ขั้นตอนที่ ' . (int)$stepNumber . ($isLatest ? ' · ล่าสุด' : '') . '</p>';
        $html .= '<h3>' . h($newStatusText) . '</h3>';
        $html .= '</div>';
        $html .= status_badge($newStatus);
        $html .= '</div>';
        $html .= '<div class="booking-timeline-flow">';
        $html .= '<span>' . h($oldStatusText) . '</span><i class="fa-solid fa-arrow-right"></i><strong>' . h($newStatusText) . '</strong>';
        $html .= '</div>';
        $html .= '<div class="booking-timeline-meta">';
        $html .= '<span><i class="fa-solid fa-calendar-day"></i>' . h(format_be_datetime((string)$log['created_at'])) . '</span>';
        $html .= '<span><i class="fa-solid fa-user"></i>' . h($changedByName) . '</span>';
        $html .= '</div>';
        if ($note !== '') {
            $html .= '<p class="booking-timeline-note"><i class="fa-solid fa-note-sticky"></i>' . nl2br(h($note)) . '</p>';
        }
        $html .= '</div>';
        $html .= '</article>';
    }

    return $html . '</div></div>';
}

function sync_availability_after_booking_status(int $bookingId): void
{
    $booking = db_fetch_all('SELECT id, photographer_id, booking_date, time_slot, status FROM bookings WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$bookingId]);
    if (!$booking) {
        return;
    }

    $booking = $booking[0];
    $photographerId = (int)$booking['photographer_id'];
    $bookingDate = (string)$booking['booking_date'];
    $timeSlot = (string)$booking['time_slot'];
    $status = (string)$booking['status'];

    if (in_array($status, ['accepted', 'confirmed'], true)) {
        $stmt = db()->prepare('INSERT INTO photographer_availability (photographer_id, available_date, time_slot, status, note, created_at, updated_at)
                               VALUES (?, ?, ?, "booked", "ระบบเปลี่ยนเป็นถูกจองแล้วจากคำขอจอง", NOW(), NOW())
                               ON DUPLICATE KEY UPDATE status = "booked", note = VALUES(note), updated_at = NOW()');
        $stmt->execute([$photographerId, $bookingDate, $timeSlot]);
        return;
    }

    if (in_array($status, ['rejected', 'cancelled'], true)) {
        $conflictSql = 'SELECT id FROM bookings
                        WHERE photographer_id = ?
                          AND booking_date = ?
                          AND status IN ("pending","accepted","confirmed")
                          AND deleted_at IS NULL
                          AND id <> ?
                          AND (time_slot = ? OR time_slot = "full_day" OR ? = "full_day")
                        LIMIT 1';
        $stmt = db()->prepare($conflictSql);
        $stmt->execute([$photographerId, $bookingDate, $bookingId, $timeSlot, $timeSlot]);

        if (!$stmt->fetchColumn()) {
            $stmt = db()->prepare('UPDATE photographer_availability
                                   SET status = "available", note = NULL, updated_at = NOW()
                                   WHERE photographer_id = ?
                                     AND available_date = ?
                                     AND time_slot = ?
                                     AND status = "booked"');
            $stmt->execute([$photographerId, $bookingDate, $timeSlot]);
        }
    }
}

function can_book_slot(int $photographerId, string $date, string $slot, ?int $excludeBookingId = null): bool
{
    if ($date < date('Y-m-d')) {
        return false;
    }

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

function paginate_clean(int $total, int $page, int $perPage, string $path, array $baseParams = []): string
{
    $pages = (int)ceil($total / $perPage);
    if ($pages <= 1) {
        return '';
    }

    $html = '<div class="mt-8 flex flex-wrap gap-2">';
    for ($i = 1; $i <= $pages; $i++) {
        $params = $baseParams;
        $params['page'] = $i;
        $class = $i === $page ? 'bg-red-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50';
        $html .= clean_context_button($path, $params, '<i class="fa-solid fa-file-lines mr-1"></i>' . $i, 'rounded-xl border px-4 py-2 text-sm font-semibold ' . $class);
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
    $logKey = sha1($keyword . '|' . $districtId . '|' . $categoryId . '|' . date('Y-m-d'));
    if (!isset($_SESSION['last_search_log']) || !is_array($_SESSION['last_search_log'])) {
        $_SESSION['last_search_log'] = [];
    }
    if (isset($_SESSION['last_search_log'][$logKey]) && (time() - (int)$_SESSION['last_search_log'][$logKey]) < 60) {
        return;
    }
    $_SESSION['last_search_log'][$logKey] = time();

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
    return (int)request_cache_remember('photographer_completion_percent:' . $photographerId, function () use ($photographerId) {
        $profile = db_fetch_all('SELECT * FROM photographer_profiles WHERE id = ? LIMIT 1', [$photographerId]);
        if (!$profile) {
            return 0;
        }
        $p = $profile[0];
        $summary = db_fetch_all('SELECT
                (SELECT COUNT(*) FROM photographer_service_areas WHERE photographer_id = ? AND is_active = 1) AS service_area_count,
                (SELECT COUNT(*) FROM photographer_services WHERE photographer_id = ? AND is_active = 1) AS service_count,
                (SELECT COUNT(*) FROM photographer_portfolios WHERE photographer_id = ? AND deleted_at IS NULL) AS portfolio_count,
                (SELECT COUNT(*) FROM photographer_availability WHERE photographer_id = ? AND available_date >= CURDATE() AND status = "available") AS availability_count', [
            $photographerId,
            $photographerId,
            $photographerId,
            $photographerId,
        ]);
        $row = $summary[0] ?? [];
        $checks = [];
        $checks[] = !empty($p['profile_image']);
        $checks[] = !empty($p['cover_image']);
        $checks[] = trim((string)$p['bio']) !== '';
        $checks[] = trim((string)$p['phone_public']) !== '' || trim((string)$p['line_id']) !== '';
        $checks[] = (int)($row['service_area_count'] ?? 0) > 0;
        $checks[] = (int)($row['service_count'] ?? 0) > 0;
        $checks[] = (int)($row['portfolio_count'] ?? 0) >= 5;
        $checks[] = (int)($row['availability_count'] ?? 0) > 0;

        $done = 0;
        foreach ($checks as $check) {
            if ($check) {
                $done++;
            }
        }
        return (int)round(($done / count($checks)) * 100);
    });
}

function footer_public_data(): array
{
    return cache_remember('footer_public_data_v2', 300, function () {
        return [
            'categories' => db_fetch_all('SELECT id, name, slug FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name LIMIT 6'),
            'districts' => db_fetch_all('SELECT district_name FROM districts WHERE is_active = 1 ORDER BY district_name LIMIT 8'),
        ];
    });
}

function predefined_article_tag_groups(): array
{
    return [
        'ประเภทงาน' => [
            'งานแต่งงาน',
            'พรีเวดดิ้ง',
            'รับปริญญา',
            'ครอบครัว',
            'เด็กและทารก',
            'พอร์ตเทรต',
            'สินค้า',
            'อาหาร',
            'อีเวนต์',
            'องค์กร',
            'ท่องเที่ยว',
            'อสังหาริมทรัพย์',
        ],
        'สไตล์ภาพ' => [
            'แคนดิด',
            'มินิมอล',
            'โทนอุ่น',
            'โทนฟิล์ม',
            'แฟชั่น',
            'สารคดี',
            'ไลฟ์สไตล์',
            'ธรรมชาติ',
            'กลางคืน',
            'สตูดิโอ',
            'ภาพขาวดำ',
            'ภาพเล่าเรื่อง',
        ],
        'สถานที่เชียงราย' => [
            'เชียงราย',
            'แม่สาย',
            'เชียงแสน',
            'แม่จัน',
            'แม่ฟ้าหลวง',
            'เทิง',
            'ภูชี้ฟ้า',
            'ดอยตุง',
            'วัดร่องขุ่น',
            'ไร่ชา',
            'คาเฟ่',
            'สวนดอกไม้',
        ],
        'คำแนะนำลูกค้า' => [
            'เตรียมตัวก่อนถ่าย',
            'เลือกชุด',
            'เลือกโลเคชัน',
            'โพสท่า',
            'วางแผนเวลา',
            'งบประมาณ',
            'เช็กลิสต์',
            'วันฝนตก',
            'แสงธรรมชาติ',
            'ไฟสตูดิโอ',
            'ส่งมอบไฟล์',
            'การจองช่างภาพ',
        ],
    ];
}

function ensure_tags_status_column(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $exists = (int)db_fetch_value('SELECT COUNT(*)
                                  FROM INFORMATION_SCHEMA.COLUMNS
                                  WHERE TABLE_SCHEMA = DATABASE()
                                    AND TABLE_NAME = "tags"
                                    AND COLUMN_NAME = "is_active"');
    if ($exists === 0) {
        db()->exec('ALTER TABLE tags ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER slug');
        db()->exec('ALTER TABLE tags ADD INDEX idx_tags_active (is_active, name)');
    }

    $checked = true;
}

function predefined_article_tag_names(): array
{
    $names = [];
    foreach (predefined_article_tag_groups() as $groupTags) {
        foreach ($groupTags as $name) {
            $names[] = $name;
        }
    }

    return array_values(array_unique($names));
}

function ensure_predefined_article_tags(): array
{
    ensure_tags_status_column();

    $tagRows = [];
    foreach (predefined_article_tag_names() as $tagName) {
        $slug = slugify($tagName);
        $existingId = db_fetch_value('SELECT id FROM tags WHERE name = ? OR slug = ? LIMIT 1', [$tagName, $slug]);
        if ($existingId) {
            $tagRows[$tagName] = (int)$existingId;
            continue;
        }

        $stmt = db()->prepare('INSERT INTO tags (name, slug, is_active, created_at) VALUES (?, ?, 1, NOW())');
        $stmt->execute([$tagName, unique_slug('tags', $tagName)]);
        $tagRows[$tagName] = (int)db()->lastInsertId();
    }

    return $tagRows;
}

function article_tag_options(): array
{
    ensure_tags_status_column();

    $tagIdsByName = ensure_predefined_article_tags();
    $activeRows = db_fetch_all('SELECT id FROM tags WHERE is_active = 1');
    $activeIds = [];
    foreach ($activeRows as $row) {
        $activeIds[(int)$row['id']] = true;
    }
    $groups = [];

    foreach (predefined_article_tag_groups() as $groupName => $tagNames) {
        $groups[$groupName] = [];
        foreach ($tagNames as $tagName) {
            if (!isset($tagIdsByName[$tagName])) {
                continue;
            }
            if (!isset($activeIds[(int)$tagIdsByName[$tagName]])) {
                continue;
            }
            $groups[$groupName][] = [
                'id' => (int)$tagIdsByName[$tagName],
                'name' => $tagName,
            ];
        }
    }

    return $groups;
}

function allowed_article_tag_ids(): array
{
    $ids = [];
    foreach (article_tag_options() as $tags) {
        foreach ($tags as $tag) {
            $ids[] = (int)$tag['id'];
        }
    }

    return array_values(array_unique($ids));
}

function selected_article_tag_ids_from_post(): array
{
    $rawIds = $_POST['tag_ids'] ?? [];
    if (!is_array($rawIds)) {
        $rawIds = [];
    }

    $allowed = array_flip(allowed_article_tag_ids());
    $selected = [];

    foreach ($rawIds as $rawId) {
        $tagId = (int)$rawId;
        if ($tagId > 0 && isset($allowed[$tagId])) {
            $selected[] = $tagId;
        }
    }

    return array_values(array_unique($selected));
}

function selected_article_tag_ids(string $relationTable, string $recordColumn, int $recordId): array
{
    if ($recordId <= 0) {
        return [];
    }

    $allowedTables = [
        'article_tags' => 'article_id',
        'blog_tags' => 'blog_id',
    ];

    if (!isset($allowedTables[$relationTable]) || $allowedTables[$relationTable] !== $recordColumn) {
        return [];
    }

    $rows = db_fetch_all('SELECT tag_id FROM ' . $relationTable . ' WHERE ' . $recordColumn . ' = ?', [$recordId]);
    $ids = [];
    foreach ($rows as $row) {
        $ids[] = (int)$row['tag_id'];
    }

    return $ids;
}

function sync_article_tag_relations(string $relationTable, string $recordColumn, int $recordId, array $tagIds): void
{
    $allowedTables = [
        'article_tags' => 'article_id',
        'blog_tags' => 'blog_id',
    ];

    if ($recordId <= 0 || !isset($allowedTables[$relationTable]) || $allowedTables[$relationTable] !== $recordColumn) {
        return;
    }

    $allowed = array_flip(allowed_article_tag_ids());
    $cleanTagIds = [];
    foreach ($tagIds as $tagId) {
        $tagId = (int)$tagId;
        if ($tagId > 0 && isset($allowed[$tagId])) {
            $cleanTagIds[] = $tagId;
        }
    }
    $cleanTagIds = array_values(array_unique($cleanTagIds));

    $stmt = db()->prepare('DELETE FROM ' . $relationTable . ' WHERE ' . $recordColumn . ' = ?');
    $stmt->execute([$recordId]);

    if (!$cleanTagIds) {
        cache_clear_all();
        return;
    }

    $stmt = db()->prepare('INSERT IGNORE INTO ' . $relationTable . ' (' . $recordColumn . ', tag_id) VALUES (?, ?)');
    foreach ($cleanTagIds as $tagId) {
        $stmt->execute([$recordId, $tagId]);
    }

    cache_clear_all();
}

function article_tag_selector_html(array $selectedIds = [], string $inputName = 'tag_ids'): string
{
    $selectedLookup = array_flip(array_map('intval', $selectedIds));
    $html = '<div class="grid gap-4">';

    foreach (article_tag_options() as $groupName => $tags) {
        $html .= '<div class="rounded-2xl border border-neutral-200 bg-neutral-50 p-4">';
        $html .= '<div class="mb-3 text-sm font-black text-neutral-800"><i class="fa-solid fa-tags mr-2 text-red-600"></i>' . h($groupName) . '</div>';
        $html .= '<div class="flex flex-wrap gap-2">';

        foreach ($tags as $tag) {
            $tagId = (int)$tag['id'];
            $checked = isset($selectedLookup[$tagId]) ? ' checked' : '';
            $html .= '<label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-neutral-200 bg-white px-3 py-2 text-xs font-black text-neutral-700 transition hover:border-red-300 hover:bg-red-50">';
            $html .= '<input type="checkbox" name="' . h($inputName) . '[]" value="' . $tagId . '" class="h-4 w-4 rounded border-neutral-300 text-red-600 focus:ring-red-500"' . $checked . '>';
            $html .= '<span>' . h($tag['name']) . '</span>';
            $html .= '</label>';
        }

        $html .= '</div></div>';
    }

    return $html . '</div>';
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

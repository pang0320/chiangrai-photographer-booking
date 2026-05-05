<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

function activity_action_label(string $action): string
{
    $map = [
        'login' => 'เข้าสู่ระบบ',
        'logout' => 'ออกจากระบบ',
        'update_profile' => 'แก้ไขโปรไฟล์',
        'update_photographer_profile' => 'แก้ไขโปรไฟล์ช่างภาพ',
        'create_booking' => 'สร้างคำขอจอง',
        'change_booking_status' => 'เปลี่ยนสถานะคำขอจอง',
        'admin_change_booking' => 'ผู้ดูแลเปลี่ยนสถานะคำขอจอง',
        'admin_approve_photographer' => 'อนุมัติช่างภาพ',
        'admin_reject_photographer' => 'ปฏิเสธช่างภาพ',
        'admin_suspend_photographer' => 'ระงับช่างภาพ',
        'admin_verify_photographer' => 'ยืนยันช่างภาพ',
        'admin_feature_photographer' => 'ตั้งช่างภาพแนะนำ',
        'admin_unfeature_photographer' => 'ยกเลิกช่างภาพแนะนำ',
        'admin_activate_user' => 'เปิดใช้งานผู้ใช้',
        'admin_suspend_user' => 'ระงับผู้ใช้',
        'admin_delete_user' => 'ลบผู้ใช้',
        'admin_show_review' => 'แสดงรีวิว',
        'admin_hide_review' => 'ซ่อนรีวิว',
        'admin_delete_review' => 'ลบรีวิว',
        'admin_publish_article' => 'เผยแพร่บทความ',
        'admin_hide_article' => 'ซ่อนบทความ',
        'admin_delete_article' => 'ลบบทความ',
        'manage_availability' => 'จัดการวันว่าง',
        'manage_portfolio' => 'จัดการผลงาน',
        'manage_services' => 'จัดการประเภทงาน',
        'manage_service_areas' => 'จัดการพื้นที่ให้บริการ',
        'manage_articles' => 'จัดการบทความ',
        'manage_categories' => 'จัดการหมวดหมู่',
        'manage_districts' => 'จัดการอำเภอ',
        'manage_banners' => 'จัดการแบนเนอร์',
        'update_settings' => 'แก้ไขตั้งค่า',
        'create_blog' => 'สร้างบทความเว็บ',
        'update_blog' => 'แก้ไขบทความเว็บ',
        'delete_blog' => 'ลบบทความเว็บ',
        'update_blog_status' => 'เปลี่ยนสถานะบทความเว็บ',
        'create_faq' => 'สร้างคำถามที่พบบ่อย',
        'update_faq' => 'แก้ไขคำถามที่พบบ่อย',
        'delete_faq' => 'ลบคำถามที่พบบ่อย',
        'moderate_report' => 'ตรวจรายงานปัญหา',
        'send_contact_message' => 'ส่งข้อความติดต่อ',
        'update_contact_message' => 'จัดการข้อความติดต่อ',
        'upload_security_rejected' => 'บล็อกไฟล์อัปโหลดเสี่ยง',
        'security_threat_detected' => 'พบความเสี่ยงด้านความปลอดภัย',
        'blocked_suspended_user' => 'บล็อกบัญชีที่ถูกระงับ',
    ];

    if (isset($map[$action])) {
        return $map[$action];
    }

    return str_replace('_', ' ', $action);
}

function activity_table_label(?string $table): string
{
    $table = (string)$table;
    $map = [
        'users' => 'ผู้ใช้งาน',
        'photographer_profiles' => 'โปรไฟล์ช่างภาพ',
        'bookings' => 'คำขอจอง',
        'reviews' => 'รีวิว',
        'photographer_portfolios' => 'ผลงาน',
        'photographer_availability' => 'วันว่าง',
        'photographer_services' => 'ประเภทงานช่างภาพ',
        'photographer_service_areas' => 'พื้นที่ให้บริการ',
        'photographer_articles' => 'บทความช่างภาพ',
        'service_categories' => 'หมวดหมู่งาน',
        'districts' => 'อำเภอ',
        'banners' => 'แบนเนอร์',
        'settings' => 'ตั้งค่า',
        'blogs' => 'บทความเว็บ',
        'faqs' => 'คำถามที่พบบ่อย',
        'reports' => 'รายงานปัญหา',
        'contact_messages' => 'ข้อความติดต่อ',
        'security' => 'ความปลอดภัย',
    ];

    if (isset($map[$table])) {
        return $map[$table];
    }

    if ($table === '') {
        return 'ระบบ';
    }

    return $table;
}

function activity_icon(string $action): string
{
    if (strpos($action, 'security') !== false || strpos($action, 'blocked') !== false || strpos($action, 'suspend') !== false) {
        return 'fa-shield-halved';
    }

    if (strpos($action, 'booking') !== false) {
        return 'fa-calendar-check';
    }

    if (strpos($action, 'review') !== false) {
        return 'fa-star';
    }

    if (strpos($action, 'login') !== false) {
        return 'fa-right-to-bracket';
    }

    if (strpos($action, 'upload') !== false || strpos($action, 'portfolio') !== false) {
        return 'fa-image';
    }

    if (strpos($action, 'admin') !== false) {
        return 'fa-user-shield';
    }

    return 'fa-clipboard-list';
}

function activity_color_class(string $action): string
{
    if (strpos($action, 'security') !== false || strpos($action, 'blocked') !== false || strpos($action, 'suspend') !== false || strpos($action, 'reject') !== false) {
        return 'bg-red-50 text-red-700 border-red-100';
    }

    if (strpos($action, 'booking') !== false) {
        return 'bg-indigo-50 text-indigo-700 border-indigo-100';
    }

    if (strpos($action, 'review') !== false) {
        return 'bg-yellow-50 text-yellow-700 border-yellow-100';
    }

    if (strpos($action, 'login') !== false) {
        return 'bg-emerald-50 text-emerald-700 border-emerald-100';
    }

    return 'bg-slate-50 text-slate-700 border-slate-100';
}

$q = trim((string)($_GET['q'] ?? ''));
$actionFilter = trim((string)($_GET['action'] ?? ''));
$tableFilter = trim((string)($_GET['table_name'] ?? ''));
$userFilter = (int)($_GET['user_id'] ?? 0);
$dateFrom = parse_be_date_to_iso((string)($_GET['date_from'] ?? ''));
$dateTo = parse_be_date_to_iso((string)($_GET['date_to'] ?? ''));

$where = ['1=1'];
$params = [];

if ($q !== '') {
    $where[] = '(l.action LIKE ? OR l.table_name LIKE ? OR l.description LIKE ? OR l.ip_address LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    for ($i = 0; $i < 6; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if ($actionFilter !== '') {
    $where[] = 'l.action = ?';
    $params[] = $actionFilter;
}

if ($tableFilter !== '') {
    $where[] = 'l.table_name = ?';
    $params[] = $tableFilter;
}

if ($userFilter > 0) {
    $where[] = 'l.user_id = ?';
    $params[] = $userFilter;
}

if ($dateFrom !== '') {
    $where[] = 'DATE(l.created_at) >= ?';
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'DATE(l.created_at) <= ?';
    $params[] = $dateTo;
}

$whereSql = implode(' AND ', $where);

$items = db_fetch_all('SELECT l.*, u.name, u.email
                       FROM activity_logs l
                       LEFT JOIN users u ON u.id = l.user_id
                       WHERE ' . $whereSql . '
                       ORDER BY l.created_at DESC
                       LIMIT 300', $params);

$totalLogs = (int)db_fetch_value('SELECT COUNT(*) FROM activity_logs');
$todayLogs = (int)db_fetch_value('SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()');
$securityLogs = (int)db_fetch_value('SELECT COUNT(*) FROM activity_logs WHERE action LIKE "%security%" OR action LIKE "%blocked%" OR action LIKE "%upload_security%"');
$uniqueUsers = (int)db_fetch_value('SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE user_id IS NOT NULL');
$filteredTotal = count($items);

$daily = db_fetch_all('SELECT DATE(created_at) AS activity_date, COUNT(*) AS total
                       FROM activity_logs
                       WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                       GROUP BY DATE(created_at)
                       ORDER BY activity_date ASC');
$dailyMap = [];
foreach ($daily as $row) {
    $dailyMap[(string)$row['activity_date']] = (int)$row['total'];
}
$dailyChart = [];
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime('-' . $i . ' days'));
    $dailyChart[] = [
        'date' => $date,
        'label' => format_be_date($date),
        'total' => $dailyMap[$date] ?? 0,
    ];
}
$maxDaily = 1;
foreach ($dailyChart as $row) {
    if ((int)$row['total'] > $maxDaily) {
        $maxDaily = (int)$row['total'];
    }
}

$topActions = db_fetch_all('SELECT action, COUNT(*) AS total
                            FROM activity_logs
                            GROUP BY action
                            ORDER BY total DESC
                            LIMIT 8');
$maxAction = 1;
foreach ($topActions as $row) {
    if ((int)$row['total'] > $maxAction) {
        $maxAction = (int)$row['total'];
    }
}

$topTables = db_fetch_all('SELECT IFNULL(NULLIF(table_name, ""), "system") AS table_name, COUNT(*) AS total
                           FROM activity_logs
                           GROUP BY IFNULL(NULLIF(table_name, ""), "system")
                           ORDER BY total DESC
                           LIMIT 8');

$topUsers = db_fetch_all('SELECT l.user_id, COALESCE(u.name, "ระบบ") AS name, COALESCE(u.email, "-") AS email, COUNT(*) AS total
                          FROM activity_logs l
                          LEFT JOIN users u ON u.id = l.user_id
                          GROUP BY l.user_id, u.name, u.email
                          ORDER BY total DESC
                          LIMIT 8');

$actionOptions = db_fetch_all('SELECT DISTINCT action FROM activity_logs ORDER BY action');
$tableOptions = db_fetch_all('SELECT DISTINCT table_name FROM activity_logs WHERE table_name IS NOT NULL AND table_name <> "" ORDER BY table_name');
$userOptions = db_fetch_all('SELECT DISTINCT u.id, u.name, u.email
                             FROM activity_logs l
                             JOIN users u ON u.id = l.user_id
                             ORDER BY u.name');

$pageTitle = 'แดชบอร์ดประวัติการใช้งาน';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="dashboard-hero rounded-[2rem] p-6 text-white sm:p-8">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/58">ประวัติการใช้งาน</p>
                <h1 class="mt-2 text-3xl font-black sm:text-5xl">แดชบอร์ดประวัติการใช้งาน</h1>
                <p class="mt-3 max-w-2xl leading-8 text-white/68">ดูภาพรวมการใช้งานระบบ ตรวจจับกิจกรรมสำคัญ และติดตามเหตุการณ์ความปลอดภัยแบบอ่านง่าย</p>
            </div>
            <a href="/admin/dashboard.php" class="rounded-full bg-white px-5 py-3 font-black text-neutral-950 transition hover:bg-red-600 hover:text-white">
                <i class="fa-solid fa-gauge mr-2"></i>กลับแดชบอร์ด
            </a>
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="metric-card rounded-[1.5rem] p-5">
            <div class="flex items-center justify-between gap-4">
                <p class="text-sm font-bold text-neutral-500">รายการทั้งหมด</p>
                <i class="fa-solid fa-clipboard-list text-red-600"></i>
            </div>
            <p class="mt-3 text-3xl font-black text-neutral-950"><?= number_format($totalLogs) ?></p>
        </div>
        <div class="metric-card rounded-[1.5rem] p-5">
            <div class="flex items-center justify-between gap-4">
                <p class="text-sm font-bold text-neutral-500">วันนี้</p>
                <i class="fa-solid fa-calendar-day text-red-600"></i>
            </div>
            <p class="mt-3 text-3xl font-black text-neutral-950"><?= number_format($todayLogs) ?></p>
        </div>
        <div class="metric-card rounded-[1.5rem] p-5">
            <div class="flex items-center justify-between gap-4">
                <p class="text-sm font-bold text-neutral-500">ผู้ใช้ที่เกี่ยวข้อง</p>
                <i class="fa-solid fa-users text-red-600"></i>
            </div>
            <p class="mt-3 text-3xl font-black text-neutral-950"><?= number_format($uniqueUsers) ?></p>
        </div>
        <div class="metric-card rounded-[1.5rem] p-5">
            <div class="flex items-center justify-between gap-4">
                <p class="text-sm font-bold text-neutral-500">เหตุการณ์เสี่ยง</p>
                <i class="fa-solid fa-shield-halved text-red-600"></i>
            </div>
            <p class="mt-3 text-3xl font-black text-neutral-950"><?= number_format($securityLogs) ?></p>
        </div>
    </div>

    <form class="stock-card mt-6 grid gap-3 rounded-[1.75rem] p-5 lg:grid-cols-6">
        <label class="icon-input block lg:col-span-2">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input name="q" value="<?= h($q) ?>" placeholder="ค้นหาการกระทำ, ผู้ใช้, IP, รายละเอียด" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold">
        </label>
        <select name="action" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <option value="">ทุกการกระทำ</option>
            <?php foreach ($actionOptions as $option): ?>
                <option value="<?= h($option['action']) ?>" <?php if ($actionFilter === $option['action']): ?>selected<?php endif; ?>>
                    <?= h(activity_action_label((string)$option['action'])) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="table_name" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <option value="">ทุกข้อมูล</option>
            <?php foreach ($tableOptions as $option): ?>
                <option value="<?= h($option['table_name']) ?>" <?php if ($tableFilter === $option['table_name']): ?>selected<?php endif; ?>>
                    <?= h(activity_table_label((string)$option['table_name'])) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="user_id" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <option value="0">ทุกผู้ใช้</option>
            <?php foreach ($userOptions as $option): ?>
                <option value="<?= (int)$option['id'] ?>" <?php if ($userFilter === (int)$option['id']): ?>selected<?php endif; ?>>
                    <?= h($option['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="stock-button rounded-2xl px-5 py-3 font-black"><i class="fa-solid fa-chart-line mr-2"></i>ดูรายงาน</button>
        <div class="grid gap-3 lg:col-span-6 sm:grid-cols-3">
            <?= be_date_input('date_from', $dateFrom, 'stock-input rounded-2xl px-4 py-3 font-semibold', false, 'วันที่เริ่ม พ.ศ.') ?>
            <?= be_date_input('date_to', $dateTo, 'stock-input rounded-2xl px-4 py-3 font-semibold', false, 'วันที่สิ้นสุด พ.ศ.') ?>
            <a href="/admin/activity_logs.php" class="grid place-items-center rounded-2xl border border-neutral-200 px-5 py-3 font-black text-neutral-700 transition hover:bg-neutral-950 hover:text-white">
                <i class="fa-solid fa-rotate-left mr-2"></i>ล้างตัวกรอง
            </a>
        </div>
    </form>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
        <div class="stock-card rounded-[1.75rem] p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="section-kicker">กราฟรายวัน</p>
                    <h2 class="mt-1 text-2xl font-black text-neutral-950">กิจกรรม 14 วันล่าสุด</h2>
                </div>
                <span class="rounded-full bg-neutral-100 px-4 py-2 text-sm font-black text-neutral-600">
                    <i class="fa-solid fa-chart-column mr-2 text-red-600"></i>สูงสุด <?= number_format($maxDaily) ?> log
                </span>
            </div>
            <div class="mt-6 flex h-72 items-end gap-2 overflow-x-auto rounded-[1.5rem] bg-neutral-50 p-4">
                <?php foreach ($dailyChart as $row): ?>
                    <?php
                    $height = 6;
                    if ($maxDaily > 0) {
                        $height = max(6, ((int)$row['total'] / $maxDaily) * 100);
                    }
                    ?>
                    <div class="flex min-w-[52px] flex-1 flex-col items-center justify-end gap-2">
                        <div class="text-xs font-black text-neutral-500"><?= (int)$row['total'] ?></div>
                        <div class="w-full rounded-t-2xl bg-gradient-to-t from-red-600 to-neutral-950 shadow-lg shadow-red-900/10 transition hover:from-neutral-950 hover:to-red-600" style="height: <?= number_format($height, 0) ?>%"></div>
                        <div class="text-center text-[10px] font-black leading-4 text-neutral-400"><?= h(substr($row['label'], 0, 5)) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">การกระทำยอดนิยม</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950">การกระทำที่เกิดบ่อย</h2>
            <div class="mt-5 grid gap-4">
                <?php foreach ($topActions as $row): ?>
                    <?php
                    $width = 0;
                    if ($maxAction > 0) {
                        $width = ((int)$row['total'] / $maxAction) * 100;
                    }
                    ?>
                    <div>
                        <div class="mb-2 flex justify-between gap-4 text-sm font-black">
                            <span><i class="fa-solid <?= h(activity_icon((string)$row['action'])) ?> mr-2 text-red-600"></i><?= h(activity_action_label((string)$row['action'])) ?></span>
                            <span><?= number_format((int)$row['total']) ?></span>
                        </div>
                        <div class="rating-bar"><span style="width: <?= number_format($width, 0) ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">ข้อมูลที่ถูกแก้ไข</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950">ตาราง/โมดูลที่มีการใช้งาน</h2>
            <div class="mt-4 grid gap-3">
                <?php foreach ($topTables as $row): ?>
                    <div class="flex items-center justify-between rounded-2xl bg-neutral-50 px-4 py-3 text-sm">
                        <span class="font-black"><i class="fa-solid fa-table mr-2 text-red-600"></i><?= h(activity_table_label((string)$row['table_name'])) ?></span>
                        <span class="rounded-full bg-white px-3 py-1 font-black text-neutral-600"><?= number_format((int)$row['total']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">ผู้ใช้งานที่ active</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950">ผู้ใช้ที่มีกิจกรรมสูง</h2>
            <div class="mt-4 grid gap-3">
                <?php foreach ($topUsers as $row): ?>
                    <div class="flex items-center justify-between gap-4 rounded-2xl bg-neutral-50 px-4 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-black"><?= h($row['name']) ?></p>
                            <p class="truncate text-xs font-bold text-neutral-500"><?= h($row['email']) ?></p>
                        </div>
                        <span class="rounded-full bg-white px-3 py-1 font-black text-neutral-600"><?= number_format((int)$row['total']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] bg-red-50 p-6">
            <p class="section-kicker">สรุปตัวกรอง</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950">ผลลัพธ์ที่กำลังแสดง</h2>
            <div class="mt-4 grid gap-3 text-sm font-bold text-red-700">
                <div class="rounded-2xl bg-white px-4 py-3"><i class="fa-solid fa-filter mr-2"></i>พบ <?= number_format($filteredTotal) ?> รายการล่าสุด</div>
                <div class="rounded-2xl bg-white px-4 py-3"><i class="fa-solid fa-calendar mr-2"></i>ช่วงวันที่: <?php if ($dateFrom !== '' || $dateTo !== ''): ?><?= h(format_be_date($dateFrom ?: '')) ?> - <?= h(format_be_date($dateTo ?: '')) ?><?php else: ?>ทั้งหมด<?php endif; ?></div>
                <div class="rounded-2xl bg-white px-4 py-3"><i class="fa-solid fa-shield-halved mr-2"></i>ตรวจดูเหตุการณ์เสี่ยงได้จากการค้นหา “security” หรือเลือกการกระทำที่เกี่ยวข้อง</div>
            </div>
        </div>
    </div>

    <div class="stock-card mt-6 rounded-[1.75rem] p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="section-kicker">ไทม์ไลน์ล่าสุด</p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">รายการกิจกรรมล่าสุด</h2>
            </div>
            <span class="rounded-full bg-neutral-100 px-4 py-2 text-sm font-black text-neutral-600">
                <i class="fa-solid fa-list mr-2 text-red-600"></i><?= number_format($filteredTotal) ?> รายการ
            </span>
        </div>

        <div class="mt-6 grid gap-3">
            <?php foreach ($items as $log): ?>
                <?php
                $logName = 'ระบบ';
                if (!empty($log['name'])) {
                    $logName = $log['name'];
                }
                $logEmail = '-';
                if (!empty($log['email'])) {
                    $logEmail = $log['email'];
                }
                $description = trim((string)($log['description'] ?? ''));
                ?>
                <article class="rounded-[1.5rem] border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg <?= h(activity_color_class((string)$log['action'])) ?>">
                    <div class="grid gap-4 lg:grid-cols-[52px_1fr_auto] lg:items-start">
                        <div class="grid h-12 w-12 place-items-center rounded-2xl bg-white text-lg shadow-sm">
                            <i class="fa-solid <?= h(activity_icon((string)$log['action'])) ?>"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-black text-neutral-950"><?= h(activity_action_label((string)$log['action'])) ?></h3>
                                <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-neutral-600"><?= h(activity_table_label($log['table_name'] ?? '')) ?></span>
                                <?php if (!empty($log['record_id'])): ?>
                                    <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-neutral-600">#<?= (int)$log['record_id'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs font-bold text-neutral-500">
                                <span><i class="fa-solid fa-user mr-1 text-red-600"></i><?= h($logName) ?></span>
                                <span><i class="fa-solid fa-envelope mr-1 text-red-600"></i><?= h($logEmail) ?></span>
                                <span><i class="fa-solid fa-network-wired mr-1 text-red-600"></i><?= h($log['ip_address']) ?></span>
                            </div>
                            <?php if ($description !== ''): ?>
                                <p class="mt-3 rounded-2xl bg-white/80 px-4 py-3 text-sm font-semibold leading-6 text-neutral-700"><?= h($description) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($log['user_agent'])): ?>
                                <p class="mt-2 line-clamp-1 text-xs font-semibold text-neutral-400"><i class="fa-solid fa-desktop mr-1"></i><?= h($log['user_agent']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="rounded-2xl bg-white px-4 py-3 text-right text-xs font-black text-neutral-500">
                            <i class="fa-solid fa-clock mr-1 text-red-600"></i><?= h(format_be_datetime($log['created_at'])) ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if (!$items): ?>
                <div class="empty-state rounded-[2rem] p-10 text-center">
                    <i class="fa-solid fa-clipboard-list text-5xl text-red-600"></i>
                    <h3 class="mt-4 text-2xl font-black">ไม่พบประวัติการใช้งาน</h3>
                    <p class="mt-2 text-neutral-600">ลองล้างตัวกรองหรือเลือกช่วงวันที่ใหม่</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

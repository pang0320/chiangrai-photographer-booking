<?php
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user = current_user();
$notificationsPath = '/notifications.php';
if (defined('CUSTOMER_NOTIFICATIONS_PAGE')) {
    requireRole('customer');
    $notificationsPath = '/customer/notifications.php';
}
$cleanContext = clean_context_init(['filter'], $notificationsPath);

function notification_is_report_notice(array $notification): bool
{
    $type = (string)($notification['type'] ?? '');
    $title = (string)($notification['title'] ?? '');
    $relatedId = (int)($notification['related_id'] ?? 0);

    if ($relatedId <= 0) {
        return false;
    }

    if ($type === 'report_notice') {
        return true;
    }

    return $title === 'แจ้งเตือนรายงานปัญหา';
}

function notification_report_detail(array $notification): ?array
{
    if (!notification_is_report_notice($notification)) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM reports WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$notification['related_id']]);
    $report = $stmt->fetch();

    if (!$report) {
        return null;
    }

    return $report;
}

function notification_report_target_text(array $report): string
{
    $targetType = (string)$report['target_type'];
    $targetId = (int)$report['target_id'];

    if ($targetType === 'photographer') {
        $rows = db_fetch_all('SELECT p.display_name, d.district_name
                              FROM photographer_profiles p
                              LEFT JOIN districts d ON d.id = p.main_district_id
                              WHERE p.id = ?
                              LIMIT 1', [$targetId]);
        if ($rows) {
            return 'โปรไฟล์ช่างภาพ: ' . (string)$rows[0]['display_name'] . ' · อำเภอหลัก: ' . (string)($rows[0]['district_name'] ?: '-');
        }

        return 'โปรไฟล์ช่างภาพ #' . $targetId;
    }

    if ($targetType === 'review') {
        $rows = db_fetch_all('SELECT r.rating_overall, r.comment, p.display_name
                              FROM reviews r
                              JOIN photographer_profiles p ON p.id = r.photographer_id
                              WHERE r.id = ?
                              LIMIT 1', [$targetId]);
        if ($rows) {
            $comment = trim(strip_tags((string)$rows[0]['comment']));
            if ($comment === '') {
                $comment = 'ไม่มีข้อความรีวิว';
            }
            return 'รีวิวของช่างภาพ: ' . (string)$rows[0]['display_name'] . ' · คะแนน ' . (string)$rows[0]['rating_overall'] . '/5 · ' . $comment;
        }

        return 'รีวิว #' . $targetId;
    }

    if ($targetType === 'booking') {
        $rows = db_fetch_all('SELECT b.booking_code, b.status, b.booking_date, p.display_name
                              FROM bookings b
                              JOIN photographer_profiles p ON p.id = b.photographer_id
                              WHERE b.id = ?
                              LIMIT 1', [$targetId]);
        if ($rows) {
            return 'คำขอจอง: ' . (string)$rows[0]['booking_code'] . ' · ช่างภาพ: ' . (string)$rows[0]['display_name'] . ' · สถานะ: ' . booking_status_label((string)$rows[0]['status']);
        }

        return 'คำขอจอง #' . $targetId;
    }

    if ($targetType === 'article') {
        $rows = db_fetch_all('SELECT a.title, p.display_name
                              FROM photographer_articles a
                              JOIN photographer_profiles p ON p.id = a.photographer_id
                              WHERE a.id = ?
                              LIMIT 1', [$targetId]);
        if ($rows) {
            return 'บทความช่างภาพ: ' . (string)$rows[0]['title'] . ' · ผู้เขียน: ' . (string)$rows[0]['display_name'];
        }

        $blogs = db_fetch_all('SELECT title FROM blogs WHERE id = ? LIMIT 1', [$targetId]);
        if ($blogs) {
            return 'บทความระบบ: ' . (string)$blogs[0]['title'];
        }

        return 'บทความ #' . $targetId;
    }

    return 'รายการที่ถูกรายงาน #' . $targetId;
}

if (is_post()) {
    verify_csrf();

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'open_notification') {
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        $stmt = db()->prepare('SELECT * FROM notifications WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$notificationId, (int)$user['id']]);
        $notification = $stmt->fetch();

        if ($notification) {
            $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
            $stmt->execute([$notificationId, (int)$user['id']]);
            log_activity('open_notification', 'notifications', $notificationId);

            if (notification_is_report_notice($notification)) {
                $_SESSION['notification_detail_id'] = $notificationId;
                redirect($notificationsPath);
            }

            redirect(notification_target_url($notification, $user));
        }

        flash('error', 'ไม่พบแจ้งเตือนนี้');
    }

    if ($action === 'mark_all_read') {
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([(int)$user['id']]);
        log_activity('mark_notifications_read', 'notifications', null);
        flash('success', 'อ่านแจ้งเตือนทั้งหมดแล้ว');
    }

    redirect($notificationsPath);
}

$filter = 'all';
if (isset($cleanContext['filter'])) {
    $filter = (string)$cleanContext['filter'];
}
if (!in_array($filter, ['all', 'read', 'unread'], true)) {
    $filter = 'all';
}

$whereSql = 'user_id = ?';
$params = [(int)$user['id']];
if ($filter === 'read') {
    $whereSql .= ' AND is_read = 1';
}
if ($filter === 'unread') {
    $whereSql .= ' AND is_read = 0';
}
$notifications = db_fetch_all('SELECT * FROM notifications WHERE ' . $whereSql . ' ORDER BY created_at DESC LIMIT 50', $params);

$detailNotification = null;
$detailReport = null;
if (!empty($_SESSION['notification_detail_id'])) {
    $detailNotificationId = (int)$_SESSION['notification_detail_id'];
    unset($_SESSION['notification_detail_id']);

    $stmt = db()->prepare('SELECT * FROM notifications WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$detailNotificationId, (int)$user['id']]);
    $detailNotification = $stmt->fetch();

    if ($detailNotification) {
        $detailReport = notification_report_detail($detailNotification);
    }
}

$pageTitle = 'แจ้งเตือน';
include __DIR__ . '/includes/header.php';
?>

<section class="stock-shell px-4 py-10 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">ศูนย์รวมแจ้งเตือน</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950">แจ้งเตือน</h1>
            <p class="mt-2 max-w-3xl text-sm font-bold leading-7 text-neutral-500">รวมแจ้งเตือนสำคัญ เช่น คำขอจองใหม่ การเปลี่ยนสถานะ รีวิว การอนุมัติช่างภาพ และการระงับบัญชี</p>
        </div>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_all_read">
            <button class="rounded-full bg-neutral-950 px-5 py-3 text-sm font-black text-white hover:bg-red-600">
                <i class="fa-solid fa-circle-check mr-2"></i>อ่านทั้งหมด
            </button>
        </form>
    </div>

    <div class="mt-6 flex flex-wrap gap-2">
        <?= clean_context_button($notificationsPath, ['filter' => 'all'], '<i class="fa-solid fa-list mr-1"></i>ทั้งหมด', 'rounded-full px-4 py-2 text-sm font-black ' . ($filter === 'all' ? 'bg-neutral-950 text-white' : 'bg-white text-neutral-700')) ?>
        <?= clean_context_button($notificationsPath, ['filter' => 'unread'], '<i class="fa-solid fa-bell mr-1"></i>ยังไม่อ่าน', 'rounded-full px-4 py-2 text-sm font-black ' . ($filter === 'unread' ? 'bg-neutral-950 text-white' : 'bg-white text-neutral-700')) ?>
        <?= clean_context_button($notificationsPath, ['filter' => 'read'], '<i class="fa-solid fa-circle-check mr-1"></i>อ่านแล้ว', 'rounded-full px-4 py-2 text-sm font-black ' . ($filter === 'read' ? 'bg-neutral-950 text-white' : 'bg-white text-neutral-700')) ?>
    </div>

    <?php if ($detailNotification): ?>
        <div class="stock-card mt-6 overflow-hidden rounded-[2rem] border-red-200">
            <div class="bg-gradient-to-r from-neutral-950 to-red-700 p-6 text-white">
                <p class="text-xs font-black uppercase tracking-[0.22em] text-red-100">
                    <i class="fa-solid fa-shield-halved mr-2"></i>รายละเอียดแจ้งเตือน
                </p>
                <h2 class="mt-2 text-2xl font-black"><?= h($detailNotification['title']) ?></h2>
                <p class="mt-2 text-sm font-bold text-white/70">
                    <i class="fa-solid fa-clock mr-1"></i><?= h(format_be_datetime($detailNotification['created_at'])) ?>
                </p>
            </div>

            <div class="grid gap-4 p-6">
                <div class="rounded-[1.5rem] bg-neutral-50 p-5">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-neutral-400">
                        <i class="fa-solid fa-message mr-2 text-red-600"></i>ข้อความแจ้งเตือนฉบับเต็ม
                    </p>
                    <p class="mt-3 whitespace-pre-line text-base font-bold leading-8 text-neutral-800"><?= h($detailNotification['message']) ?></p>
                </div>

                <?php if ($detailReport): ?>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-[1.5rem] border border-neutral-100 bg-white p-5 shadow-sm">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-red-600">
                                <i class="fa-solid fa-bullseye mr-2"></i>รายการที่ถูกรายงาน
                            </p>
                            <h3 class="mt-2 text-lg font-black text-neutral-950"><?= h(notification_report_target_text($detailReport)) ?></h3>
                            <p class="mt-3 text-sm font-bold leading-7 text-neutral-500">
                                ระบบแสดงรายละเอียดรายงานให้ผู้ถูกรายงานทราบเท่านั้น และไม่เปิดเผยชื่อหรือข้อมูลผู้รายงาน
                            </p>
                        </div>

                        <div class="rounded-[1.5rem] border border-red-100 bg-red-50 p-5">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-red-700">
                                <i class="fa-solid fa-triangle-exclamation mr-2"></i>เหตุผลที่ถูกรายงาน
                            </p>
                            <h3 class="mt-2 text-lg font-black text-neutral-950"><?= h($detailReport['reason']) ?></h3>
                            <?php if (trim((string)$detailReport['detail']) !== ''): ?>
                                <p class="mt-3 whitespace-pre-line text-sm font-bold leading-7 text-neutral-700"><?= h($detailReport['detail']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (trim((string)$detailReport['admin_note']) !== ''): ?>
                        <div class="rounded-[1.5rem] border border-sky-100 bg-sky-50 p-5 text-sm font-bold leading-7 text-sky-900">
                            <i class="fa-solid fa-user-shield mr-2"></i>หมายเหตุจากผู้ดูแลระบบ: <?= nl2br(h($detailReport['admin_note'])) ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="flex flex-wrap gap-2">
                    <a href="<?= h($notificationsPath) ?>" class="rounded-full bg-neutral-950 px-5 py-3 text-sm font-black text-white transition hover:bg-red-600">
                        <i class="fa-solid fa-arrow-left mr-2"></i>กลับไปแจ้งเตือนทั้งหมด
                    </a>
                    <?php if ($detailReport && (string)$detailReport['target_type'] === 'review'): ?>
                        <a href="/photographer/reviews.php" class="rounded-full bg-white px-5 py-3 text-sm font-black text-neutral-800 shadow-sm transition hover:bg-neutral-950 hover:text-white">
                            <i class="fa-solid fa-star mr-2"></i>ไปหน้ารีวิวของฉัน
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$notifications): ?>
        <div class="stock-card mt-6 rounded-[2rem] p-10 text-center">
            <div class="mx-auto grid h-16 w-16 place-items-center rounded-3xl bg-red-50 text-2xl text-red-600">
                <i class="fa-regular fa-bell"></i>
            </div>
            <h2 class="mt-4 text-2xl font-black text-neutral-950">ยังไม่มีแจ้งเตือน</h2>
            <p class="mt-2 text-neutral-600">เมื่อมีคำขอจอง รีวิว หรือการเปลี่ยนสถานะ ระบบจะแสดงที่นี่</p>
        </div>
    <?php else: ?>
        <div class="mt-6 grid gap-3">
            <?php foreach ($notifications as $notification): ?>
                <form method="post" class="contents">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="open_notification">
                    <input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>">
                    <button type="submit" class="stock-card stock-card-hover block w-full rounded-[1.5rem] p-5 text-left <?php if (!(int)$notification['is_read']): ?>border-red-200<?php endif; ?>">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="font-black text-neutral-950"><?= h($notification['title']) ?></h2>
                                <p class="mt-1 text-sm leading-6 text-neutral-600"><?= h($notification['message']) ?></p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-black <?php if ((int)$notification['is_read']): ?>bg-neutral-100 text-neutral-600<?php else: ?>bg-red-50 text-red-700<?php endif; ?>">
                                <?php if ((int)$notification['is_read']): ?>
                                    อ่านแล้ว
                                <?php else: ?>
                                    ใหม่
                                <?php endif; ?>
                            </span>
                        </div>
                        <p class="mt-3 text-xs font-bold text-neutral-400">
                            <?= h(format_be_datetime($notification['created_at'])) ?>
                            <span class="ml-2 text-red-600"><i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>เปิดรายละเอียด</span>
                        </p>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

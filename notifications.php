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

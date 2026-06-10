<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['customer', 'photographer']);
ensure_booking_range_columns();
$user = current_user();
$isPhotographerHiring = (string)$user['role_name'] === 'photographer';
$cleanContext = clean_context_init(['id']);
$id = 0;
if (isset($cleanContext['id'])) {
    $id = (int)$cleanContext['id'];
}

$stmt = db()->prepare('SELECT b.*, p.display_name, p.phone_public, p.line_id, p.facebook_url, p.instagram_url, p.user_id AS photographer_user_id, sc.name category_name, d.district_name FROM bookings b JOIN photographer_profiles p ON p.id=b.photographer_id JOIN service_categories sc ON sc.id=b.category_id JOIN districts d ON d.id=b.district_id WHERE b.id=? AND b.customer_id=? AND b.deleted_at IS NULL LIMIT 1');
$stmt->execute([$id, (int)$user['id']]);
$booking = $stmt->fetch();
if (!$booking) exit('Booking not found');

if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'cancel_booking') {
        $currentStatus = (string)$booking['status'];
        if (in_array($currentStatus, ['completed', 'cancelled', 'rejected'], true)) {
            flash('error', 'รายการนี้ไม่สามารถยกเลิกได้');
            clean_redirect('/customer/booking_detail.php', ['id' => $id]);
        }

        db()->beginTransaction();

        $stmt = db()->prepare('UPDATE bookings
                               SET status = "cancelled",
                                   rejection_reason = "ยกเลิกโดยผู้จ้าง",
                                   updated_at = NOW()
                               WHERE id = ?
                                 AND customer_id = ?
                                 AND deleted_at IS NULL
                                 AND status NOT IN ("completed", "cancelled", "rejected")');
        $stmt->execute([$id, (int)$user['id']]);

        if ($stmt->rowCount() <= 0) {
            db()->rollBack();
            flash('error', 'รายการนี้ไม่สามารถยกเลิกได้');
            clean_redirect('/customer/booking_detail.php', ['id' => $id]);
        }

        add_booking_status_log($id, $currentStatus, 'cancelled', (int)$user['id'], 'ยกเลิกโดยผู้จ้าง');
        sync_availability_after_booking_status($id);
        notify_user((int)$booking['photographer_user_id'], 'ผู้จ้างยกเลิกคำขอจอง', (string)$booking['booking_code'] . ' · ยกเลิกโดยผู้จ้าง', 'booking', $id);
        log_activity('customer_cancel_booking', 'bookings', $id);

        db()->commit();

        flash('success', 'ยกเลิกงานเรียบร้อยแล้ว');
        clean_redirect('/customer/booking_detail.php', ['id' => $id]);
    }

    flash('warning', 'ไม่พบคำสั่งที่ต้องทำรายการ');
    clean_redirect('/customer/booking_detail.php', ['id' => $id]);
}

$logs = db()->prepare('SELECT l.*, u.name FROM booking_status_logs l LEFT JOIN users u ON u.id=l.changed_by WHERE l.booking_id=? ORDER BY l.created_at');
$logs->execute([$id]);
$logs = $logs->fetchAll();
$reviewExists = db()->prepare('SELECT id FROM reviews WHERE booking_id=? AND deleted_at IS NULL');
$reviewExists->execute([$id]);
$hasReview = (bool)$reviewExists->fetchColumn();
$canCancelBooking = !in_array((string)$booking['status'], ['completed', 'cancelled', 'rejected'], true);
$statusHtml = status_badge((string)$booking['status']);
if ((string)$booking['status'] === 'cancelled' && (string)($booking['rejection_reason'] ?? '') === 'ยกเลิกโดยผู้จ้าง') {
    $statusHtml = '<span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700"><i class="fa-solid fa-ban"></i>ยกเลิกโดยผู้จ้าง</span>';
}
$pageTitle = 'รายละเอียดการจอง';
include __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-5xl px-4 py-10">
    <div class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap justify-between gap-4"><div><p class="section-kicker"><i class="fa-solid <?= $isPhotographerHiring ? 'fa-camera-retro' : 'fa-user' ?> mr-2"></i><?= $isPhotographerHiring ? 'งานที่ฉันจ้าง' : 'รายละเอียดการจอง' ?></p><h1 class="mt-1 text-2xl font-extrabold"><?= h($booking['booking_code']) ?></h1><p class="text-slate-600"><?= h($booking['display_name']) ?> · <?= h($booking['category_name']) ?></p></div><?= $statusHtml ?></div>
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="rounded-3xl bg-slate-50 p-5"><b>รายละเอียดงาน</b><p class="mt-2"><?= nl2br(h($booking['job_detail'])) ?></p><p class="mt-3 text-sm text-slate-600"><?= h(booking_range_label($booking)) ?> · <?= h($booking['district_name']) ?></p></div>
            <div class="rounded-3xl bg-slate-50 p-5"><b>ช่องทางติดต่อช่างภาพ</b><p class="mt-2">โทร: <?= h($booking['phone_public']) ?></p><p>LINE: <?= h($booking['line_id']) ?></p><p>Facebook: <?= h($booking['facebook_url']) ?></p><p>Instagram: <?= h($booking['instagram_url']) ?></p></div>
        </div>
        <div class="mt-6 flex flex-wrap gap-3">
            <?php if ($canCancelBooking): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel_booking">
                    <button class="btn-danger btn-lg rounded-2xl"
                            data-confirm="ยืนยันยกเลิกงานนี้?"
                            data-confirm-text="สถานะจะเปลี่ยนเป็นยกเลิกโดยผู้จ้าง และระบบจะแจ้งเตือนไปยังช่างภาพ"
                            data-confirm-button="ยกเลิกงาน">
                        <i class="fa-solid fa-ban mr-2"></i>ยกเลิกงาน
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($booking['status'] === 'completed' && !$hasReview): ?>
                <?= clean_context_button('/customer/review.php', ['booking_id' => $id], '<i class="fa-solid fa-star mr-2"></i>รีวิวช่างภาพ', 'btn-success btn-lg rounded-2xl') ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="mt-6 rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
        <div>
            <p class="section-kicker"><i class="fa-solid fa-clock-rotate-left mr-2"></i>ประวัติสถานะ</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950">เส้นเวลาการจอง</h2>
            <p class="mt-2 text-sm font-bold text-neutral-500">ติดตามทุกจังหวะของคำขอจอง ตั้งแต่สร้างรายการจนถึงสถานะล่าสุด</p>
        </div>
        <div class="mt-6">
            <?= booking_status_timeline_html($logs) ?>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>

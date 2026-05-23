<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole(['customer', 'photographer']);
$user = current_user();
$isPhotographerHiring = (string)$user['role_name'] === 'photographer';
$cleanContext = clean_context_init(['id']);
$id = 0;
if (isset($cleanContext['id'])) {
    $id = (int)$cleanContext['id'];
}

$stmt = db()->prepare('SELECT b.*, p.display_name, p.phone_public, p.line_id, p.facebook_url, p.instagram_url, sc.name category_name, d.district_name FROM bookings b JOIN photographer_profiles p ON p.id=b.photographer_id JOIN service_categories sc ON sc.id=b.category_id JOIN districts d ON d.id=b.district_id WHERE b.id=? AND b.customer_id=? AND b.deleted_at IS NULL LIMIT 1');
$stmt->execute([$id, (int)$user['id']]);
$booking = $stmt->fetch();
if (!$booking) exit('Booking not found');

$postedAction = '';
if (isset($_POST['action'])) {
    $postedAction = (string)$_POST['action'];
}
if (is_post() && $postedAction === 'cancel') {
    verify_csrf();
    if (!in_array($booking['status'], ['completed','cancelled'], true)) {
        db()->prepare('UPDATE bookings SET status="cancelled", updated_at=NOW() WHERE id=?')->execute([$id]);
        $cancelNote = 'ลูกค้ายกเลิก';
        if ($isPhotographerHiring) {
            $cancelNote = 'ช่างภาพผู้จ้างยกเลิก';
        }
        add_booking_status_log($id, $booking['status'], 'cancelled', (int)$user['id'], $cancelNote);
        sync_availability_after_booking_status($id);
        $ownerStmt = db()->prepare('SELECT user_id FROM photographer_profiles WHERE id = ?');
        $ownerStmt->execute([(int)$booking['photographer_id']]);
        $cancelTitle = 'ลูกค้ายกเลิกคำขอจอง';
        if ($isPhotographerHiring) {
            $cancelTitle = 'ช่างภาพผู้จ้างยกเลิกคำขอจอง';
        }
        notify_user((int)$ownerStmt->fetchColumn(), $cancelTitle, $booking['booking_code'], 'booking', $id);
        log_activity('cancel_booking', 'bookings', $id);
        flash('success', 'ยกเลิกคำขอจองแล้ว');
    }
    clean_redirect('/customer/booking_detail.php', ['id' => $id]);
}

$logs = db()->prepare('SELECT l.*, u.name FROM booking_status_logs l LEFT JOIN users u ON u.id=l.changed_by WHERE l.booking_id=? ORDER BY l.created_at');
$logs->execute([$id]);
$logs = $logs->fetchAll();
$reviewExists = db()->prepare('SELECT id FROM reviews WHERE booking_id=? AND deleted_at IS NULL');
$reviewExists->execute([$id]);
$hasReview = (bool)$reviewExists->fetchColumn();
$pageTitle = 'รายละเอียดการจอง';
include __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-5xl px-4 py-10">
    <div class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap justify-between gap-4"><div><p class="section-kicker"><i class="fa-solid <?= $isPhotographerHiring ? 'fa-camera-retro' : 'fa-user' ?> mr-2"></i><?= $isPhotographerHiring ? 'งานที่ฉันจ้าง' : 'รายละเอียดการจอง' ?></p><h1 class="mt-1 text-2xl font-extrabold"><?= h($booking['booking_code']) ?></h1><p class="text-slate-600"><?= h($booking['display_name']) ?> · <?= h($booking['category_name']) ?></p></div><?= status_badge($booking['status']) ?></div>
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="rounded-3xl bg-slate-50 p-5"><b>รายละเอียดงาน</b><p class="mt-2"><?= nl2br(h($booking['job_detail'])) ?></p><p class="mt-3 text-sm text-slate-600"><?= h(format_be_date($booking['booking_date'])) ?> · <?= h(time_slot_label($booking['time_slot'])) ?> · <?= h($booking['district_name']) ?></p></div>
            <div class="rounded-3xl bg-slate-50 p-5"><b>ช่องทางติดต่อช่างภาพ</b><p class="mt-2">โทร: <?= h($booking['phone_public']) ?></p><p>LINE: <?= h($booking['line_id']) ?></p><p>Facebook: <?= h($booking['facebook_url']) ?></p><p>Instagram: <?= h($booking['instagram_url']) ?></p></div>
        </div>
        <div class="mt-6 flex flex-wrap gap-3">
            <?php if (!in_array($booking['status'], ['completed', 'cancelled'], true)): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <button data-confirm="ยืนยันยกเลิกคำขอจอง?" class="btn-muted btn-lg rounded-2xl">
                        <i class="fa-solid fa-xmark mr-2"></i>ยกเลิก
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

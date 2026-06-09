<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');
ensure_booking_range_columns();

$profile = photographer_profile_by_user((int)current_user()['id']);
$pid = (int)$profile['id'];
$cleanContext = clean_context_init(['id']);
$id = 0;
if (isset($cleanContext['id'])) {
    $id = (int)$cleanContext['id'];
}

$stmt = db()->prepare('SELECT b.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone, sc.name AS category_name, d.district_name
                       FROM bookings b
                       JOIN users u ON u.id = b.customer_id
                       JOIN service_categories sc ON sc.id = b.category_id
                       JOIN districts d ON d.id = b.district_id
                       WHERE b.id = ? AND b.photographer_id = ? AND b.deleted_at IS NULL
                       LIMIT 1');
$stmt->execute([$id, $pid]);
$booking = $stmt->fetch();

if (!$booking) {
    exit('ไม่พบรายการจอง');
}

$stmt = db()->prepare('SELECT l.*, u.name
                       FROM booking_status_logs l
                       LEFT JOIN users u ON u.id = l.changed_by
                       WHERE l.booking_id = ?
                       ORDER BY l.created_at');
$stmt->execute([$id]);
$logs = $stmt->fetchAll();

$pageTitle = 'รายละเอียดคำขอจอง';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="stock-card rounded-[1.5rem] p-6">
        <div class="flex flex-wrap justify-between gap-4">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">รายละเอียดการจอง</p>
                <h1 class="mt-1 text-3xl font-black text-neutral-950"><?= h($booking['booking_code']) ?></h1>
                <p class="text-neutral-600"><?= h($booking['customer_name']) ?> · <?= h($booking['category_name']) ?></p>
            </div>
            <?= status_badge($booking['status']) ?>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="rounded-[1.35rem] bg-neutral-50 p-5">
                <b>ข้อมูลงาน</b>
                <p class="mt-2"><?= nl2br(h($booking['job_detail'])) ?></p>
                <p class="mt-3 text-sm text-neutral-600">
                    <?= h(booking_range_label($booking)) ?> · <?= h($booking['district_name']) ?>
                </p>
            </div>
            <div class="rounded-[1.35rem] bg-neutral-50 p-5">
                <b>ข้อมูลลูกค้า</b>
                <p class="mt-2"><?= h($booking['customer_name']) ?></p>
                <p><?= h($booking['customer_email']) ?></p>
                <?php
                $contactPhoneText = $booking['customer_phone'];
                if (!empty($booking['contact_phone'])) {
                    $contactPhoneText = $booking['contact_phone'];
                }
                ?>
                <p><?= h($contactPhoneText) ?></p>
                <p><?= h($booking['contact_channel']) ?></p>
            </div>
        </div>

        <?= clean_context_button('/photographer/bookings.php', ['tab' => 'active'], '<i class="fa-solid fa-pen mr-2"></i>จัดการสถานะ', 'mt-6 inline-flex rounded-full bg-neutral-950 px-5 py-3 font-black text-white hover:bg-red-600') ?>
    </div>

    <div class="stock-card mt-6 rounded-[1.5rem] p-6">
        <div>
            <p class="section-kicker"><i class="fa-solid fa-clock-rotate-left mr-2"></i>ประวัติสถานะต่าง ๆ</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950">Timeline การเปลี่ยนสถานะ</h2>
            <p class="mt-2 text-sm font-bold text-neutral-500">ทุกครั้งที่มีการเปลี่ยนสถานะ ระบบจะบันทึกผู้ดำเนินการ เวลา สถานะเดิม และสถานะใหม่</p>
        </div>
        <div class="mt-6">
            <?= booking_status_timeline_html($logs) ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

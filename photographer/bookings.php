<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');

$profile = photographer_profile_by_user((int)current_user()['id']);
$pid = (int)$profile['id'];
$allowedStatuses = ['pending', 'accepted', 'confirmed', 'completed', 'rejected', 'cancelled'];

if (is_post()) {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $newStatus = (string)($_POST['status'] ?? '');
    $reason = trim((string)($_POST['rejection_reason'] ?? ''));

    $stmt = db()->prepare('SELECT * FROM bookings WHERE id = ? AND photographer_id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$id, $pid]);
    $booking = $stmt->fetch();

    if ($booking && in_array($newStatus, ['accepted', 'rejected', 'confirmed', 'completed'], true)) {
        if ($newStatus === 'rejected' && $reason === '') {
            flash('error', 'กรุณาระบุเหตุผลการปฏิเสธ');
            redirect('/photographer/bookings.php');
        }

        $rejectionReason = null;
        if ($newStatus === 'rejected') {
            $rejectionReason = $reason;
        }

        $stmt = db()->prepare('UPDATE bookings
                               SET status = ?, rejection_reason = ?, completed_at = IF(? = "completed", NOW(), completed_at), updated_at = NOW()
                               WHERE id = ?');
        $stmt->execute([$newStatus, $rejectionReason, $newStatus, $id]);

        add_booking_status_log($id, $booking['status'], $newStatus, (int)current_user()['id'], $reason);
        update_photographer_response_stats($pid);
        notify_user((int)$booking['customer_id'], 'สถานะคำขอจองเปลี่ยนแปลง', $booking['booking_code'] . ' เป็น ' . booking_status_label($newStatus), 'booking', $id);
        log_activity('change_booking_status', 'bookings', $id);
        flash('success', 'เปลี่ยนสถานะแล้ว');
    }

    redirect('/photographer/bookings.php');
}

$status = (string)($_GET['status'] ?? '');
$where = 'b.photographer_id = ? AND b.deleted_at IS NULL';
$params = [$pid];

if ($status !== '') {
    $where .= ' AND b.status = ?';
    $params[] = $status;
}

$sql = "SELECT b.*, u.name AS customer_name, sc.name AS category_name, d.district_name
        FROM bookings b
        JOIN users u ON u.id = b.customer_id
        JOIN service_categories sc ON sc.id = b.category_id
        JOIN districts d ON d.id = b.district_id
        WHERE {$where}
        ORDER BY b.created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$pageTitle = 'คำขอจอง';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Photographer Studio</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950">คำขอจอง</h1>
        </div>

        <form>
            <select name="status" class="stock-input rounded-2xl px-4 py-3 font-semibold" onchange="this.form.submit()">
                <option value="">ทุกสถานะ</option>
                <?php foreach ($allowedStatuses as $statusOption): ?>
                    <option value="<?= h($statusOption) ?>" <?php if ($status === $statusOption): ?>selected<?php endif; ?>>
                        <?= h(booking_status_label($statusOption)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="w-full text-left text-sm">
            <thead class="text-neutral-500">
                <tr>
                    <th class="py-3">Code</th>
                    <th>ลูกค้า</th>
                    <th>ประเภท</th>
                    <th>วันที่</th>
                    <th>สถานะ</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                    <tr class="border-t align-top">
                        <td class="py-3 font-black">
                            <a class="text-red-600" href="/photographer/booking_detail.php?id=<?= (int)$booking['id'] ?>">
                                <?= h($booking['booking_code']) ?>
                            </a>
                        </td>
                        <td><?= h($booking['customer_name']) ?></td>
                        <td><?= h($booking['category_name']) ?></td>
                        <td>
                            <?= h($booking['booking_date']) ?> <?= h(time_slot_label($booking['time_slot'])) ?><br>
                            <?= h($booking['district_name']) ?>
                        </td>
                        <td><?= status_badge($booking['status']) ?></td>
                        <td>
                            <?php if (in_array($booking['status'], ['pending', 'accepted', 'confirmed'], true)): ?>
                                <form method="post" class="grid gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$booking['id'] ?>">
                                    <select name="status" class="stock-input rounded-xl px-3 py-2">
                                        <option value="accepted">ตอบรับ</option>
                                        <option value="confirmed">ยืนยันงาน</option>
                                        <option value="completed">เสร็จสิ้น</option>
                                        <option value="rejected">ปฏิเสธ</option>
                                    </select>
                                    <input name="rejection_reason" placeholder="เหตุผลถ้าปฏิเสธ" class="stock-input rounded-xl px-3 py-2">
                                    <button data-confirm="ยืนยันเปลี่ยนสถานะคำขอจอง?" class="rounded-xl bg-neutral-950 px-3 py-2 font-black text-white hover:bg-red-600">
                                        <i class="fa-solid fa-floppy-disk mr-1"></i>บันทึก
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

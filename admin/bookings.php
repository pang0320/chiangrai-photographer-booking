<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$allowedStatuses = ['pending', 'accepted', 'rejected', 'cancelled', 'confirmed', 'completed'];
$cleanContext = clean_context_init(['status', 'photographer_id', 'customer_id', 'date']);

if (is_post()) {
    verify_csrf();

    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $newStatus = (string)($_POST['status'] ?? '');

    if ($action !== 'change_status') {
        flash('error', 'ไม่พบคำสั่งที่ต้องการทำรายการ');
        redirect('/admin/bookings.php');
    }

    $stmt = db()->prepare('SELECT * FROM bookings WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        flash('error', 'ไม่พบรายการจองที่ต้องการแก้ไข');
        redirect('/admin/bookings.php');
    }

    if (!in_array($newStatus, $allowedStatuses, true)) {
        flash('error', 'สถานะที่เลือกไม่ถูกต้อง');
        redirect('/admin/bookings.php');
    }

    $oldStatus = (string)$booking['status'];

    if ($oldStatus === $newStatus) {
        flash('info', 'สถานะนี้ถูกใช้อยู่แล้ว');
        redirect('/admin/bookings.php');
    }

    try {
        db()->beginTransaction();

        $stmt = db()->prepare('UPDATE bookings
                               SET status = ?,
                                   completed_at = CASE WHEN ? = "completed" THEN COALESCE(completed_at, NOW()) ELSE NULL END,
                                   updated_at = NOW()
                               WHERE id = ?');
        $stmt->execute([$newStatus, $newStatus, $id]);

        add_booking_status_log($id, $oldStatus, $newStatus, (int)current_user()['id'], 'ผู้ดูแลระบบเปลี่ยนสถานะ');
        notify_user((int)$booking['customer_id'], 'Admin เปลี่ยนสถานะคำขอจอง', $booking['booking_code'] . ' เป็น ' . booking_status_label($newStatus), 'booking', $id);

        $stmt = db()->prepare('SELECT user_id FROM photographer_profiles WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$booking['photographer_id']]);
        $photographerUserId = (int)$stmt->fetchColumn();

        if ($photographerUserId > 0) {
            notify_user($photographerUserId, 'Admin เปลี่ยนสถานะคำขอจอง', $booking['booking_code'] . ' เป็น ' . booking_status_label($newStatus), 'booking', $id);
        }

        log_activity('admin_change_booking', 'bookings', $id);
        db()->commit();
        flash('success', 'อัปเดตคำขอจองแล้ว');
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        log_activity('admin_change_booking_failed', 'bookings', $id, $e->getMessage());
        flash('error', 'อัปเดตสถานะไม่สำเร็จ กรุณาลองใหม่');
    }

    redirect('/admin/bookings.php');
}

$where = ['b.deleted_at IS NULL'];
$params = [];

$selectedStatus = (string)clean_context_value($cleanContext, 'status', '');
$selectedPhotographerId = (string)clean_context_value($cleanContext, 'photographer_id', '');
$selectedCustomerId = (string)clean_context_value($cleanContext, 'customer_id', '');
$selectedDate = (string)clean_context_value($cleanContext, 'date', '');

if ($selectedStatus !== '') {
    $where[] = 'b.status = ?';
    $params[] = $selectedStatus;
}

if ($selectedPhotographerId !== '') {
    $where[] = 'b.photographer_id = ?';
    $params[] = $selectedPhotographerId;
}

if ($selectedCustomerId !== '') {
    $where[] = 'b.customer_id = ?';
    $params[] = $selectedCustomerId;
}

if ($selectedDate !== '') {
    $filterDate = parse_be_date_to_iso($selectedDate);
    if ($filterDate !== '') {
        $where[] = 'b.booking_date = ?';
        $params[] = $filterDate;
    }
}

$sql = 'SELECT b.*, u.name AS customer_name, p.display_name, sc.name AS category_name
        FROM bookings b
        JOIN users u ON u.id = b.customer_id
        JOIN photographer_profiles p ON p.id = b.photographer_id
        JOIN service_categories sc ON sc.id = b.category_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY b.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$pageTitle = 'จัดการคำขอจอง';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">ผู้ดูแลระบบ</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">จัดการคำขอจอง</h1>
    </div>

    <form method="post" action="/admin/bookings.php" class="stock-card mt-6 grid gap-3 rounded-[1.5rem] p-5 md:grid-cols-5">
        <?= clean_context_inputs([]) ?>
        <select name="status" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <option value="">ทุกสถานะ</option>
            <?php foreach ($allowedStatuses as $status): ?>
                <option value="<?= h($status) ?>" <?php if ($selectedStatus === $status): ?>selected<?php endif; ?>>
                    <?= h(booking_status_label($status)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input name="photographer_id" value="<?= h($selectedPhotographerId) ?>" placeholder="รหัสช่างภาพ" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input name="customer_id" value="<?= h($selectedCustomerId) ?>" placeholder="รหัสลูกค้า" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <?= be_date_input('date', $selectedDate, 'stock-input rounded-2xl px-4 py-3 font-semibold', false, 'วันที่จอง พ.ศ.') ?>
        <button class="stock-button rounded-2xl px-5 py-3 font-black"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
    </form>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>รหัสจอง</th>
                    <th>ลูกค้า</th>
                    <th>ช่างภาพ</th>
                    <th>ประเภท</th>
                    <th>วันที่</th>
                    <th>สถานะ</th>
                    <th>เปลี่ยนสถานะ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $booking): ?>
                    <tr>
                        <td class="font-black"><?= h($booking['booking_code']) ?></td>
                        <td><?= h($booking['customer_name']) ?></td>
                        <td><?= h($booking['display_name']) ?></td>
                        <td><?= h($booking['category_name']) ?></td>
                        <td><?= h(format_be_date($booking['booking_date'])) ?> <?= h(time_slot_label($booking['time_slot'])) ?></td>
                        <td><?= status_badge($booking['status']) ?></td>
                        <td>
                            <form method="post" class="flex flex-wrap gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="change_status">
                                <input type="hidden" name="id" value="<?= (int)$booking['id'] ?>">

                                <select name="status" class="rounded-xl border border-neutral-200 px-3 py-2">
                                    <?php foreach ($allowedStatuses as $status): ?>
                                        <option value="<?= h($status) ?>" <?php if ($booking['status'] === $status): ?>selected<?php endif; ?>>
                                            <?= h(booking_status_label($status)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button
                                    type="submit"
                                    data-confirm="ยืนยันเปลี่ยนสถานะคำขอจอง?"
                                    data-confirm-text="รหัส <?= h($booking['booking_code']) ?> จะถูกเปลี่ยนเป็นสถานะที่เลือก"
                                    data-confirm-button="ยืนยันเปลี่ยนสถานะ"
                                    class="rounded-xl bg-neutral-950 px-3 py-2 font-black text-white hover:bg-red-600">
                                    <i class="fa-solid fa-floppy-disk mr-1"></i>บันทึก
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

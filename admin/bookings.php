<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$allowedStatuses = ['pending', 'accepted', 'rejected', 'cancelled', 'confirmed', 'completed'];

if (is_post()) {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $newStatus = (string)($_POST['status'] ?? '');

    $stmt = db()->prepare('SELECT * FROM bookings WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $booking = $stmt->fetch();

    if ($booking && in_array($newStatus, $allowedStatuses, true)) {
        $stmt = db()->prepare('UPDATE bookings SET status = ?, completed_at = IF(? = "completed", NOW(), completed_at), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$newStatus, $newStatus, $id]);

        add_booking_status_log($id, $booking['status'], $newStatus, (int)current_user()['id'], 'ผู้ดูแลระบบเปลี่ยนสถานะ');
        notify_user((int)$booking['customer_id'], 'Admin เปลี่ยนสถานะคำขอจอง', $booking['booking_code'] . ' เป็น ' . booking_status_label($newStatus), 'booking', $id);

        $stmt = db()->prepare('SELECT user_id FROM photographer_profiles WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$booking['photographer_id']]);
        $photographerUserId = (int)$stmt->fetchColumn();

        if ($photographerUserId > 0) {
            notify_user($photographerUserId, 'Admin เปลี่ยนสถานะคำขอจอง', $booking['booking_code'] . ' เป็น ' . booking_status_label($newStatus), 'booking', $id);
        }

        log_activity('admin_change_booking', 'bookings', $id);
        flash('success', 'อัปเดตคำขอจองแล้ว');
    }

    redirect('/admin/bookings.php');
}

$where = ['b.deleted_at IS NULL'];
$params = [];

if (!empty($_GET['status'])) {
    $where[] = 'b.status = ?';
    $params[] = (string)$_GET['status'];
}

if (!empty($_GET['photographer_id'])) {
    $where[] = 'b.photographer_id = ?';
    $params[] = (string)$_GET['photographer_id'];
}

if (!empty($_GET['customer_id'])) {
    $where[] = 'b.customer_id = ?';
    $params[] = (string)$_GET['customer_id'];
}

if (!empty($_GET['date'])) {
    $filterDate = parse_be_date_to_iso((string)$_GET['date']);
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

    <form class="stock-card mt-6 grid gap-3 rounded-[1.5rem] p-5 md:grid-cols-5">
        <select name="status" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <option value="">ทุกสถานะ</option>
            <?php foreach ($allowedStatuses as $status): ?>
                <option value="<?= h($status) ?>" <?php if (($_GET['status'] ?? '') === $status): ?>selected<?php endif; ?>>
                    <?= h(booking_status_label($status)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input name="photographer_id" value="<?= h($_GET['photographer_id'] ?? '') ?>" placeholder="รหัสช่างภาพ" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input name="customer_id" value="<?= h($_GET['customer_id'] ?? '') ?>" placeholder="รหัสลูกค้า" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <?= be_date_input('date', (string)($_GET['date'] ?? ''), 'stock-input rounded-2xl px-4 py-3 font-semibold', false, 'วันที่จอง พ.ศ.') ?>
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
                                <input type="hidden" name="id" value="<?= (int)$booking['id'] ?>">

                                <select name="status" class="rounded-xl border border-neutral-200 px-3 py-2">
                                    <?php foreach ($allowedStatuses as $status): ?>
                                        <option value="<?= h($status) ?>" <?php if ($booking['status'] === $status): ?>selected<?php endif; ?>>
                                            <?= h(booking_status_label($status)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button data-confirm="ยืนยันเปลี่ยนสถานะ booking?" class="rounded-xl bg-neutral-950 px-3 py-2 font-black text-white hover:bg-red-600">
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

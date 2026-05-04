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

        add_booking_status_log($id, $booking['status'], $newStatus, (int)current_user()['id'], 'Admin change');
        log_activity('admin_change_booking', 'bookings', $id);
        flash('success', 'อัปเดต booking แล้ว');
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
    $where[] = 'b.booking_date = ?';
    $params[] = (string)$_GET['date'];
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

$pageTitle = 'จัดการ Booking';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Admin</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">จัดการ Booking</h1>
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

        <input name="photographer_id" value="<?= h($_GET['photographer_id'] ?? '') ?>" placeholder="photographer_id" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input name="customer_id" value="<?= h($_GET['customer_id'] ?? '') ?>" placeholder="customer_id" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input type="date" name="date" value="<?= h($_GET['date'] ?? '') ?>" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <button class="stock-button rounded-2xl px-5 py-3 font-black">ค้นหา</button>
    </form>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>ลูกค้า</th>
                    <th>ช่างภาพ</th>
                    <th>ประเภท</th>
                    <th>วันที่</th>
                    <th>Status</th>
                    <th>Change</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $booking): ?>
                    <tr>
                        <td class="font-black"><?= h($booking['booking_code']) ?></td>
                        <td><?= h($booking['customer_name']) ?></td>
                        <td><?= h($booking['display_name']) ?></td>
                        <td><?= h($booking['category_name']) ?></td>
                        <td><?= h($booking['booking_date']) ?> <?= h(time_slot_label($booking['time_slot'])) ?></td>
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

                                <button class="rounded-xl bg-neutral-950 px-3 py-2 font-black text-white hover:bg-red-600">
                                    save
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

<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');

$profile = photographer_profile_by_user((int)current_user()['id']);
$pid = (int)$profile['id'];
ensure_booking_range_columns();
$allowedStatuses = ['pending', 'accepted', 'in_progress', 'completed', 'rejected', 'cancelled'];
$cleanContext = clean_context_init(['status', 'tab']);

if (is_post()) {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $newStatus = (string)($_POST['status'] ?? '');
    $reason = trim((string)($_POST['rejection_reason'] ?? ''));

    $stmt = db()->prepare('SELECT * FROM bookings WHERE id = ? AND photographer_id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$id, $pid]);
    $booking = $stmt->fetch();

    if ($booking && in_array($newStatus, $allowedStatuses, true)) {
        $currentStatus = (string)$booking['status'];
        $allowedNextStatuses = [];

        if ($currentStatus === 'pending') {
            $allowedNextStatuses = ['accepted', 'rejected'];
        } elseif ($currentStatus === 'accepted') {
            $allowedNextStatuses = ['in_progress'];
        } elseif ($currentStatus === 'in_progress') {
            $allowedNextStatuses = ['completed'];
        }

        if (!in_array($newStatus, $allowedNextStatuses, true)) {
            flash('error', 'สถานะปัจจุบันไม่สามารถเปลี่ยนเป็นสถานะที่เลือกได้');
            redirect('/photographer/bookings.php');
        }

        if ($newStatus === 'rejected' && $reason === '') {
            flash('error', 'กรุณาระบุเหตุผล');
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
        sync_availability_after_booking_status($id);
        update_photographer_response_stats($pid);
        notify_user((int)$booking['customer_id'], 'สถานะคำขอจองเปลี่ยนแปลง', $booking['booking_code'] . ' เป็น ' . booking_status_label($newStatus), 'booking', $id);
        log_activity('change_booking_status', 'bookings', $id);
        flash('success', 'เปลี่ยนสถานะแล้ว');
    }

    redirect('/photographer/bookings.php');
}

$status = (string)clean_context_value($cleanContext, 'status', '');
$tab = (string)clean_context_value($cleanContext, 'tab', 'active');
$allowedTabs = ['all', 'active', 'pending', 'accepted', 'completed', 'closed'];

if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'active';
}

$where = 'b.photographer_id = ? AND b.deleted_at IS NULL';
$params = [$pid];

if ($status !== '') {
    $where .= ' AND b.status = ?';
    $params[] = $status;
} elseif ($tab === 'active') {
    $where .= ' AND b.status IN ("pending", "accepted", "in_progress")';
} elseif ($tab === 'pending') {
    $where .= ' AND b.status = "pending"';
} elseif ($tab === 'accepted') {
    $where .= ' AND b.status IN ("accepted", "in_progress")';
} elseif ($tab === 'completed') {
    $where .= ' AND b.status = "completed"';
} elseif ($tab === 'closed') {
    $where .= ' AND b.status IN ("rejected", "cancelled")';
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

$bookingCounts = [
    'all' => 0,
    'active' => 0,
    'pending' => 0,
    'accepted' => 0,
    'completed' => 0,
    'closed' => 0,
];

$countStmt = db()->prepare('SELECT
    COUNT(*) AS total_all,
    SUM(CASE WHEN status IN ("pending", "accepted", "in_progress") THEN 1 ELSE 0 END) AS total_active,
    SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS total_pending,
    SUM(CASE WHEN status IN ("accepted", "in_progress") THEN 1 ELSE 0 END) AS total_accepted,
    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS total_completed,
    SUM(CASE WHEN status IN ("rejected", "cancelled") THEN 1 ELSE 0 END) AS total_closed
    FROM bookings
    WHERE photographer_id = ? AND deleted_at IS NULL');
$countStmt->execute([$pid]);
$countRow = $countStmt->fetch();

if ($countRow) {
    $bookingCounts['all'] = (int)$countRow['total_all'];
    $bookingCounts['active'] = (int)$countRow['total_active'];
    $bookingCounts['pending'] = (int)$countRow['total_pending'];
    $bookingCounts['accepted'] = (int)$countRow['total_accepted'];
    $bookingCounts['completed'] = (int)$countRow['total_completed'];
    $bookingCounts['closed'] = (int)$countRow['total_closed'];
}

$pageTitle = 'คำขอจอง';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
	        <div>
	            <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">สตูดิโอช่างภาพ</p>
	            <h1 class="mt-1 text-3xl font-black text-neutral-950">คำขอจอง</h1>
	            <p class="mt-2 text-sm font-bold text-neutral-500">ดูงานที่ต้องดำเนินการต่อและงานที่ปิดสำเร็จแล้วแยกกัน</p>
	        </div>

        <form method="post" action="/photographer/bookings.php">
            <?= clean_context_inputs([]) ?>
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

	    <?php
	    $tabs = [
	        'active' => ['กำลังดำเนินการ', 'fa-hourglass-half', $bookingCounts['active']],
	        'pending' => ['คำขอใหม่', 'fa-bell', $bookingCounts['pending']],
	        'accepted' => ['รับงาน/กำลังทำ', 'fa-calendar-check', $bookingCounts['accepted']],
	        'completed' => ['เสร็จสิ้นแล้ว', 'fa-circle-check', $bookingCounts['completed']],
	        'closed' => ['ยกเลิก/ปฏิเสธ', 'fa-ban', $bookingCounts['closed']],
	        'all' => ['ประวัติทั้งหมด', 'fa-list', $bookingCounts['all']],
	    ];
	    ?>
	    <div class="mt-6 flex flex-wrap gap-2">
	        <?php foreach ($tabs as $tabKey => $tabItem): ?>
	            <?php
	            $tabClass = 'btn-muted btn-md rounded-full';
	            if ($tab === $tabKey && $status === '') {
	                $tabClass = 'btn-primary btn-md rounded-full';
	            }
	            ?>
	            <?= clean_context_button('/photographer/bookings.php', ['tab' => $tabKey], '<i class="fa-solid ' . h($tabItem[1]) . '"></i>' . h($tabItem[0]) . ' <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">' . number_format((int)$tabItem[2]) . '</span>', $tabClass) ?>
	        <?php endforeach; ?>
	    </div>

	    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
	        <?php if ($bookings): ?>
	            <table class="w-full text-left text-sm">
	                <thead class="text-neutral-500">
	                    <tr>
	                        <th class="py-3">รหัสจอง</th>
	                        <th>ลูกค้า</th>
	                        <th>ประเภท</th>
	                        <th>วันที่</th>
	                        <th>สถานะ</th>
	                        <th>จัดการ</th>
	                    </tr>
	                </thead>
	                <tbody data-block-paginate="5">
	                    <?php foreach ($bookings as $booking): ?>
	                        <tr class="border-t align-top">
	                            <td class="py-3 font-black">
	                                <?= clean_context_button('/photographer/booking_detail.php', ['id' => (int)$booking['id']], h($booking['booking_code']), 'text-red-600') ?>
	                            </td>
	                            <td><?= h($booking['customer_name']) ?></td>
	                            <td><?= h($booking['category_name']) ?></td>
	                            <td>
	                                <?= h(booking_range_label($booking)) ?><br>
	                                <?= h($booking['district_name']) ?>
	                            </td>
	                            <td><?= status_badge($booking['status']) ?></td>
	                            <td>
	                                <?php if (in_array($booking['status'], ['pending', 'accepted', 'in_progress'], true)): ?>
	                                    <form method="post" class="grid gap-2">
	                                        <?= csrf_field() ?>
	                                        <input type="hidden" name="id" value="<?= (int)$booking['id'] ?>">
	                                        <select name="status" class="stock-input rounded-xl px-3 py-2">
                                                <?php if ((string)$booking['status'] === 'pending'): ?>
	                                                <option value="accepted">รับงาน</option>
	                                                <option value="rejected">ปฏิเสธงาน</option>
                                                <?php elseif ((string)$booking['status'] === 'accepted'): ?>
	                                                <option value="in_progress">เริ่มดำเนินงาน</option>
                                                <?php elseif ((string)$booking['status'] === 'in_progress'): ?>
	                                                <option value="completed">งานเสร็จสิ้น</option>
                                                <?php endif; ?>
	                                        </select>
	                                        <input name="rejection_reason" placeholder="เหตุผลถ้าปฏิเสธงาน" class="stock-input rounded-xl px-3 py-2">
	                                        <button data-confirm="ยืนยันเปลี่ยนสถานะคำขอจอง?" class="btn-cta btn-md rounded-xl">
	                                            <i class="fa-solid fa-floppy-disk mr-1"></i>บันทึก
	                                        </button>
	                                    </form>
	                                <?php else: ?>
	                                    <?= clean_context_button('/photographer/booking_detail.php', ['id' => (int)$booking['id']], '<i class="fa-solid fa-eye mr-1"></i>รายละเอียด', 'btn-primary btn-sm') ?>
	                                <?php endif; ?>
	                            </td>
	                        </tr>
	                    <?php endforeach; ?>
	                </tbody>
	            </table>
	        <?php else: ?>
	            <div class="empty-state rounded-[2rem] p-10 text-center">
	                <i class="fa-solid fa-calendar-check text-4xl text-red-600"></i>
	                <h2 class="mt-3 text-xl font-black text-neutral-950">ยังไม่มีรายการในแท็บนี้</h2>
	                <p class="mt-2 text-neutral-600">เมื่อมีคำขอจองหรืองานที่เสร็จสิ้นแล้ว ระบบจะแสดงที่นี่</p>
	            </div>
	        <?php endif; ?>
	    </div>
	</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

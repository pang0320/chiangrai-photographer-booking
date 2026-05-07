<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('customer');

$user = current_user();
$cleanContext = clean_context_init(['tab']);
$tab = (string)clean_context_value($cleanContext, 'tab', 'active');
$allowedTabs = ['all', 'active', 'completed'];

if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'active';
}

$where = ['b.customer_id = ?', 'b.deleted_at IS NULL'];
$params = [(int)$user['id']];

if ($tab === 'active') {
    $where[] = 'b.status IN ("pending", "accepted", "confirmed")';
} elseif ($tab === 'completed') {
    $where[] = 'b.status = "completed"';
}

$stmt = db()->prepare('SELECT b.*, p.display_name, sc.name AS category_name, d.district_name, r.id AS review_id
                       FROM bookings b
                       JOIN photographer_profiles p ON p.id = b.photographer_id
                       JOIN service_categories sc ON sc.id = b.category_id
                       JOIN districts d ON d.id = b.district_id
                       LEFT JOIN reviews r ON r.booking_id = b.id AND r.deleted_at IS NULL
                       WHERE ' . implode(' AND ', $where) . '
                       ORDER BY b.created_at DESC');
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$bookingCounts = [
    'all' => 0,
    'active' => 0,
    'completed' => 0,
];

$countStmt = db()->prepare('SELECT
    COUNT(*) AS total_all,
    SUM(CASE WHEN status IN ("pending", "accepted", "confirmed") THEN 1 ELSE 0 END) AS total_active,
    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS total_completed
    FROM bookings
    WHERE customer_id = ? AND deleted_at IS NULL');
$countStmt->execute([(int)$user['id']]);
$countRow = $countStmt->fetch();

if ($countRow) {
    $bookingCounts['all'] = (int)$countRow['total_all'];
    $bookingCounts['active'] = (int)$countRow['total_active'];
    $bookingCounts['completed'] = (int)$countRow['total_completed'];
}

$pageTitle = 'รายการจองของฉัน';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
	    <div>
	        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">พื้นที่ลูกค้า</p>
	        <h1 class="mt-1 text-3xl font-black text-neutral-950">รายการจองของฉัน</h1>
	        <p class="mt-2 text-sm font-bold text-neutral-500">แยกดูงานที่กำลังดำเนินการและงานที่เสร็จสิ้นแล้วได้ชัดเจน</p>
	    </div>

	    <?php
	    $tabs = [
	        'active' => ['กำลังดำเนินการ', 'fa-hourglass-half', $bookingCounts['active']],
	        'completed' => ['เสร็จสิ้นแล้ว', 'fa-circle-check', $bookingCounts['completed']],
	        'all' => ['ทั้งหมด', 'fa-list', $bookingCounts['all']],
	    ];
	    ?>
	    <div class="mt-6 flex flex-wrap gap-2">
	        <?php foreach ($tabs as $tabKey => $tabItem): ?>
	            <?php
	            $tabClass = 'btn-muted btn-md rounded-full';
	            if ($tab === $tabKey) {
	                $tabClass = 'btn-primary btn-md rounded-full';
	            }
	            ?>
	            <?= clean_context_button('/customer/bookings.php', ['tab' => $tabKey], '<i class="fa-solid ' . h($tabItem[1]) . '"></i>' . h($tabItem[0]) . ' <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">' . number_format((int)$tabItem[2]) . '</span>', $tabClass) ?>
	        <?php endforeach; ?>
	    </div>

	    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
	        <?php if ($bookings): ?>
	            <table class="w-full text-left text-sm">
	                <thead class="text-neutral-500">
	                    <tr>
	                        <th class="py-3">รหัสจอง</th>
	                        <th>ช่างภาพ</th>
	                        <th>ประเภท</th>
	                        <th>วันที่</th>
	                        <th>อำเภอ</th>
	                        <th>สถานะ</th>
	                        <th></th>
	                    </tr>
	                </thead>
	                <tbody>
	                    <?php foreach ($bookings as $booking): ?>
	                        <tr class="border-t">
	                            <td class="py-3 font-black"><?= h($booking['booking_code']) ?></td>
	                            <td><?= h($booking['display_name']) ?></td>
	                            <td><?= h($booking['category_name']) ?></td>
	                            <td><?= h(format_be_date($booking['booking_date'])) ?> <?= h(time_slot_label($booking['time_slot'])) ?></td>
	                            <td><?= h($booking['district_name']) ?></td>
	                            <td><?= status_badge($booking['status']) ?></td>
	                            <td class="whitespace-nowrap">
	                                <?= clean_context_button('/customer/booking_detail.php', ['id' => (int)$booking['id']], '<i class="fa-solid fa-eye mr-1"></i>รายละเอียด', 'btn-primary btn-sm') ?>

	                                <?php if ($booking['status'] === 'completed' && !$booking['review_id']): ?>
	                                    <?= clean_context_button('/customer/review.php', ['booking_id' => (int)$booking['id']], '<i class="fa-solid fa-star mr-1"></i>รีวิว', 'btn-success btn-sm ml-2') ?>
	                                <?php endif; ?>
	                            </td>
	                        </tr>
	                    <?php endforeach; ?>
	                </tbody>
	            </table>
	        <?php else: ?>
	            <div class="empty-state rounded-[2rem] p-10 text-center">
	                <i class="fa-solid fa-calendar-xmark text-4xl text-red-600"></i>
	                <h2 class="mt-3 text-xl font-black text-neutral-950">ยังไม่มีรายการในแท็บนี้</h2>
	                <p class="mt-2 text-neutral-600">ลองเปลี่ยนแท็บ หรือค้นหาช่างภาพเพื่อส่งคำขอจองใหม่</p>
	                <a href="/photographers.php" class="btn-cta btn-md mt-5 rounded-full"><i class="fa-solid fa-magnifying-glass"></i>ค้นหาช่างภาพ</a>
	            </div>
	        <?php endif; ?>
	    </div>
	</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

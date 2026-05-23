<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$allowedStatuses = ['pending', 'accepted', 'rejected', 'cancelled', 'confirmed', 'completed'];
$cleanContext = clean_context_init(['status', 'photographer_id', 'customer_id', 'date', 'tab']);

if (is_post()) {
    verify_csrf();
    flash('error', 'หน้าคำขอจองของผู้ดูแลระบบเป็นโหมดดูข้อมูลเท่านั้น ไม่สามารถเปลี่ยนสถานะจากหน้านี้ได้');
    redirect('/admin/bookings.php');
}

$where = ['b.deleted_at IS NULL'];
$params = [];

$selectedStatus = (string)clean_context_value($cleanContext, 'status', '');
$selectedPhotographerId = (string)clean_context_value($cleanContext, 'photographer_id', '');
$selectedCustomerId = (string)clean_context_value($cleanContext, 'customer_id', '');
$selectedDate = (string)clean_context_value($cleanContext, 'date', '');
$tab = (string)clean_context_value($cleanContext, 'tab', 'active');
$allowedTabs = ['all', 'active', 'completed'];

if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'active';
}

if ($selectedStatus !== '') {
    $where[] = 'b.status = ?';
    $params[] = $selectedStatus;
} elseif ($tab === 'active') {
    $where[] = 'b.status IN ("pending", "accepted", "confirmed")';
} elseif ($tab === 'completed') {
    $where[] = 'b.status = "completed"';
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

$sql = 'SELECT b.*,
               u.name AS customer_name,
               u.email AS customer_email,
               u.phone AS customer_phone,
               p.display_name,
               p.phone_public,
               p.line_id,
               p.facebook_url,
               p.instagram_url,
               sc.name AS category_name,
               d.district_name
        FROM bookings b
        JOIN users u ON u.id = b.customer_id
        JOIN photographer_profiles p ON p.id = b.photographer_id
        JOIN service_categories sc ON sc.id = b.category_id
        LEFT JOIN districts d ON d.id = b.district_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY b.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$bookingCounts = [
    'all' => (int)db_fetch_value('SELECT COUNT(*) FROM bookings WHERE deleted_at IS NULL'),
    'active' => (int)db_fetch_value('SELECT COUNT(*) FROM bookings WHERE deleted_at IS NULL AND status IN ("pending", "accepted", "confirmed")'),
    'completed' => (int)db_fetch_value('SELECT COUNT(*) FROM bookings WHERE deleted_at IS NULL AND status = "completed"'),
];

$pageTitle = 'ตรวจสอบคำขอจอง';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
	    <div>
	        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">ผู้ดูแลระบบ</p>
	        <h1 class="mt-1 text-3xl font-black text-neutral-950">ตรวจสอบคำขอจอง</h1>
	        <p class="mt-2 text-sm font-bold text-neutral-500">แยกดูงานที่กำลังดำเนินการและงานที่เสร็จสิ้นแล้ว พร้อมตัวกรองละเอียดของแอดมิน</p>
            <p class="mt-1 text-sm font-bold text-amber-700"><i class="fa-solid fa-circle-info mr-1"></i>หน้านี้เป็นโหมดดูข้อมูลเท่านั้น สถานะคำขอจองให้ลูกค้าและช่างภาพดำเนินการตามขั้นตอนของระบบ</p>
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
	            if ($tab === $tabKey && $selectedStatus === '') {
	                $tabClass = 'btn-primary btn-md rounded-full';
	            }
	            ?>
	            <?= clean_context_button('/admin/bookings.php', ['tab' => $tabKey], '<i class="fa-solid ' . h($tabItem[1]) . '"></i>' . h($tabItem[0]) . ' <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">' . number_format((int)$tabItem[2]) . '</span>', $tabClass) ?>
	        <?php endforeach; ?>
	    </div>

	    <form method="post" action="/admin/bookings.php" class="stock-card mt-6 grid gap-3 rounded-[1.5rem] p-5 md:grid-cols-5">
	        <?= clean_context_inputs(['tab' => $tab]) ?>
        <label class="grid gap-2 text-xs font-black text-neutral-500">
            <span><i class="fa-solid fa-filter mr-1 text-red-600"></i>กรองสถานะ (ดูอย่างเดียว)</span>
            <select name="status" class="stock-input rounded-2xl px-4 py-3 font-semibold">
                <option value="">ทุกสถานะ</option>
                <?php foreach ($allowedStatuses as $status): ?>
                    <option value="<?= h($status) ?>" <?php if ($selectedStatus === $status): ?>selected<?php endif; ?>>
                        <?= h(booking_status_label($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="grid gap-2 text-xs font-black text-neutral-500">
            <span><i class="fa-solid fa-camera mr-1 text-red-600"></i>กรองรหัสช่างภาพ</span>
            <input name="photographer_id" value="<?= h($selectedPhotographerId) ?>" placeholder="รหัสอ้างอิงช่างภาพ" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        </label>
        <label class="grid gap-2 text-xs font-black text-neutral-500">
            <span><i class="fa-solid fa-user mr-1 text-red-600"></i>กรองรหัสลูกค้า</span>
            <input name="customer_id" value="<?= h($selectedCustomerId) ?>" placeholder="รหัสอ้างอิงลูกค้า" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        </label>
        <label class="grid gap-2 text-xs font-black text-neutral-500">
            <span><i class="fa-solid fa-calendar-day mr-1 text-red-600"></i>กรองวันที่จอง</span>
            <?= be_date_input('date', $selectedDate, 'stock-input rounded-2xl px-4 py-3 font-semibold', false, 'วันที่จอง พ.ศ.') ?>
        </label>
        <label class="grid gap-2 text-xs font-black text-transparent">
            <span>ค้นหา</span>
            <button class="stock-button rounded-2xl px-5 py-3 font-black"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
        </label>
    </form>

	    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
	        <?php if ($items): ?>
	            <table class="datatable w-full text-sm">
	                <thead>
	                    <tr>
	                        <th>รหัสจอง</th>
	                        <th>ลูกค้า</th>
	                        <th>ช่างภาพ</th>
	                        <th>ประเภท</th>
	                        <th>วันที่</th>
	                        <th>สถานะ (ดูอย่างเดียว)</th>
	                        <th>รายละเอียด</th>
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
	                                <button type="button" data-booking-modal-open="admin-booking-modal-<?= (int)$booking['id'] ?>" class="inline-flex min-w-[116px] items-center justify-center rounded-full bg-neutral-950 px-4 py-2 text-sm font-black text-white transition hover:bg-red-600">
	                                    <i class="fa-solid fa-eye mr-2"></i>รายละเอียด
	                                </button>
	                            </td>
	                        </tr>
	                    <?php endforeach; ?>
	                </tbody>
	            </table>
	        <?php else: ?>
	            <div class="empty-state rounded-[2rem] p-10 text-center">
	                <i class="fa-solid fa-calendar-xmark text-4xl text-red-600"></i>
	                <h2 class="mt-3 text-xl font-black text-neutral-950">ไม่พบรายการจองในเงื่อนไขนี้</h2>
	                <p class="mt-2 text-neutral-600">ลองเปลี่ยนแท็บ หรือปรับตัวกรองสถานะ/วันที่ใหม่</p>
	            </div>
	        <?php endif; ?>
	    </div>
	</section>

<?php foreach ($items as $booking): ?>
    <?php
    $bookingLogs = db_fetch_all('SELECT l.*, u.name AS changed_by_name
                                 FROM booking_status_logs l
                                 LEFT JOIN users u ON u.id = l.changed_by
                                 WHERE l.booking_id = ?
                                 ORDER BY l.created_at ASC', [(int)$booking['id']]);
    $contactChannel = '-';
    if (!empty($booking['contact_channel'])) {
        $contactChannel = $booking['contact_channel'];
    }
    $bookingNote = '-';
    if (!empty($booking['note'])) {
        $bookingNote = $booking['note'];
    }
    $rejectionReason = '-';
    if (!empty($booking['rejection_reason'])) {
        $rejectionReason = $booking['rejection_reason'];
    }
    $completedAt = '-';
    if (!empty($booking['completed_at'])) {
        $completedAt = format_be_datetime($booking['completed_at']);
    }
    $customerEmail = '-';
    if (!empty($booking['customer_email'])) {
        $customerEmail = $booking['customer_email'];
    }
    $customerPhone = '-';
    if (!empty($booking['customer_phone'])) {
        $customerPhone = $booking['customer_phone'];
    }
    $districtName = '-';
    if (!empty($booking['district_name'])) {
        $districtName = $booking['district_name'];
    }
    $phonePublic = '-';
    if (!empty($booking['phone_public'])) {
        $phonePublic = $booking['phone_public'];
    }
    $lineId = '-';
    if (!empty($booking['line_id'])) {
        $lineId = $booking['line_id'];
    }
    $facebookUrl = '-';
    if (!empty($booking['facebook_url'])) {
        $facebookUrl = $booking['facebook_url'];
    }
    $instagramUrl = '-';
    if (!empty($booking['instagram_url'])) {
        $instagramUrl = $booking['instagram_url'];
    }
    ?>
    <div id="admin-booking-modal-<?= (int)$booking['id'] ?>" class="admin-booking-modal fixed inset-0 z-[95] hidden items-center justify-center bg-neutral-950/75 px-4 py-8 backdrop-blur-sm" aria-hidden="true">
        <div data-booking-modal-close class="absolute inset-0"></div>
        <div class="relative max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-[2rem] bg-white shadow-2xl">
            <div class="sticky top-0 z-10 bg-gradient-to-r from-neutral-950 via-slate-900 to-red-700 p-6 text-white">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.22em] text-red-200"><i class="fa-solid fa-calendar-check mr-2"></i>รายละเอียดคำขอจอง</p>
                        <h2 class="mt-2 text-3xl font-black"><?= h($booking['booking_code']) ?></h2>
                        <p class="mt-2 text-sm font-bold text-white/75">
                            <?= h($booking['customer_name']) ?> จอง <?= h($booking['display_name']) ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <?= status_badge($booking['status']) ?>
                        <button type="button" data-booking-modal-close class="grid h-11 w-11 place-items-center rounded-full bg-white/12 text-white transition hover:bg-white hover:text-neutral-950" aria-label="ปิดรายละเอียดคำขอจอง">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 p-6 lg:grid-cols-[1.2fr_.8fr]">
                <div class="grid gap-5">
                    <div class="rounded-[1.5rem] border border-neutral-200 bg-neutral-50 p-5">
                        <h3 class="text-lg font-black text-neutral-950"><i class="fa-solid fa-camera mr-2 text-red-600"></i>ข้อมูลงาน</h3>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl bg-white p-4">
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">ประเภทงาน</p>
                                <p class="mt-1 font-black text-neutral-950"><?= h($booking['category_name']) ?></p>
                            </div>
                            <div class="rounded-2xl bg-white p-4">
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">อำเภอ</p>
                                <p class="mt-1 font-black text-neutral-950"><?= h($districtName) ?></p>
                            </div>
                            <div class="rounded-2xl bg-white p-4">
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">วันที่ถ่าย</p>
                                <p class="mt-1 font-black text-neutral-950"><?= h(format_be_date($booking['booking_date'])) ?></p>
                            </div>
                            <div class="rounded-2xl bg-white p-4">
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">ช่วงเวลา</p>
                                <p class="mt-1 font-black text-neutral-950"><?= h(time_slot_label($booking['time_slot'])) ?></p>
                            </div>
                        </div>
                        <div class="mt-4 rounded-2xl bg-white p-4">
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">รายละเอียดงาน</p>
                            <p class="mt-2 whitespace-pre-line leading-7 text-neutral-700"><?= h($booking['job_detail']) ?></p>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl bg-white p-4">
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">หมายเหตุ</p>
                                <p class="mt-1 font-bold text-neutral-700"><?= h($bookingNote) ?></p>
                            </div>
                            <div class="rounded-2xl bg-white p-4">
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">เหตุผลปฏิเสธ</p>
                                <p class="mt-1 font-bold text-neutral-700"><?= h($rejectionReason) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[1.5rem] border border-neutral-200 p-5">
                        <h3 class="text-lg font-black text-neutral-950"><i class="fa-solid fa-clock-rotate-left mr-2 text-red-600"></i>ประวัติสถานะ</h3>
                        <div class="mt-4 grid gap-3">
                            <?php foreach ($bookingLogs as $log): ?>
                                <?php
                                $oldStatusText = '-';
                                if (!empty($log['old_status'])) {
                                    $oldStatusText = booking_status_label((string)$log['old_status']);
                                }
                                $changedBy = 'ระบบ';
                                if (!empty($log['changed_by_name'])) {
                                    $changedBy = $log['changed_by_name'];
                                }
                                ?>
                                <div class="rounded-2xl bg-neutral-50 p-4 text-sm">
                                    <p class="font-black text-neutral-950">
                                        <?= h($oldStatusText) ?> <i class="fa-solid fa-arrow-right mx-2 text-red-600"></i> <?= h(booking_status_label((string)$log['new_status'])) ?>
                                    </p>
                                    <p class="mt-1 font-bold text-neutral-500"><?= h(format_be_datetime($log['created_at'])) ?> · ผู้ดำเนินการ: <?= h($changedBy) ?></p>
                                    <?php if (!empty($log['note'])): ?>
                                        <p class="mt-2 text-neutral-600"><?= h($log['note']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$bookingLogs): ?>
                                <div class="empty-state rounded-2xl p-6 text-center text-sm font-bold text-neutral-600">
                                    <i class="fa-solid fa-clipboard-list text-3xl text-red-600"></i>
                                    <p class="mt-2">ยังไม่มีประวัติสถานะ</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5">
                    <div class="rounded-[1.5rem] border border-neutral-200 p-5">
                        <h3 class="text-lg font-black text-neutral-950"><i class="fa-solid fa-user mr-2 text-red-600"></i>ข้อมูลลูกค้า</h3>
                        <div class="mt-4 grid gap-3 text-sm font-bold text-neutral-700">
                            <p><i class="fa-solid fa-user mr-2 text-red-600"></i><?= h($booking['customer_name']) ?></p>
                            <p><i class="fa-solid fa-envelope mr-2 text-red-600"></i><?= h($customerEmail) ?></p>
                            <p><i class="fa-solid fa-phone mr-2 text-red-600"></i><?= h($customerPhone) ?></p>
                            <p><i class="fa-solid fa-phone-volume mr-2 text-red-600"></i><?= h($booking['contact_phone']) ?></p>
                            <p><i class="fa-solid fa-comment mr-2 text-red-600"></i><?= h($contactChannel) ?></p>
                            <p><i class="fa-solid fa-id-card mr-2 text-red-600"></i><?= h($booking['contact_name']) ?></p>
                        </div>
                    </div>

                    <div class="rounded-[1.5rem] border border-neutral-200 p-5">
                        <h3 class="text-lg font-black text-neutral-950"><i class="fa-solid fa-camera-retro mr-2 text-red-600"></i>ข้อมูลช่างภาพ</h3>
                        <div class="mt-4 grid gap-3 text-sm font-bold text-neutral-700">
                            <p><i class="fa-solid fa-camera mr-2 text-red-600"></i><?= h($booking['display_name']) ?></p>
                            <p><i class="fa-solid fa-phone mr-2 text-red-600"></i><?= h($phonePublic) ?></p>
                            <p><i class="fa-brands fa-line mr-2 text-red-600"></i><?= h($lineId) ?></p>
                            <p><i class="fa-brands fa-facebook mr-2 text-red-600"></i><?= h($facebookUrl) ?></p>
                            <p><i class="fa-brands fa-instagram mr-2 text-red-600"></i><?= h($instagramUrl) ?></p>
                        </div>
                    </div>

                    <div class="rounded-[1.5rem] bg-neutral-950 p-5 text-white">
                        <h3 class="text-lg font-black"><i class="fa-solid fa-lock mr-2 text-red-300"></i>สิทธิ์ของผู้ดูแลระบบ</h3>
                        <div class="mt-4 grid gap-3 text-sm font-bold text-white/75">
                            <p><i class="fa-solid fa-eye mr-2 text-red-300"></i>หน้านี้ใช้ดูและตรวจสอบคำขอจองเท่านั้น</p>
                            <p><i class="fa-solid fa-ban mr-2 text-red-300"></i>ไม่มีปุ่มเปลี่ยนสถานะสำหรับแอดมิน</p>
                            <p><i class="fa-solid fa-calendar-plus mr-2 text-red-300"></i>สร้างเมื่อ <?= h(format_be_datetime($booking['created_at'])) ?></p>
                            <p><i class="fa-solid fa-pen mr-2 text-red-300"></i>อัปเดตล่าสุด <?= h(format_be_datetime($booking['updated_at'])) ?></p>
                            <p><i class="fa-solid fa-circle-check mr-2 text-red-300"></i>เสร็จสิ้นเมื่อ <?= h($completedAt) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function openBookingModal(id) {
        var modal = document.getElementById(id);
        if (!modal) {
            return;
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    }

    function closeBookingModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    }

    document.querySelectorAll('[data-booking-modal-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            openBookingModal(button.getAttribute('data-booking-modal-open'));
        });
    });

    document.querySelectorAll('[data-booking-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeBookingModal(button.closest('.admin-booking-modal'));
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        document.querySelectorAll('.admin-booking-modal.flex').forEach(function (modal) {
            closeBookingModal(modal);
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

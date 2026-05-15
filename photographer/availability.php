<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');

$profile = photographer_profile_by_user((int)current_user()['id']);
$pid = (int)$profile['id'];
$timeSlots = ['morning' => 'เช้า', 'afternoon' => 'บ่าย', 'evening' => 'เย็น', 'full_day' => 'เต็มวัน'];
$statuses = ['available', 'unavailable'];

if (is_post()) {
    verify_csrf();

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM photographer_availability WHERE id = ? AND photographer_id = ?');
        $stmt->execute([$id, $pid]);
        flash('success', 'ลบวันว่างแล้ว');
    } else {
        $availableDate = parse_be_date_to_iso((string)($_POST['available_date'] ?? ''));
        $timeSlot = (string)($_POST['time_slot'] ?? '');
        $status = (string)($_POST['status'] ?? 'available');
        $note = trim((string)($_POST['note'] ?? ''));

        if ($availableDate === '') {
            flash('error', 'รูปแบบวันที่ไม่ถูกต้อง กรุณากรอกแบบ วว/ดด/พ.ศ.');
            redirect('/photographer/availability.php');
        }

        if ($availableDate < date('Y-m-d')) {
            flash('error', 'ไม่สามารถป้อนวันย้อนหลังได้');
            redirect('/photographer/availability.php');
        }

        if (!array_key_exists($timeSlot, $timeSlots)) {
            flash('error', 'ช่วงเวลาไม่ถูกต้อง');
            redirect('/photographer/availability.php');
        }

        if (!in_array($status, $statuses, true)) {
            flash('error', 'สถานะไม่ถูกต้อง');
            redirect('/photographer/availability.php');
        }

        $stmt = db()->prepare('INSERT INTO photographer_availability (photographer_id, available_date, time_slot, status, note, created_at, updated_at)
                               VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                               ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note), updated_at = NOW()');
        $stmt->execute([$pid, $availableDate, $timeSlot, $status, $note]);
        flash('success', 'บันทึกวันว่างแล้ว');
    }

    log_activity('manage_availability', 'photographer_availability', $pid);
    redirect('/photographer/availability.php');
}

$stmt = db()->prepare('SELECT * FROM photographer_availability WHERE photographer_id = ? AND status <> "booked" ORDER BY available_date DESC, time_slot');
$stmt->execute([$pid]);
$items = $stmt->fetchAll();

$calendarRows = db_fetch_all('SELECT pa.available_date, pa.status,
                              (SELECT b.status
                               FROM bookings b
                               WHERE b.photographer_id = pa.photographer_id
                                 AND b.booking_date = pa.available_date
                                 AND b.status IN ("pending","accepted","confirmed")
                                 AND b.deleted_at IS NULL
                               ORDER BY FIELD(b.status, "confirmed", "accepted", "pending")
                               LIMIT 1) AS booking_status
                              FROM photographer_availability pa
                              WHERE pa.photographer_id = ?
                                AND pa.available_date >= CURDATE()', [$pid]);
$GLOBALS['calendar_date_statuses']['available_date'] = [];
$calendarStatusPriority = ['unavailable' => 0, 'available' => 1, 'pending' => 2, 'booked' => 3];
foreach ($calendarRows as $row) {
    $dateKey = (string)$row['available_date'];
    $statusKey = (string)$row['status'];
    if ($row['booking_status'] === 'pending') {
        $statusKey = 'pending';
    } elseif (in_array((string)$row['booking_status'], ['accepted', 'confirmed'], true)) {
        $statusKey = 'booked';
    }
    $currentStatus = $GLOBALS['calendar_date_statuses']['available_date'][$dateKey] ?? 'unavailable';
    if (($calendarStatusPriority[$statusKey] ?? 0) >= ($calendarStatusPriority[$currentStatus] ?? 0)) {
        $GLOBALS['calendar_date_statuses']['available_date'][$dateKey] = $statusKey;
    }
}
$GLOBALS['calendar_date_labels']['available_date'] = 'วันที่รับงาน';

$pageTitle = 'วันว่าง';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">สตูดิโอช่างภาพ</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">จัดการวันว่าง</h1>
        <p class="mt-2 max-w-3xl text-sm font-bold leading-7 text-neutral-500">ถ้าต้องการรับงานให้เลือก “ว่าง” ถ้าต้องการปิดบางวันในปฏิทินให้เลือก “ไม่ว่าง” ส่วน “ถูกจองแล้ว” ระบบจะเปลี่ยนให้อัตโนมัติเมื่อมีคำขอจองที่ตอบรับหรือยืนยันแล้ว</p>
    </div>

    <div class="mt-5 grid gap-3 md:grid-cols-3">
        <div class="rounded-[1.35rem] bg-emerald-50 p-4 text-sm font-bold leading-7 text-emerald-700"><i class="fa-solid fa-circle-check mr-2"></i>ว่าง = ลูกค้าส่งคำขอจองได้</div>
        <div class="rounded-[1.35rem] bg-slate-100 p-4 text-sm font-bold leading-7 text-slate-700"><i class="fa-solid fa-circle-minus mr-2"></i>ไม่ว่าง = ปิดรับงานในวัน/ช่วงเวลานั้น</div>
        <div class="rounded-[1.35rem] bg-indigo-50 p-4 text-sm font-bold leading-7 text-indigo-700"><i class="fa-solid fa-calendar-check mr-2"></i>ถูกจองแล้ว = ระบบอัปเดตจากสถานะการจอง</div>
    </div>

    <form method="post" class="stock-card mt-6 grid gap-4 rounded-[1.5rem] p-5 md:grid-cols-[1.4fr_1fr_1fr_1fr_auto]">
        <?= csrf_field() ?>

        <?= be_date_input('available_date', '', 'stock-input rounded-2xl px-4 py-3 font-semibold', true, 'วันที่ พ.ศ. เช่น 05/05/2569') ?>

        <select name="time_slot" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <?php foreach ($timeSlots as $value => $label): ?>
                <option value="<?= h($value) ?>"><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <?php foreach ($statuses as $status): ?>
                <option value="<?= h($status) ?>"><?= h(booking_status_label($status)) ?></option>
            <?php endforeach; ?>
        </select>

        <input name="note" placeholder="หมายเหตุ" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <button class="stock-button rounded-2xl px-5 py-3 font-black"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึก</button>
    </form>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="w-full text-left text-sm">
            <thead>
                <tr>
                    <th class="py-3">วันที่</th>
                    <th>ช่วงเวลา</th>
                    <th>สถานะ</th>
                    <th>หมายเหตุ</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr class="border-t">
                        <td class="py-3 font-bold"><?= h(format_be_date($item['available_date'])) ?></td>
                        <td><?= h(time_slot_label($item['time_slot'])) ?></td>
                        <td><?= status_badge((string)$item['status']) ?></td>
                        <td><?= h($item['note']) ?></td>
                        <td>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <button data-confirm="ลบรายการนี้?" class="btn-danger btn-sm">
                                    <i class="fa-solid fa-trash mr-1"></i>ลบ
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

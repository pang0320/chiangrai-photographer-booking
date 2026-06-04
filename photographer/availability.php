<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');

$profile = photographer_profile_by_user((int)current_user()['id']);
$pid = (int)$profile['id'];
$timeSlots = ['morning' => 'เช้า', 'afternoon' => 'บ่าย', 'evening' => 'เย็น', 'full_day' => 'เต็มวัน'];
$statuses = ['available', 'unavailable'];

/**
 * ดึงข้อมูลแถววันว่างของช่างภาพตามรหัสที่ระบุ
 *
 * @param int $id รหัสแถววันว่าง
 * @param int $photographerId รหัสช่างภาพ (เพื่อตรวจสอบความเป็นเจ้าของ)
 * @return array|null ข้อมูลวันว่าง หรือ null หากไม่พบ
 */
function photographer_availability_row(int $id, int $photographerId): ?array
{
    $stmt = db()->prepare('SELECT * FROM photographer_availability WHERE id = ? AND photographer_id = ? LIMIT 1');
    $stmt->execute([$id, $photographerId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return $row;
}

/**
 * ตรวจสอบว่าในวันและช่วงเวลาที่ระบุ มีคำขอจองที่กำลังดำเนินการอยู่หรือไม่ (เพื่อป้องกันการลบหรือแก้ไข)
 *
 * @param array $row ข้อมูลแถววันว่างที่ต้องการตรวจสอบ
 * @return bool คืนค่า true หากมีคำขอจองที่ยังไม่เสร็จสิ้น/ไม่ถูกยกเลิก
 */
function photographer_availability_has_active_booking(array $row): bool
{
    $stmt = db()->prepare('SELECT COUNT(*)
                           FROM bookings
                           WHERE photographer_id = ?
                             AND booking_date = ?
                             AND status IN ("pending", "accepted", "confirmed")
                             AND deleted_at IS NULL
                             AND (time_slot = ? OR time_slot = "full_day" OR ? = "full_day")');
    $stmt->execute([
        (int)$row['photographer_id'],
        (string)$row['available_date'],
        (string)$row['time_slot'],
        (string)$row['time_slot'],
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

/**
 * ตรวจสอบความถูกต้องของข้อมูลวันว่างที่ส่งมาจากฟอร์ม
 *
 * @param array $timeSlots รายการช่วงเวลาที่อนุญาต
 * @param array $statuses รายการสถานะที่อนุญาต
 * @return array ข้อมูลที่ผ่านการตรวจสอบแล้ว [availableDate, timeSlot, status, note]
 */
function validate_availability_payload(array $timeSlots, array $statuses): array
{
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

    return [$availableDate, $timeSlot, $status, $note];
}

if (is_post()) {
    verify_csrf();

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'hide') {
        $id = (int)($_POST['id'] ?? 0);
        $row = photographer_availability_row($id, $pid);

        if (!$row) {
            flash('error', 'ไม่พบรายการวันว่างที่ต้องการซ่อน');
            redirect('/photographer/availability.php');
        }

        if (photographer_availability_has_active_booking($row)) {
            flash('error', 'รายการนี้มีคำขอจองที่ยังดำเนินการอยู่ จึงซ่อนไม่ได้');
            redirect('/photographer/availability.php');
        }

        $stmt = db()->prepare('UPDATE photographer_availability SET status = "unavailable", updated_at = NOW() WHERE id = ? AND photographer_id = ?');
        $stmt->execute([$id, $pid]);
        flash('success', 'ซ่อนวันว่างจากหน้าจองแล้ว ข้อมูลเดิมยังอยู่');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $row = photographer_availability_row($id, $pid);

        if (!$row) {
            flash('error', 'ไม่พบรายการวันว่างที่ต้องการลบ');
            redirect('/photographer/availability.php');
        }

        if (photographer_availability_has_active_booking($row)) {
            flash('error', 'รายการนี้มีคำขอจองที่ยังดำเนินการอยู่ จึงลบไม่ได้');
            redirect('/photographer/availability.php');
        }

        $stmt = db()->prepare('DELETE FROM photographer_availability WHERE id = ? AND photographer_id = ? AND status <> "booked"');
        $stmt->execute([$id, $pid]);
        flash('success', 'ลบรายการวันว่างแล้ว');
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $row = photographer_availability_row($id, $pid);

        if (!$row) {
            flash('error', 'ไม่พบรายการวันว่างที่ต้องการแก้ไข');
            redirect('/photographer/availability.php');
        }

        if (photographer_availability_has_active_booking($row)) {
            flash('error', 'รายการนี้มีคำขอจองที่ยังดำเนินการอยู่ จึงแก้ไขไม่ได้');
            redirect('/photographer/availability.php');
        }

        [$availableDate, $timeSlot, $status, $note] = validate_availability_payload($timeSlots, $statuses);
        $duplicateId = db_fetch_value('SELECT id
                                       FROM photographer_availability
                                       WHERE photographer_id = ?
                                         AND available_date = ?
                                         AND time_slot = ?
                                         AND id <> ?
                                       LIMIT 1', [$pid, $availableDate, $timeSlot, $id]);

        if ($duplicateId !== false) {
            flash('error', 'มีวันและช่วงเวลานี้อยู่แล้ว กรุณาแก้รายการเดิมแทน');
            redirect('/photographer/availability.php');
        }

        $stmt = db()->prepare('UPDATE photographer_availability
                               SET available_date = ?, time_slot = ?, status = ?, note = ?, updated_at = NOW()
                               WHERE id = ? AND photographer_id = ? AND status <> "booked"');
        $stmt->execute([$availableDate, $timeSlot, $status, $note, $id, $pid]);
        flash('success', 'แก้ไขวันว่างแล้ว');
    } else {
        $timeSlot = (string)($_POST['time_slot'] ?? '');
        $note = trim((string)($_POST['note'] ?? ''));

        if (!array_key_exists($timeSlot, $timeSlots)) {
            flash('error', 'ช่วงเวลาไม่ถูกต้อง');
            redirect('/photographer/availability.php');
        }
        
        $rawDates = explode(',', (string)($_POST['available_date'] ?? ''));
        $datesToInsert = [];
        foreach ($rawDates as $rawDate) {
            $parsed = parse_be_date_to_iso($rawDate);
            if ($parsed !== '' && $parsed >= date('Y-m-d')) {
                $datesToInsert[] = $parsed;
            }
        }
        
        if (empty($datesToInsert)) {
            flash('error', 'รูปแบบวันที่ไม่ถูกต้อง กรุณาเลือกอย่างน้อย 1 วัน (และไม่สามารถป้อนวันย้อนหลังได้)');
            redirect('/photographer/availability.php');
        }

        $stmt = db()->prepare('INSERT INTO photographer_availability (photographer_id, available_date, time_slot, status, note, created_at, updated_at)
                               VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                               ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note), updated_at = NOW()');
        foreach ($datesToInsert as $d) {
            $stmt->execute([$pid, $d, $timeSlot, 'available', $note]);
        }
        flash('success', 'บันทึกวันว่างแล้ว');
    }

    log_activity('manage_availability', 'photographer_availability', $pid);
    redirect('/photographer/availability.php');
}

$stmt = db()->prepare('SELECT * FROM photographer_availability WHERE photographer_id = ? AND available_date >= CURDATE() ORDER BY available_date DESC, time_slot');
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

    <form method="post" class="stock-card mt-6 grid gap-4 rounded-[1.5rem] p-5 md:grid-cols-[1.4fr_1fr_1fr_1fr_auto] items-end">
        <?= csrf_field() ?>

        <label class="grid gap-2 text-sm font-black text-neutral-700">
            <span><i class="fa-solid fa-calendar-day mr-2 text-red-600"></i>วันที่ พ.ศ. (เลือกได้หลายวัน) <?= required_mark() ?></span>
            <?= be_date_input('available_date', '', 'stock-input rounded-2xl px-4 py-3 font-semibold', true, 'เลือกวันที่ พ.ศ. (เลือกได้หลายวัน)', true) ?>
        </label>

        <label class="grid gap-2 text-sm font-black text-neutral-700">
            <span><i class="fa-solid fa-clock mr-2 text-red-600"></i>ช่วงเวลา</span>
            <select name="time_slot" class="stock-input rounded-2xl px-4 py-3 font-semibold">
                <?php foreach ($timeSlots as $value => $label): ?>
                    <option value="<?= h($value) ?>"><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="grid gap-2 text-sm font-black text-neutral-700">
            <span><i class="fa-solid fa-note-sticky mr-2 text-red-600"></i>หมายเหตุ</span>
            <input name="note" placeholder="หมายเหตุ" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        </label>
        
        <button class="stock-button h-[50px] rounded-2xl px-5 font-black"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึก</button>
    </form>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="w-full text-left text-sm">
            <thead>
                <tr>
                    <th class="py-3">วันที่</th>
                    <th>ช่วงเวลา</th>
                    <th>สถานะ</th>
                    <th>หมายเหตุ</th>
                    <th class="min-w-[360px]">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr class="border-t">
                        <td class="py-3 font-bold"><?= h(format_be_date($item['available_date'])) ?></td>
                        <td><?= h(time_slot_label($item['time_slot'])) ?></td>
                        <?php 
                            $hasActiveBooking = photographer_availability_has_active_booking($item);
                            $displayStatus = (string)$item['status'];
                            if ($displayStatus === 'available' && $hasActiveBooking) {
                                $displayStatus = 'booked';
                            }
                        ?>
                        <td><?= status_badge($displayStatus) ?></td>
                        <td><?= h($item['note']) ?></td>
                        <td class="py-3 align-top">
                            <div class="flex flex-wrap items-start gap-2">
                                <details class="w-full sm:w-auto">
                                    <summary class="btn-warning btn-sm inline-flex cursor-pointer list-none items-center gap-1 rounded-full">
                                        <i class="fa-solid fa-pen"></i>แก้ไข
                                    </summary>
                                    <form method="post" class="mt-3 grid min-w-[320px] gap-3 rounded-[1.25rem] border border-amber-100 bg-amber-50 p-4 shadow-sm">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

                                        <label class="grid gap-1 text-xs font-black text-neutral-700">
                                            <span><i class="fa-solid fa-calendar-day mr-1 text-red-600"></i>วันที่ พ.ศ. <?= required_mark() ?></span>
                                            <input name="available_date" required value="<?= h(format_be_date($item['available_date'])) ?>" placeholder="เช่น 05/05/2569" class="stock-input rounded-xl px-3 py-2 text-sm font-semibold">
                                        </label>

                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <label class="grid gap-1 text-xs font-black text-neutral-700">
                                                <span><i class="fa-solid fa-clock mr-1 text-red-600"></i>ช่วงเวลา</span>
                                                <select name="time_slot" class="stock-input rounded-xl px-3 py-2 text-sm font-semibold">
                                                    <?php foreach ($timeSlots as $value => $label): ?>
                                                        <option value="<?= h($value) ?>" <?php if ((string)$item['time_slot'] === (string)$value): ?>selected<?php endif; ?>><?= h($label) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <label class="grid gap-1 text-xs font-black text-neutral-700">
                                                <span><i class="fa-solid fa-toggle-on mr-1 text-red-600"></i>สถานะ</span>
                                                <select name="status" class="stock-input rounded-xl px-3 py-2 text-sm font-semibold">
                                                    <?php foreach ($statuses as $status): ?>
                                                        <option value="<?= h($status) ?>" <?php if ((string)$item['status'] === (string)$status): ?>selected<?php endif; ?>><?= h(booking_status_label($status)) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                        </div>

                                        <label class="grid gap-1 text-xs font-black text-neutral-700">
                                            <span><i class="fa-solid fa-note-sticky mr-1 text-red-600"></i>หมายเหตุ</span>
                                            <input name="note" value="<?= h($item['note']) ?>" placeholder="หมายเหตุ" class="stock-input rounded-xl px-3 py-2 text-sm font-semibold">
                                        </label>

                                        <?php if ($hasActiveBooking): ?>
                                            <div class="rounded-xl bg-white px-3 py-2 text-xs font-black leading-5 text-amber-700">
                                                <i class="fa-solid fa-triangle-exclamation mr-1"></i>รายการนี้มีคำขอจองที่ยังดำเนินการอยู่ จึงแก้ไขไม่ได้
                                            </div>
                                        <?php endif; ?>

                                        <button type="submit" class="btn-success btn-sm justify-self-start" <?php if ($hasActiveBooking): ?>disabled<?php endif; ?>>
                                            <i class="fa-solid fa-floppy-disk"></i>บันทึกแก้ไข
                                        </button>
                                    </form>
                                </details>

                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="hide">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" data-confirm="ซ่อนรายการนี้จากหน้าจอง?" data-confirm-text="ระบบจะเปลี่ยนสถานะเป็นไม่ว่าง ข้อมูลเดิมยังอยู่ในฐานข้อมูล" data-confirm-button="ซ่อนรายการ" class="btn-muted btn-sm" <?php if ($hasActiveBooking): ?>disabled<?php endif; ?>>
                                        <i class="fa-solid fa-eye-slash"></i>ซ่อน
                                    </button>
                                </form>

                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" data-confirm="ลบรายการวันว่างนี้?" data-confirm-text="ระบบจะลบรายการนี้ออกจากฐานข้อมูล ถ้ามีคำขอจองที่กำลังดำเนินการอยู่จะไม่สามารถลบได้" data-confirm-button="ลบรายการ" class="btn-danger btn-sm" <?php if ($hasActiveBooking): ?>disabled<?php endif; ?>>
                                        <i class="fa-solid fa-trash"></i>ลบ
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

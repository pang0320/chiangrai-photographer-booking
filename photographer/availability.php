<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');

ensure_availability_range_columns();
ensure_booking_range_columns();

$profile = photographer_profile_by_user((int)current_user()['id']);
$pid = (int)$profile['id'];
$statuses = ['available', 'unavailable'];

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

function photographer_availability_has_active_booking(array $row): bool
{
    $startDate = (string)($row['start_date'] ?? $row['available_date'] ?? '');
    $endDate = (string)($row['end_date'] ?? $startDate);
    $startTime = normalize_time_input((string)($row['start_time'] ?? ''));
    $endTime = normalize_time_input((string)($row['end_time'] ?? ''));

    if ($startTime === '' || $endTime === '') {
        [$startTime, $endTime] = slot_time_range((string)($row['time_slot'] ?? 'full_day'));
    }

    $stmt = db()->prepare('SELECT COUNT(*)
                           FROM bookings
                           WHERE photographer_id = ?
                             AND deleted_at IS NULL
                             AND status IN ("pending", "accepted", "in_progress")
                             AND COALESCE(start_date, booking_date) <= ?
                             AND COALESCE(end_date, booking_date) >= ?
                             AND COALESCE(start_time, CASE time_slot WHEN "morning" THEN "09:00:00" WHEN "afternoon" THEN "13:00:00" WHEN "evening" THEN "17:00:00" ELSE "09:00:00" END) < ?
                             AND COALESCE(end_time, CASE time_slot WHEN "morning" THEN "12:00:00" WHEN "afternoon" THEN "17:00:00" WHEN "evening" THEN "20:00:00" ELSE "17:00:00" END) > ?');
    $stmt->execute([
        (int)$row['photographer_id'],
        $endDate,
        $startDate,
        $endTime . ':00',
        $startTime . ':00',
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function availability_legacy_slot(string $startTime, string $endTime): string
{
    foreach (['morning', 'afternoon', 'evening', 'full_day'] as $slotName) {
        [$slotStart, $slotEnd] = slot_time_range($slotName);
        if ($slotStart === $startTime && $slotEnd === $endTime) {
            return $slotName;
        }
    }

    return 'full_day';
}

function availability_time_picker_input(string $name, string $value): string
{
    $value = normalize_time_input($value);
    if ($value === '') {
        $value = '09:00';
    }

    $id = 'time_picker_' . bin2hex(random_bytes(4));
    $options = [];
    for ($hour = 6; $hour <= 22; $hour++) {
        foreach ([0, 30] as $minute) {
            if ($hour === 22 && $minute === 30) {
                continue;
            }
            $options[] = sprintf('%02d:%02d', $hour, $minute);
        }
    }

    $html = '<div class="relative" data-time-picker data-target="' . h($id) . '">';
    $html .= '<input type="hidden" id="' . h($id) . '" name="' . h($name) . '" value="' . h($value) . '">';
    $html .= '<button type="button" class="stock-input flex w-full items-center justify-between rounded-2xl px-4 py-3 text-left font-semibold" data-time-picker-trigger>';
    $html .= '<span><i class="fa-solid fa-clock mr-2 text-red-600"></i><span data-time-picker-label>' . h($value) . '</span></span><i class="fa-solid fa-chevron-down text-neutral-400"></i>';
    $html .= '</button>';
    $html .= '<div class="time-picker-popover hidden absolute left-0 top-[calc(100%+.65rem)] z-[280] max-h-72 w-full overflow-y-auto rounded-[1.25rem] border border-neutral-200 bg-white p-2 shadow-2xl" data-time-picker-popover>';
    foreach ($options as $option) {
        $activeClass = $option === $value ? ' bg-neutral-950 text-white' : ' bg-neutral-50 text-neutral-800 hover:bg-red-50 hover:text-red-700';
        $html .= '<button type="button" data-time-value="' . h($option) . '" class="mb-1 flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm font-black transition' . h($activeClass) . '"><i class="fa-solid fa-clock"></i>' . h($option) . ' น.</button>';
    }
    $html .= '</div></div>';

    return $html;
}

function validate_availability_range_payload(array $statuses): array
{
    $startDate = parse_be_date_to_iso((string)($_POST['start_date'] ?? ''));
    $endDate = parse_be_date_to_iso((string)($_POST['end_date'] ?? ''));
    $startTime = normalize_time_input((string)($_POST['start_time'] ?? ''));
    $endTime = normalize_time_input((string)($_POST['end_time'] ?? ''));
    $status = (string)($_POST['status'] ?? 'available');
    $note = trim((string)($_POST['note'] ?? ''));

    if ($startDate === '' || $endDate === '') {
        flash('error', 'กรุณากรอกวันที่เริ่มต้นและวันที่สิ้นสุดให้ถูกต้อง');
        redirect('/photographer/availability.php');
    }

    if ($startDate < date('Y-m-d')) {
        flash('error', 'ไม่สามารถเพิ่มวันย้อนหลังได้');
        redirect('/photographer/availability.php');
    }

    if ($endDate < $startDate) {
        flash('error', 'วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่มต้น');
        redirect('/photographer/availability.php');
    }

    if ($startTime === '' || $endTime === '' || $endTime <= $startTime) {
        flash('error', 'เวลาเริ่มต้นและเวลาสิ้นสุดไม่ถูกต้อง');
        redirect('/photographer/availability.php');
    }

    if (!in_array($status, $statuses, true)) {
        flash('error', 'สถานะไม่ถูกต้อง');
        redirect('/photographer/availability.php');
    }

    return [$startDate, $endDate, $startTime, $endTime, $status, $note];
}

function availability_range_exists(int $photographerId, string $startDate, string $endDate, string $startTime, string $endTime, ?int $excludeId = null): bool
{
    $sql = 'SELECT id
            FROM photographer_availability
            WHERE photographer_id = ?
              AND status = "available"
              AND COALESCE(start_date, available_date) <= ?
              AND COALESCE(end_date, available_date) >= ?
              AND COALESCE(start_time, CASE time_slot WHEN "morning" THEN "09:00:00" WHEN "afternoon" THEN "13:00:00" WHEN "evening" THEN "17:00:00" ELSE "09:00:00" END) < ?
              AND COALESCE(end_time, CASE time_slot WHEN "morning" THEN "12:00:00" WHEN "afternoon" THEN "17:00:00" WHEN "evening" THEN "20:00:00" ELSE "17:00:00" END) > ?';
    $params = [$photographerId, $endDate, $startDate, $endTime . ':00', $startTime . ':00'];

    if ($excludeId) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }

    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);

    return (bool)$stmt->fetchColumn();
}

function create_daily_availability_rows(int $photographerId, string $startDate, string $endDate, string $startTime, string $endTime, string $status, string $note): int
{
    $created = 0;
    $legacySlot = availability_legacy_slot($startTime, $endTime);
    $stmt = db()->prepare('INSERT INTO photographer_availability
        (photographer_id, available_date, time_slot, start_date, end_date, start_time, end_time, status, note, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');

    try {
        $period = new DatePeriod(new DateTime($startDate), new DateInterval('P1D'), (new DateTime($endDate))->modify('+1 day'));
    } catch (Exception $exception) {
        return 0;
    }

    foreach ($period as $date) {
        $day = $date->format('Y-m-d');
        if ($status === 'available' && availability_range_exists($photographerId, $day, $day, $startTime, $endTime)) {
            continue;
        }

        $stmt->execute([$photographerId, $day, $legacySlot, $day, $day, $startTime . ':00', $endTime . ':00', $status, $note]);
        $created++;
    }

    return $created;
}

function normalize_existing_availability_ranges_to_days(int $photographerId): void
{
    $rows = db_fetch_all('SELECT *,
                                 COALESCE(start_date, available_date) AS range_start_date,
                                 COALESCE(end_date, available_date) AS range_end_date,
                                 COALESCE(start_time, CASE time_slot WHEN "morning" THEN "09:00:00" WHEN "afternoon" THEN "13:00:00" WHEN "evening" THEN "17:00:00" ELSE "09:00:00" END) AS range_start_time,
                                 COALESCE(end_time, CASE time_slot WHEN "morning" THEN "12:00:00" WHEN "afternoon" THEN "17:00:00" WHEN "evening" THEN "20:00:00" ELSE "17:00:00" END) AS range_end_time
                          FROM photographer_availability
                          WHERE photographer_id = ?
                            AND COALESCE(end_date, available_date) >= CURDATE()
                            AND COALESCE(start_date, available_date) <> COALESCE(end_date, available_date)', [$photographerId]);

    foreach ($rows as $row) {
        if (photographer_availability_has_active_booking($row)) {
            continue;
        }

        $startDate = (string)$row['range_start_date'];
        $endDate = (string)$row['range_end_date'];
        $startTime = normalize_time_input((string)$row['range_start_time']);
        $endTime = normalize_time_input((string)$row['range_end_time']);
        $status = (string)$row['status'];
        $note = trim((string)($row['note'] ?? ''));

        if ($startDate === '' || $endDate === '' || $startTime === '' || $endTime === '') {
            continue;
        }

        $deleteStmt = db()->prepare('DELETE FROM photographer_availability WHERE id = ? AND photographer_id = ?');
        $deleteStmt->execute([(int)$row['id'], $photographerId]);
        create_daily_availability_rows($photographerId, $startDate, $endDate, $startTime, $endTime, $status, $note);
    }
}

if (is_post()) {
    verify_csrf();

    $action = (string)($_POST['action'] ?? 'create');

    if (in_array($action, ['hide', 'delete', 'update'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        $row = photographer_availability_row($id, $pid);

        if (!$row) {
            flash('error', 'ไม่พบรายการวันว่าง');
            redirect('/photographer/availability.php');
        }

        if (photographer_availability_has_active_booking($row)) {
            flash('error', 'รายการนี้มีงานที่กำลังดำเนินการอยู่ จึงแก้ไขหรือลบไม่ได้');
            redirect('/photographer/availability.php');
        }

        if ($action === 'hide') {
            $stmt = db()->prepare('UPDATE photographer_availability SET status = "unavailable", updated_at = NOW() WHERE id = ? AND photographer_id = ?');
            $stmt->execute([$id, $pid]);
            flash('success', 'ซ่อนช่วงวันว่างจากหน้าจองแล้ว');
        } elseif ($action === 'delete') {
            $stmt = db()->prepare('DELETE FROM photographer_availability WHERE id = ? AND photographer_id = ?');
            $stmt->execute([$id, $pid]);
            flash('success', 'ลบรายการวันว่างแล้ว');
        } else {
            [$startDate, $endDate, $startTime, $endTime, $status, $note] = validate_availability_range_payload($statuses);

            if ($status === 'available' && availability_range_exists($pid, $startDate, $endDate, $startTime, $endTime, $id)) {
                flash('error', 'มีช่วงวัน/เวลาที่เปิดว่างทับกันอยู่แล้ว');
                redirect('/photographer/availability.php');
            }

            if ($startDate === $endDate) {
                $legacySlot = availability_legacy_slot($startTime, $endTime);
                $stmt = db()->prepare('UPDATE photographer_availability
                                       SET available_date = ?, time_slot = ?, start_date = ?, end_date = ?, start_time = ?, end_time = ?, status = ?, note = ?, updated_at = NOW()
                                       WHERE id = ? AND photographer_id = ?');
                $stmt->execute([$startDate, $legacySlot, $startDate, $endDate, $startTime . ':00', $endTime . ':00', $status, $note, $id, $pid]);
            } else {
                $stmt = db()->prepare('DELETE FROM photographer_availability WHERE id = ? AND photographer_id = ?');
                $stmt->execute([$id, $pid]);
                create_daily_availability_rows($pid, $startDate, $endDate, $startTime, $endTime, $status, $note);
            }
            flash('success', 'แก้ไขช่วงวันว่างแล้ว');
        }
    } else {
        [$startDate, $endDate, $startTime, $endTime, $status, $note] = validate_availability_range_payload($statuses);

        if ($status === 'available' && availability_range_exists($pid, $startDate, $endDate, $startTime, $endTime)) {
            flash('error', 'มีช่วงวัน/เวลาที่เปิดว่างทับกันอยู่แล้ว');
            redirect('/photographer/availability.php');
        }

        $created = create_daily_availability_rows($pid, $startDate, $endDate, $startTime, $endTime, $status, $note);
        flash('success', 'บันทึกวันว่างแบบแยกรายวันแล้ว ' . number_format($created) . ' รายการ');
    }

    log_activity('manage_availability', 'photographer_availability', $pid);
    redirect('/photographer/availability.php');
}

normalize_existing_availability_ranges_to_days($pid);

$stmt = db()->prepare('SELECT *,
                              COALESCE(start_date, available_date) AS range_start_date,
                              COALESCE(end_date, available_date) AS range_end_date,
                              COALESCE(start_time, CASE time_slot WHEN "morning" THEN "09:00:00" WHEN "afternoon" THEN "13:00:00" WHEN "evening" THEN "17:00:00" ELSE "09:00:00" END) AS range_start_time,
                              COALESCE(end_time, CASE time_slot WHEN "morning" THEN "12:00:00" WHEN "afternoon" THEN "17:00:00" WHEN "evening" THEN "20:00:00" ELSE "17:00:00" END) AS range_end_time
                       FROM photographer_availability
                       WHERE photographer_id = ?
                         AND COALESCE(end_date, available_date) >= CURDATE()
                       ORDER BY COALESCE(start_date, available_date) ASC, COALESCE(start_time, "09:00:00") ASC');
$stmt->execute([$pid]);
$items = $stmt->fetchAll();

$pageTitle = 'วันว่าง';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">สตูดิโอช่างภาพ</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">จัดการวันว่าง</h1>
        <p class="mt-2 max-w-3xl text-sm font-bold leading-7 text-neutral-500">กำหนดวันว่างเป็นช่วงวันที่และช่วงเวลาได้ เช่น 10–15 มิถุนายน 2569 เวลา 09:00–17:00 น. ลูกค้าจะจองได้เฉพาะช่วงที่เปิดว่างและไม่ชนกับงานเดิม</p>
    </div>

    <div class="mt-5 grid gap-3 md:grid-cols-3">
        <div class="rounded-[1.35rem] bg-emerald-50 p-4 text-sm font-bold leading-7 text-emerald-700"><i class="fa-solid fa-circle-check mr-2"></i>ว่าง = ลูกค้าส่งคำขอจองได้</div>
        <div class="rounded-[1.35rem] bg-slate-100 p-4 text-sm font-bold leading-7 text-slate-700"><i class="fa-solid fa-circle-minus mr-2"></i>ไม่ว่าง = เก็บไว้เป็นบันทึก แต่ไม่แสดงให้จอง</div>
        <div class="rounded-[1.35rem] bg-red-50 p-4 text-sm font-bold leading-7 text-red-700"><i class="fa-solid fa-shield-halved mr-2"></i>ระบบกันจองซ้อนจากสถานะ pending, accepted, in_progress</div>
    </div>

    <form method="post" class="stock-card mt-6 grid items-end gap-4 rounded-[1.5rem] p-5 md:grid-cols-2 xl:grid-cols-[1.6fr_.8fr_.8fr_1.2fr_auto]">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">

        <div class="grid gap-2 text-sm font-black text-neutral-700">
            <span><i class="fa-solid fa-calendar-days mr-2 text-red-600"></i>ช่วงวันที่รับงาน <?= required_mark() ?></span>
            <?= calendar_date_range_input('start_date', 'end_date', '', '', 'เลือกช่วงวันที่รับงาน', true) ?>
        </div>

        <label class="grid gap-2 text-sm font-black text-neutral-700">
            <span><i class="fa-solid fa-clock mr-2 text-red-600"></i>เวลาเริ่มต้น</span>
            <?= availability_time_picker_input('start_time', '09:00') ?>
        </label>

        <label class="grid gap-2 text-sm font-black text-neutral-700">
            <span><i class="fa-solid fa-clock mr-2 text-red-600"></i>เวลาสิ้นสุด</span>
            <?= availability_time_picker_input('end_time', '17:00') ?>
        </label>

        <label class="grid gap-2 text-sm font-black text-neutral-700">
            <span><i class="fa-solid fa-note-sticky mr-2 text-red-600"></i>หมายเหตุ</span>
            <input name="note" placeholder="เช่น รับงานครึ่งวันเช้า" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        </label>

        <button class="stock-button h-[50px] rounded-2xl px-5 font-black"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึก</button>
    </form>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <?php if ($items): ?>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th class="py-3">ช่วงวันที่</th>
                        <th>ช่วงเวลา</th>
                        <th>สถานะ</th>
                        <th>หมายเหตุ</th>
                        <th class="min-w-[400px]">จัดการ</th>
                    </tr>
                </thead>
                <tbody data-block-paginate="5">
                    <?php foreach ($items as $item): ?>
                        <?php $hasActiveBooking = photographer_availability_has_active_booking($item); ?>
                        <tr class="border-t align-top">
                            <td class="py-3 font-bold"><?= h(format_booking_date_range($item['range_start_date'], $item['range_end_date'])) ?></td>
                            <td><?= h(format_booking_time_range($item['range_start_time'], $item['range_end_time'])) ?></td>
                            <td>
                                <?php if ($hasActiveBooking && (string)$item['status'] === 'available'): ?>
                                    <?= status_badge('booked') ?>
                                <?php else: ?>
                                    <?= status_badge((string)$item['status']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= h((string)$item['note']) ?></td>
                            <td class="py-3 align-top">
                                <div class="flex flex-wrap items-start gap-2">
                                    <details class="w-full sm:w-auto">
                                        <summary class="btn-warning btn-sm inline-flex cursor-pointer list-none items-center gap-1 rounded-full">
                                            <i class="fa-solid fa-pen"></i>แก้ไข
                                        </summary>
                                        <form method="post" class="mt-3 grid min-w-[360px] gap-3 rounded-[1.25rem] border border-amber-100 bg-amber-50 p-4 shadow-sm">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

                                            <input type="hidden" name="status" value="<?= h((string)$item['status']) ?>">

                                            <div class="grid gap-3">
                                                <label class="grid gap-1 text-xs font-black text-neutral-700">
                                                    <span><i class="fa-solid fa-calendar-days mr-1 text-red-600"></i>ช่วงวันที่ <?= required_mark() ?></span>
                                                    <?= calendar_date_range_input('start_date', 'end_date', (string)$item['range_start_date'], (string)$item['range_end_date'], 'แก้ไขช่วงวันที่', true) ?>
                                                </label>
                                            </div>

                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <label class="grid gap-1 text-xs font-black text-neutral-700">
                                                    <span><i class="fa-solid fa-clock mr-1 text-red-600"></i>เวลาเริ่มต้น</span>
                                                    <?= availability_time_picker_input('start_time', format_time_hm($item['range_start_time'])) ?>
                                                </label>
                                                <label class="grid gap-1 text-xs font-black text-neutral-700">
                                                    <span><i class="fa-solid fa-clock mr-1 text-red-600"></i>เวลาสิ้นสุด</span>
                                                    <?= availability_time_picker_input('end_time', format_time_hm($item['range_end_time'])) ?>
                                                </label>
                                            </div>

                                            <label class="grid gap-1 text-xs font-black text-neutral-700">
                                                <span><i class="fa-solid fa-note-sticky mr-1 text-red-600"></i>หมายเหตุ</span>
                                                <input name="note" value="<?= h((string)$item['note']) ?>" placeholder="หมายเหตุ" class="stock-input rounded-xl px-3 py-2 text-sm font-semibold">
                                            </label>

                                            <?php if ($hasActiveBooking): ?>
                                                <div class="rounded-xl bg-white px-3 py-2 text-xs font-black leading-5 text-amber-700">
                                                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>รายการนี้มีงานที่กำลังดำเนินการอยู่ จึงแก้ไขไม่ได้
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
                                        <button type="submit" data-confirm="ลบรายการวันว่างนี้?" data-confirm-text="ระบบจะลบรายการนี้ออกจากตารางวันว่าง ถ้ามีงานกำลังดำเนินการอยู่จะลบไม่ได้" data-confirm-button="ลบรายการ" class="btn-danger btn-sm" <?php if ($hasActiveBooking): ?>disabled<?php endif; ?>>
                                            <i class="fa-solid fa-trash"></i>ลบ
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state rounded-[2rem] p-10 text-center">
                <i class="fa-solid fa-calendar-xmark text-4xl text-red-600"></i>
                <h2 class="mt-3 text-xl font-black text-neutral-950">ยังไม่มีช่วงวันว่าง</h2>
                <p class="mt-2 text-neutral-600">เพิ่มช่วงวันที่และเวลาที่เปิดรับงาน เพื่อให้ลูกค้าส่งคำขอจองได้</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-time-picker]').forEach(function (picker) {
        var hidden = document.getElementById(picker.dataset.target || '');
        var trigger = picker.querySelector('[data-time-picker-trigger]');
        var popover = picker.querySelector('[data-time-picker-popover]');
        var label = picker.querySelector('[data-time-picker-label]');

        if (!hidden || !trigger || !popover || !label) {
            return;
        }

        trigger.addEventListener('click', function (event) {
            event.stopPropagation();
            document.querySelectorAll('[data-time-picker-popover]').forEach(function (item) {
                if (item !== popover) {
                    item.classList.add('hidden');
                }
            });
            popover.classList.toggle('hidden');
        });

        popover.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        popover.querySelectorAll('[data-time-value]').forEach(function (button) {
            button.addEventListener('click', function () {
                hidden.value = button.dataset.timeValue || '';
                label.textContent = hidden.value;
                popover.querySelectorAll('[data-time-value]').forEach(function (item) {
                    item.classList.remove('bg-neutral-950', 'text-white');
                    item.classList.add('bg-neutral-50', 'text-neutral-800');
                });
                button.classList.add('bg-neutral-950', 'text-white');
                button.classList.remove('bg-neutral-50', 'text-neutral-800');
                popover.classList.add('hidden');
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    });

    document.addEventListener('click', function () {
        document.querySelectorAll('[data-time-picker-popover]').forEach(function (popover) {
            popover.classList.add('hidden');
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

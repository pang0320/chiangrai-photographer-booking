<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');

$profile = photographer_profile_by_user((int)current_user()['id']);
$pid = (int)$profile['id'];
$timeSlots = ['morning' => 'เช้า', 'afternoon' => 'บ่าย', 'evening' => 'เย็น', 'full_day' => 'เต็มวัน'];
$statuses = ['available', 'unavailable', 'booked'];

if (is_post()) {
    verify_csrf();

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM photographer_availability WHERE id = ? AND photographer_id = ?');
        $stmt->execute([$id, $pid]);
        flash('success', 'ลบวันว่างแล้ว');
    } else {
        $availableDate = (string)($_POST['available_date'] ?? '');
        $timeSlot = (string)($_POST['time_slot'] ?? '');
        $status = (string)($_POST['status'] ?? 'available');
        $note = trim((string)($_POST['note'] ?? ''));

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

$stmt = db()->prepare('SELECT * FROM photographer_availability WHERE photographer_id = ? ORDER BY available_date DESC, time_slot');
$stmt->execute([$pid]);
$items = $stmt->fetchAll();

$pageTitle = 'วันว่าง';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Photographer Studio</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">จัดการวันว่าง</h1>
    </div>

    <form method="post" class="stock-card mt-6 grid gap-4 rounded-[1.5rem] p-5 md:grid-cols-5">
        <?= csrf_field() ?>

        <input type="date" name="available_date" required class="stock-input rounded-2xl px-4 py-3 font-semibold">

        <select name="time_slot" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <?php foreach ($timeSlots as $value => $label): ?>
                <option value="<?= h($value) ?>"><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <?php foreach ($statuses as $status): ?>
                <option value="<?= h($status) ?>"><?= h($status) ?></option>
            <?php endforeach; ?>
        </select>

        <input name="note" placeholder="note" class="stock-input rounded-2xl px-4 py-3 font-semibold">
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
                        <td class="py-3 font-bold"><?= h($item['available_date']) ?></td>
                        <td><?= h(time_slot_label($item['time_slot'])) ?></td>
                        <td><?= h($item['status']) ?></td>
                        <td><?= h($item['note']) ?></td>
                        <td>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <button data-confirm="ลบรายการนี้?" class="rounded-full bg-red-50 px-3 py-1.5 font-black text-red-700">
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

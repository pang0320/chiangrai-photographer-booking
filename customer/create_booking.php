<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('customer');
$user = current_user();
$photographerId = (int)($_GET['photographer_id'] ?? $_POST['photographer_id'] ?? 0);
$stmt = db()->prepare('SELECT p.*, u.id AS photographer_user_id
                       FROM photographer_profiles p
                       JOIN users u ON u.id = p.user_id
                       WHERE p.id = ?
                         AND p.approval_status = "approved"
                         AND p.is_available = 1
                         AND u.status = "active"
                         AND p.deleted_at IS NULL
                         AND u.deleted_at IS NULL
                       LIMIT 1');
$stmt->execute([$photographerId]);
$profile = $stmt->fetch();
if (!$profile) exit('Photographer unavailable');

$categories = db()->prepare('SELECT sc.* FROM photographer_services ps JOIN service_categories sc ON sc.id=ps.category_id WHERE ps.photographer_id=? AND ps.is_active=1 ORDER BY sc.sort_order');
$categories->execute([$photographerId]);
$categories = $categories->fetchAll();
$districts = db()->prepare('SELECT d.* FROM photographer_service_areas psa JOIN districts d ON d.id=psa.district_id WHERE psa.photographer_id=? AND psa.is_active=1 ORDER BY psa.is_primary DESC, d.district_name');
$districts->execute([$photographerId]);
$districts = $districts->fetchAll();

if (is_post()) {
    verify_csrf();
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $districtId = (int)($_POST['district_id'] ?? 0);
    $date = (string)($_POST['booking_date'] ?? '');
    $slot = (string)($_POST['time_slot'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !in_array($slot, ['morning','afternoon','evening','full_day'], true) || !can_book_slot($photographerId, $date, $slot)) {
        flash('error', 'วันหรือช่วงเวลานี้ไม่พร้อมจอง');
        redirect('/customer/create_booking.php?photographer_id=' . $photographerId);
    }

    $categoryAllowed = false;
    foreach ($categories as $category) {
        if ((int)$category['id'] === $categoryId) {
            $categoryAllowed = true;
            break;
        }
    }

    $districtAllowed = false;
    foreach ($districts as $district) {
        if ((int)$district['id'] === $districtId) {
            $districtAllowed = true;
            break;
        }
    }

    if (!$categoryAllowed || !$districtAllowed) {
        flash('error', 'ประเภทงานหรือพื้นที่ไม่ถูกต้อง');
        redirect('/customer/create_booking.php?photographer_id=' . $photographerId);
    }

    if (trim((string)$_POST['contact_name']) === '' || trim((string)$_POST['contact_phone']) === '' || trim((string)$_POST['job_detail']) === '') {
        flash('error', 'กรุณากรอกข้อมูลสำคัญให้ครบ');
        redirect('/customer/create_booking.php?photographer_id=' . $photographerId);
    }
    $code = generate_booking_code();
    db()->beginTransaction();
    $stmt = db()->prepare('INSERT INTO bookings (booking_code, customer_id, photographer_id, category_id, district_id, booking_date, time_slot, contact_name, contact_phone, contact_channel, job_detail, note, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", NOW(), NOW())');
    $stmt->execute([$code, (int)$user['id'], $photographerId, $categoryId, $districtId, $date, $slot, trim((string)$_POST['contact_name']), trim((string)$_POST['contact_phone']), trim((string)$_POST['contact_channel']), trim((string)$_POST['job_detail']), trim((string)$_POST['note'])]);
    $bookingId = (int)db()->lastInsertId();
    add_booking_status_log($bookingId, null, 'pending', (int)$user['id'], 'สร้างคำขอจอง');
    notify_user((int)$profile['photographer_user_id'], 'มีคำขอจองใหม่', $code, 'booking', $bookingId);
    log_activity('create_booking', 'bookings', $bookingId);
    db()->commit();
    flash('success', 'ส่งคำขอจองแล้ว');
    redirect('/customer/booking_detail.php?id=' . $bookingId);
}

$pageTitle = 'ส่งคำขอจอง';
include __DIR__ . '/../includes/header.php';
?>
<section class="stock-shell px-4 py-10">
    <div class="mx-auto max-w-3xl stock-card rounded-[2rem] p-8">
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Booking request</p>
        <h1 class="mt-2 text-3xl font-black text-neutral-950">ส่งคำขอจอง: <?= h($profile['display_name']) ?></h1>
        <p class="mt-4 rounded-2xl bg-red-50 p-4 text-sm font-black text-red-700"><?= h(PAYMENT_DISCLAIMER) ?></p>
        <form method="post" class="mt-6 grid gap-4">
            <?= csrf_field() ?><input type="hidden" name="photographer_id" value="<?= $photographerId ?>">
            <div class="grid gap-4 sm:grid-cols-2">
                <select name="category_id" required class="stock-input rounded-2xl px-4 py-3 font-semibold">
                    <option value="">ประเภทงาน</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>">
                            <?= h($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="district_id" required class="stock-input rounded-2xl px-4 py-3 font-semibold">
                    <option value="">อำเภอ</option>
                    <?php foreach ($districts as $district): ?>
                        <option value="<?= (int)$district['id'] ?>">
                            <?= h($district['district_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="booking_date" required class="stock-input rounded-2xl px-4 py-3 font-semibold">
                <select name="time_slot" required class="stock-input rounded-2xl px-4 py-3 font-semibold"><option value="">ช่วงเวลา</option><option value="morning">เช้า</option><option value="afternoon">บ่าย</option><option value="evening">เย็น</option><option value="full_day">เต็มวัน</option></select>
                <input name="contact_name" value="<?= h($user['name']) ?>" required placeholder="ชื่อผู้ติดต่อ" class="stock-input rounded-2xl px-4 py-3 font-semibold">
                <input name="contact_phone" value="<?= h($user['phone']) ?>" required placeholder="เบอร์โทรศัพท์" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            </div>
            <input name="contact_channel" required placeholder="ช่องทางติดต่อกลับ เช่น LINE, Facebook" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <textarea name="job_detail" required rows="5" placeholder="รายละเอียดงาน" class="stock-input rounded-2xl px-4 py-3 font-semibold"></textarea>
            <textarea name="note" rows="3" placeholder="หมายเหตุ" class="stock-input rounded-2xl px-4 py-3 font-semibold"></textarea>
            <button data-confirm="ยืนยันส่งคำขอจอง?" class="stock-button rounded-full px-5 py-3 font-black"><i class="fa-solid fa-calendar-check mr-2"></i>ส่งคำขอจอง</button>
        </form>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>

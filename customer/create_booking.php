<?php
require_once __DIR__ . '/../includes/functions.php';
$cleanContext = clean_context_init(['photographer_id', 'booking_date', 'time_slot']);
requireRole('customer');
$user = current_user();
$photographerId = (int)clean_context_value($cleanContext, 'photographer_id', ($_POST['photographer_id'] ?? 0));
$selectedBookingDate = parse_be_date_to_iso((string)clean_context_value($cleanContext, 'booking_date', ''));
$selectedTimeSlot = (string)clean_context_value($cleanContext, 'time_slot', '');
if (!in_array($selectedTimeSlot, ['morning', 'afternoon', 'evening', 'full_day'], true)) {
    $selectedTimeSlot = '';
}
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
if (!$profile) exit('ช่างภาพไม่พร้อมรับจอง');

$categories = db()->prepare('SELECT sc.* FROM photographer_services ps JOIN service_categories sc ON sc.id=ps.category_id WHERE ps.photographer_id=? AND ps.is_active=1 ORDER BY sc.sort_order');
$categories->execute([$photographerId]);
$categories = $categories->fetchAll();
$districts = db()->prepare('SELECT d.* FROM photographer_service_areas psa JOIN districts d ON d.id=psa.district_id WHERE psa.photographer_id=? AND psa.is_active=1 ORDER BY psa.is_primary DESC, d.district_name');
$districts->execute([$photographerId]);
$districts = $districts->fetchAll();

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
                                AND pa.available_date >= CURDATE()', [$photographerId]);
$GLOBALS['calendar_date_statuses']['booking_date'] = [];
$GLOBALS['calendar_date_default_status']['booking_date'] = 'unavailable';
$calendarStatusPriority = ['unavailable' => 0, 'available' => 1, 'pending' => 2, 'booked' => 3];
foreach ($calendarRows as $row) {
    $dateKey = (string)$row['available_date'];
    $statusKey = (string)$row['status'];
    if ($row['booking_status'] === 'pending') {
        $statusKey = 'pending';
    } elseif (in_array((string)$row['booking_status'], ['accepted', 'confirmed'], true)) {
        $statusKey = 'booked';
    }
    $currentStatus = $GLOBALS['calendar_date_statuses']['booking_date'][$dateKey] ?? 'unavailable';
    if (($calendarStatusPriority[$statusKey] ?? 0) >= ($calendarStatusPriority[$currentStatus] ?? 0)) {
        $GLOBALS['calendar_date_statuses']['booking_date'][$dateKey] = $statusKey;
    }
}
$GLOBALS['calendar_date_labels']['booking_date'] = 'วันที่ต้องการจ้าง';

if (is_post()) {
    verify_csrf();
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $districtId = (int)($_POST['district_id'] ?? 0);
    $date = parse_be_date_to_iso((string)($_POST['booking_date'] ?? ''));
    $slot = (string)($_POST['time_slot'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !in_array($slot, ['morning','afternoon','evening','full_day'], true) || !can_book_slot($photographerId, $date, $slot)) {
        flash('error', 'วันหรือช่วงเวลานี้ไม่พร้อมจอง');
        clean_redirect('/customer/create_booking.php', ['photographer_id' => $photographerId]);
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
        clean_redirect('/customer/create_booking.php', ['photographer_id' => $photographerId]);
    }

    if (trim((string)$_POST['contact_name']) === '' || trim((string)$_POST['contact_phone']) === '' || trim((string)$_POST['job_detail']) === '') {
        flash('error', 'กรุณากรอกข้อมูลสำคัญให้ครบ');
        clean_redirect('/customer/create_booking.php', ['photographer_id' => $photographerId]);
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
    clean_redirect('/customer/booking_detail.php', ['id' => $bookingId]);
}

$pageTitle = 'ส่งคำขอจอง';
include __DIR__ . '/../includes/header.php';
?>
<section class="stock-shell px-4 py-10 sm:px-6 lg:px-8">
    <div class="dashboard-hero overflow-hidden rounded-[2rem] p-6 text-white md:p-8">
        <div class="grid gap-6 lg:grid-cols-[1fr_360px] lg:items-end">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/60">
                    <i class="fa-solid fa-calendar-check mr-2"></i>คำขอจอง
                </p>
                <h1 class="mt-2 text-3xl font-black md:text-4xl">ส่งคำขอจองช่างภาพ</h1>
                <p class="mt-3 max-w-3xl text-base font-semibold leading-8 text-white/75 md:text-lg">
                    กรอกข้อมูลสำคัญให้ครบใน 5 ส่วน เพื่อให้ช่างภาพประเมินงานและตอบกลับได้เร็วขึ้น
                </p>
            </div>
            <div class="rounded-[1.75rem] bg-white/12 p-5 backdrop-blur">
                <p class="text-sm font-black text-white/60"><i class="fa-solid fa-circle-info mr-2"></i>หมายเหตุสำคัญ</p>
                <p class="mt-2 text-sm font-bold leading-7 text-white/80"><?= h(PAYMENT_DISCLAIMER) ?></p>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
        <form method="post" class="grid gap-6">
            <?= csrf_field() ?>
            <input type="hidden" name="photographer_id" value="<?= $photographerId ?>">

            <section class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker"><i class="fa-solid fa-id-card mr-2"></i>ข้อมูลช่างภาพ</p>
                <div class="mt-4 flex flex-col gap-5 sm:flex-row sm:items-center">
                    <img class="h-24 w-24 rounded-[1.5rem] object-cover" src="<?= h(public_image($profile['profile_image'], '/assets/uploads/seed/photo-1500648767791-00dcc994a43e.jpg')) ?>" alt="">
                    <div class="flex-1">
                        <h2 class="text-2xl font-black text-neutral-950"><?= h($profile['display_name']) ?></h2>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="rounded-full bg-red-50 px-3 py-1.5 text-sm font-black text-red-700">
                                <i class="fa-solid fa-star mr-1"></i>คะแนน <?= number_format((float)$profile['average_rating'], 1) ?>
                            </span>
                            <span class="rounded-full bg-neutral-100 px-3 py-1.5 text-sm font-black text-neutral-700">
                                <i class="fa-solid fa-comment mr-1"></i>รีวิว <?= number_format((int)$profile['total_reviews']) ?> รายการ
                            </span>
                            <span class="rounded-full bg-neutral-100 px-3 py-1.5 text-sm font-black text-neutral-700">
                                <i class="fa-solid fa-tag mr-1"></i>ราคาเริ่มต้นโดยประมาณ <?= number_format((float)$profile['starting_price']) ?> บาท
                            </span>
                        </div>
                        <p class="mt-3 text-sm font-bold leading-7 text-neutral-500">ราคาเป็นข้อมูลประกอบการตัดสินใจ ลูกค้าและช่างภาพต้องตกลงราคาและชำระเงินกันเองภายนอกระบบ</p>
                    </div>
                </div>
            </section>

            <section class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker"><i class="fa-solid fa-location-dot mr-2"></i>พื้นที่รับงาน</p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">เลือกอำเภอที่ต้องการจ้าง</h2>
                <p class="mt-2 text-base font-semibold leading-7 text-neutral-600">เลือกจากพื้นที่ที่ช่างภาพเปิดรับงานไว้เท่านั้น</p>
                <label class="mt-5 block text-sm font-black text-neutral-700" for="district_id">
                    <i class="fa-solid fa-map-location-dot mr-2 text-red-600"></i>อำเภอที่ถ่ายงาน
                </label>
                <select id="district_id" name="district_id" required class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
                    <option value="">เลือกอำเภอที่ต้องการจ้าง</option>
                    <?php foreach ($districts as $district): ?>
                        <option value="<?= (int)$district['id'] ?>">
                            <?= h($district['district_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </section>

            <section class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker"><i class="fa-solid fa-calendar-days mr-2"></i>วันที่ต้องการจ้าง</p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">เลือกประเภทงาน วันที่ และช่วงเวลา</h2>
                <p class="mt-2 text-base font-semibold leading-7 text-neutral-600">ระบบจะรับจองเฉพาะวันที่ช่างภาพเปิดว่างและยังไม่ถูกจองซ้ำ</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-layer-group mr-2 text-red-600"></i>ประเภทงาน</span>
                        <select name="category_id" required class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
                            <option value="">เลือกประเภทงาน</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>">
                                    <?= h($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-clock mr-2 text-red-600"></i>ช่วงเวลา</span>
                        <select name="time_slot" required class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
                            <option value="">เลือกช่วงเวลา</option>
                            <option value="morning" <?php if ($selectedTimeSlot === 'morning'): ?>selected<?php endif; ?>>เช้า</option>
                            <option value="afternoon" <?php if ($selectedTimeSlot === 'afternoon'): ?>selected<?php endif; ?>>บ่าย</option>
                            <option value="evening" <?php if ($selectedTimeSlot === 'evening'): ?>selected<?php endif; ?>>เย็น</option>
                            <option value="full_day" <?php if ($selectedTimeSlot === 'full_day'): ?>selected<?php endif; ?>>เต็มวัน</option>
                        </select>
                    </label>

                    <div class="sm:col-span-2">
                        <label class="mb-2 block text-sm font-black text-neutral-700">
                            <i class="fa-solid fa-calendar-day mr-2 text-red-600"></i>วันที่ถ่ายงาน
                        </label>
                        <?= be_date_input('booking_date', $selectedBookingDate, 'stock-input rounded-2xl px-4 py-3 font-semibold', true, 'วันที่ถ่าย พ.ศ. เช่น 05/05/2569') ?>
                        <?php if ($selectedBookingDate !== ''): ?>
                            <p class="mt-2 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-black text-emerald-700">
                                <i class="fa-solid fa-circle-check mr-1"></i>เลือกวันที่จากปฏิทินวันว่างแล้ว: <?= h(format_be_date($selectedBookingDate)) ?>
                                <?php if ($selectedTimeSlot !== ''): ?>
                                    · <?= h(time_slot_label($selectedTimeSlot)) ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker"><i class="fa-solid fa-address-book mr-2"></i>ช่องทางติดต่อ</p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">ข้อมูลสำหรับให้ช่างภาพติดต่อกลับ</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-user mr-2 text-red-600"></i>ชื่อผู้ติดต่อ</span>
                        <input name="contact_name" value="<?= h($user['name']) ?>" required placeholder="ชื่อผู้ติดต่อ" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
                    </label>
                    <label class="block">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-phone mr-2 text-red-600"></i>เบอร์โทรศัพท์</span>
                        <input name="contact_phone" value="<?= h($user['phone']) ?>" required placeholder="เบอร์โทรศัพท์" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-comments mr-2 text-red-600"></i>ช่องทางติดต่อกลับ</span>
                        <input name="contact_channel" required placeholder="เช่น LINE: yourline หรือ Facebook: profile link" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
                    </label>
                </div>
            </section>

            <section class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker"><i class="fa-solid fa-clipboard-list mr-2"></i>รายละเอียดคำขอจอง</p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">เล่ารายละเอียดงานให้ช่างภาพเข้าใจ</h2>
                <div class="mt-5 grid gap-4">
                    <label class="block">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-camera mr-2 text-red-600"></i>รายละเอียดงาน</span>
                        <textarea name="job_detail" required rows="5" placeholder="เช่น ถ่ายรับปริญญา 2 คน สถานที่ มฟล. อยากได้โทนสดใส มีรูปครอบครัว และต้องการไฟล์หลังแต่งสี" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold"></textarea>
                    </label>
                    <label class="block">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-note-sticky mr-2 text-red-600"></i>หมายเหตุเพิ่มเติม</span>
                        <textarea name="note" rows="3" placeholder="เช่น ต้องการคุยรายละเอียดเพิ่มเติมก่อนยืนยันงาน" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold"></textarea>
                    </label>
                </div>
                <div class="mt-5 rounded-[1.5rem] bg-red-50 p-4 text-sm font-black leading-7 text-red-700">
                    <i class="fa-solid fa-circle-info mr-2"></i><?= h(PAYMENT_DISCLAIMER) ?> ราคาและการชำระเงินตกลงกับช่างภาพโดยตรงภายนอกระบบ
                </div>
                <button data-confirm="ยืนยันส่งคำขอจอง?" class="stock-button mt-5 w-full rounded-full px-5 py-3 font-black">
                    <i class="fa-solid fa-calendar-check mr-2"></i>ส่งคำขอจอง
                </button>
            </section>
        </form>

        <aside class="space-y-5 lg:sticky lg:top-28 lg:self-start">
            <div class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker"><i class="fa-solid fa-list-check mr-2"></i>สรุปขั้นตอน</p>
                <div class="mt-5 grid gap-3">
                    <?php foreach ([['ข้อมูลช่างภาพ', 'fa-id-card'], ['พื้นที่รับงาน', 'fa-location-dot'], ['วันที่ต้องการจ้าง', 'fa-calendar-days'], ['ช่องทางติดต่อ', 'fa-address-book'], ['รายละเอียดคำขอจอง', 'fa-clipboard-list']] as $step): ?>
                        <div class="flex items-center gap-3 rounded-2xl bg-neutral-50 px-4 py-3 font-black text-neutral-700">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-red-50 text-red-600"><i class="fa-solid <?= h($step[1]) ?>"></i></span>
                            <?= h($step[0]) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker"><i class="fa-solid fa-location-dot mr-2"></i>พื้นที่รับงานของช่างภาพ</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <?php foreach ($districts as $district): ?>
                        <span class="rounded-full bg-neutral-100 px-3 py-1.5 text-sm font-black text-neutral-700"><?= h($district['district_name']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>

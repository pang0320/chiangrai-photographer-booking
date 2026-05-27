<?php
require_once __DIR__ . '/../includes/functions.php';
ensure_service_categories_deleted_at_column();
$cleanContext = clean_context_init(['photographer_id', 'booking_date', 'time_slot']);
requireRole(['customer', 'photographer']);
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

if ($user && (string)$user['role_name'] === 'photographer' && (int)$profile['photographer_user_id'] === (int)$user['id']) {
    flash('warning', 'ไม่สามารถส่งคำขอจองให้โปรไฟล์ช่างภาพของตัวเองได้');
    clean_redirect('/photographers.php', []);
}

$categories = db()->prepare('SELECT sc.* FROM photographer_services ps JOIN service_categories sc ON sc.id=ps.category_id WHERE ps.photographer_id=? AND ps.is_active=1 AND sc.is_active=1 AND sc.deleted_at IS NULL ORDER BY sc.sort_order');
$categories->execute([$photographerId]);
$categories = $categories->fetchAll();
$districts = db()->prepare('SELECT d.* FROM photographer_service_areas psa JOIN districts d ON d.id=psa.district_id WHERE psa.photographer_id=? AND psa.is_active=1 ORDER BY psa.is_primary DESC, d.district_name');
$districts->execute([$photographerId]);
$districts = $districts->fetchAll();

$lockedBookingDate = '';
$lockedTimeSlot = '';
if ($selectedBookingDate !== '' && $selectedTimeSlot !== '' && can_book_slot($photographerId, $selectedBookingDate, $selectedTimeSlot)) {
    $lockedBookingDate = $selectedBookingDate;
    $lockedTimeSlot = $selectedTimeSlot;
}

$calendarRows = db_fetch_all('SELECT pa.available_date, pa.status, pa.time_slot,
                              (SELECT b.status
                               FROM bookings b
                               WHERE b.photographer_id = pa.photographer_id
                                 AND b.booking_date = pa.available_date
                                 AND b.status IN ("pending","accepted","confirmed")
                                 AND b.deleted_at IS NULL
                                 AND (b.time_slot = pa.time_slot OR b.time_slot = "full_day" OR pa.time_slot = "full_day")
                               ORDER BY FIELD(b.status, "confirmed", "accepted", "pending")
                               LIMIT 1) AS booking_status
                              FROM photographer_availability pa
                              WHERE pa.photographer_id = ?
                                AND pa.available_date >= CURDATE()', [$photographerId]);
$GLOBALS['calendar_date_statuses']['booking_date'] = [];
$GLOBALS['calendar_date_default_status']['booking_date'] = 'unavailable';
$GLOBALS['calendar_date_selectable_statuses']['booking_date'] = ['available'];
$calendarStatusPriority = ['unavailable' => 0, 'available' => 1, 'pending' => 2, 'booked' => 3];
$availableSlotsByDate = [];

foreach ($calendarRows as $row) {
    $dateKey = (string)$row['available_date'];
    $statusKey = (string)$row['status'];
    if ($row['booking_status'] === 'pending') {
        $statusKey = 'pending';
    } elseif (in_array((string)$row['booking_status'], ['accepted', 'confirmed'], true)) {
        $statusKey = 'booked';
    }
    
    if ($statusKey === 'available') {
        if (!isset($availableSlotsByDate[$dateKey])) {
            $availableSlotsByDate[$dateKey] = [];
        }
        $availableSlotsByDate[$dateKey][] = (string)$row['time_slot'];
    }

    $currentStatus = $GLOBALS['calendar_date_statuses']['booking_date'][$dateKey] ?? 'unavailable';
    if (($calendarStatusPriority[$statusKey] ?? 0) >= ($calendarStatusPriority[$currentStatus] ?? 0)) {
        $GLOBALS['calendar_date_statuses']['booking_date'][$dateKey] = $statusKey;
    }
}
$GLOBALS['calendar_date_labels']['booking_date'] = 'วันที่ต้องการจ้าง';

function booking_field_error_class(string $field, array $errors): string
{
    if (!isset($errors[$field])) {
        return '';
    }

    return ' border-red-300 bg-red-50 ring-2 ring-red-100';
}

function booking_field_wrap_class(string $field, array $errors): string
{
    if (!isset($errors[$field])) {
        return '';
    }

    return ' booking-field-error rounded-[1.5rem] border border-red-200 bg-red-50/35 p-3';
}

function booking_field_error_html(string $field, array $errors): string
{
    if (!isset($errors[$field])) {
        return '';
    }

    return '<p class="mt-2 text-sm font-black text-red-600"><i class="fa-solid fa-circle-exclamation mr-1"></i>' . h($errors[$field]) . '</p>';
}

$bookingFormOld = [
    'category_id' => '',
    'district_id' => '',
    'booking_date' => $selectedBookingDate,
    'time_slot' => $selectedTimeSlot,
    'contact_name' => (string)($user['name'] ?? ''),
    'contact_phone' => (string)($user['phone'] ?? ''),
    'contact_channel' => '',
    'job_detail' => '',
    'note' => '',
];
$bookingFormErrors = [];

if (is_post()) {
    foreach ($bookingFormOld as $field => $defaultValue) {
        $bookingFormOld[$field] = (string)($_POST[$field] ?? $defaultValue);
    }

    verify_csrf();
    $categoryId = (int)$bookingFormOld['category_id'];
    $districtId = (int)$bookingFormOld['district_id'];
    $date = parse_be_date_to_iso($bookingFormOld['booking_date']);
    $slot = (string)$bookingFormOld['time_slot'];

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

    if (!$categoryAllowed) {
        $bookingFormErrors['category_id'] = 'กรุณาเลือกประเภทงาน';
    }

    if (!$districtAllowed) {
        $bookingFormErrors['district_id'] = 'กรุณาเลือกอำเภอที่ถ่ายงาน';
    }

    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $bookingFormErrors['booking_date'] = 'กรุณาเลือกวันที่ต้องการจ้าง';
    }

    if (!in_array($slot, ['morning','afternoon','evening','full_day'], true)) {
        $bookingFormErrors['time_slot'] = 'กรุณาเลือกช่วงเวลา';
    }

    if (!isset($bookingFormErrors['booking_date']) && !isset($bookingFormErrors['time_slot']) && !can_book_slot($photographerId, $date, $slot)) {
        $bookingFormErrors['booking_date'] = 'วันหรือช่วงเวลานี้ไม่พร้อมจอง กรุณาเลือกใหม่';
    }

    if (trim($bookingFormOld['contact_name']) === '') {
        $bookingFormErrors['contact_name'] = 'กรุณากรอกชื่อผู้ติดต่อ';
    }

    if (trim($bookingFormOld['contact_phone']) === '') {
        $bookingFormErrors['contact_phone'] = 'กรุณากรอกเบอร์โทรศัพท์';
    }

    if (trim($bookingFormOld['contact_channel']) === '') {
        $bookingFormErrors['contact_channel'] = 'กรุณากรอกช่องทางติดต่อกลับ';
    }

    if (trim($bookingFormOld['job_detail']) === '') {
        $bookingFormErrors['job_detail'] = 'กรุณากรอกรายละเอียดงาน';
    }

    if ($bookingFormErrors) {
        flash('error', 'กรุณากรอกข้อมูลสำคัญให้ครบ');
    } else {
        $code = generate_booking_code();
        db()->beginTransaction();
        $stmt = db()->prepare('INSERT INTO bookings (booking_code, customer_id, photographer_id, category_id, district_id, booking_date, time_slot, contact_name, contact_phone, contact_channel, job_detail, note, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", NOW(), NOW())');
        $stmt->execute([$code, (int)$user['id'], $photographerId, $categoryId, $districtId, $date, $slot, trim($bookingFormOld['contact_name']), trim($bookingFormOld['contact_phone']), trim($bookingFormOld['contact_channel']), trim($bookingFormOld['job_detail']), trim($bookingFormOld['note'])]);
        $bookingId = (int)db()->lastInsertId();
        add_booking_status_log($bookingId, null, 'pending', (int)$user['id'], 'สร้างคำขอจอง');
        notify_user((int)$profile['photographer_user_id'], 'มีคำขอจองใหม่', $code, 'booking', $bookingId);
        log_activity('create_booking', 'bookings', $bookingId);
        db()->commit();
        flash('success', 'ส่งคำขอจองแล้ว');
        clean_redirect('/customer/booking_detail.php', ['id' => $bookingId]);
    }
}

$bookingActorLabel = 'ลูกค้า';
if ($user && (string)$user['role_name'] === 'photographer') {
    $bookingActorLabel = 'ช่างภาพผู้จ้าง';
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
                    <?php if ($bookingActorLabel === 'ช่างภาพผู้จ้าง'): ?>
                        บัญชีช่างภาพสามารถส่งคำขอจองช่างภาพคนอื่นได้เหมือนลูกค้า
                    <?php endif; ?>
                </p>
            </div>
            <div class="rounded-[1.75rem] bg-white/12 p-5 backdrop-blur">
                <p class="text-sm font-black text-white/60"><i class="fa-solid fa-circle-info mr-2"></i>หมายเหตุสำคัญ</p>
                <p class="mt-2 text-sm font-bold leading-7 text-white/80"><?= h(PAYMENT_DISCLAIMER) ?></p>
            </div>
        </div>
    </div>

    <?php if ($bookingFormErrors): ?>
        <div class="mt-6 rounded-[1.5rem] border border-red-200 bg-red-50 p-5 text-red-700" data-booking-error-summary>
            <p class="text-lg font-black"><i class="fa-solid fa-circle-exclamation mr-2"></i>กรุณากรอกข้อมูลที่จำเป็นให้ครบ</p>
            <p class="mt-2 text-sm font-bold leading-7">ระบบเก็บข้อมูลที่กรอกไว้ให้แล้ว กรุณาตรวจช่องที่มีเครื่องหมาย <?= required_mark() ?> และข้อความสีแดง</p>
        </div>
    <?php endif; ?>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
        <form method="post" class="grid gap-6" novalidate data-booking-form>
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
                <label class="mt-5 block text-sm font-black text-neutral-700<?= h(booking_field_wrap_class('district_id', $bookingFormErrors)) ?>" for="district_id">
                    <i class="fa-solid fa-map-location-dot mr-2 text-red-600"></i>อำเภอที่ถ่ายงาน <?= required_mark() ?>
                </label>
                <select id="district_id" name="district_id" required class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold<?= h(booking_field_error_class('district_id', $bookingFormErrors)) ?>">
                    <option value="">เลือกอำเภอที่ต้องการจ้าง</option>
                    <?php foreach ($districts as $district): ?>
                        <option value="<?= (int)$district['id'] ?>" <?php if ((int)$bookingFormOld['district_id'] === (int)$district['id']): ?>selected<?php endif; ?>>
                            <?= h($district['district_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= booking_field_error_html('district_id', $bookingFormErrors) ?>
            </section>

            <section class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker"><i class="fa-solid fa-calendar-days mr-2"></i>วันที่ต้องการจ้าง</p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">เลือกประเภทงาน วันที่ และช่วงเวลา</h2>
                <p class="mt-2 text-base font-semibold leading-7 text-neutral-600">ระบบจะรับจองเฉพาะวันที่ช่างภาพเปิดว่างและยังไม่ถูกจองซ้ำ</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="block<?= h(booking_field_wrap_class('category_id', $bookingFormErrors)) ?>">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-layer-group mr-2 text-red-600"></i>ประเภทงาน <?= required_mark() ?></span>
                        <select name="category_id" required class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold<?= h(booking_field_error_class('category_id', $bookingFormErrors)) ?>">
                            <option value="">เลือกประเภทงาน</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>" <?php if ((int)$bookingFormOld['category_id'] === (int)$category['id']): ?>selected<?php endif; ?>>
                                    <?= h($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= booking_field_error_html('category_id', $bookingFormErrors) ?>
                    </label>

                    <?php if ($lockedTimeSlot !== ''): ?>
                        <div class="block">
                            <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-lock mr-2 text-red-600"></i>ช่วงเวลาที่เลือกไว้</span>
                            <input type="hidden" name="time_slot" value="<?= h($lockedTimeSlot) ?>">
                            <div class="mt-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 font-black text-emerald-700"><?= h(time_slot_label($lockedTimeSlot)) ?></div>
                        </div>
                    <?php else: ?>
                        <label class="block<?= h(booking_field_wrap_class('time_slot', $bookingFormErrors)) ?>">
                            <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-clock mr-2 text-red-600"></i>ช่วงเวลา <?= required_mark() ?></span>
                            <select name="time_slot" required class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold<?= h(booking_field_error_class('time_slot', $bookingFormErrors)) ?>">
                                <option value="">เลือกช่วงเวลา</option>
                                <option value="morning" <?php if ($bookingFormOld['time_slot'] === 'morning'): ?>selected<?php endif; ?>>เช้า</option>
                                <option value="afternoon" <?php if ($bookingFormOld['time_slot'] === 'afternoon'): ?>selected<?php endif; ?>>บ่าย</option>
                                <option value="evening" <?php if ($bookingFormOld['time_slot'] === 'evening'): ?>selected<?php endif; ?>>เย็น</option>
                                <option value="full_day" <?php if ($bookingFormOld['time_slot'] === 'full_day'): ?>selected<?php endif; ?>>เต็มวัน</option>
                            </select>
                            <?= booking_field_error_html('time_slot', $bookingFormErrors) ?>
                        </label>
                    <?php endif; ?>

                    <div class="sm:col-span-2<?= h(booking_field_wrap_class('booking_date', $bookingFormErrors)) ?>">
                        <label class="mb-2 block text-sm font-black text-neutral-700">
                            <i class="fa-solid fa-calendar-day mr-2 text-red-600"></i>วันที่ถ่ายงาน <?= required_mark() ?>
                        </label>
                        <?php if ($lockedBookingDate !== ''): ?>
                            <input type="hidden" name="booking_date" value="<?= h($lockedBookingDate) ?>">
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 font-black text-emerald-700">
                                <i class="fa-solid fa-lock mr-1"></i><?= h(format_be_date($lockedBookingDate)) ?>
                            </div>
                        <?php else: ?>
                            <?= be_date_input('booking_date', $bookingFormOld['booking_date'], 'stock-input rounded-2xl px-4 py-3 font-semibold' . booking_field_error_class('booking_date', $bookingFormErrors), true, 'วันที่ถ่าย พ.ศ. เช่น 05/05/2569') ?>
                        <?php endif; ?>
                        <?= booking_field_error_html('booking_date', $bookingFormErrors) ?>
                        <?php if ($lockedBookingDate !== ''): ?>
                            <p class="mt-2 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-black text-emerald-700">
                                <i class="fa-solid fa-circle-check mr-1"></i>ล็อกวันที่และช่วงเวลาจากหน้าโปรไฟล์แล้ว: <?= h(format_be_date($lockedBookingDate)) ?> · <?= h(time_slot_label($lockedTimeSlot)) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker"><i class="fa-solid fa-address-book mr-2"></i>ช่องทางติดต่อ</p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">ข้อมูลสำหรับให้ช่างภาพติดต่อกลับ</h2>
                <p class="mt-2 text-base font-semibold leading-7 text-neutral-600">ผู้ส่งคำขอนี้คือ <?= h($bookingActorLabel) ?> ระบบจะบันทึกคำขอนี้ในเมนูงานที่ฉันจ้าง</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="block<?= h(booking_field_wrap_class('contact_name', $bookingFormErrors)) ?>">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-user mr-2 text-red-600"></i>ชื่อผู้ติดต่อ <?= required_mark() ?></span>
                        <input name="contact_name" value="<?= h($bookingFormOld['contact_name']) ?>" required placeholder="ชื่อผู้ติดต่อ" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold<?= h(booking_field_error_class('contact_name', $bookingFormErrors)) ?>">
                        <?= booking_field_error_html('contact_name', $bookingFormErrors) ?>
                    </label>
                    <label class="block<?= h(booking_field_wrap_class('contact_phone', $bookingFormErrors)) ?>">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-phone mr-2 text-red-600"></i>เบอร์โทรศัพท์ <?= required_mark() ?></span>
                        <input name="contact_phone" value="<?= h($bookingFormOld['contact_phone']) ?>" required placeholder="เบอร์โทรศัพท์" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold<?= h(booking_field_error_class('contact_phone', $bookingFormErrors)) ?>">
                        <?= booking_field_error_html('contact_phone', $bookingFormErrors) ?>
                    </label>
                    <label class="block sm:col-span-2<?= h(booking_field_wrap_class('contact_channel', $bookingFormErrors)) ?>">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-comments mr-2 text-red-600"></i>ช่องทางติดต่อกลับ <?= required_mark() ?></span>
                        <input name="contact_channel" value="<?= h($bookingFormOld['contact_channel']) ?>" required placeholder="เช่น LINE: yourline หรือ Facebook: profile link" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold<?= h(booking_field_error_class('contact_channel', $bookingFormErrors)) ?>">
                        <?= booking_field_error_html('contact_channel', $bookingFormErrors) ?>
                    </label>
                </div>
            </section>

            <section class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker"><i class="fa-solid fa-clipboard-list mr-2"></i>รายละเอียดคำขอจอง</p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">เล่ารายละเอียดงานให้ช่างภาพเข้าใจ</h2>
                <div class="mt-5 grid gap-4">
                    <label class="block<?= h(booking_field_wrap_class('job_detail', $bookingFormErrors)) ?>">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-camera mr-2 text-red-600"></i>รายละเอียดงาน <?= required_mark() ?></span>
                        <textarea name="job_detail" required rows="5" placeholder="เช่น ถ่ายรับปริญญา 2 คน สถานที่ มฟล. อยากได้โทนสดใส มีรูปครอบครัว และต้องการไฟล์หลังแต่งสี" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold<?= h(booking_field_error_class('job_detail', $bookingFormErrors)) ?>"><?= h($bookingFormOld['job_detail']) ?></textarea>
                        <?= booking_field_error_html('job_detail', $bookingFormErrors) ?>
                    </label>
                    <label class="block">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-note-sticky mr-2 text-red-600"></i>หมายเหตุเพิ่มเติม</span>
                        <textarea name="note" rows="3" placeholder="เช่น ต้องการคุยรายละเอียดเพิ่มเติมก่อนยืนยันงาน" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold"><?= h($bookingFormOld['note']) ?></textarea>
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
<?php if ($bookingFormErrors): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var firstError = document.querySelector('.booking-field-error');
    if (!firstError) {
        firstError = document.querySelector('[data-booking-error-summary]');
    }
    if (firstError) {
        setTimeout(function () {
            firstError.scrollIntoView({behavior: 'smooth', block: 'center'});
            var focusable = firstError.querySelector('input, select, textarea, button');
            if (!focusable && firstError.nextElementSibling && firstError.nextElementSibling.matches('input, select, textarea, button')) {
                focusable = firstError.nextElementSibling;
            }
            if (focusable && typeof focusable.focus === 'function') {
                focusable.focus({preventScroll: true});
            }
        }, 450);
    }
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const availableSlotsByDate = <?= json_encode($availableSlotsByDate, JSON_UNESCAPED_UNICODE) ?>;
    const timeSlotSelect = document.querySelector('select[name="time_slot"]');
    const bookingDateInputs = document.querySelectorAll('input[name="booking_date"]');

    function updateAvailableSlots(selectedDate) {
        if (!timeSlotSelect) return;
        
        let slots = [];
        if (selectedDate && availableSlotsByDate[selectedDate]) {
            slots = availableSlotsByDate[selectedDate];
        }
        
        const options = timeSlotSelect.options;
        for (let i = 0; i < options.length; i++) {
            const opt = options[i];
            if (opt.value === "") continue;
            
            if (selectedDate) {
                if (slots.includes(opt.value)) {
                    opt.style.display = '';
                    opt.disabled = false;
                } else {
                    opt.style.display = 'none';
                    opt.disabled = true;
                }
            } else {
                opt.style.display = '';
                opt.disabled = false;
            }
        }
        
        if (timeSlotSelect.value && timeSlotSelect.selectedOptions[0] && timeSlotSelect.selectedOptions[0].disabled) {
            timeSlotSelect.value = "";
        }
    }

    bookingDateInputs.forEach(input => {
        input.addEventListener('change', (e) => {
            updateAvailableSlots(e.target.value);
        });
    });

    // Initialize on load
    const activeDateInput = document.querySelector('input[name="booking_date"]');
    if (activeDateInput && activeDateInput.value) {
        updateAvailableSlots(activeDateInput.value);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

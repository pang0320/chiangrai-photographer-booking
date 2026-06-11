<?php
require_once __DIR__ . '/../includes/functions.php';

ensure_service_categories_deleted_at_column();
ensure_booking_range_columns();
ensure_availability_range_columns();

$cleanContext = clean_context_init(['photographer_id', 'booking_date', 'time_slot', 'start_date', 'end_date', 'start_time', 'end_time']);
requireRole(['customer', 'photographer']);

$user = current_user();
$photographerId = (int)clean_context_value($cleanContext, 'photographer_id', ($_POST['photographer_id'] ?? 0));
$selectedBookingDate = parse_be_date_to_iso((string)clean_context_value($cleanContext, 'booking_date', ''));
$selectedTimeSlot = (string)clean_context_value($cleanContext, 'time_slot', '');
$selectedStartDate = parse_be_date_to_iso((string)clean_context_value($cleanContext, 'start_date', $selectedBookingDate));
$selectedEndDate = parse_be_date_to_iso((string)clean_context_value($cleanContext, 'end_date', $selectedStartDate));
$selectedStartTime = normalize_time_input((string)clean_context_value($cleanContext, 'start_time', ''));
$selectedEndTime = normalize_time_input((string)clean_context_value($cleanContext, 'end_time', ''));

if (in_array($selectedTimeSlot, ['morning', 'afternoon', 'evening', 'full_day'], true)) {
    [$slotStartTime, $slotEndTime] = slot_time_range($selectedTimeSlot);
    if ($selectedStartTime === '') {
        $selectedStartTime = $slotStartTime;
    }
    if ($selectedEndTime === '') {
        $selectedEndTime = $slotEndTime;
    }
}

if ($selectedStartTime === '') {
    $selectedStartTime = '09:00';
}
if ($selectedEndTime === '') {
    $selectedEndTime = '17:00';
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

if (!$profile) {
    exit('ช่างภาพไม่พร้อมรับจอง');
}

if ($user && (string)$user['role_name'] === 'photographer' && (int)$profile['photographer_user_id'] === (int)$user['id']) {
    flash('warning', 'ไม่สามารถส่งคำขอจองให้โปรไฟล์ช่างภาพของตัวเองได้');
    clean_redirect('/photographers.php', []);
}

$categoriesStmt = db()->prepare('SELECT sc.*
                                 FROM photographer_services ps
                                 JOIN service_categories sc ON sc.id = ps.category_id
                                 WHERE ps.photographer_id = ?
                                   AND ps.is_active = 1
                                   AND sc.is_active = 1
                                   AND sc.deleted_at IS NULL
                                 ORDER BY sc.sort_order, sc.name');
$categoriesStmt->execute([$photographerId]);
$categories = $categoriesStmt->fetchAll();

if (!$categories) {
    $categoriesStmt = db()->prepare('SELECT DISTINCT sc.*
                                     FROM photographer_services ps
                                     JOIN service_categories sc ON sc.id = ps.category_id
                                     WHERE ps.photographer_id = ?
                                       AND sc.is_active = 1
                                       AND sc.deleted_at IS NULL
                                     ORDER BY sc.sort_order, sc.name');
    $categoriesStmt->execute([$photographerId]);
    $categories = $categoriesStmt->fetchAll();
}

if (!$categories) {
    $categories = db_fetch_all('SELECT *
                                FROM service_categories
                                WHERE is_active = 1
                                  AND deleted_at IS NULL
                                ORDER BY sort_order, name');
}

$districtsStmt = db()->prepare('SELECT d.*
                                FROM photographer_service_areas psa
                                JOIN districts d ON d.id = psa.district_id
                                WHERE psa.photographer_id = ?
                                  AND psa.is_active = 1
                                  AND d.is_active = 1
                                ORDER BY psa.is_primary DESC, d.district_name');
$districtsStmt->execute([$photographerId]);
$districts = $districtsStmt->fetchAll();

$availabilityRows = db_fetch_all('SELECT id,
                                         COALESCE(start_date, available_date) AS start_date,
                                         COALESCE(end_date, available_date) AS end_date,
                                         COALESCE(start_time, CASE time_slot WHEN "morning" THEN "09:00:00" WHEN "afternoon" THEN "13:00:00" WHEN "evening" THEN "17:00:00" ELSE "09:00:00" END) AS start_time,
                                         COALESCE(end_time, CASE time_slot WHEN "morning" THEN "12:00:00" WHEN "afternoon" THEN "17:00:00" WHEN "evening" THEN "20:00:00" ELSE "17:00:00" END) AS end_time,
                                         note
                                  FROM photographer_availability
                                  WHERE photographer_id = ?
                                    AND status = "available"
                                    AND COALESCE(end_date, available_date) >= CURDATE()
                                  ORDER BY COALESCE(start_date, available_date) ASC, COALESCE(start_time, "09:00:00") ASC
                                  LIMIT 12', [$photographerId]);

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

function booking_time_picker_input(string $name, string $value, array $errors = []): string
{
    $value = normalize_time_input($value);
    if ($value === '') {
        if ($name === 'end_time') {
            $value = '17:00';
        } else {
            $value = '09:00';
        }
    }

    $id = 'booking_time_picker_' . bin2hex(random_bytes(4));
    $options = [];

    for ($hour = 6; $hour <= 22; $hour++) {
        foreach ([0, 30] as $minute) {
            if ($hour === 22 && $minute === 30) {
                continue;
            }

            $options[] = sprintf('%02d:%02d', $hour, $minute);
        }
    }

    $buttonClass = 'stock-input flex w-full items-center justify-between rounded-2xl px-4 py-3 text-left font-semibold' . booking_field_error_class($name, $errors);
    $html = '<div class="relative" data-booking-time-picker data-target="' . h($id) . '">';
    $html .= '<input type="hidden" id="' . h($id) . '" name="' . h($name) . '" value="' . h($value) . '" required>';
    $html .= '<button type="button" class="' . h($buttonClass) . '" data-booking-time-picker-trigger>';
    $html .= '<span><i class="fa-solid fa-clock mr-2 text-red-600"></i><span data-booking-time-picker-label>' . h($value) . '</span> น.</span>';
    $html .= '<i class="fa-solid fa-chevron-down text-neutral-400"></i>';
    $html .= '</button>';
    $html .= '<div class="time-picker-popover hidden absolute left-0 top-[calc(100%+.65rem)] z-[280] max-h-72 w-full overflow-y-auto rounded-[1.25rem] border border-neutral-200 bg-white p-2 shadow-2xl" data-booking-time-picker-popover>';

    foreach ($options as $option) {
        $activeClass = $option === $value ? ' bg-neutral-950 text-white' : ' bg-neutral-50 text-neutral-800 hover:bg-red-50 hover:text-red-700';
        $html .= '<button type="button" data-time-value="' . h($option) . '" class="mb-1 flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm font-black transition' . h($activeClass) . '"><i class="fa-solid fa-clock"></i>' . h($option) . ' น.</button>';
    }

    $html .= '</div></div>';

    return $html;
}

$bookingFormOld = [
    'category_id' => '',
    'district_id' => '',
    'start_date' => $selectedStartDate,
    'end_date' => $selectedEndDate,
    'start_time' => $selectedStartTime,
    'end_time' => $selectedEndTime,
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
    $startDate = parse_be_date_to_iso($bookingFormOld['start_date']);
    $endDate = parse_be_date_to_iso($bookingFormOld['end_date']);
    $startTime = normalize_time_input($bookingFormOld['start_time']);
    $endTime = normalize_time_input($bookingFormOld['end_time']);

    $bookingFormOld['start_date'] = $startDate;
    $bookingFormOld['end_date'] = $endDate;
    $bookingFormOld['start_time'] = $startTime;
    $bookingFormOld['end_time'] = $endTime;

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
        $bookingFormErrors['category_id'] = 'กรุณาเลือกประเภทงานถ่ายภาพที่ช่างภาพระบุไว้';
    }

    if (!$districtAllowed) {
        $bookingFormErrors['district_id'] = 'กรุณาเลือกอำเภอที่ช่างภาพเปิดรับงาน';
    }

    if ($startDate === '') {
        $bookingFormErrors['start_date'] = 'กรุณาเลือกวันที่เริ่มต้น';
    } elseif ($startDate < date('Y-m-d')) {
        $bookingFormErrors['start_date'] = 'ไม่สามารถจองย้อนหลังได้';
    }

    if ($endDate === '') {
        $bookingFormErrors['end_date'] = 'กรุณาเลือกวันที่สิ้นสุด';
    } elseif ($startDate !== '' && $endDate < $startDate) {
        $bookingFormErrors['end_date'] = 'วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่มต้น';
    }

    if ($startTime === '') {
        $bookingFormErrors['start_time'] = 'กรุณาเลือกเวลาเริ่มต้น';
    }

    if ($endTime === '') {
        $bookingFormErrors['end_time'] = 'กรุณาเลือกเวลาสิ้นสุด';
    } elseif ($startTime !== '' && $endTime <= $startTime) {
        $bookingFormErrors['end_time'] = 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น';
    }

    if (
        !isset($bookingFormErrors['start_date']) &&
        !isset($bookingFormErrors['end_date']) &&
        !isset($bookingFormErrors['start_time']) &&
        !isset($bookingFormErrors['end_time']) &&
        !can_book_range($photographerId, $startDate, $endDate, $startTime, $endTime)
    ) {
        $bookingFormErrors['start_date'] = 'ช่วงวัน/เวลานี้ไม่พร้อมจอง หรือมีงานอื่นชนอยู่แล้ว';
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
        $legacySlot = 'full_day';
        foreach (['morning', 'afternoon', 'evening', 'full_day'] as $slotName) {
            [$slotStart, $slotEnd] = slot_time_range($slotName);
            if ($slotStart === $startTime && $slotEnd === $endTime) {
                $legacySlot = $slotName;
                break;
            }
        }

        db()->beginTransaction();
        $stmt = db()->prepare('INSERT INTO bookings (
            booking_code, customer_id, photographer_id, category_id, district_id,
            booking_date, time_slot, start_date, end_date, start_time, end_time,
            contact_name, contact_phone, contact_channel, job_detail, note,
            status, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", NOW(), NOW())');
        $stmt->execute([
            $code,
            (int)$user['id'],
            $photographerId,
            $categoryId,
            $districtId,
            $startDate,
            $legacySlot,
            $startDate,
            $endDate,
            $startTime . ':00',
            $endTime . ':00',
            trim($bookingFormOld['contact_name']),
            trim($bookingFormOld['contact_phone']),
            trim($bookingFormOld['contact_channel']),
            trim($bookingFormOld['job_detail']),
            trim($bookingFormOld['note']),
        ]);
        $bookingId = (int)db()->lastInsertId();

        add_booking_status_log($bookingId, null, 'pending', (int)$user['id'], 'สร้างคำขอจอง');
        split_availability_after_booking_request($photographerId, $startDate, $endDate, $startTime, $endTime);
        notify_user((int)$profile['photographer_user_id'], 'มีคำขอจองใหม่', $code . ' · ' . booking_range_label([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]), 'booking', $bookingId);
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
                <h1 class="mt-2 text-3xl font-black md:text-4xl">ส่งคำขอจ้างช่างภาพ</h1>
                <p class="mt-3 max-w-3xl text-base font-semibold leading-8 text-white/75 md:text-lg">
                    เลือกประเภทงานจากความเชี่ยวชาญของช่างภาพ กำหนดช่วงวันที่และช่วงเวลาที่ต้องการจ้าง แล้วส่งคำขอให้ช่างภาพพิจารณา
                    <?php if ($bookingActorLabel === 'ช่างภาพผู้จ้าง'): ?>
                        บัญชีช่างภาพสามารถจ้างช่างภาพคนอื่นได้เหมือนลูกค้า
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

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]">
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
                <p class="section-kicker"><i class="fa-solid fa-calendar-days mr-2"></i>ช่วงวันที่และเวลา</p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">เลือกประเภทงาน วันที่ และเวลาที่ต้องการจ้าง</h2>
                <p class="mt-2 text-base font-semibold leading-7 text-neutral-600">ถ้าเลือกหลายวัน ระบบจะใช้ช่วงเวลาเดียวกันกับทุกวันในช่วงนั้น</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="block sm:col-span-2<?= h(booking_field_wrap_class('category_id', $bookingFormErrors)) ?>">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-layer-group mr-2 text-red-600"></i>ประเภทงานถ่ายภาพ <?= required_mark() ?></span>
                        <select name="category_id" required class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold<?= h(booking_field_error_class('category_id', $bookingFormErrors)) ?>">
                            <option value="">เลือกประเภทงานจากความเชี่ยวชาญของช่างภาพ</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>" <?php if ((int)$bookingFormOld['category_id'] === (int)$category['id']): ?>selected<?php endif; ?>>
                                    <?= h($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= booking_field_error_html('category_id', $bookingFormErrors) ?>
                    </label>

                    <div class="block sm:col-span-2<?= h(booking_field_wrap_class('start_date', $bookingFormErrors) ?: booking_field_wrap_class('end_date', $bookingFormErrors)) ?>">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-calendar-days mr-2 text-red-600"></i>ช่วงวันที่ต้องการจ้าง <?= required_mark() ?></span>
                        <div class="mt-2">
                            <?= calendar_date_range_input('start_date', 'end_date', $bookingFormOld['start_date'], $bookingFormOld['end_date'], 'เลือกช่วงวันที่ต้องการจ้าง', true) ?>
                        </div>
                        <p class="mt-2 text-sm font-bold leading-6 text-neutral-500">
                            <i class="fa-solid fa-circle-info mr-1 text-red-500"></i>
                            เลือกวันแรกเป็นวันที่เริ่มต้น แล้วเลือกอีกวันเป็นวันที่สิ้นสุด ถ้าจ้างวันเดียวให้เลือกวันเดียวกัน 2 ครั้ง
                        </p>
                        <?= booking_field_error_html('start_date', $bookingFormErrors) ?>
                        <?= booking_field_error_html('end_date', $bookingFormErrors) ?>
                    </div>

                    <label class="block<?= h(booking_field_wrap_class('start_time', $bookingFormErrors)) ?>">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-clock mr-2 text-red-600"></i>เวลาเริ่มต้น <?= required_mark() ?></span>
                        <div class="mt-2">
                            <?= booking_time_picker_input('start_time', $bookingFormOld['start_time'], $bookingFormErrors) ?>
                        </div>
                        <?= booking_field_error_html('start_time', $bookingFormErrors) ?>
                    </label>

                    <label class="block<?= h(booking_field_wrap_class('end_time', $bookingFormErrors)) ?>">
                        <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-clock mr-2 text-red-600"></i>เวลาสิ้นสุด <?= required_mark() ?></span>
                        <div class="mt-2">
                            <?= booking_time_picker_input('end_time', $bookingFormOld['end_time'], $bookingFormErrors) ?>
                        </div>
                        <?= booking_field_error_html('end_time', $bookingFormErrors) ?>
                    </label>
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
                <p class="section-kicker"><i class="fa-solid fa-calendar-days mr-2"></i>วันว่างที่เปิดรับ</p>
                <div class="mt-4 grid gap-3">
                    <?php if ($availabilityRows): ?>
                        <?php foreach ($availabilityRows as $row): ?>
                            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-sm font-black text-emerald-800">
                                <p><i class="fa-solid fa-calendar-check mr-2"></i><?= h(format_booking_date_range($row['start_date'], $row['end_date'])) ?></p>
                                <p class="mt-1 text-emerald-700"><i class="fa-solid fa-clock mr-2"></i><?= h(format_booking_time_range($row['start_time'], $row['end_time'])) ?></p>
                                <?php if (!empty($row['note'])): ?>
                                    <p class="mt-1 text-xs font-bold leading-5 text-emerald-700"><?= h($row['note']) ?></p>
                                <?php endif; ?>
                                <button type="button"
                                        class="mt-3 inline-flex items-center gap-2 rounded-full bg-emerald-600 px-4 py-2 text-xs font-black text-white shadow-lg shadow-emerald-200 transition hover:-translate-y-0.5 hover:bg-emerald-700"
                                        data-use-availability
                                        data-start-date="<?= h((string)$row['start_date']) ?>"
                                        data-end-date="<?= h((string)$row['end_date']) ?>"
                                        data-start-time="<?= h(format_time_hm((string)$row['start_time'])) ?>"
                                        data-end-time="<?= h(format_time_hm((string)$row['end_time'])) ?>"
                                        data-date-label="<?= h(format_booking_date_range($row['start_date'], $row['end_date'])) ?>">
                                    <i class="fa-solid fa-check"></i>เลือกช่วงนี้
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state rounded-[1.5rem] p-6 text-center">
                            <i class="fa-solid fa-calendar-xmark text-3xl text-red-600"></i>
                            <p class="mt-2 font-black text-neutral-950">ยังไม่มีวันว่าง</p>
                            <p class="mt-1 text-sm font-bold text-neutral-500">ช่างภาพยังไม่ได้เปิดช่วงวันที่รับงาน</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker"><i class="fa-solid fa-list-check mr-2"></i>กติกาการจอง</p>
                <div class="mt-5 grid gap-3">
                    <?php foreach ([['เลือกวันเริ่มต้นและสิ้นสุดได้', 'fa-calendar-days'], ['เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น', 'fa-clock'], ['ระบบกันจองซ้อนอัตโนมัติ', 'fa-shield-halved'], ['ไม่มีการรับชำระเงินในเว็บไซต์', 'fa-ban']] as $step): ?>
                        <div class="flex items-center gap-3 rounded-2xl bg-neutral-50 px-4 py-3 font-black text-neutral-700">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-red-50 text-red-600"><i class="fa-solid <?= h($step[1]) ?>"></i></span>
                            <?= h($step[0]) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function setBookingTimePickerValue(name, value) {
        var hidden = document.querySelector('input[type="hidden"][name="' + name + '"]');
        if (!hidden) {
            return;
        }

        hidden.value = value || '';
        hidden.dispatchEvent(new Event('change', { bubbles: true }));

        var picker = hidden.closest('[data-booking-time-picker]');
        if (!picker) {
            return;
        }

        var label = picker.querySelector('[data-booking-time-picker-label]');
        var popover = picker.querySelector('[data-booking-time-picker-popover]');
        if (label) {
            label.textContent = hidden.value;
        }

        if (popover) {
            popover.querySelectorAll('[data-time-value]').forEach(function (item) {
                item.classList.remove('bg-neutral-950', 'text-white');
                item.classList.add('bg-neutral-50', 'text-neutral-800');
                if (item.dataset.timeValue === hidden.value) {
                    item.classList.add('bg-neutral-950', 'text-white');
                    item.classList.remove('bg-neutral-50', 'text-neutral-800');
                }
            });
        }
    }

    document.querySelectorAll('[data-booking-time-picker]').forEach(function (picker) {
        var hidden = document.getElementById(picker.dataset.target || '');
        var trigger = picker.querySelector('[data-booking-time-picker-trigger]');
        var popover = picker.querySelector('[data-booking-time-picker-popover]');
        var label = picker.querySelector('[data-booking-time-picker-label]');

        if (!hidden || !trigger || !popover || !label) {
            return;
        }

        trigger.addEventListener('click', function (event) {
            event.stopPropagation();
            document.querySelectorAll('[data-booking-time-picker-popover]').forEach(function (item) {
                if (item !== popover) {
                    item.classList.add('hidden');
                }
            });
            document.querySelectorAll('[data-booking-time-picker]').forEach(function (p) {
                if (p !== picker) {
                    var els = [p, p.closest('label'), p.closest('.block'), p.closest('.grid'), p.closest('.stock-card')];
                    els.forEach(function(el) {
                        if (el) {
                            el.style.zIndex = '';
                            if (el.tagName === 'LABEL' || el.classList.contains('block') || el.classList.contains('grid') || el.classList.contains('stock-card')) {
                                el.style.position = '';
                            }
                        }
                    });
                }
            });
            popover.classList.toggle('hidden');
            var els = [picker, picker.closest('label'), picker.closest('.block'), picker.closest('.grid'), picker.closest('.stock-card')];
            if (!popover.classList.contains('hidden')) {
                els.forEach(function(el) {
                    if (el) {
                        if (el.tagName === 'LABEL' || el.classList.contains('block') || el.classList.contains('grid') || el.classList.contains('stock-card')) {
                            el.style.position = 'relative';
                        }
                        el.style.zIndex = '50';
                    }
                });
            } else {
                els.forEach(function(el) {
                    if (el) {
                        el.style.zIndex = '';
                        if (el.tagName === 'LABEL' || el.classList.contains('block') || el.classList.contains('grid') || el.classList.contains('stock-card')) {
                            el.style.position = '';
                        }
                    }
                });
            }
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
                var els = [picker, picker.closest('label'), picker.closest('.block'), picker.closest('.grid'), picker.closest('.stock-card')];
                els.forEach(function(el) {
                    if (el) {
                        el.style.zIndex = '';
                        if (el.tagName === 'LABEL' || el.classList.contains('block') || el.classList.contains('grid') || el.classList.contains('stock-card')) {
                            el.style.position = '';
                        }
                    }
                });
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    });

    document.querySelectorAll('[data-use-availability]').forEach(function (button) {
        button.addEventListener('click', function () {
            var calendar = document.querySelector('[data-booking-form] [data-calendar-range]');
            if (calendar) {
                calendar.dispatchEvent(new CustomEvent('calendarRangeSet', {
                    detail: {
                        start: button.dataset.startDate || '',
                        end: button.dataset.endDate || button.dataset.startDate || ''
                    }
                }));
            }

            setBookingTimePickerValue('start_time', button.dataset.startTime || '09:00');
            setBookingTimePickerValue('end_time', button.dataset.endTime || '17:00');

            var rangeBlock = calendar ? calendar.closest('.block') : null;
            if (rangeBlock) {
                rangeBlock.scrollIntoView({ behavior: 'smooth', block: 'center' });
                rangeBlock.classList.add('ring-4', 'ring-emerald-100');
                setTimeout(function () {
                    rangeBlock.classList.remove('ring-4', 'ring-emerald-100');
                }, 1400);
            }
        });
    });

    document.addEventListener('click', function () {
        document.querySelectorAll('[data-booking-time-picker-popover]').forEach(function (popover) {
            popover.classList.add('hidden');
        });
        document.querySelectorAll('[data-booking-time-picker]').forEach(function (p) {
            var els = [p, p.closest('label'), p.closest('.block'), p.closest('.grid'), p.closest('.stock-card')];
            els.forEach(function(el) {
                if (el) {
                    el.style.zIndex = '';
                    if (el.tagName === 'LABEL' || el.classList.contains('block') || el.classList.contains('grid') || el.classList.contains('stock-card')) {
                        el.style.position = '';
                    }
                }
            });
        });
    });
});
</script>

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
            if (focusable && typeof focusable.focus === 'function') {
                focusable.focus({preventScroll: true});
            }
        }, 450);
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

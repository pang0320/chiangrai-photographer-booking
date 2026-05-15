<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');

$user = current_user();
$profile = photographer_profile_by_user((int)$user['id']);

if (!$profile) {
    exit('Profile not found');
}

$pid = (int)$profile['id'];

$stats = [
    'total_bookings' => 0,
    'pending' => 0,
    'accepted' => 0,
    'confirmed' => 0,
    'active' => 0,
    'completed' => 0,
    'rejected' => 0,
    'cancelled' => 0,
    'won_jobs' => 0,
    'this_month' => 0,
    'profile_views' => (int)$profile['profile_views'],
    'average_rating' => round((float)$profile['average_rating'], 1),
    'total_reviews' => (int)$profile['total_reviews'],
    'available_slots' => 0,
    'portfolio' => 0,
];

$statusRows = db_fetch_all('SELECT status, COUNT(*) AS total
                            FROM bookings
                            WHERE photographer_id = ? AND deleted_at IS NULL
                            GROUP BY status', [$pid]);
foreach ($statusRows as $row) {
    $status = (string)$row['status'];
    $total = (int)$row['total'];
    $stats['total_bookings'] += $total;
    if (isset($stats[$status])) {
        $stats[$status] = $total;
    }
}

$stats['active'] = $stats['pending']
    + (int)db_fetch_value('SELECT COUNT(*) FROM bookings WHERE photographer_id = ? AND status IN ("accepted","confirmed") AND deleted_at IS NULL', [$pid]);
$stats['won_jobs'] = (int)db_fetch_value('SELECT COUNT(*)
                                          FROM bookings
                                          WHERE photographer_id = ?
                                            AND status IN ("accepted","confirmed","completed")
                                            AND deleted_at IS NULL', [$pid]);
$stats['this_month'] = (int)db_fetch_value('SELECT COUNT(*)
                                            FROM bookings
                                            WHERE photographer_id = ?
                                              AND created_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")
                                              AND deleted_at IS NULL', [$pid]);
$stats['available_slots'] = (int)db_fetch_value('SELECT COUNT(*)
                                                 FROM photographer_availability
                                                 WHERE photographer_id = ?
                                                   AND available_date >= CURDATE()
                                                   AND status = "available"', [$pid]);
$stats['portfolio'] = (int)db_fetch_value('SELECT COUNT(*) FROM photographer_portfolios WHERE photographer_id = ? AND deleted_at IS NULL', [$pid]);

$wonRate = 0;
if ($stats['total_bookings'] > 0) {
    $wonRate = round(($stats['won_jobs'] / $stats['total_bookings']) * 100);
}

$completionRate = 0;
if ($stats['total_bookings'] > 0) {
    $completionRate = round(($stats['completed'] / $stats['total_bookings']) * 100);
}

$responseRate = isset($profile['response_rate']) ? (float)$profile['response_rate'] : 0;
$averageResponseHours = isset($profile['average_response_hours']) ? (float)$profile['average_response_hours'] : 0;

$thaiMonths = [
    1 => 'ม.ค.',
    2 => 'ก.พ.',
    3 => 'มี.ค.',
    4 => 'เม.ย.',
    5 => 'พ.ค.',
    6 => 'มิ.ย.',
    7 => 'ก.ค.',
    8 => 'ส.ค.',
    9 => 'ก.ย.',
    10 => 'ต.ค.',
    11 => 'พ.ย.',
    12 => 'ธ.ค.',
];

$monthlyRows = db_fetch_all('SELECT DATE_FORMAT(created_at, "%Y-%m") AS ym,
                                    COUNT(*) AS total,
                                    SUM(CASE WHEN status IN ("accepted","confirmed","completed") THEN 1 ELSE 0 END) AS won_total,
                                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS completed_total
                             FROM bookings
                             WHERE photographer_id = ?
                               AND deleted_at IS NULL
                               AND created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
                             GROUP BY ym
                             ORDER BY ym ASC', [$pid]);
$monthlyMap = [];
foreach ($monthlyRows as $row) {
    $monthlyMap[(string)$row['ym']] = [
        'total' => (int)$row['total'],
        'won_total' => (int)$row['won_total'],
        'completed_total' => (int)$row['completed_total'],
    ];
}

$monthlyChart = [];
$maxMonthly = 1;
for ($i = 11; $i >= 0; $i--) {
    $date = new DateTime('first day of this month');
    if ($i > 0) {
        $date->modify('-' . $i . ' months');
    }
    $key = $date->format('Y-m');
    $monthNumber = (int)$date->format('n');
    $yearShort = substr((string)((int)$date->format('Y') + 543), -2);
    $values = $monthlyMap[$key] ?? ['total' => 0, 'won_total' => 0, 'completed_total' => 0];
    if ($values['total'] > $maxMonthly) {
        $maxMonthly = $values['total'];
    }
    $monthlyChart[] = [
        'label' => $thaiMonths[$monthNumber] . ' ' . $yearShort,
        'total' => $values['total'],
        'won_total' => $values['won_total'],
        'completed_total' => $values['completed_total'],
    ];
}

$categoryRows = db_fetch_all('SELECT sc.name, COUNT(b.id) AS total
                              FROM bookings b
                              JOIN service_categories sc ON sc.id = b.category_id
                              WHERE b.photographer_id = ? AND b.deleted_at IS NULL
                              GROUP BY sc.id, sc.name
                              ORDER BY total DESC, sc.sort_order ASC
                              LIMIT 6', [$pid]);
$maxCategory = 1;
foreach ($categoryRows as $row) {
    if ((int)$row['total'] > $maxCategory) {
        $maxCategory = (int)$row['total'];
    }
}

$districtRows = db_fetch_all('SELECT d.district_name, COUNT(b.id) AS total
                              FROM bookings b
                              JOIN districts d ON d.id = b.district_id
                              WHERE b.photographer_id = ? AND b.deleted_at IS NULL
                              GROUP BY d.id, d.district_name
                              ORDER BY total DESC, d.district_name ASC
                              LIMIT 6', [$pid]);
$maxDistrict = 1;
foreach ($districtRows as $row) {
    if ((int)$row['total'] > $maxDistrict) {
        $maxDistrict = (int)$row['total'];
    }
}

$ratingSummary = db_fetch_all('SELECT AVG(rating_quality) AS quality,
                                      AVG(rating_communication) AS communication,
                                      AVG(rating_punctuality) AS punctuality,
                                      AVG(rating_professional) AS professional
                               FROM reviews
                               WHERE photographer_id = ?
                                 AND status = "visible"
                                 AND deleted_at IS NULL', [$pid]);
$ratingBreakdown = [
    ['คุณภาพงาน', 0, 'fa-wand-magic-sparkles'],
    ['การสื่อสาร', 0, 'fa-comments'],
    ['ตรงเวลา', 0, 'fa-clock'],
    ['มืออาชีพ', 0, 'fa-user-tie'],
];
if ($ratingSummary) {
    $ratingBreakdown = [
        ['คุณภาพงาน', round((float)$ratingSummary[0]['quality'], 1), 'fa-wand-magic-sparkles'],
        ['การสื่อสาร', round((float)$ratingSummary[0]['communication'], 1), 'fa-comments'],
        ['ตรงเวลา', round((float)$ratingSummary[0]['punctuality'], 1), 'fa-clock'],
        ['มืออาชีพ', round((float)$ratingSummary[0]['professional'], 1), 'fa-user-tie'],
    ];
}

$recentBookings = db_fetch_all('SELECT b.*, u.name AS customer_name, sc.name AS category_name, d.district_name
                                FROM bookings b
                                JOIN users u ON u.id = b.customer_id
                                JOIN service_categories sc ON sc.id = b.category_id
                                JOIN districts d ON d.id = b.district_id
                                WHERE b.photographer_id = ? AND b.deleted_at IS NULL
                                ORDER BY b.created_at DESC
                                LIMIT 6', [$pid]);

$pageTitle = 'วิเคราะห์ผลงานช่างภาพ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="overflow-hidden rounded-[2rem] bg-neutral-950 p-6 text-white shadow-xl sm:p-8">
        <div class="grid gap-6 xl:grid-cols-[1fr_360px] xl:items-center">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-red-300"><i class="fa-solid fa-chart-line mr-2"></i>วิเคราะห์ผลงานช่างภาพ</p>
                <h1 class="mt-2 text-3xl font-black sm:text-5xl">วิเคราะห์ผลงาน <?= h($profile['display_name']) ?></h1>
                <p class="mt-4 max-w-3xl text-sm font-semibold leading-7 text-white/68">ดูจำนวนวิวโปรไฟล์ งานที่ได้ งานเสร็จสิ้น คะแนนเฉลี่ย ประเภทงานยอดนิยม และแนวโน้มคำขอจองย้อนหลัง 12 เดือน</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="/photographer/profile.php" class="rounded-full bg-white px-5 py-3 font-black text-neutral-950 transition hover:bg-red-600 hover:text-white"><i class="fa-solid fa-user-pen mr-2"></i>ปรับโปรไฟล์</a>
                    <a href="/photographer/bookings.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white transition hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-calendar-check mr-2"></i>ดูคำขอจอง</a>
                </div>
            </div>
            <div class="rounded-[1.7rem] border border-white/12 bg-white/10 p-5">
                <p class="text-sm font-black text-white/62">คะแนนเฉลี่ยรวม</p>
                <div class="mt-3 flex items-end gap-3">
                    <span class="text-6xl font-black"><?= number_format($stats['average_rating'], 1) ?></span>
                    <span class="pb-2 text-yellow-300"><i class="fa-solid fa-star"></i></span>
                </div>
                <p class="mt-2 text-sm font-bold text-white/60">จากรีวิว <?= number_format($stats['total_reviews']) ?> รายการ</p>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <?php
        $cards = [
            ['เข้าชมโปรไฟล์', number_format($stats['profile_views']), 'fa-eye', 'text-red-600', 'ยอดสะสมทั้งหมด'],
            ['งานที่ได้', number_format($stats['won_jobs']), 'fa-briefcase', 'text-sky-600', 'ตอบรับ/ยืนยัน/เสร็จสิ้น'],
            ['งานเสร็จสิ้น', number_format($stats['completed']), 'fa-circle-check', 'text-emerald-600', 'อัตราปิดงาน ' . $completionRate . '%'],
            ['คำขอทั้งหมด', number_format($stats['total_bookings']), 'fa-calendar-days', 'text-indigo-600', 'เดือนนี้ ' . number_format($stats['this_month'])],
            ['อัตราได้งาน', $wonRate . '%', 'fa-arrow-trend-up', 'text-teal-600', 'จากคำขอทั้งหมด'],
            ['ตอบกลับ', number_format($responseRate, 0) . '%', 'fa-reply', 'text-amber-600', 'เฉลี่ย ' . number_format($averageResponseHours, 1) . ' ชม.'],
        ];
        ?>
        <?php foreach ($cards as $card): ?>
            <div class="metric-card rounded-[1.5rem] p-5">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-bold text-neutral-500"><?= h($card[0]) ?></p>
                    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-white shadow-sm"><i class="fa-solid <?= h($card[2]) ?> <?= h($card[3]) ?>"></i></span>
                </div>
                <p class="mt-3 text-3xl font-black text-neutral-950"><?= h((string)$card[1]) ?></p>
                <p class="mt-1 text-xs font-black text-neutral-400"><?= h((string)$card[4]) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.35fr_.65fr]">
        <div class="stock-card rounded-[1.75rem] p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="section-kicker">แนวโน้มคำขอจอง</p>
                    <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-chart-column mr-2 text-red-600"></i>ย้อนหลัง 12 เดือน</h2>
                </div>
                <span class="rounded-full bg-red-50 px-4 py-2 text-sm font-black text-red-700">สูงสุด <?= number_format($maxMonthly) ?> รายการ/เดือน</span>
            </div>
            <div class="mt-8 flex h-72 items-end gap-2 sm:gap-3">
                <?php foreach ($monthlyChart as $month): ?>
                    <?php
                    $height = $month['total'] > 0 ? max(12, (int)round(($month['total'] / $maxMonthly) * 100)) : 4;
                    $wonHeight = $month['total'] > 0 ? ((float)$month['won_total'] / (float)$month['total']) * 100 : 0;
                    $completedHeight = $month['total'] > 0 ? ((float)$month['completed_total'] / (float)$month['total']) * 100 : 0;
                    ?>
                    <div class="flex min-w-0 flex-1 flex-col items-center justify-end gap-2">
                        <div class="text-xs font-black text-neutral-500"><?= (int)$month['total'] ?></div>
                        <div class="relative flex h-52 w-full items-end overflow-hidden rounded-b-lg rounded-t-2xl bg-neutral-100">
                            <div class="flex w-full flex-col-reverse overflow-hidden rounded-t-2xl bg-slate-300 shadow-lg transition-all duration-500" style="height: <?= $height ?>%">
                                <?php if ($wonHeight > 0): ?><div class="bg-sky-500" style="height: <?= number_format($wonHeight, 2) ?>%"></div><?php endif; ?>
                                <?php if ($completedHeight > 0): ?><div class="bg-emerald-500" style="height: <?= number_format($completedHeight, 2) ?>%"></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="w-full truncate text-center text-[11px] font-black text-neutral-500"><?= h($month['label']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-5 flex flex-wrap gap-2 text-xs font-black">
                <span class="rounded-full bg-slate-100 px-3 py-1.5 text-slate-700"><i class="fa-solid fa-square mr-1"></i>คำขอทั้งหมด</span>
                <span class="rounded-full bg-sky-50 px-3 py-1.5 text-sky-700"><i class="fa-solid fa-square mr-1"></i>งานที่ได้</span>
                <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-emerald-700"><i class="fa-solid fa-square mr-1"></i>เสร็จสิ้น</span>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">คะแนนรีวิว</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-star mr-2 text-red-600"></i>รายละเอียดคะแนน</h2>
            <div class="mt-6 grid gap-4">
                <?php foreach ($ratingBreakdown as $rating): ?>
                    <?php $ratingWidth = max(0, min(100, ((float)$rating[1] / 5) * 100)); ?>
                    <div>
                        <div class="mb-2 flex items-center justify-between text-sm font-black">
                            <span><i class="fa-solid <?= h($rating[2]) ?> mr-2 text-red-600"></i><?= h($rating[0]) ?></span>
                            <span><?= number_format((float)$rating[1], 1) ?>/5</span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-neutral-100">
                            <div class="h-full rounded-full bg-gradient-to-r from-red-500 via-amber-400 to-emerald-500" style="width: <?= number_format($ratingWidth, 2) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-neutral-50 p-4">
                    <p class="text-xs font-black text-neutral-400">วันว่างอนาคต</p>
                    <p class="mt-1 text-2xl font-black text-neutral-950"><?= number_format($stats['available_slots']) ?></p>
                </div>
                <div class="rounded-2xl bg-neutral-50 p-4">
                    <p class="text-xs font-black text-neutral-400">ผลงาน</p>
                    <p class="mt-1 text-2xl font-black text-neutral-950"><?= number_format($stats['portfolio']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">ประเภทงานยอดนิยม</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-layer-group mr-2 text-red-600"></i>หมวดที่ลูกค้าจอง</h2>
            <div class="mt-6 grid gap-4">
                <?php if ($categoryRows): ?>
                    <?php foreach ($categoryRows as $row): ?>
                        <?php $width = (int)round(((int)$row['total'] / $maxCategory) * 100); ?>
                        <div>
                            <div class="mb-2 flex justify-between text-sm font-black">
                                <span><?= h($row['name']) ?></span>
                                <span><?= number_format((int)$row['total']) ?></span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-neutral-100">
                                <div class="h-full rounded-full bg-red-600" style="width: <?= $width ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="rounded-2xl bg-neutral-50 p-5 text-sm font-bold text-neutral-500">ยังไม่มีข้อมูลคำขอจอง</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">พื้นที่ยอดนิยม</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-location-dot mr-2 text-red-600"></i>อำเภอที่มีคำขอจอง</h2>
            <div class="mt-6 grid gap-4">
                <?php if ($districtRows): ?>
                    <?php foreach ($districtRows as $row): ?>
                        <?php $width = (int)round(((int)$row['total'] / $maxDistrict) * 100); ?>
                        <div>
                            <div class="mb-2 flex justify-between text-sm font-black">
                                <span><?= h($row['district_name']) ?></span>
                                <span><?= number_format((int)$row['total']) ?></span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-neutral-100">
                                <div class="h-full rounded-full bg-neutral-950" style="width: <?= $width ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="rounded-2xl bg-neutral-50 p-5 text-sm font-bold text-neutral-500">ยังไม่มีข้อมูลพื้นที่จากคำขอจอง</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stock-card mt-6 rounded-[1.75rem] p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="section-kicker">รายการล่าสุด</p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-clock-rotate-left mr-2 text-red-600"></i>คำขอจองล่าสุด</h2>
            </div>
            <a href="/photographer/bookings.php" class="rounded-full border border-neutral-200 px-4 py-2 text-sm font-black transition hover:bg-neutral-950 hover:text-white"><i class="fa-solid fa-eye mr-2"></i>ดูทั้งหมด</a>
        </div>
        <div class="mt-5 grid gap-3">
            <?php if ($recentBookings): ?>
                <?php foreach ($recentBookings as $booking): ?>
                    <div class="grid gap-3 rounded-[1.35rem] bg-neutral-50 p-4 md:grid-cols-[1fr_auto] md:items-center">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <b><?= h($booking['booking_code']) ?></b>
                                <?= status_badge((string)$booking['status']) ?>
                            </div>
                            <p class="mt-2 text-sm font-bold text-neutral-600">
                                <?= h($booking['customer_name']) ?> · <?= h($booking['category_name']) ?> · <?= h($booking['district_name']) ?>
                            </p>
                        </div>
                        <?= clean_context_button('/photographer/booking_detail.php', ['id' => (int)$booking['id']], '<i class="fa-solid fa-arrow-up-right-from-square mr-2"></i>รายละเอียด', 'rounded-full bg-white px-4 py-2 text-sm font-black text-neutral-700 transition hover:bg-red-600 hover:text-white') ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="rounded-2xl bg-neutral-50 p-5 text-sm font-bold text-neutral-500">ยังไม่มีคำขอจอง</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

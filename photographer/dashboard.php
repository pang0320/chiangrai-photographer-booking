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
    'total' => 0,
    'pending' => 0,
    'accepted' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'rejected' => 0,
    'cancelled' => 0,
    'accepted_group' => 0,
    'upcoming' => 0,
    'this_month' => 0,
    'portfolio' => 0,
    'featured_portfolio' => 0,
    'available_slots' => 0,
    'services' => 0,
    'areas' => 0,
    'articles' => 0,
];

$statusRows = db_fetch_all('SELECT status, COUNT(*) AS total
                            FROM bookings
                            WHERE photographer_id = ? AND deleted_at IS NULL
                            GROUP BY status', [$pid]);

foreach ($statusRows as $row) {
    $status = (string)$row['status'];
    $total = (int)$row['total'];

    if (array_key_exists($status, $stats)) {
        $stats[$status] = $total;
    }

    $stats['total'] += $total;
}

$stats['accepted_group'] = $stats['accepted'] + $stats['confirmed'];
$stats['upcoming'] = (int)db_fetch_value('SELECT COUNT(*)
                                          FROM bookings
                                          WHERE photographer_id = ?
                                            AND booking_date >= CURDATE()
                                            AND status IN ("pending", "accepted", "confirmed")
                                            AND deleted_at IS NULL', [$pid]);
$stats['this_month'] = (int)db_fetch_value('SELECT COUNT(*)
                                            FROM bookings
                                            WHERE photographer_id = ?
                                              AND created_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")
                                              AND deleted_at IS NULL', [$pid]);
$stats['portfolio'] = (int)db_fetch_value('SELECT COUNT(*) FROM photographer_portfolios WHERE photographer_id = ? AND deleted_at IS NULL', [$pid]);
$stats['featured_portfolio'] = (int)db_fetch_value('SELECT COUNT(*) FROM photographer_portfolios WHERE photographer_id = ? AND is_featured = 1 AND deleted_at IS NULL', [$pid]);
$stats['available_slots'] = (int)db_fetch_value('SELECT COUNT(*)
                                                 FROM photographer_availability
                                                 WHERE photographer_id = ?
                                                   AND available_date >= CURDATE()
                                                   AND status = "available"', [$pid]);
$stats['services'] = (int)db_fetch_value('SELECT COUNT(*) FROM photographer_services WHERE photographer_id = ? AND is_active = 1', [$pid]);
$stats['areas'] = (int)db_fetch_value('SELECT COUNT(*) FROM photographer_service_areas WHERE photographer_id = ? AND is_active = 1', [$pid]);
$stats['articles'] = (int)db_fetch_value('SELECT COUNT(*) FROM photographer_articles WHERE photographer_id = ? AND deleted_at IS NULL', [$pid]);

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
                                    CASE
                                        WHEN status IN ("pending", "accepted", "confirmed") THEN "active"
                                        WHEN status = "completed" THEN "completed"
                                        WHEN status IN ("rejected", "cancelled") THEN "closed"
                                        ELSE "other"
                                    END AS status_group,
                                    COUNT(*) AS total
                             FROM bookings
                             WHERE photographer_id = ?
                               AND deleted_at IS NULL
                               AND created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
                             GROUP BY ym, status_group
                             ORDER BY ym ASC', [$pid]);
$monthlyMap = [];

foreach ($monthlyRows as $row) {
    $ym = (string)$row['ym'];
    $statusGroup = (string)$row['status_group'];

    if (!isset($monthlyMap[$ym])) {
        $monthlyMap[$ym] = [
            'active' => 0,
            'completed' => 0,
            'closed' => 0,
            'other' => 0,
        ];
    }

    $monthlyMap[$ym][$statusGroup] = (int)$row['total'];
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
    $activeTotal = 0;
    $completedTotal = 0;
    $closedTotal = 0;
    $otherTotal = 0;

    if (isset($monthlyMap[$key])) {
        $activeTotal = (int)$monthlyMap[$key]['active'];
        $completedTotal = (int)$monthlyMap[$key]['completed'];
        $closedTotal = (int)$monthlyMap[$key]['closed'];
        $otherTotal = (int)$monthlyMap[$key]['other'];
    }

    $total = $activeTotal + $completedTotal + $closedTotal + $otherTotal;

    if ($total > $maxMonthly) {
        $maxMonthly = $total;
    }

    $monthlyChart[] = [
        'label' => $thaiMonths[$monthNumber] . ' ' . $yearShort,
        'total' => $total,
        'active' => $activeTotal,
        'completed' => $completedTotal,
        'closed' => $closedTotal,
        'other' => $otherTotal,
    ];
}

$statusChart = [
    ['รอตอบรับ', $stats['pending'], 'fa-hourglass-half', 'from-amber-400 to-orange-500'],
    ['ตอบรับ/ยืนยันงาน', $stats['accepted_group'], 'fa-calendar-check', 'from-sky-500 to-indigo-500'],
    ['เสร็จสิ้น', $stats['completed'], 'fa-circle-check', 'from-emerald-500 to-teal-500'],
    ['ปฏิเสธ/ยกเลิก', $stats['rejected'] + $stats['cancelled'], 'fa-ban', 'from-rose-500 to-red-600'],
];
$maxStatus = 1;

foreach ($statusChart as $item) {
    if ((int)$item[1] > $maxStatus) {
        $maxStatus = (int)$item[1];
    }
}

$categoryRows = db_fetch_all('SELECT sc.name, COUNT(b.id) AS total
                              FROM service_categories sc
                              JOIN bookings b ON b.category_id = sc.id
                              WHERE b.photographer_id = ? AND b.deleted_at IS NULL
                              GROUP BY sc.id, sc.name
                              ORDER BY total DESC, sc.name ASC
                              LIMIT 6', [$pid]);
$maxCategory = 1;

foreach ($categoryRows as $row) {
    if ((int)$row['total'] > $maxCategory) {
        $maxCategory = (int)$row['total'];
    }
}

$ratingSummary = db_fetch_all('SELECT AVG(rating_overall) AS overall,
                                      AVG(rating_quality) AS quality,
                                      AVG(rating_communication) AS communication,
                                      AVG(rating_punctuality) AS punctuality,
                                      AVG(rating_professional) AS professional,
                                      COUNT(*) AS total
                               FROM reviews
                               WHERE photographer_id = ?
                                 AND status = "visible"
                                 AND deleted_at IS NULL', [$pid]);
$ratings = [
    'overall' => 0,
    'quality' => 0,
    'communication' => 0,
    'punctuality' => 0,
    'professional' => 0,
    'total' => 0,
];

if ($ratingSummary) {
    $ratings['overall'] = round((float)$ratingSummary[0]['overall'], 1);
    $ratings['quality'] = round((float)$ratingSummary[0]['quality'], 1);
    $ratings['communication'] = round((float)$ratingSummary[0]['communication'], 1);
    $ratings['punctuality'] = round((float)$ratingSummary[0]['punctuality'], 1);
    $ratings['professional'] = round((float)$ratingSummary[0]['professional'], 1);
    $ratings['total'] = (int)$ratingSummary[0]['total'];
}

$upcomingBookings = db_fetch_all('SELECT b.*, u.name AS customer_name, sc.name AS category_name, d.district_name
                                  FROM bookings b
                                  JOIN users u ON u.id = b.customer_id
                                  JOIN service_categories sc ON sc.id = b.category_id
                                  JOIN districts d ON d.id = b.district_id
                                  WHERE b.photographer_id = ?
                                    AND b.booking_date >= CURDATE()
                                    AND b.status IN ("pending", "accepted", "confirmed")
                                    AND b.deleted_at IS NULL
                                  ORDER BY b.booking_date ASC, b.created_at ASC
                                  LIMIT 5', [$pid]);

$bookings = db_fetch_all('SELECT b.*, u.name AS customer_name, sc.name AS category_name, d.district_name
                          FROM bookings b
                          JOIN users u ON u.id = b.customer_id
                          JOIN service_categories sc ON sc.id = b.category_id
                          JOIN districts d ON d.id = b.district_id
                          WHERE b.photographer_id = ?
                            AND b.status IN ("pending", "accepted", "confirmed")
                            AND b.deleted_at IS NULL
                          ORDER BY FIELD(b.status, "pending", "accepted", "confirmed"), b.booking_date ASC, b.created_at DESC
                          LIMIT 8', [$pid]);

$availability = db_fetch_all('SELECT *
                              FROM photographer_availability
                              WHERE photographer_id = ?
                                AND available_date >= CURDATE()
                                AND status = "available"
                              ORDER BY available_date, time_slot
                              LIMIT 8', [$pid]);

$latestReviews = db_fetch_all('SELECT r.*, u.name AS customer_name
                               FROM reviews r
                               JOIN users u ON u.id = r.customer_id
                               WHERE r.photographer_id = ?
                                 AND r.status = "visible"
                                 AND r.deleted_at IS NULL
                               ORDER BY r.created_at DESC
                               LIMIT 4', [$pid]);

$latestLogs = db_fetch_all('SELECT l.*, u.name AS changed_by_name
                            FROM booking_status_logs l
                            JOIN bookings b ON b.id = l.booking_id
                            LEFT JOIN users u ON u.id = l.changed_by
                            WHERE b.photographer_id = ?
                            ORDER BY l.created_at DESC
                            LIMIT 6', [$pid]);

$completionPercent = photographer_completion_percent($pid);
$missingSteps = [];

if (empty($profile['profile_image'])) {
    $missingSteps[] = ['เพิ่มรูปโปรไฟล์', '/photographer/profile.php', 'fa-user-circle'];
}

if (empty($profile['cover_image'])) {
    $missingSteps[] = ['เพิ่มรูปปกโปรไฟล์', '/photographer/profile.php', 'fa-image'];
}

if (trim((string)$profile['bio']) === '') {
    $missingSteps[] = ['เขียนแนะนำตัว', '/photographer/profile.php', 'fa-pen-nib'];
}

if ($stats['areas'] <= 0) {
    $missingSteps[] = ['เลือกพื้นที่ให้บริการ', '/photographer/service_areas.php', 'fa-map-location-dot'];
}

if ($stats['services'] <= 0) {
    $missingSteps[] = ['เพิ่มประเภทงานที่รับ', '/photographer/services.php', 'fa-layer-group'];
}

if ($stats['portfolio'] < 5) {
    $missingSteps[] = ['เพิ่มตัวอย่างงานถ่ายภาพอย่างน้อย 5 รูป', '/photographer/portfolio.php', 'fa-images'];
}

if ($stats['available_slots'] <= 0) {
    $missingSteps[] = ['เปิดวันว่างให้ลูกค้าจอง', '/photographer/availability.php', 'fa-calendar-plus'];
}

if (!$missingSteps) {
    $missingSteps[] = ['โปรไฟล์พร้อมรับงานแล้ว', '/photographer/dashboard.php', 'fa-circle-check'];
}

$responseRate = 0;
if (isset($profile['response_rate'])) {
    $responseRate = (float)$profile['response_rate'];
}

$averageResponseHours = 0;
if (isset($profile['average_response_hours'])) {
    $averageResponseHours = (float)$profile['average_response_hours'];
}

$successRate = 0;
if ($stats['total'] > 0) {
    $successRate = round(($stats['completed'] / $stats['total']) * 100);
}

$pendingPercent = 0;
$acceptedPercent = 0;
$completedPercent = 0;

if ($stats['total'] > 0) {
    $pendingPercent = round(($stats['pending'] / $stats['total']) * 100);
    $acceptedPercent = round(($stats['accepted_group'] / $stats['total']) * 100);
    $completedPercent = round(($stats['completed'] / $stats['total']) * 100);
}

$firstStop = $pendingPercent;
$secondStop = $pendingPercent + $acceptedPercent;
$thirdStop = $pendingPercent + $acceptedPercent + $completedPercent;
$donutStyle = 'background: conic-gradient(#f59e0b 0 ' . $firstStop . '%, #3b82f6 ' . $firstStop . '% ' . $secondStop . '%, #10b981 ' . $secondStop . '% ' . $thirdStop . '%, #e11d48 ' . $thirdStop . '% 100%);';

$pageTitle = 'แดชบอร์ดช่างภาพ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="dashboard-hero overflow-hidden rounded-[2rem] p-6 text-white sm:p-8">
        <div class="grid gap-6 xl:grid-cols-[1fr_420px] xl:items-center">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/58"><i class="fa-solid fa-chart-line mr-2 text-red-300"></i>แดชบอร์ดภาพรวมช่างภาพ</p>
                <h1 class="mt-2 text-3xl font-black sm:text-5xl">สวัสดี, <?= h($profile['display_name']) ?></h1>
                <div class="mt-3 flex flex-wrap gap-2">
                    <?= status_badge($profile['approval_status']) ?>
                    <?php if ((int)$profile['is_available'] === 1): ?>
                        <span class="rounded-full bg-emerald-400/18 px-3 py-1 text-xs font-black text-emerald-100"><i class="fa-solid fa-toggle-on mr-1"></i>เปิดรับงาน</span>
                    <?php else: ?>
                        <span class="rounded-full bg-rose-400/18 px-3 py-1 text-xs font-black text-rose-100"><i class="fa-solid fa-toggle-off mr-1"></i>ปิดรับงาน</span>
                    <?php endif; ?>
                    <?php if ((int)$profile['is_verified'] === 1): ?>
                        <span class="rounded-full bg-emerald-400/18 px-3 py-1 text-xs font-black text-emerald-100"><i class="fa-solid fa-circle-check mr-1"></i>ยืนยันตัวตนแล้ว</span>
                    <?php endif; ?>
                    <span class="rounded-full bg-white/12 px-3 py-1 text-xs font-black text-white"><i class="fa-solid fa-eye mr-1 text-red-300"></i><?= number_format((int)$profile['profile_views']) ?> เข้าชม</span>
	                    <span class="rounded-full bg-white/12 px-3 py-1 text-xs font-black text-white"><i class="fa-solid fa-star mr-1 text-yellow-300"></i>คะแนนเฉลี่ย <?= number_format((float)$profile['average_rating'], 1) ?></span>
                    <span class="rounded-full bg-white/12 px-3 py-1 text-xs font-black text-white"><i class="fa-solid fa-bolt mr-1 text-amber-300"></i>ตอบกลับ <?= number_format($responseRate, 0) ?>%</span>
                </div>
                <p class="mt-4 max-w-3xl leading-8 text-white/70">สรุปคำขอจอง คะแนน รีวิว วันว่าง และความพร้อมของโปรไฟล์ในหน้าจอเดียว เว็บไซต์เป็นเพียงตัวกลางค้นหา จอง และติดต่อช่างภาพโดยตรง ไม่มีระบบชำระเงิน</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <?= clean_context_button('/photographer/bookings.php', ['status' => 'pending'], '<i class="fa-solid fa-bell mr-2"></i>ดูคำขอใหม่', 'rounded-full bg-white px-5 py-3 font-black text-neutral-950 transition hover:bg-red-600 hover:text-white') ?>
                    <a href="/photographer/availability.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white transition hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-calendar-plus mr-2"></i>เพิ่มวันว่าง</a>
                    <a href="/photographer/portfolio.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white transition hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-images mr-2"></i>เพิ่มตัวอย่างงาน</a>
                    <a href="/photographer/profile.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white transition hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-user-pen mr-2"></i>แก้ไขโปรไฟล์</a>
                </div>
            </div>
            <div class="stat-pill rounded-[2rem] p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-black uppercase tracking-[0.18em] text-white/52"><i class="fa-solid fa-gauge-high mr-2"></i>ความสมบูรณ์โปรไฟล์</p>
                        <p class="mt-2 text-5xl font-black"><?= $completionPercent ?>%</p>
                        <p class="mt-2 text-sm font-bold text-white/70">ยิ่งครบ ลูกค้าตัดสินใจง่ายขึ้น</p>
                    </div>
                    <div class="grid h-20 w-20 place-items-center rounded-[1.5rem] bg-white text-3xl text-red-600"><i class="fa-solid fa-camera-retro"></i></div>
                </div>
                <div class="mt-5 h-3 overflow-hidden rounded-full bg-white/18">
                    <div class="h-full rounded-full bg-gradient-to-r from-red-500 via-amber-400 to-emerald-400" style="width: <?= $completionPercent ?>%"></div>
                </div>
                <div class="mt-5 grid gap-2">
                    <?php foreach (array_slice($missingSteps, 0, 3) as $step): ?>
                        <a href="<?= h($step[1]) ?>" class="flex items-center justify-between rounded-2xl bg-white/10 px-4 py-3 text-sm font-black text-white transition hover:bg-white hover:text-neutral-950">
                            <span><i class="fa-solid <?= h($step[2]) ?> mr-2 text-red-300"></i><?= h($step[0]) ?></span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <?php
        $metricCards = [
            ['คำขอใหม่', $stats['pending'], 'fa-hourglass-half', 'text-amber-600', 'รอตอบรับลูกค้า'],
            ['กำลังดำเนินการ', $stats['accepted_group'], 'fa-calendar-check', 'text-sky-600', 'ตอบรับ/ยืนยันงาน'],
            ['เสร็จสิ้น', $stats['completed'], 'fa-circle-check', 'text-emerald-600', 'งานเสร็จสิ้น'],
	            ['คะแนนเฉลี่ย', number_format((float)$profile['average_rating'], 1), 'fa-star', 'text-yellow-500', 'จำนวนรีวิว ' . number_format((int)$profile['total_reviews']) . ' รายการ'],
            ['เข้าชมโปรไฟล์', number_format((int)$profile['profile_views']), 'fa-eye', 'text-red-600', 'ยอดเปิดดูทั้งหมด'],
            ['วันว่างอนาคต', $stats['available_slots'], 'fa-calendar-days', 'text-teal-600', 'slot ที่เปิดไว้'],
        ];
        ?>
        <?php foreach ($metricCards as $card): ?>
            <div class="metric-card info-tile rounded-[1.5rem] p-5">
                <div class="flex items-center justify-between gap-4">
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
                    <p class="section-kicker">Booking Analytics</p>
                    <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-chart-column mr-2 text-red-600"></i>กราฟคำขอจอง 12 เดือนล่าสุด</h2>
                    <p class="mt-2 text-sm font-bold text-neutral-500">แยกสีตามสถานะ: กำลังดำเนินการ, เสร็จสิ้น, ยกเลิก/ปฏิเสธ</p>
                </div>
                <span class="rounded-full bg-red-50 px-4 py-2 text-sm font-black text-red-700"><i class="fa-solid fa-calendar mr-2"></i>เดือนนี้ <?= number_format($stats['this_month']) ?> รายการ</span>
            </div>
            <div class="mt-5 flex flex-wrap gap-2 text-xs font-black">
                <span class="rounded-full bg-sky-50 px-3 py-1.5 text-sky-700"><i class="fa-solid fa-square mr-1"></i>กำลังดำเนินการ</span>
                <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-emerald-700"><i class="fa-solid fa-square mr-1"></i>เสร็จสิ้น</span>
                <span class="rounded-full bg-rose-50 px-3 py-1.5 text-rose-700"><i class="fa-solid fa-square mr-1"></i>ยกเลิก/ปฏิเสธ</span>
            </div>
            <div class="mt-8 flex h-72 items-end gap-2 sm:gap-3">
                <?php foreach ($monthlyChart as $month): ?>
                    <?php
                    $height = 5;

                    if ($month['total'] > 0) {
                        $height = (int)round(($month['total'] / $maxMonthly) * 100);

                        if ($height < 14) {
                            $height = 14;
                        }
                    }
                    $activeHeight = 0;
                    $completedHeight = 0;
                    $closedHeight = 0;

                    if ((int)$month['total'] > 0) {
                        $activeHeight = (float)$month['active'] / (float)$month['total'] * 100;
                        $completedHeight = (float)$month['completed'] / (float)$month['total'] * 100;
                        $closedHeight = (float)$month['closed'] / (float)$month['total'] * 100;
                    }
                    ?>
                    <div class="flex min-w-0 flex-1 flex-col items-center justify-end gap-2">
                        <div class="text-xs font-black text-neutral-500"><?= (int)$month['total'] ?></div>
                        <div class="relative flex h-52 w-full items-end overflow-hidden rounded-b-lg rounded-t-2xl bg-neutral-100">
                            <div class="flex w-full flex-col-reverse overflow-hidden rounded-t-2xl shadow-lg transition-all duration-500" style="height: <?= $height ?>%">
                                <?php if ($activeHeight > 0): ?><div class="bg-sky-500" title="กำลังดำเนินการ <?= (int)$month['active'] ?>" style="height: <?= number_format($activeHeight, 2) ?>%"></div><?php endif; ?>
                                <?php if ($completedHeight > 0): ?><div class="bg-emerald-500" title="เสร็จสิ้น <?= (int)$month['completed'] ?>" style="height: <?= number_format($completedHeight, 2) ?>%"></div><?php endif; ?>
                                <?php if ($closedHeight > 0): ?><div class="bg-rose-500" title="ยกเลิก/ปฏิเสธ <?= (int)$month['closed'] ?>" style="height: <?= number_format($closedHeight, 2) ?>%"></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="w-full truncate text-center text-[11px] font-black text-neutral-500"><?= h($month['label']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Status Overview</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-chart-pie mr-2 text-red-600"></i>สถานะงานทั้งหมด</h2>
            <div class="mt-6 grid place-items-center">
                <div class="grid h-48 w-48 place-items-center rounded-full p-5 shadow-inner" style="<?= h($donutStyle) ?>">
                    <div class="grid h-32 w-32 place-items-center rounded-full bg-white text-center shadow-xl">
                        <div>
                            <p class="text-4xl font-black text-neutral-950"><?= number_format($stats['total']) ?></p>
                            <p class="text-xs font-black text-neutral-500">คำขอทั้งหมด</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-6 grid gap-3">
                <?php foreach ($statusChart as $item): ?>
                    <?php
                    $width = 0;

                    if ($maxStatus > 0) {
                        $width = (int)round(((int)$item[1] / $maxStatus) * 100);
                    }
                    ?>
                    <div>
                        <div class="mb-2 flex items-center justify-between text-sm font-black">
                            <span><i class="fa-solid <?= h($item[2]) ?> mr-2 text-red-600"></i><?= h($item[0]) ?></span>
                            <span><?= number_format((int)$item[1]) ?></span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-neutral-100">
                            <div class="h-full rounded-full bg-gradient-to-r <?= h($item[3]) ?>" style="width: <?= $width ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="stock-card rounded-[1.75rem] p-6 xl:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="section-kicker">งานที่ต้องดูแล</p>
                    <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-clock mr-2 text-red-600"></i>คำขอจองที่ใกล้ถึง</h2>
                </div>
                <a class="rounded-full border border-neutral-200 px-4 py-2 text-sm font-black transition hover:bg-neutral-950 hover:text-white" href="/photographer/bookings.php"><i class="fa-solid fa-eye mr-2"></i>ดูทั้งหมด</a>
            </div>
            <div class="mt-5 grid gap-3" data-block-paginate="5">
                <?php if ($upcomingBookings): ?>
                    <?php foreach ($upcomingBookings as $booking): ?>
                        <div class="grid gap-4 rounded-[1.5rem] border border-neutral-100 bg-neutral-50 p-4 transition hover:-translate-y-0.5 hover:bg-white hover:shadow-lg md:grid-cols-[1fr_auto] md:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <b class="text-neutral-950"><i class="fa-solid fa-calendar-check mr-2 text-red-600"></i><?= h($booking['booking_code']) ?></b>
                                    <?= status_badge($booking['status']) ?>
                                </div>
                                <p class="mt-2 text-sm font-bold text-neutral-600">
                                    <i class="fa-solid fa-user mr-1 text-red-600"></i><?= h($booking['customer_name']) ?>
                                    <span class="mx-2 text-neutral-300">/</span>
                                    <i class="fa-solid fa-camera mr-1 text-red-600"></i><?= h($booking['category_name']) ?>
                                    <span class="mx-2 text-neutral-300">/</span>
                                    <i class="fa-solid fa-location-dot mr-1 text-red-600"></i><?= h($booking['district_name']) ?>
                                </p>
                                <p class="mt-1 text-sm font-black text-neutral-900"><i class="fa-solid fa-clock mr-1 text-red-600"></i><?= h(format_be_date($booking['booking_date'])) ?> · <?= h(time_slot_label($booking['time_slot'])) ?></p>
                            </div>
                            <?= clean_context_button('/photographer/booking_detail.php', ['id' => (int)$booking['id']], '<i class="fa-solid fa-eye mr-2"></i>จัดการ', 'inline-flex items-center justify-center rounded-full bg-red-600 px-4 py-2 text-sm font-black text-white transition hover:bg-neutral-950') ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state rounded-[2rem] p-10 text-center">
                        <i class="fa-solid fa-calendar-plus text-4xl text-red-600"></i>
                        <h3 class="mt-3 text-xl font-black">ยังไม่มีงานที่ใกล้ถึง</h3>
                        <p class="mt-2 text-neutral-600">เปิดวันว่างเพิ่ม เพื่อให้ลูกค้าส่งคำขอจองเข้ามาได้ง่ายขึ้น</p>
                        <a href="/photographer/availability.php" class="mt-5 inline-flex items-center rounded-full bg-red-600 px-5 py-3 font-black text-white hover:bg-neutral-950"><i class="fa-solid fa-calendar-plus mr-2"></i>เพิ่มวันว่าง</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Performance</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-bolt mr-2 text-red-600"></i>สรุปประสิทธิภาพ</h2>
            <div class="mt-5 grid gap-3">
	                <div class="rounded-2xl bg-emerald-50 p-4">
	                    <div class="flex items-center justify-between gap-3"><p class="font-black text-emerald-800"><i class="fa-solid fa-reply mr-2"></i>อัตราตอบกลับ</p><b class="text-2xl text-emerald-700"><?= number_format($responseRate, 0) ?>%</b></div>
	                    <p class="mt-1 text-xs font-bold leading-5 text-emerald-700">คิดจากคำขอที่ช่างภาพตอบรับหรือปฏิเสธ เทียบกับคำขอทั้งหมด</p>
	                    <p class="mt-1 text-xs font-bold text-emerald-700">เวลาตอบกลับเฉลี่ย <?= number_format($averageResponseHours, 1) ?> ชม.</p>
	                </div>
                <div class="rounded-2xl bg-sky-50 p-4">
                    <div class="flex items-center justify-between gap-3"><p class="font-black text-sky-800"><i class="fa-solid fa-trophy mr-2"></i>อัตรางานเสร็จ</p><b class="text-2xl text-sky-700"><?= number_format($successRate) ?>%</b></div>
                    <p class="mt-1 text-xs font-bold text-sky-700">จากคำขอทั้งหมด <?= number_format($stats['total']) ?> รายการ</p>
                </div>
                <div class="rounded-2xl bg-amber-50 p-4">
                    <div class="flex items-center justify-between gap-3"><p class="font-black text-amber-800"><i class="fa-solid fa-image mr-2"></i>ตัวอย่างงานถ่ายภาพ</p><b class="text-2xl text-amber-700"><?= number_format($stats['portfolio']) ?></b></div>
                    <p class="mt-1 text-xs font-bold text-amber-700">รูปเด่น <?= number_format($stats['featured_portfolio']) ?> รูป</p>
                </div>
                <div class="rounded-2xl bg-red-50 p-4">
                    <div class="flex items-center justify-between gap-3"><p class="font-black text-red-800"><i class="fa-solid fa-location-dot mr-2"></i>พื้นที่/บริการ</p><b class="text-2xl text-red-700"><?= number_format($stats['areas']) ?>/<?= number_format($stats['services']) ?></b></div>
                    <p class="mt-1 text-xs font-bold text-red-700">พื้นที่ให้บริการ / ประเภทงาน</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[.75fr_.75fr_1.1fr]">
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Category</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950"><i class="fa-solid fa-layer-group mr-2 text-red-600"></i>งานตามหมวดหมู่</h2>
            <div class="mt-5 grid gap-3" data-block-paginate="5">
                <?php if ($categoryRows): ?>
                    <?php foreach ($categoryRows as $category): ?>
                        <?php
                        $width = 0;

                        if ($maxCategory > 0) {
                            $width = (int)round(((int)$category['total'] / $maxCategory) * 100);
                        }
                        ?>
                        <div>
                            <div class="mb-2 flex justify-between gap-3 text-sm font-black"><span><?= h($category['name']) ?></span><span><?= (int)$category['total'] ?></span></div>
                            <div class="rating-bar"><span style="width: <?= $width ?>%"></span></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state rounded-[1.5rem] p-8 text-center text-sm font-bold text-neutral-600"><i class="fa-solid fa-chart-simple mb-2 block text-3xl text-red-600"></i>ยังไม่มีข้อมูลหมวดหมู่จากคำขอจอง</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Rating Breakdown</p>
	            <h2 class="mt-1 text-xl font-black text-neutral-950"><i class="fa-solid fa-star-half-stroke mr-2 text-red-600"></i>คะแนนเฉลี่ยจากรีวิว</h2>
            <?php
            $ratingBars = [
                ['ภาพรวม', $ratings['overall'], 'fa-star'],
                ['คุณภาพงาน', $ratings['quality'], 'fa-camera'],
                ['การสื่อสาร', $ratings['communication'], 'fa-comment'],
                ['ตรงเวลา', $ratings['punctuality'], 'fa-clock'],
                ['มืออาชีพ', $ratings['professional'], 'fa-briefcase'],
            ];
            ?>
            <div class="mt-5 grid gap-3">
                <?php foreach ($ratingBars as $rating): ?>
                    <?php
                    $width = 0;

                    if ((float)$rating[1] > 0) {
                        $width = (int)round(((float)$rating[1] / 5) * 100);
                    }
                    ?>
                    <div>
	                        <div class="mb-2 flex justify-between gap-3 text-sm font-black"><span><i class="fa-solid <?= h($rating[2]) ?> mr-2 text-red-600"></i>คะแนน: <?= h($rating[0]) ?></span><span><?= number_format((float)$rating[1], 1) ?>/5</span></div>
                        <div class="rating-bar"><span style="width: <?= $width ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
                <?php if ($ratings['total'] <= 0): ?>
                    <p class="rounded-2xl bg-neutral-50 p-3 text-sm font-bold text-neutral-500"><i class="fa-solid fa-circle-info mr-1 text-red-600"></i>ยังไม่มีรีวิวจากลูกค้า</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Quick Actions</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950"><i class="fa-solid fa-wand-magic-sparkles mr-2 text-red-600"></i>ทางลัดจัดการโปรไฟล์</h2>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <a href="/photographer/profile.php" class="rounded-[1.35rem] bg-neutral-950 p-4 font-black text-white transition hover:-translate-y-1 hover:bg-red-600"><i class="fa-solid fa-id-card mb-3 block text-2xl text-red-300"></i>แก้ไขโปรไฟล์</a>
                <a href="/photographer/portfolio.php" class="rounded-[1.35rem] bg-red-50 p-4 font-black text-red-700 transition hover:-translate-y-1 hover:bg-red-600 hover:text-white"><i class="fa-solid fa-images mb-3 block text-2xl"></i>เพิ่มตัวอย่างงาน</a>
                <a href="/photographer/availability.php" class="rounded-[1.35rem] bg-amber-50 p-4 font-black text-amber-700 transition hover:-translate-y-1 hover:bg-amber-500 hover:text-white"><i class="fa-solid fa-calendar-plus mb-3 block text-2xl"></i>เพิ่มวันว่าง</a>
                <a href="/photographer/articles.php" class="rounded-[1.35rem] bg-emerald-50 p-4 font-black text-emerald-700 transition hover:-translate-y-1 hover:bg-emerald-600 hover:text-white"><i class="fa-solid fa-newspaper mb-3 block text-2xl"></i>เขียนบทความ</a>
            </div>
            <div class="mt-5 rounded-[1.35rem] bg-neutral-50 p-4 text-sm font-bold leading-7 text-neutral-600">
                <i class="fa-solid fa-circle-info mr-2 text-red-600"></i>ไม่มีระบบรับชำระเงิน ลูกค้าและช่างภาพตกลงรายละเอียด ราคา และการชำระเงินภายนอกระบบโดยตรง
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
        <div class="stock-card rounded-[1.75rem] p-6">
            <div class="flex flex-wrap justify-between gap-4">
                <div>
                    <p class="section-kicker">Active Bookings</p>
                    <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-list-check mr-2 text-red-600"></i>รายการจองที่กำลังดำเนินการ</h2>
                </div>
                <?= clean_context_button('/photographer/bookings.php', ['tab' => 'active'], '<i class="fa-solid fa-eye mr-2"></i>ดูทั้งหมด', 'rounded-full border border-neutral-200 px-4 py-2 text-sm font-black transition hover:bg-neutral-950 hover:text-white') ?>
            </div>
            <div class="mt-5 overflow-x-auto">
                <?php if ($bookings): ?>
                    <table class="w-full text-left text-sm">
                        <thead class="text-neutral-500">
                            <tr>
                                <th class="py-3"><i class="fa-solid fa-hashtag mr-1 text-red-600"></i>รหัสจอง</th>
                                <th><i class="fa-solid fa-user mr-1 text-red-600"></i>ลูกค้า</th>
                                <th><i class="fa-solid fa-camera mr-1 text-red-600"></i>ประเภท</th>
                                <th><i class="fa-solid fa-calendar mr-1 text-red-600"></i>วันที่</th>
                                <th><i class="fa-solid fa-circle-info mr-1 text-red-600"></i>สถานะ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody data-block-paginate="5">
                            <?php foreach ($bookings as $booking): ?>
                                <tr class="border-t border-neutral-100">
                                    <td class="py-4 font-black text-neutral-950"><?= h($booking['booking_code']) ?></td>
                                    <td class="font-bold"><?= h($booking['customer_name']) ?></td>
                                    <td><?= h($booking['category_name']) ?></td>
                                    <td><?= h(format_be_date($booking['booking_date'])) ?> · <?= h(time_slot_label($booking['time_slot'])) ?></td>
                                    <td><?= status_badge($booking['status']) ?></td>
                                    <td><?= clean_context_button('/photographer/booking_detail.php', ['id' => (int)$booking['id']], '<i class="fa-solid fa-eye mr-1"></i>ดู', 'inline-flex items-center rounded-full bg-red-50 px-3 py-1.5 text-xs font-black text-red-700 hover:bg-red-600 hover:text-white') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state rounded-[2rem] p-10 text-center">
                        <i class="fa-solid fa-inbox text-4xl text-red-600"></i>
                        <h3 class="mt-3 text-xl font-black">ยังไม่มีคำขอจอง</h3>
                        <p class="mt-2 text-neutral-600">เมื่อมีลูกค้าส่งคำขอจอง รายการจะแสดงที่นี่ทันที</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid gap-6">
            <div class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker">ปฏิทินวันว่าง</p>
                <h2 class="mt-1 text-xl font-black text-neutral-950"><i class="fa-solid fa-calendar-days mr-2 text-red-600"></i>วันว่างล่าสุด</h2>
                <div class="mt-4 grid gap-2" data-block-paginate="5">
                    <?php foreach ($availability as $slot): ?>
                        <div class="flex items-center justify-between rounded-2xl bg-neutral-50 px-4 py-3 text-sm">
                            <span class="font-black"><i class="fa-solid fa-calendar mr-2 text-red-600"></i><?= h(format_be_date($slot['available_date'])) ?></span>
                            <span class="font-bold text-neutral-500"><?= h(time_slot_label($slot['time_slot'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$availability): ?>
                        <div class="empty-state rounded-2xl p-6 text-center text-sm font-bold text-neutral-600">
                            <i class="fa-solid fa-calendar-plus mb-2 block text-3xl text-red-600"></i>ยังไม่มีวันว่าง เพิ่มวันว่างเพื่อให้ลูกค้าจองได้
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker">ประวัติสถานะ</p>
                <h2 class="mt-1 text-xl font-black text-neutral-950"><i class="fa-solid fa-clock-rotate-left mr-2 text-red-600"></i>ประวัติสถานะล่าสุด</h2>
                <div class="mt-4 grid gap-3" data-block-paginate="5">
                    <?php foreach ($latestLogs as $log): ?>
                        <div class="rounded-2xl bg-neutral-50 p-4 text-sm">
                            <div class="font-black text-neutral-950"><?= status_badge((string)$log['new_status']) ?></div>
                            <p class="mt-2 font-bold text-neutral-500"><i class="fa-solid fa-calendar mr-1 text-red-600"></i><?= h(format_be_datetime($log['created_at'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$latestLogs): ?>
                        <div class="empty-state rounded-2xl p-6 text-center text-sm font-bold text-neutral-600"><i class="fa-solid fa-clipboard-list mb-2 block text-3xl text-red-600"></i>ยังไม่มีประวัติการเปลี่ยนสถานะ</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_380px]">
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Latest Reviews</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-comments mr-2 text-red-600"></i>รีวิวล่าสุดจากลูกค้า</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2" data-block-paginate="5">
                <?php foreach ($latestReviews as $review): ?>
                    <article class="rounded-[1.5rem] bg-neutral-50 p-5 transition hover:-translate-y-1 hover:bg-white hover:shadow-lg">
	                        <div class="flex items-center justify-between gap-4"><b><i class="fa-solid fa-user mr-2 text-red-600"></i><?= h($review['customer_name']) ?></b><span class="text-red-600" title="คะแนนรวม <?= (int)$review['rating_overall'] ?> จาก 5"><?= str_repeat('★', (int)$review['rating_overall']) ?></span></div>
                        <p class="mt-3 line-clamp-3 text-sm leading-7 text-neutral-700"><?= h($review['comment']) ?></p>
                    </article>
                <?php endforeach; ?>
                <?php if (!$latestReviews): ?>
                    <div class="empty-state rounded-[2rem] p-8 text-center md:col-span-2">
                        <i class="fa-solid fa-comment-slash mb-2 block text-4xl text-red-600"></i>
                        <b>ยังไม่มีรีวิวจากลูกค้า</b>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] bg-red-50 p-6">
            <p class="section-kicker">Tips</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950"><i class="fa-solid fa-lightbulb mr-2 text-red-600"></i>เพิ่มโอกาสรับงาน</h2>
            <div class="mt-4 grid gap-3 text-sm font-bold leading-7 text-neutral-700">
                <p class="rounded-2xl bg-white p-4"><i class="fa-solid fa-images mr-2 text-red-600"></i>อัปโหลดตัวอย่างงานถ่ายภาพอย่างน้อย 8-12 รูป และตั้งรูปเด่นให้ชัดเจน</p>
                <p class="rounded-2xl bg-white p-4"><i class="fa-solid fa-calendar-plus mr-2 text-red-600"></i>เปิดวันว่างล่วงหน้า 2-4 สัปดาห์ เพื่อให้ลูกค้าจองได้ง่าย</p>
                <p class="rounded-2xl bg-white p-4"><i class="fa-solid fa-phone mr-2 text-red-600"></i>ระบุช่องทางติดต่อและราคาเริ่มต้นโดยประมาณให้ครบ ลดคำถามซ้ำก่อนจอง โดยลูกค้าและช่างภาพตกลงราคาและชำระเงินกันเองภายนอกระบบ</p>
                <p class="rounded-2xl bg-white p-4"><i class="fa-solid fa-reply mr-2 text-red-600"></i>ตอบรับหรือปฏิเสธคำขอเร็ว ช่วยเพิ่มความน่าเชื่อถือของโปรไฟล์</p>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

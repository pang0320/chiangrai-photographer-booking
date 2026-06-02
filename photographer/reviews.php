<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');

$profile = photographer_profile_by_user((int)current_user()['id']);

if (!$profile) {
    exit('Profile not found');
}

$pid = (int)$profile['id'];

if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'report_review') {
        $reviewId = (int)($_POST['review_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        $detail = trim((string)($_POST['detail'] ?? ''));

        $reviewOwner = db_fetch_value('SELECT id FROM reviews WHERE id = ? AND photographer_id = ? AND deleted_at IS NULL LIMIT 1', [$reviewId, $pid]);

        if (!$reviewOwner) {
            flash('error', 'ไม่พบรีวิวที่ต้องการรายงาน');
        } elseif ($reason === '' || $detail === '') {
            flash('error', 'กรุณากรอกเหตุผลและรายละเอียดในการรายงานรีวิว');
        } else {
            $stmt = db()->prepare('INSERT INTO reports (reporter_id, target_type, target_id, reason, detail, status, created_at, updated_at) VALUES (?, "review", ?, ?, ?, "pending", NOW(), NOW())');
            $stmt->execute([(int)current_user()['id'], $reviewId, $reason, $detail]);
            $reportId = (int)db()->lastInsertId();
            notify_admins('มีรายงานรีวิวใหม่', 'ช่างภาพรายงานรีวิว #' . $reviewId, 'report', $reportId);
            log_activity('report_review', 'reports', $reportId);
            flash('success', 'ส่งรายงานรีวิวให้ผู้ดูแลตรวจสอบแล้ว');
        }

        redirect('/photographer/reviews.php');
    }
}

$reviews = db_fetch_all('SELECT r.*, u.name AS customer_name, u.avatar AS customer_avatar, b.booking_code, b.booking_date, sc.name AS category_name
                         FROM reviews r
                         JOIN users u ON u.id = r.customer_id
                         JOIN bookings b ON b.id = r.booking_id
                         JOIN service_categories sc ON sc.id = b.category_id
                         WHERE r.photographer_id = ? AND r.deleted_at IS NULL
                         ORDER BY r.created_at DESC', [$pid]);

$summary = db_fetch_all('SELECT COUNT(*) AS total_reviews,
                                SUM(status = "visible") AS visible_reviews,
                                SUM(status = "hidden") AS hidden_reviews,
                                AVG(CASE WHEN status = "visible" THEN rating_overall END) AS avg_overall,
                                AVG(CASE WHEN status = "visible" THEN rating_quality END) AS avg_quality,
                                AVG(CASE WHEN status = "visible" THEN rating_communication END) AS avg_communication,
                                AVG(CASE WHEN status = "visible" THEN rating_punctuality END) AS avg_punctuality,
                                AVG(CASE WHEN status = "visible" THEN rating_professional END) AS avg_professional
                         FROM reviews
                         WHERE photographer_id = ? AND deleted_at IS NULL', [$pid]);

$reviewSummary = [
    'total_reviews' => 0,
    'visible_reviews' => 0,
    'hidden_reviews' => 0,
    'avg_overall' => 0,
    'avg_quality' => 0,
    'avg_communication' => 0,
    'avg_punctuality' => 0,
    'avg_professional' => 0,
];

if ($summary) {
    $reviewSummary['total_reviews'] = (int)$summary[0]['total_reviews'];
    $reviewSummary['visible_reviews'] = (int)$summary[0]['visible_reviews'];
    $reviewSummary['hidden_reviews'] = (int)$summary[0]['hidden_reviews'];
    $reviewSummary['avg_overall'] = round((float)$summary[0]['avg_overall'], 1);
    $reviewSummary['avg_quality'] = round((float)$summary[0]['avg_quality'], 1);
    $reviewSummary['avg_communication'] = round((float)$summary[0]['avg_communication'], 1);
    $reviewSummary['avg_punctuality'] = round((float)$summary[0]['avg_punctuality'], 1);
    $reviewSummary['avg_professional'] = round((float)$summary[0]['avg_professional'], 1);
}

$distributionRows = db_fetch_all('SELECT rating_overall, COUNT(*) AS total
                                  FROM reviews
                                  WHERE photographer_id = ?
                                    AND status = "visible"
                                    AND deleted_at IS NULL
                                  GROUP BY rating_overall', [$pid]);
$ratingDistribution = [
    5 => 0,
    4 => 0,
    3 => 0,
    2 => 0,
    1 => 0,
];

foreach ($distributionRows as $row) {
    $rating = (int)$row['rating_overall'];

    if (array_key_exists($rating, $ratingDistribution)) {
        $ratingDistribution[$rating] = (int)$row['total'];
    }
}

$maxDistribution = 1;
foreach ($ratingDistribution as $total) {
    if ($total > $maxDistribution) {
        $maxDistribution = $total;
    }
}

$monthlyRows = db_fetch_all('SELECT DATE_FORMAT(created_at, "%Y-%m") AS ym, COUNT(*) AS total, AVG(rating_overall) AS avg_rating
                             FROM reviews
                             WHERE photographer_id = ?
                               AND status = "visible"
                               AND deleted_at IS NULL
                               AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
                             GROUP BY ym
                             ORDER BY ym ASC', [$pid]);
$monthlyMap = [];

foreach ($monthlyRows as $row) {
    $monthlyMap[(string)$row['ym']] = [
        'total' => (int)$row['total'],
        'avg_rating' => round((float)$row['avg_rating'], 1),
    ];
}

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
$monthlyChart = [];
$maxMonthlyReviews = 1;

for ($i = 5; $i >= 0; $i--) {
    $date = new DateTime('first day of this month');

    if ($i > 0) {
        $date->modify('-' . $i . ' months');
    }

    $key = $date->format('Y-m');
    $monthNumber = (int)$date->format('n');
    $yearShort = substr((string)((int)$date->format('Y') + 543), -2);
    $total = 0;
    $avgRating = 0;

    if (isset($monthlyMap[$key])) {
        $total = (int)$monthlyMap[$key]['total'];
        $avgRating = (float)$monthlyMap[$key]['avg_rating'];
    }

    if ($total > $maxMonthlyReviews) {
        $maxMonthlyReviews = $total;
    }

    $monthlyChart[] = [
        'label' => $thaiMonths[$monthNumber] . ' ' . $yearShort,
        'total' => $total,
        'avg_rating' => $avgRating,
    ];
}

$reviewImages = [];
$reviewIds = [];

foreach ($reviews as $review) {
    $reviewIds[] = (int)$review['id'];
}

if ($reviewIds) {
    $placeholders = implode(',', array_fill(0, count($reviewIds), '?'));
    $imageRows = db_fetch_all('SELECT review_id, image_path FROM review_images WHERE review_id IN (' . $placeholders . ') ORDER BY id ASC', $reviewIds);

    foreach ($imageRows as $image) {
        $reviewId = (int)$image['review_id'];

        if (!isset($reviewImages[$reviewId])) {
            $reviewImages[$reviewId] = [];
        }

        $reviewImages[$reviewId][] = (string)$image['image_path'];
    }
}

$ratingBreakdown = [
    ['คะแนนรวมเฉลี่ย', $reviewSummary['avg_overall'], 'fa-star', 'from-red-600 to-amber-400'],
    ['คุณภาพงาน', $reviewSummary['avg_quality'], 'fa-camera', 'from-indigo-500 to-sky-400'],
    ['การสื่อสาร', $reviewSummary['avg_communication'], 'fa-comment', 'from-emerald-500 to-teal-400'],
    ['ความตรงเวลา', $reviewSummary['avg_punctuality'], 'fa-clock', 'from-amber-500 to-orange-400'],
    ['ความมืออาชีพ', $reviewSummary['avg_professional'], 'fa-briefcase', 'from-rose-500 to-red-500'],
];

$scorePercent = 0;
if ($reviewSummary['avg_overall'] > 0) {
    $scorePercent = min(100, max(0, (int)round(($reviewSummary['avg_overall'] / 5) * 100)));
}

$visiblePercent = 0;
if ($reviewSummary['total_reviews'] > 0) {
    $visiblePercent = (int)round(($reviewSummary['visible_reviews'] / $reviewSummary['total_reviews']) * 100);
}

$pageTitle = 'รีวิว';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="dashboard-hero overflow-hidden rounded-[2rem] p-6 text-white sm:p-8">
        <div class="grid gap-6 xl:grid-cols-[1fr_360px] xl:items-center">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/58"><i class="fa-solid fa-comments mr-2 text-red-300"></i>Review Analytics</p>
                <h1 class="mt-2 text-3xl font-black sm:text-5xl">แดชบอร์ดรีวิวลูกค้า</h1>
                <p class="mt-4 max-w-3xl leading-8 text-white/70">ดูคะแนนเฉลี่ย คุณภาพงาน การสื่อสาร ความตรงเวลา และรีวิวจริงจากลูกค้า เพื่อปรับโปรไฟล์และบริการให้ดูน่าเชื่อถือยิ่งขึ้น</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="/photographer/dashboard.php" class="rounded-full bg-white px-5 py-3 font-black text-neutral-950 transition hover:bg-red-600 hover:text-white"><i class="fa-solid fa-gauge mr-2"></i>แดชบอร์ด</a>
                    <a href="/photographer/portfolio.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white transition hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-images mr-2"></i>จัดการตัวอย่างงาน</a>
                    <a href="/photographer/profile.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white transition hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-user-pen mr-2"></i>แก้ไขโปรไฟล์</a>
                </div>
            </div>
            <div class="stat-pill rounded-[2rem] p-6 text-center">
                <div class="mx-auto grid h-44 w-44 place-items-center rounded-full p-4" style="background: conic-gradient(#e21b2d 0 <?= $scorePercent ?>%, rgba(255,255,255,.16) <?= $scorePercent ?>% 100%);">
                    <div class="grid h-32 w-32 place-items-center rounded-full bg-white text-neutral-950 shadow-2xl">
                        <div>
                            <p class="text-5xl font-black"><?= number_format($reviewSummary['avg_overall'], 1) ?></p>
                            <p class="text-xs font-black text-neutral-500">จาก 5 คะแนน</p>
                        </div>
                    </div>
                </div>
                <div class="mt-4 text-yellow-300">
                    <?= str_repeat('★', (int)round($reviewSummary['avg_overall'])) ?>
                    <span class="text-white/25"><?= str_repeat('★', max(0, 5 - (int)round($reviewSummary['avg_overall']))) ?></span>
                </div>
	                <p class="mt-2 text-sm font-bold text-white/70">จำนวนรีวิวที่แสดงผล <?= number_format($reviewSummary['visible_reviews']) ?> รายการ</p>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <?php
        $summaryCards = [
	            ['จำนวนรีวิวทั้งหมด', $reviewSummary['total_reviews'], 'fa-comments', 'text-red-600', 'รวม visible/hidden'],
            ['แสดงผล', $reviewSummary['visible_reviews'], 'fa-eye', 'text-emerald-600', $visiblePercent . '% ของทั้งหมด'],
            ['ถูกซ่อน', $reviewSummary['hidden_reviews'], 'fa-eye-slash', 'text-slate-600', 'ซ่อนโดยผู้ดูแล'],
	            ['คะแนนรวมเฉลี่ย', number_format($reviewSummary['avg_overall'], 1), 'fa-star', 'text-yellow-500', 'เฉพาะรีวิวที่แสดง'],
	            ['คะแนนโปรไฟล์', number_format((float)$profile['average_rating'], 1), 'fa-camera-retro', 'text-indigo-600', 'ค่าจากโปรไฟล์'],
        ];
        ?>
        <?php foreach ($summaryCards as $card): ?>
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

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
        <div class="stock-card rounded-[1.75rem] p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="section-kicker">Rating Breakdown</p>
                    <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-chart-simple mr-2 text-red-600"></i>กราฟคะแนนแยกตามด้าน</h2>
                    <p class="mt-2 text-sm font-bold text-neutral-500">คำนวณจากรีวิวที่มีสถานะ “แสดงผล” เท่านั้น</p>
                </div>
            </div>
            <div class="mt-6 grid gap-5">
                <?php foreach ($ratingBreakdown as $row): ?>
                    <?php
                    $width = 0;

                    if ((float)$row[1] > 0) {
                        $width = (int)round(((float)$row[1] / 5) * 100);
                    }
                    ?>
                    <div>
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-3 text-sm font-black">
	                            <span><i class="fa-solid <?= h($row[2]) ?> mr-2 text-red-600"></i>คะแนน: <?= h($row[0]) ?></span>
                            <span class="rounded-full bg-neutral-100 px-3 py-1 text-neutral-700"><?= number_format((float)$row[1], 1) ?>/5</span>
                        </div>
                        <div class="h-4 overflow-hidden rounded-full bg-neutral-100">
                            <div class="h-full rounded-full bg-gradient-to-r <?= h($row[3]) ?> shadow-lg" style="width: <?= $width ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Star Distribution</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-ranking-star mr-2 text-red-600"></i>สัดส่วนดาวรีวิว</h2>
            <div class="mt-6 grid gap-4">
                <?php foreach ($ratingDistribution as $rating => $total): ?>
                    <?php
                    $width = 0;

                    if ($maxDistribution > 0) {
                        $width = (int)round(($total / $maxDistribution) * 100);
                    }
                    ?>
                    <div class="grid grid-cols-[64px_1fr_44px] items-center gap-3">
                        <div class="text-sm font-black text-neutral-700"><?= (int)$rating ?> <i class="fa-solid fa-star text-yellow-400"></i></div>
                        <div class="h-4 overflow-hidden rounded-full bg-neutral-100">
                            <div class="h-full rounded-full bg-gradient-to-r from-yellow-400 to-red-500" style="width: <?= $width ?>%"></div>
                        </div>
                        <div class="text-right text-sm font-black text-neutral-700"><?= (int)$total ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[.9fr_1.1fr]">
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Monthly Trend</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-chart-column mr-2 text-red-600"></i>รีวิว 6 เดือนล่าสุด</h2>
            <div class="mt-8 flex h-64 items-end gap-3">
                <?php foreach ($monthlyChart as $month): ?>
                    <?php
                    $height = 6;

                    if ((int)$month['total'] > 0) {
                        $height = (int)round(((int)$month['total'] / $maxMonthlyReviews) * 100);

                        if ($height < 16) {
                            $height = 16;
                        }
                    }
                    ?>
                    <div class="flex min-w-0 flex-1 flex-col items-center justify-end gap-2">
                        <div class="text-xs font-black text-neutral-500"><?= (int)$month['total'] ?></div>
                        <div class="relative h-44 w-full overflow-hidden rounded-b-lg rounded-t-2xl bg-neutral-100">
                            <div class="absolute bottom-0 left-0 right-0 rounded-t-2xl bg-gradient-to-t from-red-600 via-red-500 to-amber-300 shadow-lg shadow-red-500/20" style="height: <?= $height ?>%"></div>
                        </div>
                        <div class="w-full truncate text-center text-[11px] font-black text-neutral-500"><?= h($month['label']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Insight</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-lightbulb mr-2 text-red-600"></i>สรุปจากคะแนน</h2>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <?php foreach ($ratingBreakdown as $row): ?>
                    <?php
                    $toneClass = 'bg-red-50 text-red-700';

                    if ((float)$row[1] >= 4.5) {
                        $toneClass = 'bg-emerald-50 text-emerald-700';
                    } elseif ((float)$row[1] >= 4.0) {
                        $toneClass = 'bg-sky-50 text-sky-700';
                    } elseif ((float)$row[1] >= 3.0) {
                        $toneClass = 'bg-amber-50 text-amber-700';
                    }
                    ?>
                    <div class="rounded-2xl <?= h($toneClass) ?> p-4">
	                        <p class="font-black"><i class="fa-solid <?= h($row[2]) ?> mr-2"></i>คะแนน: <?= h($row[0]) ?></p>
                        <p class="mt-2 text-3xl font-black"><?= number_format((float)$row[1], 1) ?></p>
                        <p class="mt-1 text-xs font-bold opacity-80">คะแนนเฉลี่ยจากรีวิวลูกค้า</p>
                    </div>
                <?php endforeach; ?>
                <div class="rounded-2xl bg-neutral-950 p-4 text-white">
                    <p class="font-black"><i class="fa-solid fa-bullseye mr-2 text-red-300"></i>เป้าหมาย</p>
                    <p class="mt-2 text-3xl font-black">4.8+</p>
                    <p class="mt-1 text-xs font-bold text-white/62">รักษาคุณภาพและตอบกลับเร็วขึ้น</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 stock-card rounded-[1.75rem] p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="section-kicker">Customer Reviews</p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950"><i class="fa-solid fa-message mr-2 text-red-600"></i>รีวิวทั้งหมด</h2>
            </div>
            <span class="rounded-full bg-red-50 px-4 py-2 text-sm font-black text-red-700"><i class="fa-solid fa-comments mr-2"></i><?= number_format(count($reviews)) ?> รายการ</span>
        </div>

        <?php if ($reviews): ?>
            <div class="mt-6 grid gap-4">
                <?php foreach ($reviews as $review): ?>
                    <?php
                    $avatar = public_image($review['customer_avatar'] ?? '', '/assets/uploads/seed/photo-1494790108377-be9c29b29330.jpg');
                    $currentReviewImages = [];

                    if (isset($reviewImages[(int)$review['id']])) {
                        $currentReviewImages = $reviewImages[(int)$review['id']];
                    }
                    ?>
                    <article class="rounded-[1.5rem] border border-neutral-100 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex min-w-0 gap-3">
                                <img src="<?= h($avatar) ?>" alt="<?= h($review['customer_name']) ?>" class="h-12 w-12 rounded-2xl object-cover">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <b class="text-neutral-950"><?= h($review['customer_name']) ?></b>
                                        <?= status_badge($review['status']) ?>
                                    </div>
                                    <p class="mt-1 text-sm font-bold text-neutral-500">
                                        <i class="fa-solid fa-receipt mr-1 text-red-600"></i><?= h($review['booking_code']) ?>
                                        <span class="mx-2 text-neutral-300">/</span>
                                        <i class="fa-solid fa-camera mr-1 text-red-600"></i><?= h($review['category_name']) ?>
                                        <span class="mx-2 text-neutral-300">/</span>
                                        <i class="fa-solid fa-calendar mr-1 text-red-600"></i><?= h(format_be_date($review['booking_date'])) ?>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
	                                <div class="text-lg text-yellow-400" title="คะแนนรวม <?= (int)$review['rating_overall'] ?> จาก 5"><?= str_repeat('★', (int)$review['rating_overall']) ?><span class="text-neutral-200"><?= str_repeat('★', max(0, 5 - (int)$review['rating_overall'])) ?></span></div>
                                <p class="text-xs font-black text-neutral-400"><?= h(format_be_datetime($review['created_at'])) ?></p>
                            </div>
                        </div>

                        <p class="mt-4 leading-8 text-neutral-700"><?= nl2br(h($review['comment'])) ?></p>

                        <div class="mt-4 grid gap-2 sm:grid-cols-4">
                            <?php
                            $reviewMiniRatings = [
                                ['คุณภาพ', $review['rating_quality'], 'fa-camera'],
                                ['สื่อสาร', $review['rating_communication'], 'fa-comment'],
                                ['ตรงเวลา', $review['rating_punctuality'], 'fa-clock'],
                                ['มืออาชีพ', $review['rating_professional'], 'fa-briefcase'],
                            ];
                            ?>
                            <?php foreach ($reviewMiniRatings as $mini): ?>
                                <div class="info-tile rounded-2xl p-3">
                                    <p class="text-xs font-black text-neutral-500"><i class="fa-solid <?= h($mini[2]) ?> mr-1 text-red-600"></i><?= h($mini[0]) ?></p>
                                    <p class="mt-1 text-lg font-black text-neutral-950"><?= number_format((float)$mini[1], 1) ?>/5</p>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($currentReviewImages): ?>
                            <div class="mt-4 flex gap-3 overflow-x-auto">
                                <?php foreach ($currentReviewImages as $imagePath): ?>
                                    <img src="<?= h(public_image($imagePath, '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg')) ?>" alt="review image" class="h-24 w-32 shrink-0 rounded-2xl object-cover">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="mt-4 grid gap-3 rounded-2xl bg-neutral-50 p-4">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="report_review">
                            <input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="grid gap-1 text-xs font-black text-neutral-600">
                                    <span><i class="fa-solid fa-triangle-exclamation mr-1 text-red-600"></i>เหตุผลในการรายงานรีวิว <?= required_mark() ?></span>
                                    <input name="reason" required maxlength="180" placeholder="เช่น รีวิวไม่เหมาะสม หรือไม่ตรงข้อเท็จจริง" class="stock-input rounded-xl px-3 py-2 text-sm">
                                </label>
                                <label class="grid gap-1 text-xs font-black text-neutral-600">
                                    <span><i class="fa-solid fa-pen-to-square mr-1 text-red-600"></i>รายละเอียดเพิ่มเติม <?= required_mark() ?></span>
                                    <textarea name="detail" required maxlength="2000" rows="2" placeholder="อธิบายเหตุผลเพื่อให้ผู้ดูแลตรวจสอบ" class="stock-input rounded-xl px-3 py-2 text-sm"></textarea>
                                </label>
                            </div>
                            <button data-confirm="ส่งรายงานรีวิวนี้ให้ผู้ดูแลตรวจสอบ?" class="btn-danger btn-sm justify-self-start rounded-xl">
                                <i class="fa-solid fa-flag"></i>รายงานรีวิว
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state mt-6 rounded-[2rem] p-10 text-center">
                <i class="fa-solid fa-comment-slash text-5xl text-red-600"></i>
                <h3 class="mt-4 text-2xl font-black text-neutral-950">ยังไม่มีรีวิวจากลูกค้า</h3>
                <p class="mt-2 text-neutral-600">เมื่อลูกค้ารีวิวงานที่ completed แล้ว คะแนนและกราฟทั้งหมดจะแสดงที่นี่</p>
                <a href="/photographer/bookings.php" class="mt-5 inline-flex items-center rounded-full bg-red-600 px-5 py-3 font-black text-white hover:bg-neutral-950"><i class="fa-solid fa-calendar-check mr-2"></i>ดูรายการจอง</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

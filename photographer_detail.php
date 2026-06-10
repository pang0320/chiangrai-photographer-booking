<?php
require_once __DIR__ . '/includes/functions.php';
ensure_service_categories_deleted_at_column();
ensure_availability_range_columns();
ensure_booking_range_columns();

$cleanContext = clean_context_init(['id', 'slug']);
$id = 0;
if (isset($cleanContext['id'])) {
    $id = (int)$cleanContext['id'];
}
$slug = '';
if (isset($cleanContext['slug'])) {
    $slug = trim((string)$cleanContext['slug']);
}

if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $targetPhotographerId = (int)($_POST['photographer_id'] ?? 0);

    if ($action === 'favorite') {
        requireRole('customer');
        $user = current_user();
        toggle_favorite_photographer((int)$user['id'], $targetPhotographerId);
        flash('success', 'อัปเดตรายการโปรดแล้ว');
        clean_redirect('/photographer_detail.php', ['id' => $targetPhotographerId]);
    }

    if ($action === 'report_photographer' || $action === 'report_review') {
        requireLogin();
        $user = current_user();
        $targetType = 'photographer';
        if ($action === 'report_review') {
            $targetType = 'review';
        }
        $targetId = (int)($_POST['target_id'] ?? $targetPhotographerId);
        $reason = trim((string)($_POST['reason'] ?? ''));
        $detail = trim((string)($_POST['detail'] ?? ''));
        $reasonLength = strlen($reason);
        if (function_exists('mb_strlen')) {
            $reasonLength = mb_strlen($reason, 'UTF-8');
        }
        $detailLength = strlen($detail);
        if (function_exists('mb_strlen')) {
            $detailLength = mb_strlen($detail, 'UTF-8');
        }

        if ($reason === '' || $detail === '') {
            flash('error', 'กรุณากรอกเหตุผลและรายละเอียดในการรายงาน');
        } elseif ($reasonLength > 180) {
            flash('error', 'เหตุผลในการรายงานต้องไม่เกิน 180 ตัวอักษร');
        } elseif ($detailLength > 2000) {
            flash('error', 'รายละเอียดในการรายงานต้องไม่เกิน 2,000 ตัวอักษร');
        } elseif ($targetId > 0) {
            $stmt = db()->prepare('INSERT INTO reports (reporter_id, target_type, target_id, reason, detail, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, "pending", NOW(), NOW())');
            $stmt->execute([(int)$user['id'], $targetType, $targetId, $reason, $detail]);
            $reportId = (int)db()->lastInsertId();
            notify_admins('มีรายงานใหม่รอตรวจสอบ', ($targetType === 'review' ? 'รายงานรีวิว #' : 'รายงานช่างภาพ #') . $targetId, 'report', $reportId);
            flash('success', 'ส่งรายงานให้ Admin ตรวจสอบแล้ว');
        } else {
            flash('error', 'ไม่พบข้อมูลที่ต้องการรายงาน');
        }
        clean_redirect('/photographer_detail.php', ['id' => $targetPhotographerId]);
    }
}

$whereSql = 'p.id = ?';
$lookupValue = $id;
if ($slug !== '') {
    $whereSql = 'p.slug = ?';
    $lookupValue = $slug;
}

$stmt = db()->prepare('SELECT p.*, d.district_name, u.phone AS user_phone
                       FROM photographer_profiles p
                       JOIN users u ON u.id = p.user_id
                       LEFT JOIN districts d ON d.id = p.main_district_id
                       WHERE ' . $whereSql . '
                         AND p.approval_status = "approved"
                         AND p.is_available = 1
                         AND u.status = "active"
                         AND p.deleted_at IS NULL
                         AND u.deleted_at IS NULL
                       LIMIT 1');
$stmt->execute([$lookupValue]);
$profile = $stmt->fetch();
if (!$profile) {
    http_response_code(404);
    exit('Photographer not found');
}
$id = (int)$profile['id'];
db()->prepare('UPDATE photographer_profiles SET profile_views = profile_views + 1 WHERE id = ?')->execute([$id]);

$currentUser = current_user();
$isOwnPhotographerProfile = false;
if ($currentUser && (string)$currentUser['role_name'] === 'photographer' && (int)$profile['user_id'] === (int)$currentUser['id']) {
    $isOwnPhotographerProfile = true;
}
$canSendBookingRequest = true;
if ($isOwnPhotographerProfile) {
    $canSendBookingRequest = false;
}
if ($currentUser && $currentUser['role_name'] === 'customer') {
    record_recently_viewed((int)$currentUser['id'], $id);
}

$areas = db()->prepare('SELECT d.* FROM photographer_service_areas psa JOIN districts d ON d.id = psa.district_id WHERE psa.photographer_id = ? AND psa.is_active = 1 ORDER BY psa.is_primary DESC, d.district_name');
$areas->execute([$id]);
$areas = $areas->fetchAll();
ensure_tags_status_column();
ensure_photographer_articles_excerpt_column();

$services = db()->prepare('SELECT ps.*, sc.name, sc.icon FROM photographer_services ps JOIN service_categories sc ON sc.id = ps.category_id WHERE ps.photographer_id = ? AND ps.is_active = 1 AND sc.is_active = 1 AND sc.deleted_at IS NULL ORDER BY sc.sort_order');
$services->execute([$id]);
$services = $services->fetchAll();
$portfolio = db()->prepare('SELECT * FROM photographer_portfolios WHERE photographer_id = ? AND deleted_at IS NULL ORDER BY is_featured DESC, sort_order ASC, id DESC');
$portfolio->execute([$id]);
$portfolio = $portfolio->fetchAll();
$availability = db()->prepare('SELECT pa.*,
                                      COALESCE(pa.start_date, pa.available_date) AS range_start_date,
                                      COALESCE(pa.end_date, pa.available_date) AS range_end_date,
                                      COALESCE(pa.start_time, CASE pa.time_slot WHEN "morning" THEN "09:00:00" WHEN "afternoon" THEN "13:00:00" WHEN "evening" THEN "17:00:00" ELSE "09:00:00" END) AS range_start_time,
                                      COALESCE(pa.end_time, CASE pa.time_slot WHEN "morning" THEN "12:00:00" WHEN "afternoon" THEN "17:00:00" WHEN "evening" THEN "20:00:00" ELSE "17:00:00" END) AS range_end_time
                               FROM photographer_availability pa
                               WHERE pa.photographer_id = ?
                                 AND COALESCE(pa.end_date, pa.available_date) >= CURDATE()
                                 AND pa.status = "available"
                               ORDER BY COALESCE(pa.start_date, pa.available_date), COALESCE(pa.start_time, "09:00:00")
                               LIMIT 80');
$availability->execute([$id]);
$availabilityRows = $availability->fetchAll();
$availability = [];
$availabilityConflictStmt = db()->prepare('SELECT id
                                           FROM bookings
                                           WHERE photographer_id = ?
                                             AND deleted_at IS NULL
                                             AND status IN ("pending","accepted","in_progress")
                                             AND COALESCE(start_date, booking_date) <= ?
                                             AND COALESCE(end_date, booking_date) >= ?
                                             AND COALESCE(start_time, CASE time_slot WHEN "morning" THEN "09:00:00" WHEN "afternoon" THEN "13:00:00" WHEN "evening" THEN "17:00:00" ELSE "09:00:00" END) < ?
                                             AND COALESCE(end_time, CASE time_slot WHEN "morning" THEN "12:00:00" WHEN "afternoon" THEN "17:00:00" WHEN "evening" THEN "20:00:00" ELSE "17:00:00" END) > ?
                                           LIMIT 1');
foreach ($availabilityRows as $row) {
    try {
        $period = new DatePeriod(new DateTime((string)$row['range_start_date']), new DateInterval('P1D'), (new DateTime((string)$row['range_end_date']))->modify('+1 day'));
    } catch (Exception $exception) {
        continue;
    }

    foreach ($period as $date) {
        $day = $date->format('Y-m-d');
        if ($day < date('Y-m-d')) {
            continue;
        }

        $startTime = normalize_time_input((string)$row['range_start_time']);
        $endTime = normalize_time_input((string)$row['range_end_time']);
        if ($startTime === '' || $endTime === '') {
            continue;
        }

        $availabilityConflictStmt->execute([$id, $day, $day, $endTime . ':00', $startTime . ':00']);
        if ($availabilityConflictStmt->fetchColumn()) {
            continue;
        }

        $item = $row;
        $item['available_date'] = $day;
        $item['range_start_date'] = $day;
        $item['range_end_date'] = $day;
        $item['range_start_time'] = $startTime;
        $item['range_end_time'] = $endTime;
        $availability[] = $item;

        if (count($availability) >= 24) {
            break 2;
        }
    }
}
$articles = db()->prepare('SELECT a.*,
                           (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ", ")
                            FROM article_tags atg
                            JOIN tags t ON t.id = atg.tag_id
                            WHERE atg.article_id = a.id
                              AND t.is_active = 1) AS tags
                           FROM photographer_articles a
                           WHERE a.photographer_id = ? AND a.status = "published" AND a.deleted_at IS NULL
                           ORDER BY a.published_at DESC
                           LIMIT 6');
$articles->execute([$id]);
$articles = $articles->fetchAll();
$reviews = db()->prepare('SELECT r.*, u.name customer_name FROM reviews r JOIN users u ON u.id = r.customer_id WHERE r.photographer_id = ? AND r.status = "visible" AND r.deleted_at IS NULL ORDER BY r.created_at DESC');
$reviews->execute([$id]);
$reviews = $reviews->fetchAll();
$reviewImages = [];
$reviewIds = [];
foreach ($reviews as $reviewRow) {
    $reviewIds[] = (int)$reviewRow['id'];
}
if ($reviewIds) {
    $placeholders = implode(',', array_fill(0, count($reviewIds), '?'));
    $imageRows = db_fetch_all('SELECT review_id, image_path FROM review_images WHERE review_id IN (' . $placeholders . ') ORDER BY id ASC', $reviewIds);
    foreach ($imageRows as $imageRow) {
        $reviewId = (int)$imageRow['review_id'];
        if (!isset($reviewImages[$reviewId])) {
            $reviewImages[$reviewId] = [];
        }
        $reviewImages[$reviewId][] = (string)$imageRow['image_path'];
    }
}
$completedJobs = db_fetch_value('SELECT COUNT(*) FROM bookings WHERE photographer_id = ? AND status = "completed" AND deleted_at IS NULL', [$id]);
$favoriteCount = favorite_count($id);
$isFavorite = false;
if ($currentUser && $currentUser['role_name'] === 'customer') {
    $isFavorite = is_favorite_photographer((int)$currentUser['id'], $id);
}
$ratingSummaryStmt = db()->prepare('SELECT AVG(rating_quality) AS quality, AVG(rating_communication) AS communication, AVG(rating_punctuality) AS punctuality, AVG(rating_professional) AS professional FROM reviews WHERE photographer_id = ? AND status = "visible" AND deleted_at IS NULL');
$ratingSummaryStmt->execute([$id]);
$ratingSummary = $ratingSummaryStmt->fetch();
$similarPhotographers = db_fetch_all('SELECT p.*, d.district_name,
                                      (SELECT image_path FROM photographer_portfolios pp WHERE pp.photographer_id = p.id AND pp.deleted_at IS NULL ORDER BY pp.is_featured DESC, pp.sort_order ASC LIMIT 1) AS featured_image,
                                      (SELECT GROUP_CONCAT(DISTINCT sc.name ORDER BY sc.sort_order SEPARATOR ", ") FROM photographer_services ps JOIN service_categories sc ON sc.id = ps.category_id WHERE ps.photographer_id = p.id AND ps.is_active = 1 AND sc.is_active = 1 AND sc.deleted_at IS NULL) AS services
                                      FROM photographer_profiles p
                                      JOIN users u ON u.id = p.user_id
                                      LEFT JOIN districts d ON d.id = p.main_district_id
                                      LEFT JOIN (
                                          SELECT photographer_id, COUNT(*) AS completed_total
                                          FROM bookings
                                          WHERE status = "completed" AND deleted_at IS NULL
                                          GROUP BY photographer_id
                                      ) bc ON bc.photographer_id = p.id
                                      WHERE p.id <> ?
                                        AND p.approval_status = "approved"
                                        AND p.is_available = 1
                                        AND u.status = "active"
                                        AND p.deleted_at IS NULL
                                        AND u.deleted_at IS NULL
                                        AND EXISTS (SELECT 1 FROM photographer_service_areas a WHERE a.photographer_id = p.id AND a.district_id = ? AND a.is_active = 1)
                                      ORDER BY ' . ranking_order_sql('p', 'COALESCE(bc.completed_total, 0)') . '
                                      LIMIT 3', [$id, (int)$profile['main_district_id']]);
$shareUrl = APP_URL . '/photographer_detail.php';

$pageTitle = $profile['display_name'];
include __DIR__ . '/includes/header.php';
?>

<section class="relative bg-neutral-950 text-white">
    <div class="absolute inset-0">
        <img class="h-full w-full object-cover opacity-55" src="<?= h(public_image($profile['cover_image'], '/assets/uploads/seed/photo-1492691527719-9d1e07e534b4.jpg')) ?>" alt="">
        <div class="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/65 to-neutral-950/25"></div>
    </div>
    <div class="relative stock-shell px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
        <div class="grid gap-8 lg:grid-cols-[1fr_360px] lg:items-end">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <?php if ((int)$profile['is_verified'] === 1): ?>
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-neutral-950"><i class="fa-solid fa-circle-check mr-1 text-red-600"></i>ยืนยันตัวตนแล้ว</span>
                    <?php endif; ?>
                    <?php if ((int)$profile['is_featured'] === 1): ?>
                        <span class="rounded-full bg-yellow-300 px-3 py-1 text-xs font-black text-neutral-950"><i class="fa-solid fa-award mr-1"></i>ช่างภาพแนะนำ</span>
                    <?php endif; ?>
                    <span class="rounded-full bg-red-600 px-3 py-1 text-xs font-black text-white"><i class="fa-solid fa-star mr-1"></i>คะแนนเฉลี่ย <?= number_format((float)$profile['average_rating'], 1) ?></span>
                    <span class="rounded-full bg-white/14 px-3 py-1 text-xs font-black text-white"><i class="fa-solid fa-comment mr-1 text-red-300"></i>จำนวนรีวิว <?= number_format((int)$profile['total_reviews']) ?> รายการ</span>
                    <span class="rounded-full bg-white/14 px-3 py-1 text-xs font-black text-white"><i class="fa-solid fa-bolt mr-1 text-yellow-300"></i>ตอบกลับไว</span>
                    <?php if ((float)$profile['average_rating'] >= 4.8): ?>
                        <span class="rounded-full bg-white/14 px-3 py-1 text-xs font-black text-white"><i class="fa-solid fa-award mr-1 text-yellow-300"></i>คะแนนสูง</span>
                    <?php endif; ?>
                </div>
                <p class="mt-6 text-sm font-black uppercase tracking-[0.22em] text-red-300">
                    <i class="fa-solid fa-id-card mr-2"></i>ข้อมูลช่างภาพ
                </p>
                <h1 class="mt-5 text-4xl font-black tracking-tight sm:text-6xl"><?= h($profile['display_name']) ?></h1>
                <p class="mt-4 max-w-3xl text-lg leading-8 text-white/78"><?= nl2br(h($profile['bio'])) ?></p>
                <div class="mt-6 flex flex-wrap gap-3 text-sm font-black">
                    <span class="rounded-full bg-white/12 px-4 py-2"><i class="fa-solid fa-location-dot mr-2 text-red-300"></i><?= h($profile['district_name']) ?></span>
                    <span class="rounded-full bg-white/12 px-4 py-2"><i class="fa-solid fa-briefcase mr-2 text-red-300"></i><?= (int)$profile['experience_years'] ?> ปี</span>
                    <span class="rounded-full bg-white/12 px-4 py-2"><i class="fa-solid fa-tag mr-2 text-red-300"></i>ราคาเริ่มต้นโดยประมาณ <?= number_format((float)$profile['starting_price']) ?> บาท</span>
                </div>
                <div class="mt-8 grid max-w-4xl gap-3 sm:grid-cols-4">
                    <div class="stat-pill rounded-3xl p-4"><p class="text-3xl font-black"><?= number_format((float)$profile['average_rating'], 1) ?></p><p class="text-sm font-bold text-white/68">คะแนนเฉลี่ย</p></div>
                    <div class="stat-pill rounded-3xl p-4"><p class="text-3xl font-black"><?= (int)$profile['total_reviews'] ?></p><p class="text-sm font-bold text-white/68">จำนวนรีวิว</p></div>
                    <div class="stat-pill rounded-3xl p-4"><p class="text-3xl font-black"><?= (int)$completedJobs ?></p><p class="text-sm font-bold text-white/68">จำนวนงานสำเร็จ</p></div>
                    <div class="stat-pill rounded-3xl p-4">
                        <p class="text-3xl font-black"><?= number_format((float)$profile['response_rate'], 0) ?>%</p>
                        <p class="text-sm font-bold text-white/68">อัตราตอบกลับ</p>
                        <p class="mt-2 text-xs font-bold leading-5 text-white/58">คิดจากคำขอที่ช่างภาพตอบรับหรือปฏิเสธ เทียบกับคำขอทั้งหมด</p>
                    </div>
                </div>
            </div>
            <div class="glass-panel relative rounded-[2rem] p-5 text-neutral-950 lg:sticky lg:top-24">
                <?php if (current_user()): ?>
                    <details class="group absolute right-4 top-4 z-20 open:z-50 hover:z-50 focus-within:z-50">
                        <summary class="grid h-10 w-10 cursor-pointer list-none place-items-center rounded-full bg-white border border-neutral-200 text-neutral-600 shadow-sm transition hover:bg-neutral-950 hover:text-white group-open:bg-neutral-950 group-open:text-white" title="เมนูเพิ่มเติม">
                            <i class="fa-solid fa-ellipsis"></i>
                        </summary>
                        <div class="absolute right-0 top-12 w-72 rounded-2xl border border-neutral-200 bg-white p-2 text-left shadow-2xl shadow-neutral-950/15 ring-1 ring-black/5">
                            <details class="group/report">
                                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-black text-neutral-700 hover:bg-neutral-50 hover:text-red-600 group-open/report:bg-neutral-50 group-open/report:text-red-600">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    รายงานโปรไฟล์นี้
                                </summary>
                                <div class="mt-2 border-t border-neutral-100 pt-3">
                                    <p class="mb-3 text-[11px] font-bold leading-5 text-neutral-500">ส่งให้ผู้ดูแลระบบตรวจสอบปัญหาที่พบ</p>
                                    <form method="post" class="grid gap-3">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="report_photographer">
                                        <input type="hidden" name="photographer_id" value="<?= (int)$profile['id'] ?>">
                                        <input type="hidden" name="target_id" value="<?= (int)$profile['id'] ?>">
                                        <label class="grid gap-1 text-[11px] font-black text-neutral-600">
                                            <span>เหตุผลในการรายงาน <?= required_mark() ?></span>
                                            <input name="reason" required maxlength="180" placeholder="เช่น ข้อมูลติดต่อไม่ถูกต้อง" class="stock-input rounded-xl px-3 py-2 text-sm">
                                        </label>
                                        <label class="grid gap-1 text-[11px] font-black text-neutral-600">
                                            <span>รายละเอียดเพิ่มเติม <?= required_mark() ?></span>
                                            <textarea name="detail" required maxlength="2000" rows="3" placeholder="พิมพ์รายละเอียดปัญหาที่พบ เพื่อให้ผู้ดูแลตรวจสอบได้ชัดเจน" class="stock-input rounded-xl px-3 py-2 text-sm"></textarea>
                                        </label>
                                        <button class="btn-danger btn-sm w-full rounded-xl">
                                            <i class="fa-solid fa-paper-plane mr-2"></i>ส่งรายงาน
                                        </button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    </details>
                <?php endif; ?>

                <div class="flex items-center gap-4">
                    <img class="h-20 w-20 rounded-3xl object-cover" src="<?= h(public_image($profile['profile_image'], '/assets/uploads/seed/photo-1500648767791-00dcc994a43e.jpg')) ?>" alt="">
                    <div class="pr-12">
                        <p class="font-black"><?= h($profile['display_name']) ?></p>
                        <p class="mt-1 text-sm font-semibold text-neutral-500"><?= h($profile['district_name']) ?></p>
                    </div>
                </div>
                <p class="mt-5 rounded-2xl bg-red-50 p-4 text-sm font-black leading-7 text-red-700"><?= h(PAYMENT_DISCLAIMER) ?> ราคาเริ่มต้นโดยประมาณใช้เป็นข้อมูลประกอบการตัดสินใจ ลูกค้าและช่างภาพต้องตกลงราคาและการชำระเงินกันเองภายนอกระบบ</p>
                <div class="mt-4 grid grid-cols-2 gap-2 text-sm font-black">
                    <div class="info-tile rounded-2xl p-3"><p class="text-xs text-neutral-500"><i class="fa-solid fa-heart mr-1 text-red-600"></i>บันทึกเป็นรายการโปรด</p><b><?= (int)$favoriteCount ?> คน</b></div>
                    <div class="info-tile rounded-2xl p-3">
                        <p class="text-xs text-neutral-500"><i class="fa-solid fa-clock mr-1 text-red-600"></i>เวลาตอบกลับเฉลี่ย</p>
                        <b><?= number_format((float)$profile['average_response_hours'], 1) ?> ชม.</b>
                        <p class="mt-1 text-xs font-bold leading-5 text-neutral-500">คำนวณจากเวลาที่ใช้ตอบรับหรือปฏิเสธคำขอจอง</p>
                    </div>
                </div>
                <div class="mt-4 grid gap-2">
                    <?php if ($canSendBookingRequest): ?>
                        <a href="#availability" class="btn-cta btn-lg block w-full text-center"><i class="fa-solid fa-calendar-days mr-2"></i>เลือกวันว่างเพื่อจอง</a>
                    <?php else: ?>
                        <a href="/photographer/profile.php" class="btn-muted btn-lg block w-full text-center"><i class="fa-solid fa-user-check mr-2"></i>นี่คือโปรไฟล์ของคุณ</a>
                    <?php endif; ?>
                    <?php if ($currentUser && $currentUser['role_name'] === 'customer'): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="favorite">
                            <input type="hidden" name="photographer_id" value="<?= (int)$profile['id'] ?>">
                            <button class="btn-muted btn-lg w-full text-center">
                                <?php if ($isFavorite): ?>
                                    <i class="fa-solid fa-heart-crack mr-2 text-red-600"></i>ยกเลิกรายการโปรด
                                <?php else: ?>
                                    <i class="fa-solid fa-heart mr-2 text-red-600"></i>บันทึกช่างภาพ
                                <?php endif; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-2">
                    <button type="button" onclick="navigator.clipboard.writeText('<?= h($shareUrl) ?>'); Swal.fire({icon:'success',title:'คัดลอกลิงก์แล้ว',timer:1600,showConfirmButton:false});" class="rounded-full bg-neutral-100 px-3 py-2 text-xs font-black hover:bg-neutral-950 hover:text-white"><i class="fa-solid fa-copy mr-1"></i>คัดลอก</button>
                    <a target="_blank" href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shareUrl) ?>" class="rounded-full bg-[#1877f2] px-3 py-2 text-center text-xs font-black text-white"><i class="fa-brands fa-facebook mr-1"></i>แชร์</a>
                    <a target="_blank" href="https://social-plugins.line.me/lineit/share?url=<?= urlencode($shareUrl) ?>" class="rounded-full bg-[#06c755] px-3 py-2 text-center text-xs font-black text-white"><i class="fa-brands fa-line mr-1"></i>LINE</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="mx-auto w-full max-w-[1760px] px-4 py-12 sm:px-6 lg:px-8">
    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_300px] xl:grid-cols-[minmax(0,1fr)_280px]">
        <div class="space-y-12">
            <div id="services" class="scroll-mt-28">
                <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600"><i class="fa-solid fa-layer-group mr-2"></i>ประเภทงาน</p>
                <h2 class="mt-1 text-3xl font-black text-neutral-950">ประเภทงานที่รับและราคาเริ่มต้นโดยประมาณ</h2>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <?php foreach ($services as $s): ?>
                        <?php
                        $serviceIcon = 'fa-camera';
                        if (!empty($s['icon'])) {
                            $serviceIcon = $s['icon'];
                        }
                        ?>
                        <div class="stock-card rounded-[1.5rem] p-6">
                            <div class="flex items-start justify-between gap-4">
                                <h3 class="font-black text-neutral-950"><i class="fa-solid <?= h($serviceIcon) ?> mr-2 text-red-600"></i><?= h($s['name']) ?></h3>
                                <span class="rounded-full bg-neutral-950 px-3 py-1 text-xs font-black text-white">ราคาเริ่มต้นโดยประมาณ <?= number_format((float)$s['starting_price']) ?> บาท</span>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-neutral-600"><?= h($s['description']) ?></p>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$services): ?>
                        <div class="empty-state rounded-[2rem] p-8 text-center md:col-span-2">
                            <i class="fa-solid fa-layer-group text-4xl text-red-600"></i>
                            <h3 class="mt-3 text-xl font-black">ยังไม่ได้ระบุประเภทงาน</h3>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="availability" class="scroll-mt-28">
                <?php
                $availabilityCount = count($availability);
                $availabilityIsSlider = $availabilityCount > 4;
                $availabilityLayoutClass = 'mt-6 grid gap-4';
                $availabilityCardClass = 'stock-card stock-card-hover min-h-[152px] w-full rounded-[1.35rem] p-5 text-left transition hover:-translate-y-1 hover:shadow-2xl';
                $availabilityStaticCardClass = 'stock-card min-h-[152px] w-full rounded-[1.35rem] p-5 text-left';

                if ($availabilityCount === 2) {
                    $availabilityLayoutClass .= ' sm:grid-cols-2';
                } elseif ($availabilityCount === 3) {
                    $availabilityLayoutClass .= ' sm:grid-cols-2 xl:grid-cols-3';
                } elseif ($availabilityCount === 4) {
                    $availabilityLayoutClass .= ' sm:grid-cols-2 xl:grid-cols-4';
                } elseif ($availabilityIsSlider) {
                    $availabilityLayoutClass = 'mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-4 pr-2';
                    $availabilityCardClass = 'stock-card stock-card-hover min-h-[152px] w-[82vw] shrink-0 snap-start rounded-[1.35rem] p-5 text-left transition hover:-translate-y-1 hover:shadow-2xl sm:w-[330px] lg:w-[calc((100%_-_3rem)/4)] lg:min-w-[260px]';
                    $availabilityStaticCardClass = 'stock-card min-h-[152px] w-[82vw] shrink-0 snap-start rounded-[1.35rem] p-5 text-left sm:w-[330px] lg:w-[calc((100%_-_3rem)/4)] lg:min-w-[260px]';
                }
                ?>
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600"><i class="fa-solid fa-calendar-days mr-2"></i>วันที่ต้องการจ้าง</p>
                        <h2 class="mt-1 text-3xl font-black text-neutral-950">ปฏิทินวันว่างสำหรับส่งคำขอจอง</h2>
                        <p class="mt-2 max-w-2xl text-base font-semibold leading-7 text-neutral-600">เลือกได้เฉพาะวันที่ช่างภาพเปิดว่าง ระบบจะตรวจซ้ำอีกครั้งตอนส่งคำขอจอง</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <?php if ($availabilityIsSlider): ?>
                            <div class="rounded-full bg-red-50 px-4 py-2 text-sm font-black text-red-700">
                                <i class="fa-solid fa-arrows-left-right mr-1"></i>เลื่อนซ้าย-ขวาเพื่อดูวันว่างทั้งหมด
                            </div>
                        <?php endif; ?>
                        <?php if ($canSendBookingRequest): ?>
                            <?= clean_context_button('/customer/create_booking.php', ['photographer_id' => $id], '<i class="fa-solid fa-calendar-plus mr-2"></i>จองคิว', 'btn-danger btn-lg shadow-xl shadow-red-600/30') ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="<?= h($availabilityLayoutClass) ?>">
                    <?php foreach ($availability as $a): ?>
                        <?php
                        $availabilityCard = '<p class="font-black text-neutral-950"><i class="fa-solid fa-calendar-day mr-2 text-red-600"></i>' . h(format_be_date($a['available_date'])) . '</p>'
                            . '<p class="mt-2 text-sm font-bold text-neutral-600"><i class="fa-solid fa-clock mr-1 text-red-600"></i>' . h(format_booking_time_range($a['range_start_time'], $a['range_end_time'])) . '</p>'
                            . '<p class="mt-4 inline-flex items-center rounded-full bg-red-600 px-4 py-2 text-sm font-black text-white shadow-lg shadow-red-600/20"><i class="fa-solid fa-calendar-check mr-2"></i>เลือกวันนี้และจอง</p>';
                        if ($canSendBookingRequest) {
                            echo clean_context_button(
                                '/customer/create_booking.php',
                                [
                                    'photographer_id' => (int)$profile['id'],
                                    'start_date' => (string)$a['available_date'],
                                    'end_date' => (string)$a['available_date'],
                                    'start_time' => format_time_hm((string)$a['range_start_time']),
                                    'end_time' => format_time_hm((string)$a['range_end_time']),
                                    'time_slot' => (string)$a['time_slot'],
                                ],
                                $availabilityCard,
                                $availabilityCardClass,
                                'contents'
                            );
                        } else {
                            echo '<div class="' . h($availabilityStaticCardClass) . '">'
                                . '<p class="font-black text-neutral-950"><i class="fa-solid fa-calendar-day mr-2 text-red-600"></i>' . h(format_be_date($a['available_date'])) . '</p>'
                                . '<p class="mt-2 text-sm font-bold text-neutral-600"><i class="fa-solid fa-clock mr-1 text-red-600"></i>' . h(format_booking_time_range($a['range_start_time'], $a['range_end_time'])) . '</p>'
                                . '<p class="mt-4 inline-flex items-center rounded-full bg-neutral-100 px-4 py-2 text-sm font-black text-neutral-600"><i class="fa-solid fa-user-check mr-2"></i>วันว่างในโปรไฟล์ของคุณ</p>'
                                . '</div>';
                        }
                        ?>
                    <?php endforeach; ?>
                    <?php if (!$availability): ?>
                        <div class="empty-state w-full rounded-[2rem] p-10 text-center">
                            <i class="fa-solid fa-calendar-xmark text-4xl text-red-600"></i>
                            <h3 class="mt-3 text-xl font-black">ยังไม่มีวันว่างที่เปิดไว้</h3>
                            <p class="mt-2 text-neutral-600">ติดต่อช่างภาพโดยตรงเพื่อสอบถามวันว่างเพิ่มเติมได้</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="portfolio" class="scroll-mt-28">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600"><i class="fa-solid fa-images mr-2"></i>อัลบั้มตัวอย่างงาน</p>
                        <h2 class="mt-1 text-3xl font-black text-neutral-950">ตัวอย่างงานถ่ายภาพ</h2>
                        <p class="mt-2 max-w-2xl text-base font-semibold leading-7 text-neutral-600">ขยายพื้นที่แสดงภาพให้กว้างขึ้น เพื่อเห็นโทนงานและรายละเอียดภาพได้ชัดเจนกว่าเดิม</p>
                    </div>
                </div>
                <?php if (!$portfolio): ?>
                    <div class="empty-state mt-6 rounded-[2rem] p-10 text-center">
                        <i class="fa-solid fa-images text-4xl text-red-600"></i>
                        <h3 class="mt-3 text-xl font-black">ยังไม่มีตัวอย่างงานถ่ายภาพ</h3>
                        <p class="mt-2 text-neutral-600">เมื่อช่างภาพอัปโหลดอัลบั้มตัวอย่างงาน รูปจะแสดงในส่วนนี้</p>
                    </div>
                <?php endif; ?>
                <div class="masonry-gallery photographer-detail-portfolio-gallery mt-6">
                    <?php foreach ($portfolio as $i => $item): ?>
                        <?php
                        $portfolioImageClass = 'min-h-[240px] w-full object-cover transition-transform duration-500 group-hover:scale-105';
                        if ($i === 0) {
                            $portfolioImageClass = 'min-h-[420px] w-full object-cover transition-transform duration-500 group-hover:scale-105';
                        }
                        ?>
                        <figure class="media-tile rounded-[1.5rem] shadow-xl cursor-pointer relative overflow-hidden group"
                                onclick="openPortfolioModal('<?= h(public_image($item['image_path'], '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg')) ?>', '<?= h(addslashes($item['title'])) ?>', '<?= h(addslashes($profile['display_name'])) ?>')">
                            <img class="<?= h($portfolioImageClass) ?>" src="<?= h(public_image($item['image_path'], '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg')) ?>" alt="">
                            <figcaption class="media-overlay p-5 opacity-100">
                                <div>
                                    <b class="text-lg"><?= h($item['title']) ?></b>
                                    <p class="mt-1 line-clamp-2 text-sm text-white/75"><?= h($item['description']) ?></p>
                                </div>
                            </figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="reviews" class="scroll-mt-28">
                <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">รีวิว</p>
                <h2 class="mt-1 text-3xl font-black text-neutral-950">รีวิวจากลูกค้า</h2>
                <div class="stock-card mt-6 rounded-[1.75rem] p-6">
                    <div class="grid gap-6 md:grid-cols-[220px_1fr] md:items-center">
                        <div class="text-center">
                            <p class="text-6xl font-black text-neutral-950"><?= number_format((float)$profile['average_rating'], 1) ?></p>
                            <p class="mt-2 text-red-600"><?= str_repeat('★', (int)round((float)$profile['average_rating'])) ?></p>
                            <p class="mt-1 text-sm font-bold text-neutral-500">จำนวนรีวิว <?= number_format((int)$profile['total_reviews']) ?> รายการ</p>
                        </div>
                        <div class="grid gap-3">
                            <?php
                            $ratingQuality = 0;
                            $ratingCommunication = 0;
                            $ratingPunctuality = 0;
                            $ratingProfessional = 0;
                            if (!empty($ratingSummary['quality'])) {
                                $ratingQuality = (float)$ratingSummary['quality'];
                            }
                            if (!empty($ratingSummary['communication'])) {
                                $ratingCommunication = (float)$ratingSummary['communication'];
                            }
                            if (!empty($ratingSummary['punctuality'])) {
                                $ratingPunctuality = (float)$ratingSummary['punctuality'];
                            }
                            if (!empty($ratingSummary['professional'])) {
                                $ratingProfessional = (float)$ratingSummary['professional'];
                            }
                            ?>
                            <?php foreach ([['คุณภาพงาน', $ratingQuality], ['การสื่อสาร', $ratingCommunication], ['ตรงเวลา', $ratingPunctuality], ['ความเป็นมืออาชีพ', $ratingProfessional]] as $ratingRow): ?>
                                <?php $ratingPercent = min(100, max(0, ((float)$ratingRow[1] / 5) * 100)); ?>
                                <div>
                                    <div class="mb-1 flex justify-between text-sm font-black"><span>คะแนน: <?= h($ratingRow[0]) ?></span><span><?= number_format((float)$ratingRow[1], 1) ?>/5</span></div>
                                    <div class="rating-bar"><span style="width: <?= number_format($ratingPercent, 0) ?>%"></span></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="mt-6 grid gap-4">
                    <?php foreach ($reviews as $r): ?>
                        <?php
                        $currentReviewImages = [];
                        if (isset($reviewImages[(int)$r['id']])) {
                            $currentReviewImages = $reviewImages[(int)$r['id']];
                        }
                        ?>
                        <div class="relative">
                            <article class="stock-card rounded-[1.5rem] p-6">
                                <div class="flex flex-wrap items-center gap-3 pr-10 sm:pr-14">
                                    <b class="text-neutral-950"><?= h($r['customer_name']) ?></b>
                                    <span class="text-red-600" title="คะแนนรวม <?= (int)$r['rating_overall'] ?> จาก 5"><?= str_repeat('★', (int)$r['rating_overall']) ?></span>
                                </div>
                                <p class="mt-3 leading-7 text-neutral-700"><?= nl2br(h($r['comment'])) ?></p>
                                <?php if ($currentReviewImages): ?>
                                    <div class="mt-5">
                                        <p class="mb-3 text-sm font-black text-neutral-700">
                                            <i class="fa-solid fa-images mr-2 text-red-600"></i>รูปภาพประกอบรีวิว
                                        </p>
                                        <div class="flex gap-3 overflow-x-auto pb-2 sm:grid sm:grid-cols-4 sm:overflow-visible">
                                            <?php foreach ($currentReviewImages as $imagePath): ?>
                                                <a href="<?= h(public_image($imagePath, '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg')) ?>" target="_blank" class="group relative h-28 w-36 shrink-0 overflow-hidden rounded-2xl bg-neutral-100 shadow-sm sm:h-32 sm:w-full">
                                                    <img src="<?= h(public_image($imagePath, '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg')) ?>" alt="รูปภาพประกอบรีวิว" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                                                    <span class="absolute inset-0 grid place-items-center bg-neutral-950/0 text-white opacity-0 transition group-hover:bg-neutral-950/35 group-hover:opacity-100">
                                                        <i class="fa-solid fa-up-right-and-down-left-from-center"></i>
                                                    </span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </article>

                            <?php if ($currentUser): ?>
                                <details class="group absolute -right-2 -top-2 z-20 open:z-50 hover:z-50 focus-within:z-50">
                                    <summary class="grid h-9 w-9 cursor-pointer list-none place-items-center rounded-full bg-white border border-neutral-200 text-neutral-600 shadow-md transition hover:bg-neutral-950 hover:text-white group-open:bg-neutral-950 group-open:text-white" title="เมนูรีวิว">
                                        <i class="fa-solid fa-ellipsis"></i>
                                    </summary>
                                    <div class="absolute right-0 top-11 w-72 rounded-2xl border border-neutral-200 bg-white p-2 text-left shadow-2xl shadow-neutral-950/15 ring-1 ring-black/5">
                                        <details class="group/report">
                                            <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-black text-neutral-700 hover:bg-neutral-50 hover:text-red-600 group-open/report:bg-neutral-50 group-open/report:text-red-600">
                                                <i class="fa-solid fa-triangle-exclamation"></i>
                                                รายงานรีวิวนี้
                                            </summary>
                                            <div class="mt-2 border-t border-neutral-100 pt-3">
                                                <p class="mb-3 text-[11px] font-bold leading-5 text-neutral-500">ส่งให้ผู้ดูแลระบบตรวจสอบ โดยไม่แจ้งเจ้าของรีวิวว่าใครเป็นผู้รายงาน</p>
                                                <form method="post" class="grid gap-3">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="report_review">
                                                    <input type="hidden" name="photographer_id" value="<?= (int)$profile['id'] ?>">
                                                    <input type="hidden" name="target_id" value="<?= (int)$r['id'] ?>">
                                                    <label class="grid gap-1 text-[11px] font-black text-neutral-600">
                                                        <span>เหตุผลในการรายงาน <?= required_mark() ?></span>
                                                        <input name="reason" required maxlength="180" placeholder="เช่น รีวิวไม่เหมาะสม" class="stock-input rounded-xl px-3 py-2 text-sm">
                                                    </label>
                                                    <label class="grid gap-1 text-[11px] font-black text-neutral-600">
                                                        <span>รายละเอียดเพิ่มเติม <?= required_mark() ?></span>
                                                        <textarea name="detail" required maxlength="2000" rows="3" placeholder="พิมพ์รายละเอียดปัญหาที่ต้องการให้ผู้ดูแลตรวจสอบ" class="stock-input rounded-xl px-3 py-2 text-sm"></textarea>
                                                    </label>
                                                    <button class="btn-danger btn-sm w-full rounded-xl">
                                                        <i class="fa-solid fa-triangle-exclamation"></i>ส่งรายงาน
                                                    </button>
                                                </form>
                                            </div>
                                        </details>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$reviews): ?>
                        <div class="empty-state rounded-[2rem] p-10 text-center">
                            <i class="fa-solid fa-star-half-stroke text-4xl text-red-600"></i>
                            <h3 class="mt-3 text-xl font-black">ยังไม่มีรีวิว</h3>
                            <p class="mt-2 text-neutral-600">รีวิวจะแสดงหลังลูกค้าทำงานเสร็จและรายการจองเป็นสถานะเสร็จสิ้น</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="articles" class="scroll-mt-28">
                <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">บทความ</p>
                <h2 class="mt-1 text-3xl font-black text-neutral-950">บทความจากช่างภาพ</h2>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <?php foreach ($articles as $a): ?>
                        <article class="stock-card rounded-[1.5rem] p-6">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <?= new_content_badge($a['published_at'] ?: $a['created_at']) ?>
                                <span class="text-xs font-black text-neutral-400"><i class="fa-solid fa-calendar-day mr-1 text-red-600"></i><?= h(format_be_datetime($a['published_at'] ?: $a['created_at'])) ?></span>
                            </div>
                            <h3 class="font-black text-neutral-950"><i class="fa-solid fa-newspaper mr-2 text-red-600"></i><?= h($a['title']) ?></h3>
                            <?php if (!empty($a['tags'])): ?>
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    <?php foreach (array_slice(array_filter(array_map('trim', explode(',', (string)$a['tags']))), 0, 4) as $tagName): ?>
                                        <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-black text-red-700"><i class="fa-solid fa-tag mr-1"></i><?= h($tagName) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php
                            $articleExcerpt = trim((string)($a['excerpt'] ?? ''));
                            if ($articleExcerpt === '') {
                                $articleExcerpt = strip_tags((string)$a['content']);
                            }
                            ?>
                            <p class="mt-3 line-clamp-3 text-sm leading-7 text-neutral-600"><?= h($articleExcerpt) ?></p>
                            <?= clean_context_button('/article_detail.php', ['slug' => $a['slug']], '<i class="fa-solid fa-eye mr-1"></i>อ่านต่อ', 'mt-4 inline-flex rounded-full bg-neutral-950 px-4 py-2 text-xs font-black text-white hover:bg-red-600') ?>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$articles): ?>
                        <div class="empty-state rounded-[2rem] p-10 text-center md:col-span-2">
                            <i class="fa-solid fa-newspaper text-4xl text-red-600"></i>
                            <h3 class="mt-3 text-xl font-black">ยังไม่มีบทความจากช่างภาพ</h3>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="booking-details-guide" class="scroll-mt-28">
                <div class="stock-card rounded-[1.75rem] p-7">
                    <p class="section-kicker"><i class="fa-solid fa-clipboard-list mr-2"></i>รายละเอียดคำขอจอง</p>
                    <h2 class="mt-1 text-3xl font-black text-neutral-950">ข้อมูลที่ควรเตรียมก่อนส่งคำขอจอง</h2>
                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <div class="rounded-[1.4rem] bg-neutral-50 p-5">
                            <h3 class="font-black text-neutral-950"><i class="fa-solid fa-camera mr-2 text-red-600"></i>ลักษณะงาน</h3>
                            <p class="mt-2 text-base font-semibold leading-7 text-neutral-600">ประเภทงาน สถานที่ จำนวนคน ช่วงเวลาที่ต้องการ และรูปแบบภาพที่อยากได้</p>
                        </div>
                        <div class="rounded-[1.4rem] bg-neutral-50 p-5">
                            <h3 class="font-black text-neutral-950"><i class="fa-solid fa-phone mr-2 text-red-600"></i>ข้อมูลติดต่อกลับ</h3>
                            <p class="mt-2 text-base font-semibold leading-7 text-neutral-600">ชื่อผู้ติดต่อ เบอร์โทร และช่องทางที่สะดวก เช่น LINE หรือ Facebook</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="contact" class="scroll-mt-28">
                <div class="stock-card rounded-[1.75rem] p-7">
                    <p class="section-kicker"><i class="fa-solid fa-address-book mr-2"></i>ช่องทางติดต่อ</p>
                    <h2 class="mt-1 text-3xl font-black text-neutral-950">ช่องทางติดต่อช่างภาพโดยตรง</h2>
                    <p class="mt-4 leading-7 text-neutral-600">ลูกค้าต้องเข้าสู่ระบบก่อนส่งคำขอจอง หลังจากช่างภาพตอบรับ สามารถติดต่อผ่านช่องทางภายนอกเพื่อตกลงรายละเอียด ราคา และการชำระเงินได้โดยตรง</p>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <?php if ($profile['phone_public']): ?><a class="rounded-2xl bg-neutral-50 px-4 py-3 font-black hover:bg-neutral-950 hover:text-white" href="tel:<?= h($profile['phone_public']) ?>"><i class="fa-solid fa-phone mr-2 text-red-600"></i><?= h($profile['phone_public']) ?></a><?php endif; ?>
                        <?php if ($profile['line_id']): ?><a class="rounded-2xl bg-neutral-50 px-4 py-3 font-black hover:bg-[#06c755] hover:text-white" href="https://line.me/ti/p/~<?= h($profile['line_id']) ?>"><i class="fa-brands fa-line mr-2"></i><?= h($profile['line_id']) ?></a><?php endif; ?>
                        <?php if ($profile['facebook_url']): ?><a class="rounded-2xl bg-neutral-50 px-4 py-3 font-black hover:bg-[#1877f2] hover:text-white" href="<?= h($profile['facebook_url']) ?>" target="_blank"><i class="fa-brands fa-facebook mr-2"></i>Facebook</a><?php endif; ?>
                        <?php if ($profile['instagram_url']): ?><a class="rounded-2xl bg-neutral-50 px-4 py-3 font-black hover:bg-[#d62976] hover:text-white" href="<?= h($profile['instagram_url']) ?>" target="_blank"><i class="fa-brands fa-instagram mr-2"></i>Instagram</a><?php endif; ?>
                        <?php if ($profile['website_url']): ?><a class="rounded-2xl bg-neutral-50 px-4 py-3 font-black hover:bg-neutral-950 hover:text-white" href="<?= h($profile['website_url']) ?>" target="_blank"><i class="fa-solid fa-globe mr-2 text-red-600"></i>Website</a><?php endif; ?>
                    </div>
                    <div class="mt-6 rounded-[1.5rem] bg-red-50 p-5 text-sm font-black leading-7 text-red-700"><?= h(PAYMENT_DISCLAIMER) ?></div>
                </div>
            </div>
        </div>

        <aside class="space-y-5">
            <div class="stock-card rounded-[1.5rem] p-6">
                <p class="section-kicker"><i class="fa-solid fa-location-dot mr-2"></i>พื้นที่รับงาน</p>
                <h2 class="mt-1 font-black text-neutral-950">อำเภอที่ช่างภาพรับงาน</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    <?php foreach ($areas as $area): ?>
                        <span class="rounded-full bg-neutral-100 px-3 py-1.5 text-sm font-bold text-neutral-700">
                            <?= h($area['district_name']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>
    </div>

    <section class="mt-14">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="section-kicker">ช่างภาพที่คล้ายกัน</p>
                <h2 class="mt-1 text-3xl font-black text-neutral-950">ช่างภาพใกล้เคียงที่น่าสนใจ</h2>
            </div>
            <?= clean_context_button('/photographers.php', ['district_id' => (int)$profile['main_district_id']], '<i class="fa-solid fa-location-dot mr-2"></i>ดูในพื้นที่นี้', 'rounded-full border border-neutral-200 px-5 py-3 font-black hover:bg-neutral-950 hover:text-white') ?>
        </div>
        <div class="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($similarPhotographers as $p): ?>
                <?php include __DIR__ . '/includes/photographer_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="mt-14 grid gap-5 md:grid-cols-2">
        <div class="stock-card rounded-[1.75rem] p-7">
            <p class="section-kicker">คำถามที่พบบ่อย</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950">การจองและติดต่อ</h2>
            <p class="mt-4 leading-7 text-neutral-600">ลูกค้าต้องเข้าสู่ระบบก่อนส่งคำขอจอง หลังจากช่างภาพตอบรับ สามารถติดต่อผ่านช่องทางภายนอกเพื่อตกลงรายละเอียด ราคา และการชำระเงินได้โดยตรง</p>
        </div>
        <div class="stock-card rounded-[1.75rem] bg-red-50 p-7">
            <p class="section-kicker">หมายเหตุสำคัญ</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950">ไม่มีระบบชำระเงิน</h2>
            <p class="mt-4 font-bold leading-7 text-red-700"><?= h(PAYMENT_DISCLAIMER) ?></p>
        </div>
    </section>
</section>

<!-- Portfolio Modal -->
<div id="portfolio-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-neutral-950/90 px-4 py-8 backdrop-blur-md" aria-hidden="true">
    <div data-portfolio-modal-backdrop class="absolute inset-0 cursor-pointer"></div>
    <div class="relative z-10 flex max-h-full w-full max-w-5xl flex-col overflow-hidden rounded-[2rem] bg-neutral-900 shadow-2xl ring-1 ring-white/10">
        <div class="flex items-center justify-between border-b border-white/10 bg-neutral-950/50 px-6 py-4">
            <h3 class="text-xl font-black text-white line-clamp-1 flex-1 pr-4" id="portfolio-modal-title"></h3>
            <button type="button" data-portfolio-modal-close class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white hover:text-neutral-950" aria-label="ปิดหน้าต่าง">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-4 md:p-8 flex flex-col items-center justify-center bg-black/50">
            <img id="portfolio-modal-img" class="max-h-[60vh] w-auto max-w-full rounded-xl object-contain shadow-lg" src="" alt="">
            <div class="mt-8 flex flex-col items-center gap-4 text-center">
                <div>
                    <p class="text-sm font-bold text-neutral-400">ภาพโดย</p>
                    <p class="text-lg font-black text-white" id="portfolio-modal-owner"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const portfolioModal = document.getElementById('portfolio-modal');
    const portfolioModalImg = document.getElementById('portfolio-modal-img');
    const portfolioModalTitle = document.getElementById('portfolio-modal-title');
    const portfolioModalOwner = document.getElementById('portfolio-modal-owner');
    const portfolioCloseButtons = document.querySelectorAll('[data-portfolio-modal-close], [data-portfolio-modal-backdrop]');

    function openPortfolioModal(imgSrc, title, owner) {
        if (!portfolioModal) return;
        
        portfolioModalImg.src = imgSrc;
        portfolioModalTitle.textContent = title;
        portfolioModalOwner.textContent = owner;

        portfolioModal.classList.remove('hidden');
        portfolioModal.classList.add('flex');
        portfolioModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closePortfolioModal() {
        if (!portfolioModal) return;
        portfolioModal.classList.add('hidden');
        portfolioModal.classList.remove('flex');
        portfolioModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        
        // Reset 
        setTimeout(() => {
            portfolioModalImg.src = '';
        }, 300);
    }

    portfolioCloseButtons.forEach(btn => {
        btn.addEventListener('click', closePortfolioModal);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && portfolioModal && !portfolioModal.classList.contains('hidden')) {
            closePortfolioModal();
        }
    });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/functions.php';

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
        if ($reason !== '' && $targetId > 0) {
            $stmt = db()->prepare('INSERT INTO reports (reporter_id, target_type, target_id, reason, detail, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, "pending", NOW(), NOW())');
            $stmt->execute([(int)$user['id'], $targetType, $targetId, $reason, $detail]);
            flash('success', 'ส่งรายงานให้ Admin ตรวจสอบแล้ว');
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
if ($currentUser && $currentUser['role_name'] === 'customer') {
    record_recently_viewed((int)$currentUser['id'], $id);
}

$areas = db()->prepare('SELECT d.* FROM photographer_service_areas psa JOIN districts d ON d.id = psa.district_id WHERE psa.photographer_id = ? AND psa.is_active = 1 ORDER BY psa.is_primary DESC, d.district_name');
$areas->execute([$id]);
$areas = $areas->fetchAll();
$services = db()->prepare('SELECT ps.*, sc.name, sc.icon FROM photographer_services ps JOIN service_categories sc ON sc.id = ps.category_id WHERE ps.photographer_id = ? AND ps.is_active = 1 ORDER BY sc.sort_order');
$services->execute([$id]);
$services = $services->fetchAll();
$portfolio = db()->prepare('SELECT * FROM photographer_portfolios WHERE photographer_id = ? AND deleted_at IS NULL ORDER BY is_featured DESC, sort_order ASC, id DESC');
$portfolio->execute([$id]);
$portfolio = $portfolio->fetchAll();
$availability = db()->prepare('SELECT * FROM photographer_availability WHERE photographer_id = ? AND available_date >= CURDATE() ORDER BY available_date, time_slot LIMIT 12');
$availability->execute([$id]);
$availability = $availability->fetchAll();
$articles = db()->prepare('SELECT * FROM photographer_articles WHERE photographer_id = ? AND status = "published" AND deleted_at IS NULL ORDER BY published_at DESC LIMIT 6');
$articles->execute([$id]);
$articles = $articles->fetchAll();
$reviews = db()->prepare('SELECT r.*, u.name customer_name FROM reviews r JOIN users u ON u.id = r.customer_id WHERE r.photographer_id = ? AND r.status = "visible" AND r.deleted_at IS NULL ORDER BY r.created_at DESC');
$reviews->execute([$id]);
$reviews = $reviews->fetchAll();
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
                                      (SELECT GROUP_CONCAT(DISTINCT sc.name ORDER BY sc.sort_order SEPARATOR ", ") FROM photographer_services ps JOIN service_categories sc ON sc.id = ps.category_id WHERE ps.photographer_id = p.id AND ps.is_active = 1) AS services
                                      FROM photographer_profiles p
                                      JOIN users u ON u.id = p.user_id
                                      LEFT JOIN districts d ON d.id = p.main_district_id
                                      WHERE p.id <> ?
                                        AND p.approval_status = "approved"
                                        AND p.is_available = 1
                                        AND u.status = "active"
                                        AND p.deleted_at IS NULL
                                        AND u.deleted_at IS NULL
                                        AND EXISTS (SELECT 1 FROM photographer_service_areas a WHERE a.photographer_id = p.id AND a.district_id = ? AND a.is_active = 1)
                                      ORDER BY p.average_rating DESC, p.total_reviews DESC
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
                    <span class="rounded-full bg-red-600 px-3 py-1 text-xs font-black text-white"><i class="fa-solid fa-star mr-1"></i><?= number_format((float)$profile['average_rating'], 1) ?> / <?= (int)$profile['total_reviews'] ?> รีวิว</span>
                    <span class="rounded-full bg-white/14 px-3 py-1 text-xs font-black text-white"><i class="fa-solid fa-bolt mr-1 text-yellow-300"></i>ตอบกลับไว</span>
                    <?php if ((float)$profile['average_rating'] >= 4.8): ?>
                        <span class="rounded-full bg-white/14 px-3 py-1 text-xs font-black text-white"><i class="fa-solid fa-award mr-1 text-yellow-300"></i>คะแนนสูง</span>
                    <?php endif; ?>
                </div>
                <h1 class="mt-5 text-4xl font-black tracking-tight sm:text-6xl"><?= h($profile['display_name']) ?></h1>
                <p class="mt-4 max-w-3xl text-lg leading-8 text-white/78"><?= nl2br(h($profile['bio'])) ?></p>
                <div class="mt-6 flex flex-wrap gap-3 text-sm font-black">
                    <span class="rounded-full bg-white/12 px-4 py-2"><i class="fa-solid fa-location-dot mr-2 text-red-300"></i><?= h($profile['district_name']) ?></span>
                    <span class="rounded-full bg-white/12 px-4 py-2"><i class="fa-solid fa-briefcase mr-2 text-red-300"></i><?= (int)$profile['experience_years'] ?> ปี</span>
                    <span class="rounded-full bg-white/12 px-4 py-2"><i class="fa-solid fa-tag mr-2 text-red-300"></i>เริ่มต้น <?= number_format((float)$profile['starting_price']) ?> บาท</span>
                </div>
                <div class="mt-8 grid max-w-4xl gap-3 sm:grid-cols-4">
                    <div class="stat-pill rounded-3xl p-4"><p class="text-3xl font-black"><?= number_format((float)$profile['average_rating'], 1) ?></p><p class="text-sm font-bold text-white/68">คะแนนเฉลี่ย</p></div>
                    <div class="stat-pill rounded-3xl p-4"><p class="text-3xl font-black"><?= (int)$profile['total_reviews'] ?></p><p class="text-sm font-bold text-white/68">รีวิว</p></div>
                    <div class="stat-pill rounded-3xl p-4"><p class="text-3xl font-black"><?= (int)$completedJobs ?></p><p class="text-sm font-bold text-white/68">งานสำเร็จ</p></div>
                    <div class="stat-pill rounded-3xl p-4"><p class="text-3xl font-black"><?= number_format((float)$profile['response_rate'], 0) ?>%</p><p class="text-sm font-bold text-white/68">อัตราตอบกลับ</p></div>
                </div>
            </div>
            <div class="glass-panel rounded-[2rem] p-5 text-neutral-950 lg:sticky lg:top-24">
                <div class="flex items-center gap-4">
                    <img class="h-20 w-20 rounded-3xl object-cover" src="<?= h(public_image($profile['profile_image'], '/assets/uploads/seed/photo-1500648767791-00dcc994a43e.jpg')) ?>" alt="">
                    <div>
                        <p class="font-black"><?= h($profile['display_name']) ?></p>
                        <p class="mt-1 text-sm font-semibold text-neutral-500"><?= h($profile['district_name']) ?></p>
                    </div>
                </div>
                <p class="mt-5 rounded-2xl bg-red-50 p-4 text-sm font-black text-red-700"><?= h(PAYMENT_DISCLAIMER) ?></p>
                <div class="mt-4 grid grid-cols-2 gap-2 text-sm font-black">
                    <div class="rounded-2xl bg-neutral-50 p-3"><i class="fa-solid fa-heart mr-1 text-red-600"></i><?= (int)$favoriteCount ?> คนบันทึก</div>
                    <div class="rounded-2xl bg-neutral-50 p-3"><i class="fa-solid fa-clock mr-1 text-red-600"></i><?= number_format((float)$profile['average_response_hours'], 1) ?> ชม.</div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <?php if ($profile['phone_public']): ?>
                        <a class="rounded-full bg-neutral-950 px-4 py-3 text-center text-sm font-black text-white hover:bg-red-600" href="tel:<?= h($profile['phone_public']) ?>">
                            <i class="fa-solid fa-phone mr-1"></i>โทร
                        </a>
                    <?php endif; ?>

                    <?php if ($profile['line_id']): ?>
                        <a class="rounded-full bg-[#06c755] px-4 py-3 text-center text-sm font-black text-white" href="https://line.me/ti/p/~<?= h($profile['line_id']) ?>">
                            <i class="fa-brands fa-line mr-1"></i>LINE
                        </a>
                    <?php endif; ?>

                    <?php if ($profile['facebook_url']): ?>
                        <a class="rounded-full bg-[#1877f2] px-4 py-3 text-center text-sm font-black text-white" href="<?= h($profile['facebook_url']) ?>" target="_blank">
                            <i class="fa-brands fa-facebook mr-1"></i>Facebook
                        </a>
                    <?php endif; ?>

                    <?php if ($profile['instagram_url']): ?>
                        <a class="rounded-full bg-[#d62976] px-4 py-3 text-center text-sm font-black text-white" href="<?= h($profile['instagram_url']) ?>" target="_blank">
                            <i class="fa-brands fa-instagram mr-1"></i>Instagram
                        </a>
                    <?php endif; ?>

                    <?php if ($profile['website_url']): ?>
                        <a class="rounded-full border border-neutral-200 px-4 py-3 text-center text-sm font-black hover:bg-neutral-950 hover:text-white" href="<?= h($profile['website_url']) ?>" target="_blank">
                            <i class="fa-solid fa-globe mr-1"></i>Website
                        </a>
                    <?php endif; ?>
                </div>
                <div class="mt-4 grid gap-2">
                    <?= clean_context_button('/customer/create_booking.php', ['photographer_id' => (int)$profile['id']], '<i class="fa-solid fa-calendar-check mr-2"></i>ส่งคำขอจอง', 'stock-button block w-full rounded-full px-5 py-3 text-center font-black') ?>
                    <?php if ($currentUser && $currentUser['role_name'] === 'customer'): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="favorite">
                            <input type="hidden" name="photographer_id" value="<?= (int)$profile['id'] ?>">
                            <button class="w-full rounded-full border border-neutral-200 px-5 py-3 text-center font-black hover:bg-neutral-950 hover:text-white">
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
                <form method="post" class="mt-4 grid gap-2 rounded-2xl bg-neutral-50 p-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="report_photographer">
                    <input type="hidden" name="photographer_id" value="<?= (int)$profile['id'] ?>">
                    <input type="hidden" name="target_id" value="<?= (int)$profile['id'] ?>">
                    <input name="reason" required placeholder="เหตุผลรายงานโปรไฟล์" class="stock-input rounded-xl px-3 py-2 text-sm">
                    <button class="rounded-xl bg-red-50 px-3 py-2 text-sm font-black text-red-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i>รายงานโปรไฟล์</button>
                </form>
            </div>
        </div>
    </div>
</section>

<section class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
    <div class="grid gap-8 lg:grid-cols-[1fr_340px]">
        <div class="space-y-12">
            <div>
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">แกลเลอรีผลงาน</p>
                        <h2 class="mt-1 text-3xl font-black text-neutral-950">ผลงานภาพถ่าย</h2>
                    </div>
                </div>
                <?php if (!$portfolio): ?>
                    <div class="empty-state mt-6 rounded-[2rem] p-10 text-center">
                        <i class="fa-solid fa-images text-4xl text-red-600"></i>
                        <h3 class="mt-3 text-xl font-black">ยังไม่มีผลงาน</h3>
                        <p class="mt-2 text-neutral-600">เมื่อช่างภาพอัปโหลดผลงาน รูปจะแสดงในส่วนนี้</p>
                    </div>
                <?php endif; ?>
                <div class="masonry-gallery mt-6">
                    <?php foreach ($portfolio as $i => $item): ?>
                        <?php
                        $portfolioImageClass = 'min-h-[240px]';
                        if ($i === 0) {
                            $portfolioImageClass = 'min-h-[420px]';
                        }
                        ?>
                        <figure class="media-tile rounded-[1.5rem] shadow-xl">
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

            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">บริการ</p>
                <h2 class="mt-1 text-3xl font-black text-neutral-950">ข้อมูลบริการ</h2>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <?php foreach ($services as $s): ?>
                        <?php
                        $serviceIcon = 'fa-camera';
                        if (!empty($s['icon'])) {
                            $serviceIcon = $s['icon'];
                        }
                        ?>
                        <div class="stock-card stock-card-hover rounded-[1.5rem] p-6">
                            <div class="flex items-start justify-between gap-4">
                                <h3 class="font-black text-neutral-950"><i class="fa-solid <?= h($serviceIcon) ?> mr-2 text-red-600"></i><?= h($s['name']) ?></h3>
                                <span class="rounded-full bg-neutral-950 px-3 py-1 text-xs font-black text-white"><?= number_format((float)$s['starting_price']) ?> ฿</span>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-neutral-600"><?= h($s['description']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="stock-card rounded-[1.75rem] p-7">
                <p class="section-kicker">เกี่ยวกับช่างภาพ</p>
                <h2 class="mt-1 text-3xl font-black text-neutral-950">แนะนำตัว</h2>
                <p class="mt-4 leading-8 text-neutral-700"><?= nl2br(h($profile['bio'])) ?></p>
                <div class="mt-5 rounded-[1.5rem] bg-red-50 p-5 text-sm font-black leading-7 text-red-700"><?= h(PAYMENT_DISCLAIMER) ?></div>
            </div>

            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">รีวิว</p>
                <h2 class="mt-1 text-3xl font-black text-neutral-950">รีวิวจากลูกค้า</h2>
                <div class="stock-card mt-6 rounded-[1.75rem] p-6">
                    <div class="grid gap-6 md:grid-cols-[220px_1fr] md:items-center">
                        <div class="text-center">
                            <p class="text-6xl font-black text-neutral-950"><?= number_format((float)$profile['average_rating'], 1) ?></p>
                            <p class="mt-2 text-red-600"><?= str_repeat('★', (int)round((float)$profile['average_rating'])) ?></p>
                            <p class="mt-1 text-sm font-bold text-neutral-500"><?= (int)$profile['total_reviews'] ?> รีวิว</p>
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
                                    <div class="mb-1 flex justify-between text-sm font-black"><span><?= h($ratingRow[0]) ?></span><span><?= number_format((float)$ratingRow[1], 1) ?></span></div>
                                    <div class="rating-bar"><span style="width: <?= number_format($ratingPercent, 0) ?>%"></span></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="mt-6 grid gap-4">
                    <?php foreach ($reviews as $r): ?>
                        <article class="stock-card rounded-[1.5rem] p-6">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <b class="text-neutral-950"><?= h($r['customer_name']) ?></b>
                                <span class="text-red-600"><?= str_repeat('★', (int)$r['rating_overall']) ?></span>
                            </div>
                            <p class="mt-3 leading-7 text-neutral-700"><?= nl2br(h($r['comment'])) ?></p>
                            <?php if ($currentUser): ?>
                                <form method="post" class="mt-4 flex flex-wrap gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="report_review">
                                    <input type="hidden" name="photographer_id" value="<?= (int)$profile['id'] ?>">
                                    <input type="hidden" name="target_id" value="<?= (int)$r['id'] ?>">
                                    <input name="reason" required placeholder="เหตุผลรายงานรีวิว" class="stock-input rounded-xl px-3 py-2 text-sm">
                                    <button class="rounded-xl bg-red-50 px-3 py-2 text-sm font-black text-red-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i>รายงานรีวิว</button>
                                </form>
                            <?php endif; ?>
                        </article>
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
        </div>

        <aside class="space-y-5">
            <div class="stock-card rounded-[1.5rem] p-6">
                <h2 class="font-black text-neutral-950">พื้นที่ให้บริการ</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    <?php foreach ($areas as $area): ?>
                        <span class="rounded-full bg-neutral-100 px-3 py-1.5 text-sm font-bold text-neutral-700">
                            <?= h($area['district_name']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="stock-card rounded-[1.5rem] p-6">
                <h2 class="font-black text-neutral-950">วันว่าง</h2>
                <div class="mt-4 grid gap-2">
                    <?php foreach ($availability as $a): ?>
                        <div class="rounded-2xl border border-neutral-100 bg-neutral-50 px-4 py-3 text-sm font-semibold">
                            <?= h(format_be_date($a['available_date'])) ?> · <?= h(time_slot_label($a['time_slot'])) ?> · <?= h(booking_status_label($a['status'])) ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$availability): ?>
                        <div class="empty-state rounded-2xl p-5 text-center text-sm font-bold text-neutral-600">ยังไม่มีวันว่างที่เปิดไว้</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="stock-card rounded-[1.5rem] p-6">
                <h2 class="font-black text-neutral-950">ช่องทางติดต่อ</h2>
                <div class="mt-4 grid gap-2 text-sm font-black">
                    <?php if ($profile['phone_public']): ?><a class="rounded-2xl bg-neutral-50 px-4 py-3 hover:bg-neutral-950 hover:text-white" href="tel:<?= h($profile['phone_public']) ?>"><i class="fa-solid fa-phone mr-2 text-red-600"></i><?= h($profile['phone_public']) ?></a><?php endif; ?>
                    <?php if ($profile['line_id']): ?><a class="rounded-2xl bg-neutral-50 px-4 py-3 hover:bg-[#06c755] hover:text-white" href="https://line.me/ti/p/~<?= h($profile['line_id']) ?>"><i class="fa-brands fa-line mr-2"></i><?= h($profile['line_id']) ?></a><?php endif; ?>
                    <?php if ($profile['facebook_url']): ?><a class="rounded-2xl bg-neutral-50 px-4 py-3 hover:bg-[#1877f2] hover:text-white" href="<?= h($profile['facebook_url']) ?>" target="_blank"><i class="fa-brands fa-facebook mr-2"></i>Facebook</a><?php endif; ?>
                    <?php if ($profile['instagram_url']): ?><a class="rounded-2xl bg-neutral-50 px-4 py-3 hover:bg-[#d62976] hover:text-white" href="<?= h($profile['instagram_url']) ?>" target="_blank"><i class="fa-brands fa-instagram mr-2"></i>Instagram</a><?php endif; ?>
                </div>
            </div>
            <div class="stock-card rounded-[1.5rem] p-6">
                <h2 class="font-black text-neutral-950">บทความจากช่างภาพ</h2>
                <div class="mt-4 grid gap-3">
                    <?php foreach ($articles as $a): ?>
                        <div class="rounded-2xl bg-neutral-50 p-4">
                            <b class="text-neutral-950"><?= h($a['title']) ?></b>
                            <p class="mt-1 line-clamp-2 text-sm leading-6 text-neutral-600"><?= h(strip_tags($a['content'])) ?></p>
                        </div>
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
            <p class="mt-4 leading-7 text-neutral-600">ลูกค้าต้องเข้าสู่ระบบก่อนส่งคำขอจอง หลังจากช่างภาพตอบรับ สามารถติดต่อผ่านช่องทางภายนอกเพื่อตกลงรายละเอียดได้โดยตรง</p>
        </div>
        <div class="stock-card rounded-[1.75rem] bg-red-50 p-7">
            <p class="section-kicker">หมายเหตุสำคัญ</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950">ไม่มีระบบชำระเงิน</h2>
            <p class="mt-4 font-bold leading-7 text-red-700"><?= h(PAYMENT_DISCLAIMER) ?></p>
        </div>
    </section>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

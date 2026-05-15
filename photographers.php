<?php
require_once __DIR__ . '/includes/functions.php';

if (defined('CUSTOMER_PHOTOGRAPHERS_PAGE')) {
    requireRole('customer');
}

$photographerSearchPath = '/photographers.php';
if (defined('CUSTOMER_PHOTOGRAPHERS_PAGE')) {
    $photographerSearchPath = '/customer/photographers.php';
}

$districts = db_fetch_all('SELECT * FROM districts WHERE is_active = 1 ORDER BY district_name');
$categories = db_fetch_all('SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name');
$cleanContext = clean_context_init(['district_id', 'category_id', 'available_date', 'min_rating', 'max_price', 'q', 'sort', 'page']);

$districtId = 0;
if (isset($cleanContext['district_id'])) {
    $districtId = (int)$cleanContext['district_id'];
}
$categoryId = 0;
if (isset($cleanContext['category_id'])) {
    $categoryId = (int)$cleanContext['category_id'];
}
$availableDate = '';
if (isset($cleanContext['available_date'])) {
    $availableDate = parse_be_date_to_iso((string)$cleanContext['available_date']);
}
$minRating = 0;
if (isset($cleanContext['min_rating'])) {
    $minRating = (float)$cleanContext['min_rating'];
}
$maxPrice = 0;
if (isset($cleanContext['max_price'])) {
    $maxPrice = (float)$cleanContext['max_price'];
}
$keyword = '';
if (isset($cleanContext['q'])) {
    $keyword = trim((string)$cleanContext['q']);
}
$sort = 'rating';
if (isset($cleanContext['sort'])) {
    $sort = (string)$cleanContext['sort'];
}
$page = 1;
if (isset($cleanContext['page'])) {
    $page = max(1, (int)$cleanContext['page']);
}

if (!empty($cleanContext)) {
    record_search_log($keyword, $districtId, $categoryId);
}
$perPage = 9;
$offset = ($page - 1) * $perPage;

$where = ['p.approval_status = "approved"', 'p.is_available = 1', 'u.status = "active"', 'p.deleted_at IS NULL', 'u.deleted_at IS NULL'];
$params = [];
if ($districtId > 0) {
    $where[] = 'EXISTS (SELECT 1 FROM photographer_service_areas psa WHERE psa.photographer_id = p.id AND psa.district_id = ? AND psa.is_active = 1)';
    $params[] = $districtId;
}
if ($categoryId > 0) {
    $where[] = 'EXISTS (SELECT 1 FROM photographer_services ps WHERE ps.photographer_id = p.id AND ps.category_id = ? AND ps.is_active = 1)';
    $params[] = $categoryId;
}
if ($availableDate !== '') {
    $where[] = 'EXISTS (
        SELECT 1
        FROM photographer_availability pa
        WHERE pa.photographer_id = p.id
          AND pa.available_date = ?
          AND pa.status = "available"
          AND NOT EXISTS (
            SELECT 1
            FROM bookings b_filter
            WHERE b_filter.photographer_id = pa.photographer_id
              AND b_filter.booking_date = pa.available_date
              AND b_filter.status IN ("pending","accepted","confirmed")
              AND b_filter.deleted_at IS NULL
              AND (b_filter.time_slot = pa.time_slot OR b_filter.time_slot = "full_day" OR pa.time_slot = "full_day")
          )
    )';
    $params[] = $availableDate;
}
if ($minRating > 0) {
    $where[] = 'p.average_rating >= ?';
    $params[] = $minRating;
}
if ($maxPrice > 0) {
    $where[] = 'p.starting_price <= ?';
    $params[] = $maxPrice;
}
if ($keyword !== '') {
    $where[] = 'p.display_name LIKE ?';
    $params[] = '%' . $keyword . '%';
}

$rankingOrder = ranking_order_sql('p');
$orderOptions = [
    'reviews' => 'p.total_reviews DESC, ' . $rankingOrder,
    'newest' => 'p.created_at DESC, ' . $rankingOrder,
    'price_low' => 'p.starting_price ASC, ' . $rankingOrder,
    'rating' => $rankingOrder,
];
$order = $rankingOrder;
if (isset($orderOptions[$sort])) {
    $order = $orderOptions[$sort];
}

$whereSql = implode(' AND ', $where);
$count = db()->prepare("SELECT COUNT(*)
                        FROM photographer_profiles p
                        JOIN users u ON u.id = p.user_id
                        WHERE {$whereSql}");
$count->execute($params);
$total = (int)$count->fetchColumn();

$sql = "SELECT p.*, d.district_name,
        (SELECT image_path FROM photographer_portfolios pp WHERE pp.photographer_id = p.id AND pp.deleted_at IS NULL ORDER BY pp.is_featured DESC, pp.sort_order ASC LIMIT 1) featured_image,
        (SELECT GROUP_CONCAT(DISTINCT d2.district_name ORDER BY d2.district_name SEPARATOR ', ') FROM photographer_service_areas psa JOIN districts d2 ON d2.id = psa.district_id WHERE psa.photographer_id = p.id AND psa.is_active = 1) areas,
        (SELECT GROUP_CONCAT(DISTINCT sc.name ORDER BY sc.sort_order SEPARATOR ', ') FROM photographer_services ps JOIN service_categories sc ON sc.id = ps.category_id WHERE ps.photographer_id = p.id AND ps.is_active = 1) services
        FROM photographer_profiles p
        JOIN users u ON u.id = p.user_id
        LEFT JOIN districts d ON d.id = p.main_district_id
        WHERE {$whereSql}
        ORDER BY {$order}
        LIMIT {$perPage} OFFSET {$offset}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$photographers = $stmt->fetchAll();

$nearby = [];
if (!$photographers && $districtId > 0) {
    $radius = (float)setting('nearby_radius_km', '30');
    $nearCategorySql = '';
    $nearParams = [$districtId];

    if ($categoryId > 0) {
        $nearCategorySql = ' AND EXISTS (
            SELECT 1
            FROM photographer_services ps_filter
            WHERE ps_filter.photographer_id = p.id
              AND ps_filter.category_id = ?
              AND ps_filter.is_active = 1
        )';
        $nearParams[] = $categoryId;
    }

    $nearParams[] = $radius;
    $nearSql = "SELECT p.*, main_d.district_name,
        MIN(6371 * ACOS(LEAST(1, GREATEST(-1,
            COS(RADIANS(src.latitude)) * COS(RADIANS(d.latitude)) * COS(RADIANS(d.longitude) - RADIANS(src.longitude)) +
            SIN(RADIANS(src.latitude)) * SIN(RADIANS(d.latitude))
        )))) AS distance_km,
        (SELECT image_path FROM photographer_portfolios pp WHERE pp.photographer_id = p.id AND pp.deleted_at IS NULL ORDER BY pp.is_featured DESC, pp.sort_order ASC LIMIT 1) featured_image,
        (SELECT GROUP_CONCAT(DISTINCT d2.district_name ORDER BY d2.district_name SEPARATOR ', ') FROM photographer_service_areas psa2 JOIN districts d2 ON d2.id = psa2.district_id WHERE psa2.photographer_id = p.id AND psa2.is_active = 1) areas,
        (SELECT GROUP_CONCAT(DISTINCT sc.name ORDER BY sc.sort_order SEPARATOR ', ') FROM photographer_services ps JOIN service_categories sc ON sc.id = ps.category_id WHERE ps.photographer_id = p.id AND ps.is_active = 1) services
        FROM districts src
        JOIN photographer_service_areas psa ON psa.district_id <> src.id AND psa.is_active = 1
        JOIN photographer_profiles p ON p.id = psa.photographer_id AND p.approval_status = 'approved' AND p.is_available = 1 AND p.deleted_at IS NULL
        JOIN users u ON u.id = p.user_id AND u.status = 'active' AND u.deleted_at IS NULL
        JOIN districts d ON d.id = psa.district_id
        LEFT JOIN districts main_d ON main_d.id = p.main_district_id
        WHERE src.id = ?
        {$nearCategorySql}
        GROUP BY p.id
        HAVING distance_km <= ?
        ORDER BY distance_km ASC, " . ranking_order_sql('p') . "
        LIMIT 6";
    $nearStmt = db()->prepare($nearSql);
    $nearStmt->execute($nearParams);
    $nearby = $nearStmt->fetchAll();
}

$selectedDistrictName = '';
foreach ($districts as $district) {
    if ((int)$district['id'] === $districtId) {
        $selectedDistrictName = $district['district_name'];
    }
}

$selectedCategoryName = '';
foreach ($categories as $category) {
    if ((int)$category['id'] === $categoryId) {
        $selectedCategoryName = $category['name'];
    }
}

$pageTitle = 'ค้นหาช่างภาพ';
include __DIR__ . '/includes/header.php';
?>
<section class="relative overflow-hidden bg-neutral-950 text-white">
    <div class="absolute inset-0">
        <img class="h-full w-full object-cover opacity-42" src="/assets/uploads/seed/photo-1492691527719-9d1e07e534b4.jpg" alt="">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_12%_18%,rgba(226,27,45,.34),transparent_24rem),linear-gradient(110deg,rgba(0,0,0,.92),rgba(0,0,0,.56))]"></div>
    </div>
    <div class="relative stock-shell px-4 py-16 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-[1fr_420px] lg:items-end">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.24em] text-red-400">ค้นหาช่างภาพ</p>
                <h1 class="mt-3 text-4xl font-black tracking-tight sm:text-6xl">ค้นหาช่างภาพเชียงราย</h1>
	                <p class="mt-4 max-w-2xl text-lg font-semibold leading-8 text-white/70">ค้นหาจากอำเภอ ประเภทงาน วันที่ว่าง คะแนนเฉลี่ย จำนวนรีวิว ชื่อ และราคาเริ่มต้นโดยประมาณ พร้อมแนะนำช่างภาพใกล้เคียงเมื่อพื้นที่ที่เลือกยังไม่มีผลลัพธ์</p>
                <div class="mt-6 flex flex-wrap gap-2">
                    <span class="info-chip"><i class="fa-solid fa-shield-halved text-red-600"></i> โปรไฟล์ผ่านอนุมัติ</span>
                    <span class="info-chip"><i class="fa-solid fa-location-crosshairs text-red-600"></i> แนะนำพื้นที่ใกล้เคียง</span>
                    <span class="info-chip"><i class="fa-solid fa-credit-card text-red-600"></i> ไม่มีรับชำระเงิน</span>
                </div>
            </div>
            <div class="stock-card rounded-[2rem] bg-white/95 p-5 text-neutral-950">
                <p class="text-sm font-black uppercase tracking-[0.2em] text-red-600">สรุปผลการค้นหา</p>
                <h2 class="mt-2 text-3xl font-black">พบ <?= number_format($total) ?> คน</h2>
                <p class="mt-2 text-sm font-bold leading-6 text-neutral-600">
                    <?php if ($selectedDistrictName !== ''): ?>
                        ในอำเภอ<?= h($selectedDistrictName) ?>
                    <?php else: ?>
                        จากทุกอำเภอในเชียงราย
                    <?php endif; ?>
                </p>
                <?php
                $summaryDate = 'ยังไม่เลือก';
                if ($availableDate !== '') {
                    $summaryDate = format_be_date($availableDate);
                }
                $summaryRating = 'ทุกคะแนนเฉลี่ย';
                if ($minRating > 0) {
                    $summaryRating = number_format((float)$minRating, 1) . ' คะแนนขึ้นไป';
                }
                ?>
                <div class="mt-4 grid gap-2 text-center sm:grid-cols-3">
                    <div class="info-tile rounded-2xl p-3"><b><?= number_format(count($photographers)) ?></b><p class="text-xs font-bold text-neutral-500">จำนวนช่างภาพในหน้านี้</p></div>
                    <div class="info-tile rounded-2xl p-3"><b><?= h($summaryRating) ?></b><p class="text-xs font-bold text-neutral-500">คะแนนขั้นต่ำ</p></div>
                    <div class="info-tile rounded-2xl p-3"><b><?= h($summaryDate) ?></b><p class="text-xs font-bold text-neutral-500">วันที่ต้องการจ้าง</p></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="stock-shell px-4 py-8 sm:px-6 lg:px-8">
    <div class="grid gap-8 lg:grid-cols-[320px_1fr]">
        <aside class="lg:sticky lg:top-24 lg:self-start">
            <form method="post" action="<?= h($photographerSearchPath) ?>" class="photographer-filter-form stock-card rounded-[2rem] p-5">
                <?= clean_context_inputs([]) ?>
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-xl font-black text-neutral-950">ตัวกรอง</h2>
                    <?= clean_context_button($photographerSearchPath, [], 'ล้าง', 'text-sm font-black text-red-600') ?>
                </div>
                <div class="mt-5 grid gap-3">
                    <label class="icon-input block"><i class="fa-solid fa-camera"></i><input name="q" value="<?= h($keyword) ?>" placeholder="ชื่อช่างภาพ" class="stock-input w-full rounded-[1.2rem] px-4 py-3 font-semibold"></label>
                    <label class="icon-input block">
                        <i class="fa-solid fa-location-dot"></i>
                        <select name="district_id" class="stock-input w-full rounded-[1.2rem] px-4 py-3 font-semibold">
                            <option value="">ทุกอำเภอ</option>
                            <?php foreach ($districts as $district): ?>
                                <?php
                                $isSelectedDistrict = false;
                                if ($districtId === (int)$district['id']) {
                                    $isSelectedDistrict = true;
                                }
                                ?>
                                <option value="<?= (int)$district['id'] ?>" <?php if ($isSelectedDistrict): ?>selected<?php endif; ?>><?= h($district['district_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <select name="category_id" class="stock-input rounded-[1.2rem] px-4 py-3 font-semibold">
                        <option value="">ทุกประเภท</option>
                        <?php foreach ($categories as $category): ?>
                            <?php
                            $isSelectedCategory = false;
                            if ($categoryId === (int)$category['id']) {
                                $isSelectedCategory = true;
                            }
                            ?>
                            <option value="<?= (int)$category['id'] ?>" <?php if ($isSelectedCategory): ?>selected<?php endif; ?>><?= h($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= be_date_input('available_date', $availableDate, 'stock-input w-full rounded-[1.2rem] px-4 py-3 font-semibold', false, 'วันที่ต้องการจ้าง') ?>
	                    <select name="min_rating" class="stock-input rounded-[1.2rem] px-4 py-3 font-semibold">
	                        <option value="0">ทุกคะแนนเฉลี่ย</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <?php
                            $isSelectedRating = false;
                            if ((int)$minRating === $i) {
                                $isSelectedRating = true;
                            }
                            ?>
                            <option value="<?= $i ?>" <?php if ($isSelectedRating): ?>selected<?php endif; ?>><?= $i ?> คะแนนขึ้นไป</option>
                        <?php endfor; ?>
                    </select>
                    <?php
                    $maxPriceValue = '';
                    if ($maxPrice > 0) {
                        $maxPriceValue = (string)$maxPrice;
                    }
                    ?>
                    <label class="icon-input block"><i class="fa-solid fa-tag"></i><input type="number" min="0" name="max_price" value="<?= h($maxPriceValue) ?>" placeholder="ราคาเริ่มต้นโดยประมาณไม่เกิน (บาท)" class="stock-input w-full rounded-[1.2rem] px-4 py-3 font-semibold"></label>
                    <select name="sort" class="stock-input rounded-[1.2rem] px-4 py-3 font-semibold">
	                        <option value="rating" <?php if ($sort === 'rating'): ?>selected<?php endif; ?>>คะแนนเฉลี่ยสูงสุด</option>
	                        <option value="reviews" <?php if ($sort === 'reviews'): ?>selected<?php endif; ?>>จำนวนรีวิวมากที่สุด</option>
                        <option value="newest" <?php if ($sort === 'newest'): ?>selected<?php endif; ?>>ใหม่ล่าสุด</option>
                        <option value="price_low" <?php if ($sort === 'price_low'): ?>selected<?php endif; ?>>ราคาเริ่มต้นโดยประมาณต่ำสุด</option>
                    </select>
                    <div class="rounded-2xl bg-red-50 p-4 text-sm font-black leading-6 text-red-700">
                        <i class="fa-solid fa-circle-info mr-2"></i>ราคาเป็นราคาเริ่มต้นโดยประมาณ เว็บไซต์ไม่รับชำระเงิน ลูกค้าและช่างภาพตกลงราคากันภายนอกระบบ
                    </div>
                    <button class="stock-button rounded-[1.2rem] px-5 py-3 font-black"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
                </div>
            </form>

            <div class="stock-card mt-5 rounded-[2rem] p-5">
                <h3 class="font-black text-neutral-950">ตัวอย่างแผนที่</h3>
                <div class="mt-4 grid h-56 place-items-center rounded-[1.5rem] bg-[radial-gradient(circle_at_30%_30%,rgba(226,27,45,.18),transparent_9rem),linear-gradient(135deg,#f8fafc,#e7edf4)] text-center">
                    <div>
                        <i class="fa-solid fa-map-location-dot text-4xl text-red-600"></i>
                        <p class="mt-3 text-sm font-black text-neutral-700">แสดงพื้นที่ให้บริการ</p>
                        <p class="text-xs font-bold text-neutral-500">พื้นที่สำหรับแผนที่ในอนาคต</p>
                    </div>
                </div>
            </div>
        </aside>

        <div>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="section-kicker">ผลการค้นหา</p>
                    <h2 class="mt-1 text-3xl font-black text-neutral-950">
                        <?php if ($selectedDistrictName !== ''): ?>
                            พบช่างภาพ <?= number_format($total) ?> คนในอำเภอ<?= h($selectedDistrictName) ?>
                        <?php else: ?>
                            พบช่างภาพ <?= number_format($total) ?> คน
                        <?php endif; ?>
                    </h2>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <?php if ($keyword !== ''): ?><span class="info-chip">ชื่อช่างภาพ: <?= h($keyword) ?></span><?php endif; ?>
                <?php if ($selectedDistrictName !== ''): ?><span class="info-chip">อำเภอ: <?= h($selectedDistrictName) ?></span><?php endif; ?>
                <?php if ($selectedCategoryName !== ''): ?><span class="info-chip">ประเภทงาน: <?= h($selectedCategoryName) ?></span><?php endif; ?>
                <?php if ($availableDate !== ''): ?><span class="info-chip">วันที่ต้องการจ้าง: <?= h(format_be_date($availableDate)) ?></span><?php endif; ?>
                <?php if ($minRating > 0): ?><span class="info-chip">คะแนนเฉลี่ยขั้นต่ำ: <?= number_format($minRating, 0) ?> คะแนนขึ้นไป</span><?php endif; ?>
                <?php if ($maxPrice > 0): ?><span class="info-chip">ราคาเริ่มต้นโดยประมาณไม่เกิน: <?= number_format($maxPrice) ?> บาท</span><?php endif; ?>
            </div>

            <?php if ($photographers): ?>
                <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($photographers as $p): ?>
                        <?php include __DIR__ . '/includes/photographer_card.php'; ?>
                    <?php endforeach; ?>
                </div>
                <?php
                $paginationParams = $cleanContext;
                unset($paginationParams['page']);
                ?>
                <?= paginate_clean($total, $page, $perPage, $photographerSearchPath, $paginationParams) ?>
            <?php else: ?>
                <div class="empty-state mt-6 rounded-[2rem] p-10 text-center">
                    <div class="mx-auto grid h-20 w-20 place-items-center rounded-3xl bg-red-50 text-3xl text-red-600"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                    <h2 class="mt-4 text-2xl font-black text-neutral-950">ไม่พบช่างภาพตามเงื่อนไข</h2>
                    <p class="mx-auto mt-2 max-w-md text-neutral-600">
                        <?php if ($selectedDistrictName !== ''): ?>
                            ไม่พบช่างภาพในอำเภอ<?= h($selectedDistrictName) ?>ตามเงื่อนไขนี้
                        <?php else: ?>
                            ลองปรับตัวกรอง หรือดูช่างภาพใกล้เคียงที่ระบบคำนวณจากพิกัดอำเภอให้
                        <?php endif; ?>
                    </p>
                    <?= clean_context_button($photographerSearchPath, [], '<i class="fa-solid fa-xmark mr-2"></i>ล้างตัวกรอง', 'mt-5 inline-flex rounded-full bg-neutral-950 px-5 py-3 font-black text-white hover:bg-red-600') ?>
                </div>
                <?php if ($nearby): ?>
                    <div class="mt-10">
                        <p class="section-kicker">ช่างภาพใกล้เคียง</p>
                        <h2 class="mt-1 text-2xl font-black text-neutral-950">ช่างภาพใกล้เคียงที่แนะนำ</h2>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span class="info-chip"><i class="fa-solid fa-location-dot text-red-600"></i>อำเภอที่เลือก: <?= h($selectedDistrictName) ?></span>
                            <?php if ($selectedCategoryName !== ''): ?>
                                <span class="info-chip"><i class="fa-solid fa-layer-group text-red-600"></i>ล็อกประเภทงาน: <?= h($selectedCategoryName) ?></span>
                            <?php endif; ?>
                            <span class="info-chip"><i class="fa-solid fa-route text-red-600"></i>รัศมีแนะนำ: ไม่เกิน <?= number_format($radius, 0) ?> กม.</span>
                        </div>
                        <div class="mt-4 rounded-[1.5rem] bg-amber-50 p-5 text-sm font-black leading-7 text-amber-800">
                            <i class="fa-solid fa-circle-info mr-2"></i>
                            ระยะทางเป็นค่าประมาณจากพิกัด latitude/longitude ของอำเภอที่เลือกไปยังอำเภอให้บริการที่ใกล้ที่สุดของช่างภาพ ไม่ใช่ระยะทางถนนจริง
                        </div>
                    </div>
                    <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        <?php foreach ($nearby as $p): ?>
                            <?php include __DIR__ . '/includes/photographer_card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($districtId > 0): ?>
                    <div class="mt-8 rounded-[1.5rem] bg-neutral-50 p-6 text-center font-bold text-neutral-600">
                        <i class="fa-solid fa-location-crosshairs mb-2 block text-3xl text-red-600"></i>
                        ยังไม่พบช่างภาพใกล้เคียงในรัศมี <?= number_format($radius, 0) ?> กม.
                        <?php if ($selectedCategoryName !== ''): ?>
                            สำหรับประเภทงาน<?= h($selectedCategoryName) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <section class="mt-12 rounded-[2rem] bg-neutral-950 p-6 text-white">
                <div class="flex flex-wrap items-center justify-between gap-5">
                    <div>
                        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-400">Suggested Categories</p>
                        <h2 class="mt-1 text-2xl font-black">ลองค้นหาตามประเภทงานยอดนิยม</h2>
                    </div>
                    <?= clean_context_button('/register.php', ['role' => 'photographer'], '<i class="fa-solid fa-user-plus mr-2"></i>สมัครเป็นช่างภาพ', 'rounded-full bg-white px-5 py-3 font-black text-neutral-950 hover:bg-red-600 hover:text-white') ?>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <?php foreach (array_slice($categories, 0, 8) as $category): ?>
                        <?php
                        $categoryIcon = 'fa-camera';
                        if (!empty($category['icon'])) {
                            $categoryIcon = $category['icon'];
                        }
                        ?>
                        <?= clean_context_button($photographerSearchPath, ['category_id' => (int)$category['id']], '<i class="fa-solid ' . h($categoryIcon) . ' mr-1 text-red-300"></i>' . h($category['name']), 'rounded-full bg-white/10 px-4 py-2 text-sm font-black hover:bg-white hover:text-neutral-950') ?>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

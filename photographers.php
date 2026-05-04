<?php
require_once __DIR__ . '/includes/functions.php';

$districts = db_fetch_all('SELECT * FROM districts WHERE is_active = 1 ORDER BY district_name');
$categories = db_fetch_all('SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name');

$districtId = 0;
if (isset($_GET['district_id'])) {
    $districtId = (int)$_GET['district_id'];
}
$categoryId = 0;
if (isset($_GET['category_id'])) {
    $categoryId = (int)$_GET['category_id'];
}
$availableDate = '';
if (isset($_GET['available_date'])) {
    $availableDate = trim((string)$_GET['available_date']);
}
$minRating = 0;
if (isset($_GET['min_rating'])) {
    $minRating = (float)$_GET['min_rating'];
}
$maxPrice = 0;
if (isset($_GET['max_price'])) {
    $maxPrice = (float)$_GET['max_price'];
}
$keyword = '';
if (isset($_GET['q'])) {
    $keyword = trim((string)$_GET['q']);
}
$sort = 'rating';
if (isset($_GET['sort'])) {
    $sort = (string)$_GET['sort'];
}
$page = 1;
if (isset($_GET['page'])) {
    $page = max(1, (int)$_GET['page']);
}

if (!empty($_GET)) {
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
    $where[] = 'EXISTS (SELECT 1 FROM photographer_availability pa WHERE pa.photographer_id = p.id AND pa.available_date = ? AND pa.status = "available")';
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

$orderOptions = [
    'reviews' => 'p.total_reviews DESC, p.average_rating DESC',
    'newest' => 'p.created_at DESC',
    'price_low' => 'p.starting_price ASC',
    'rating' => 'p.average_rating DESC, p.total_reviews DESC',
];
$order = 'p.average_rating DESC, p.total_reviews DESC';
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
    $nearSql = "SELECT p.*, d.district_name,
        (6371 * ACOS(COS(RADIANS(src.latitude)) * COS(RADIANS(d.latitude)) * COS(RADIANS(d.longitude) - RADIANS(src.longitude)) + SIN(RADIANS(src.latitude)) * SIN(RADIANS(d.latitude)))) AS distance_km,
        (SELECT image_path FROM photographer_portfolios pp WHERE pp.photographer_id = p.id AND pp.deleted_at IS NULL ORDER BY pp.is_featured DESC, pp.sort_order ASC LIMIT 1) featured_image
        FROM districts src
        JOIN photographer_service_areas psa ON psa.district_id <> src.id AND psa.is_active = 1
        JOIN photographer_profiles p ON p.id = psa.photographer_id AND p.approval_status = 'approved' AND p.is_available = 1 AND p.deleted_at IS NULL
        JOIN users u ON u.id = p.user_id AND u.status = 'active' AND u.deleted_at IS NULL
        JOIN districts d ON d.id = psa.district_id
        WHERE src.id = ?
        HAVING distance_km <= ?
        ORDER BY distance_km ASC, p.average_rating DESC
        LIMIT 6";
    $nearStmt = db()->prepare($nearSql);
    $nearStmt->execute([$districtId, $radius]);
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
        <img class="h-full w-full object-cover opacity-42" src="https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?auto=format&fit=crop&w=2200&q=85" alt="">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_12%_18%,rgba(226,27,45,.34),transparent_24rem),linear-gradient(110deg,rgba(0,0,0,.92),rgba(0,0,0,.56))]"></div>
    </div>
    <div class="relative stock-shell px-4 py-16 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-[1fr_420px] lg:items-end">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.24em] text-red-400">Browse photographers</p>
                <h1 class="mt-3 text-4xl font-black tracking-tight sm:text-6xl">ค้นหาช่างภาพเชียงราย</h1>
                <p class="mt-4 max-w-2xl text-lg font-semibold leading-8 text-white/70">ค้นหาจากอำเภอ ประเภทงาน วันที่ว่าง คะแนน รีวิว ชื่อ และราคาเริ่มต้น พร้อมแนะนำช่างภาพใกล้เคียงเมื่อพื้นที่ที่เลือกยังไม่มีผลลัพธ์</p>
                <div class="mt-6 flex flex-wrap gap-2">
                    <span class="premium-chip"><i class="fa-solid fa-shield-halved text-red-600"></i> โปรไฟล์ผ่านอนุมัติ</span>
                    <span class="premium-chip"><i class="fa-solid fa-location-crosshairs text-red-600"></i> Nearby matching</span>
                    <span class="premium-chip"><i class="fa-solid fa-credit-card text-red-600"></i> ไม่มีรับชำระเงิน</span>
                </div>
            </div>
            <div class="stock-card rounded-[2rem] bg-white/95 p-5 text-neutral-950">
                <p class="text-sm font-black uppercase tracking-[0.2em] text-red-600">Search Summary</p>
                <h2 class="mt-2 text-3xl font-black">พบ <?= number_format($total) ?> คน</h2>
                <p class="mt-2 text-sm font-bold leading-6 text-neutral-600">
                    <?php if ($selectedDistrictName !== ''): ?>
                        ในอำเภอ<?= h($selectedDistrictName) ?>
                    <?php else: ?>
                        จากทุกอำเภอในเชียงราย
                    <?php endif; ?>
                </p>
                <?php
                $summaryDate = '-';
                if ($availableDate !== '') {
                    $summaryDate = $availableDate;
                }
                ?>
                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-2xl bg-neutral-50 p-3"><b><?= number_format(count($photographers)) ?></b><p class="text-xs font-bold text-neutral-500">หน้านี้</p></div>
                    <div class="rounded-2xl bg-neutral-50 p-3"><b><?= number_format((float)$minRating, 1) ?></b><p class="text-xs font-bold text-neutral-500">ขั้นต่ำ</p></div>
                    <div class="rounded-2xl bg-neutral-50 p-3"><b><?= h($summaryDate) ?></b><p class="text-xs font-bold text-neutral-500">วันที่</p></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="stock-shell px-4 py-8 sm:px-6 lg:px-8">
    <div class="grid gap-8 lg:grid-cols-[320px_1fr]">
        <aside class="lg:sticky lg:top-24 lg:self-start">
            <form class="stock-card rounded-[2rem] p-5">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-xl font-black text-neutral-950">ตัวกรอง</h2>
                    <a href="/photographers.php" class="text-sm font-black text-red-600">ล้าง</a>
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
                    <label class="icon-input block"><i class="fa-solid fa-calendar"></i><input type="date" name="available_date" value="<?= h($availableDate) ?>" class="stock-input w-full rounded-[1.2rem] px-4 py-3 font-semibold"></label>
                    <select name="min_rating" class="stock-input rounded-[1.2rem] px-4 py-3 font-semibold">
                        <option value="0">ทุกคะแนน</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <?php
                            $isSelectedRating = false;
                            if ((int)$minRating === $i) {
                                $isSelectedRating = true;
                            }
                            ?>
                            <option value="<?= $i ?>" <?php if ($isSelectedRating): ?>selected<?php endif; ?>><?= $i ?> ดาวขึ้นไป</option>
                        <?php endfor; ?>
                    </select>
                    <?php
                    $maxPriceValue = '';
                    if ($maxPrice > 0) {
                        $maxPriceValue = (string)$maxPrice;
                    }
                    ?>
                    <label class="icon-input block"><i class="fa-solid fa-tag"></i><input type="number" min="0" name="max_price" value="<?= h($maxPriceValue) ?>" placeholder="ราคาไม่เกิน" class="stock-input w-full rounded-[1.2rem] px-4 py-3 font-semibold"></label>
                    <select name="sort" class="stock-input rounded-[1.2rem] px-4 py-3 font-semibold">
                        <option value="rating" <?php if ($sort === 'rating'): ?>selected<?php endif; ?>>คะแนนสูงสุด</option>
                        <option value="reviews" <?php if ($sort === 'reviews'): ?>selected<?php endif; ?>>รีวิวมากที่สุด</option>
                        <option value="newest" <?php if ($sort === 'newest'): ?>selected<?php endif; ?>>ใหม่ล่าสุด</option>
                        <option value="price_low" <?php if ($sort === 'price_low'): ?>selected<?php endif; ?>>ราคาเริ่มต้นต่ำสุด</option>
                    </select>
                    <button class="stock-button rounded-[1.2rem] px-5 py-3 font-black"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
                </div>
            </form>

            <div class="stock-card mt-5 rounded-[2rem] p-5">
                <h3 class="font-black text-neutral-950">Map Preview</h3>
                <div class="mt-4 grid h-56 place-items-center rounded-[1.5rem] bg-[radial-gradient(circle_at_30%_30%,rgba(226,27,45,.18),transparent_9rem),linear-gradient(135deg,#f8fafc,#e7edf4)] text-center">
                    <div>
                        <i class="fa-solid fa-map-location-dot text-4xl text-red-600"></i>
                        <p class="mt-3 text-sm font-black text-neutral-700">แสดงพื้นที่ให้บริการ</p>
                        <p class="text-xs font-bold text-neutral-500">Placeholder สำหรับแผนที่ในอนาคต</p>
                    </div>
                </div>
            </div>
        </aside>

        <div>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="section-kicker">Search result</p>
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
                <?php if ($keyword !== ''): ?><span class="premium-chip">ชื่อ: <?= h($keyword) ?></span><?php endif; ?>
                <?php if ($selectedDistrictName !== ''): ?><span class="premium-chip">อำเภอ: <?= h($selectedDistrictName) ?></span><?php endif; ?>
                <?php if ($selectedCategoryName !== ''): ?><span class="premium-chip">ประเภท: <?= h($selectedCategoryName) ?></span><?php endif; ?>
                <?php if ($availableDate !== ''): ?><span class="premium-chip">วันที่: <?= h($availableDate) ?></span><?php endif; ?>
                <?php if ($minRating > 0): ?><span class="premium-chip"><?= number_format($minRating, 0) ?> ดาวขึ้นไป</span><?php endif; ?>
                <?php if ($maxPrice > 0): ?><span class="premium-chip">ไม่เกิน <?= number_format($maxPrice) ?> บาท</span><?php endif; ?>
            </div>

            <?php if ($photographers): ?>
                <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($photographers as $p): ?>
                        <?php include __DIR__ . '/includes/photographer_card.php'; ?>
                    <?php endforeach; ?>
                </div>
                <?= paginate($total, $page, $perPage, '/photographers.php?' . http_build_query(array_diff_key($_GET, ['page' => true]))) ?>
            <?php else: ?>
                <div class="empty-state mt-6 rounded-[2rem] p-10 text-center">
                    <div class="mx-auto grid h-20 w-20 place-items-center rounded-3xl bg-red-50 text-3xl text-red-600"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                    <h2 class="mt-4 text-2xl font-black text-neutral-950">ไม่พบช่างภาพตามเงื่อนไข</h2>
                    <p class="mx-auto mt-2 max-w-md text-neutral-600">ลองปรับตัวกรอง หรือดูช่างภาพใกล้เคียงที่ระบบคำนวณจากพิกัดอำเภอให้</p>
                    <a href="/photographers.php" class="mt-5 inline-flex rounded-full bg-neutral-950 px-5 py-3 font-black text-white hover:bg-red-600"><i class="fa-solid fa-xmark mr-2"></i>ล้างตัวกรอง</a>
                </div>
                <?php if ($nearby): ?>
                    <div class="mt-10">
                        <p class="section-kicker">Nearby recommendation</p>
                        <h2 class="mt-1 text-2xl font-black text-neutral-950">ช่างภาพใกล้เคียงที่แนะนำ</h2>
                    </div>
                    <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        <?php foreach ($nearby as $p): ?>
                            <?php include __DIR__ . '/includes/photographer_card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <section class="mt-12 rounded-[2rem] bg-neutral-950 p-6 text-white">
                <div class="flex flex-wrap items-center justify-between gap-5">
                    <div>
                        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-400">Suggested Categories</p>
                        <h2 class="mt-1 text-2xl font-black">ลองค้นหาตามประเภทงานยอดนิยม</h2>
                    </div>
                    <a href="/register.php?role=photographer" class="rounded-full bg-white px-5 py-3 font-black text-neutral-950 hover:bg-red-600 hover:text-white"><i class="fa-solid fa-user-plus mr-2"></i>สมัครเป็นช่างภาพ</a>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <?php foreach (array_slice($categories, 0, 8) as $category): ?>
                        <?php
                        $categoryIcon = 'fa-camera';
                        if (!empty($category['icon'])) {
                            $categoryIcon = $category['icon'];
                        }
                        ?>
                        <a href="/photographers.php?category_id=<?= (int)$category['id'] ?>" class="rounded-full bg-white/10 px-4 py-2 text-sm font-black hover:bg-white hover:text-neutral-950">
                            <i class="fa-solid <?= h($categoryIcon) ?> mr-1 text-red-300"></i><?= h($category['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

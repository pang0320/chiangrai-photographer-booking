<?php
require_once __DIR__ . '/includes/functions.php';

$districts = db()->query('SELECT * FROM districts WHERE is_active = 1 ORDER BY district_name')->fetchAll();
$categories = db()->query('SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();

$districtId = (int)($_GET['district_id'] ?? 0);
$categoryId = (int)($_GET['category_id'] ?? 0);
$availableDate = trim((string)($_GET['available_date'] ?? ''));
$minRating = (float)($_GET['min_rating'] ?? 0);
$maxPrice = (float)($_GET['max_price'] ?? 0);
$keyword = trim((string)($_GET['q'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'rating');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

$where = ['p.approval_status = "approved"', 'p.is_available = 1', 'p.deleted_at IS NULL'];
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

$order = [
    'reviews' => 'p.total_reviews DESC, p.average_rating DESC',
    'newest' => 'p.created_at DESC',
    'price_low' => 'p.starting_price ASC',
    'rating' => 'p.average_rating DESC, p.total_reviews DESC',
][$sort] ?? 'p.average_rating DESC, p.total_reviews DESC';

$whereSql = implode(' AND ', $where);
$count = db()->prepare("SELECT COUNT(*) FROM photographer_profiles p WHERE {$whereSql}");
$count->execute($params);
$total = (int)$count->fetchColumn();

$sql = "SELECT p.*, d.district_name,
        (SELECT image_path FROM photographer_portfolios pp WHERE pp.photographer_id = p.id AND pp.deleted_at IS NULL ORDER BY pp.is_featured DESC, pp.sort_order ASC LIMIT 1) featured_image,
        (SELECT GROUP_CONCAT(DISTINCT d2.district_name ORDER BY d2.district_name SEPARATOR ', ') FROM photographer_service_areas psa JOIN districts d2 ON d2.id = psa.district_id WHERE psa.photographer_id = p.id AND psa.is_active = 1) areas,
        (SELECT GROUP_CONCAT(DISTINCT sc.name ORDER BY sc.sort_order SEPARATOR ', ') FROM photographer_services ps JOIN service_categories sc ON sc.id = ps.category_id WHERE ps.photographer_id = p.id AND ps.is_active = 1) services
        FROM photographer_profiles p
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
        JOIN districts d ON d.id = psa.district_id
        WHERE src.id = ?
        HAVING distance_km <= ?
        ORDER BY distance_km ASC, p.average_rating DESC
        LIMIT 6";
    $nearStmt = db()->prepare($nearSql);
    $nearStmt->execute([$districtId, $radius]);
    $nearby = $nearStmt->fetchAll();
}

$pageTitle = 'ค้นหาช่างภาพ';
include __DIR__ . '/includes/header.php';
?>
<section class="border-b border-neutral-200 bg-neutral-950 text-white">
    <div class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-[1fr_420px] lg:items-end">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.24em] text-red-400">Browse photographers</p>
                <h1 class="mt-3 text-4xl font-black tracking-tight sm:text-5xl">ค้นหาช่างภาพเชียงราย</h1>
                <p class="mt-4 max-w-2xl text-white/70">ค้นหาจากอำเภอ ประเภทงาน วันที่ว่าง คะแนน รีวิว ชื่อ และราคาเริ่มต้น พร้อมแนะนำช่างภาพใกล้เคียงเมื่อพื้นที่ที่เลือกยังไม่มีผลลัพธ์</p>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <img class="h-28 rounded-3xl object-cover" src="https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=500&q=80" alt="">
                <img class="h-28 rounded-3xl object-cover" src="https://images.unsplash.com/photo-1520854221256-17451cc331bf?auto=format&fit=crop&w=500&q=80" alt="">
                <img class="h-28 rounded-3xl object-cover" src="https://images.unsplash.com/photo-1517457373958-b7bdd4587205?auto=format&fit=crop&w=500&q=80" alt="">
            </div>
        </div>
    </div>
</section>

<section class="stock-shell px-4 py-8 sm:px-6 lg:px-8">
    <form class="-mt-16 grid gap-3 rounded-[2rem] bg-white p-4 shadow-2xl ring-1 ring-black/10 lg:grid-cols-7">
        <input name="q" value="<?= h($keyword) ?>" placeholder="ชื่อช่างภาพ" class="stock-input rounded-[1.35rem] px-4 py-3 font-semibold lg:col-span-2">
        <select name="district_id" class="stock-input rounded-[1.35rem] px-4 py-3 font-semibold">
            <option value="">ทุกอำเภอ</option>
            <?php foreach ($districts as $district): ?>
                <?php
                $isSelectedDistrict = false;

                if ($districtId === (int)$district['id']) {
                    $isSelectedDistrict = true;
                }
                ?>
                <option value="<?= (int)$district['id'] ?>" <?php if ($isSelectedDistrict): ?>selected<?php endif; ?>>
                    <?= h($district['district_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="category_id" class="stock-input rounded-[1.35rem] px-4 py-3 font-semibold">
            <option value="">ทุกประเภท</option>
            <?php foreach ($categories as $category): ?>
                <?php
                $isSelectedCategory = false;

                if ($categoryId === (int)$category['id']) {
                    $isSelectedCategory = true;
                }
                ?>
                <option value="<?= (int)$category['id'] ?>" <?php if ($isSelectedCategory): ?>selected<?php endif; ?>>
                    <?= h($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="available_date" value="<?= h($availableDate) ?>" class="stock-input rounded-[1.35rem] px-4 py-3 font-semibold">
        <input type="number" min="0" name="max_price" value="<?= $maxPrice ? h((string)$maxPrice) : '' ?>" placeholder="ราคาไม่เกิน" class="stock-input rounded-[1.35rem] px-4 py-3 font-semibold">
        <button class="stock-button rounded-[1.35rem] px-5 py-3 font-black">ค้นหา</button>
        <select name="min_rating" class="stock-input rounded-[1.35rem] px-4 py-3 font-semibold">
            <option value="0">ทุกคะแนน</option>
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <?php
                $isSelectedRating = false;

                if ((int)$minRating === $i) {
                    $isSelectedRating = true;
                }
                ?>
                <option value="<?= $i ?>" <?php if ($isSelectedRating): ?>selected<?php endif; ?>>
                    <?= $i ?> ดาวขึ้นไป
                </option>
            <?php endfor; ?>
        </select>
        <select name="sort" class="stock-input rounded-[1.35rem] px-4 py-3 font-semibold lg:col-span-2">
            <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>คะแนนสูงสุด</option>
            <option value="reviews" <?= $sort === 'reviews' ? 'selected' : '' ?>>รีวิวมากที่สุด</option>
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>ใหม่ล่าสุด</option>
            <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>ราคาเริ่มต้นต่ำสุด</option>
        </select>
    </form>

    <?php if ($photographers): ?>
        <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Search result</p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">พบ <?= number_format($total) ?> โปรไฟล์</h2>
            </div>
        </div>
        <div class="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($photographers as $p): include __DIR__ . '/includes/photographer_card.php'; endforeach; ?>
        </div>
        <?= paginate($total, $page, $perPage, '/photographers.php?' . http_build_query(array_diff_key($_GET, ['page' => true]))) ?>
    <?php else: ?>
        <div class="stock-card mt-8 rounded-[2rem] p-10 text-center">
            <div class="mx-auto grid h-16 w-16 place-items-center rounded-3xl bg-red-50 text-2xl text-red-600"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
            <h2 class="mt-4 text-2xl font-black text-neutral-950">ไม่พบช่างภาพตามเงื่อนไข</h2>
            <p class="mx-auto mt-2 max-w-md text-neutral-600">ลองปรับตัวกรอง หรือดูช่างภาพใกล้เคียงที่ระบบคำนวณจากพิกัดอำเภอให้</p>
        </div>
        <?php if ($nearby): ?>
            <div class="mt-10">
                <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Nearby recommendation</p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">ช่างภาพใกล้เคียงที่แนะนำ</h2>
            </div>
            <div class="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($nearby as $p): include __DIR__ . '/includes/photographer_card.php'; endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

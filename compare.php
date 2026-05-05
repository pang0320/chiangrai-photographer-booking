<?php
require_once __DIR__ . '/includes/functions.php';
$ids = [];
if (isset($_GET['ids'])) {
    foreach (explode(',', (string)$_GET['ids']) as $id) {
        $id = (int)$id;
        if ($id > 0 && count($ids) < 3) {
            $ids[] = $id;
        }
    }
}
$all = db_fetch_all('SELECT id, display_name FROM photographer_profiles WHERE approval_status = "approved" AND deleted_at IS NULL ORDER BY display_name');
$items = [];
if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $items = db_fetch_all("SELECT p.*, d.district_name,
        (SELECT COUNT(*) FROM photographer_portfolios pp WHERE pp.photographer_id = p.id AND pp.deleted_at IS NULL) AS portfolio_count,
        (SELECT GROUP_CONCAT(DISTINCT sc.name ORDER BY sc.sort_order SEPARATOR ', ') FROM photographer_services ps JOIN service_categories sc ON sc.id = ps.category_id WHERE ps.photographer_id = p.id AND ps.is_active = 1) AS services,
        (SELECT GROUP_CONCAT(DISTINCT d2.district_name ORDER BY d2.district_name SEPARATOR ', ') FROM photographer_service_areas psa JOIN districts d2 ON d2.id = psa.district_id WHERE psa.photographer_id = p.id AND psa.is_active = 1) AS areas
        FROM photographer_profiles p LEFT JOIN districts d ON d.id = p.main_district_id
        WHERE p.id IN ({$placeholders}) AND p.approval_status = 'approved'", $ids);
}
$pageTitle = 'เปรียบเทียบช่างภาพ';
include __DIR__ . '/includes/header.php';
?>
<section class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="section-kicker">Compare</p><h1 class="mt-2 text-4xl font-black">เปรียบเทียบช่างภาพ</h1></div>
    </div>
    <form class="stock-card mt-6 grid gap-3 rounded-[1.75rem] p-5 md:grid-cols-4">
        <?php for ($i = 0; $i < 3; $i++): ?>
            <select name="select_<?= $i ?>" class="stock-input rounded-2xl px-4 py-3 font-semibold" onchange="const s=[...this.form.querySelectorAll('select')].map(x=>x.value).filter(Boolean); location.href='/compare.php?ids='+s.join(',')">
                <option value="">เลือกช่างภาพ</option>
                <?php foreach ($all as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?php if (isset($ids[$i]) && $ids[$i] === (int)$p['id']): ?>selected<?php endif; ?>><?= h($p['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endfor; ?>
        <a href="/photographers.php" class="stock-button rounded-2xl px-5 py-3 text-center font-black"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหาเพิ่ม</a>
    </form>
    <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($items as $p): ?>
            <article class="stock-card rounded-[1.75rem] p-6">
                <img class="h-28 w-28 rounded-3xl object-cover" src="<?= h(public_image($p['profile_image'], '/assets/uploads/seed/photo-1500648767791-00dcc994a43e.jpg')) ?>" alt="">
                <h2 class="mt-4 text-2xl font-black"><?= h($p['display_name']) ?></h2>
                <div class="mt-4 grid gap-3 text-sm font-bold text-neutral-700">
                    <p><i class="fa-solid fa-star mr-2 text-red-600"></i><?= number_format((float)$p['average_rating'], 1) ?> / <?= (int)$p['total_reviews'] ?> รีวิว</p>
                    <p><i class="fa-solid fa-tag mr-2 text-red-600"></i><?= number_format((float)$p['starting_price']) ?> บาท</p>
                    <p><i class="fa-solid fa-location-dot mr-2 text-red-600"></i><?= h($p['areas']) ?></p>
                    <p><i class="fa-solid fa-camera mr-2 text-red-600"></i><?= h($p['services']) ?></p>
                    <p><i class="fa-solid fa-images mr-2 text-red-600"></i><?= (int)$p['portfolio_count'] ?> ผลงาน</p>
                    <p><i class="fa-solid fa-phone mr-2 text-red-600"></i><?= h($p['phone_public']) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

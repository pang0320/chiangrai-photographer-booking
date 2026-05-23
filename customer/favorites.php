<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('customer');
ensure_service_categories_deleted_at_column();
$user = current_user();

if (is_post()) {
    verify_csrf();
    $photographerId = (int)($_POST['photographer_id'] ?? 0);
    if ($photographerId > 0) {
        toggle_favorite_photographer((int)$user['id'], $photographerId);
        flash('success', 'อัปเดตรายการโปรดแล้ว');
    }
    redirect('/customer/favorites.php');
}

$items = db_fetch_all('SELECT p.*, d.district_name,
    (SELECT image_path FROM photographer_portfolios pp WHERE pp.photographer_id = p.id AND pp.deleted_at IS NULL ORDER BY pp.is_featured DESC, pp.sort_order ASC LIMIT 1) AS featured_image,
    (SELECT GROUP_CONCAT(DISTINCT sc.name ORDER BY sc.sort_order SEPARATOR ", ") FROM photographer_services ps JOIN service_categories sc ON sc.id = ps.category_id WHERE ps.photographer_id = p.id AND ps.is_active = 1 AND sc.is_active = 1 AND sc.deleted_at IS NULL) AS services,
    (SELECT GROUP_CONCAT(DISTINCT d2.district_name ORDER BY d2.district_name SEPARATOR ", ") FROM photographer_service_areas psa JOIN districts d2 ON d2.id = psa.district_id WHERE psa.photographer_id = p.id AND psa.is_active = 1) AS areas
    FROM favorite_photographers f
    JOIN photographer_profiles p ON p.id = f.photographer_id
    LEFT JOIN districts d ON d.id = p.main_district_id
    WHERE f.customer_id = ? AND p.deleted_at IS NULL
    ORDER BY f.created_at DESC', [(int)$user['id']]);

$pageTitle = 'ช่างภาพโปรด';
include __DIR__ . '/../includes/header.php';
?>
<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="section-kicker">Favorites</p><h1 class="mt-1 text-3xl font-black">ช่างภาพที่บันทึกไว้</h1></div>
        <a href="/customer/photographers.php" class="stock-button rounded-full px-5 py-3 font-black"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหาเพิ่ม</a>
    </div>
    <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($items as $p): ?>
            <div>
                <?php include __DIR__ . '/../includes/photographer_card.php'; ?>
                <form method="post" class="mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="photographer_id" value="<?= (int)$p['id'] ?>">
                    <button data-confirm="ยกเลิกรายการโปรด?" class="btn-muted btn-lg w-full"><i class="fa-solid fa-heart-crack mr-2"></i>ยกเลิกรายการโปรด</button>
                </form>
            </div>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <div class="empty-state rounded-[2rem] p-10 text-center md:col-span-2 xl:col-span-3">
                <i class="fa-solid fa-heart text-5xl text-red-600"></i>
                <h2 class="mt-4 text-2xl font-black">ยังไม่มีช่างภาพโปรด</h2>
                <p class="mt-2 text-neutral-600">กดหัวใจในหน้าโปรไฟล์ช่างภาพเพื่อบันทึกไว้ดูภายหลัง</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>

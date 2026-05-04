<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('customer');
$user = current_user();
$items = db_fetch_all('SELECT p.*, d.district_name, rv.viewed_at,
    (SELECT image_path FROM photographer_portfolios pp WHERE pp.photographer_id = p.id AND pp.deleted_at IS NULL ORDER BY pp.is_featured DESC, pp.sort_order ASC LIMIT 1) AS featured_image,
    (SELECT GROUP_CONCAT(DISTINCT sc.name ORDER BY sc.sort_order SEPARATOR ", ") FROM photographer_services ps JOIN service_categories sc ON sc.id = ps.category_id WHERE ps.photographer_id = p.id AND ps.is_active = 1) AS services,
    (SELECT GROUP_CONCAT(DISTINCT d2.district_name ORDER BY d2.district_name SEPARATOR ", ") FROM photographer_service_areas psa JOIN districts d2 ON d2.id = psa.district_id WHERE psa.photographer_id = p.id AND psa.is_active = 1) AS areas
    FROM recently_viewed_photographers rv
    JOIN photographer_profiles p ON p.id = rv.photographer_id
    LEFT JOIN districts d ON d.id = p.main_district_id
    WHERE rv.user_id = ? AND p.deleted_at IS NULL
    ORDER BY rv.viewed_at DESC', [(int)$user['id']]);
$pageTitle = 'ประวัติการดูช่างภาพ';
include __DIR__ . '/../includes/header.php';
?>
<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div><p class="section-kicker">Recently Viewed</p><h1 class="mt-1 text-3xl font-black">ช่างภาพที่เคยดู</h1></div>
    <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($items as $p): ?>
            <?php include __DIR__ . '/../includes/photographer_card.php'; ?>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <div class="empty-state rounded-[2rem] p-10 text-center md:col-span-2 xl:col-span-3">
                <i class="fa-solid fa-clock-rotate-left text-5xl text-red-600"></i>
                <h2 class="mt-4 text-2xl font-black">ยังไม่มีประวัติการดู</h2>
                <p class="mt-2 text-neutral-600">เมื่อเปิดดูโปรไฟล์ช่างภาพ ระบบจะแสดงรายการล่าสุดที่นี่</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>

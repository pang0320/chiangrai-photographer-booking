<?php
require_once __DIR__ . '/includes/functions.php';

$districts = db()->query('SELECT * FROM districts WHERE is_active = 1 ORDER BY district_name')->fetchAll();
$categories = db()->query('SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
$featured = db()->query('SELECT p.*, d.district_name FROM photographer_profiles p LEFT JOIN districts d ON d.id = p.main_district_id WHERE p.approval_status = "approved" AND p.is_available = 1 AND p.deleted_at IS NULL ORDER BY p.total_reviews DESC, p.profile_views DESC LIMIT 6')->fetchAll();
$articles = db()->query('SELECT a.*, p.display_name FROM photographer_articles a JOIN photographer_profiles p ON p.id = a.photographer_id WHERE a.status = "published" AND a.deleted_at IS NULL ORDER BY a.published_at DESC LIMIT 3')->fetchAll();
$reviews = db()->query('SELECT r.*, u.name customer_name, p.display_name FROM reviews r JOIN users u ON u.id = r.customer_id JOIN photographer_profiles p ON p.id = r.photographer_id WHERE r.status = "visible" AND r.deleted_at IS NULL ORDER BY r.created_at DESC LIMIT 3')->fetchAll();

$heroImages = [
    'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=900&q=85',
    'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?auto=format&fit=crop&w=900&q=85',
    'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=900&q=85',
    'https://images.unsplash.com/photo-1520854221256-17451cc331bf?auto=format&fit=crop&w=900&q=85',
    'https://images.unsplash.com/photo-1517457373958-b7bdd4587205?auto=format&fit=crop&w=900&q=85',
    'https://images.unsplash.com/photo-1511285560929-80b456fea0bc?auto=format&fit=crop&w=900&q=85',
];

$pageTitle = 'ค้นหาช่างภาพมืออาชีพในจังหวัดเชียงราย';
include __DIR__ . '/includes/header.php';
?>

<section class="relative overflow-hidden bg-neutral-950 text-white">
    <div class="absolute inset-0 opacity-55">
        <div class="grid h-full grid-cols-3 gap-1 md:grid-cols-6">
            <?php foreach ($heroImages as $image): ?>
                <div class="media-tile">
                    <img src="<?= h($image) ?>" alt="">
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(0,0,0,.25),rgba(0,0,0,.88))]"></div>
    <div class="relative stock-shell px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
        <div class="mx-auto max-w-5xl text-center">
            <p class="text-sm font-black uppercase tracking-[0.28em] text-white/70">Local photographer marketplace</p>
            <h1 class="mt-5 text-4xl font-black leading-tight tracking-tight sm:text-6xl lg:text-7xl">ค้นหาช่างภาพมืออาชีพในจังหวัดเชียงราย</h1>
            <p class="mx-auto mt-5 max-w-2xl text-lg font-medium leading-8 text-white/78">เลือกดูผลงานแบบภาพใหญ่ ตรวจสอบพื้นที่ให้บริการ วันว่าง และส่งคำขอจองเพื่อคุยกับช่างภาพโดยตรง</p>
        </div>

        <form action="/photographers.php" class="glass-panel mx-auto mt-10 grid max-w-5xl gap-3 rounded-[2rem] p-3 text-neutral-950 md:grid-cols-[1fr_1fr_1fr_auto]">
            <select name="district_id" class="stock-input rounded-[1.4rem] px-5 py-4 font-bold">
                <option value="">เลือกอำเภอ</option>
                <?php foreach ($districts as $district): ?>
                    <option value="<?= (int)$district['id'] ?>"><?= h($district['district_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="category_id" class="stock-input rounded-[1.4rem] px-5 py-4 font-bold">
                <option value="">ประเภทงาน</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int)$category['id'] ?>"><?= h($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="available_date" class="stock-input rounded-[1.4rem] px-5 py-4 font-bold">
            <button class="stock-button rounded-[1.4rem] px-8 py-4 text-base font-black"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
        </form>

        <div class="mx-auto mt-6 flex max-w-5xl flex-wrap justify-center gap-2">
            <?php foreach (array_slice($categories, 0, 6) as $category): ?>
                <a href="/photographers.php?category_id=<?= (int)$category['id'] ?>" class="rounded-full bg-white/12 px-4 py-2 text-sm font-bold text-white backdrop-blur hover:bg-white hover:text-neutral-950"><?= h($category['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="stock-shell px-4 py-14 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-5">
        <div>
            <p class="text-sm font-black uppercase tracking-[0.24em] text-red-600">Browse by work type</p>
            <h2 class="mt-2 text-3xl font-black tracking-tight text-neutral-950">หมวดหมู่งานยอดนิยม</h2>
        </div>
        <a href="/photographers.php" class="rounded-full border border-neutral-200 px-5 py-2.5 text-sm font-black hover:border-neutral-950 hover:bg-neutral-950 hover:text-white">ดูช่างภาพทั้งหมด</a>
    </div>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($categories as $category): ?>
            <a href="/photographers.php?category_id=<?= (int)$category['id'] ?>" class="stock-card stock-card-hover rounded-[1.75rem] p-6">
                <div class="flex items-start justify-between gap-5">
                    <div class="grid h-12 w-12 place-items-center rounded-2xl bg-neutral-950 text-white"><i class="fa-solid <?= h($category['icon'] ?: 'fa-camera') ?>"></i></div>
                    <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-600">Explore</span>
                </div>
                <h3 class="mt-5 text-xl font-black text-neutral-950"><?= h($category['name']) ?></h3>
                <p class="mt-2 line-clamp-2 text-sm leading-6 text-neutral-600"><?= h($category['description']) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="bg-neutral-950 py-16 text-white">
    <div class="stock-shell px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.24em] text-red-400">Curated creators</p>
                <h2 class="mt-2 text-3xl font-black tracking-tight">ช่างภาพแนะนำ</h2>
                <p class="mt-2 text-white/62">โปรไฟล์ที่ผ่านการอนุมัติ พร้อมผลงานและช่องทางติดต่อ</p>
            </div>
            <a href="/photographers.php" class="rounded-full bg-white px-5 py-2.5 text-sm font-black text-neutral-950 hover:bg-red-600 hover:text-white">ดูทั้งหมด</a>
        </div>
        <div class="mt-8 grid gap-5 md:grid-cols-3">
            <?php foreach ($featured as $p): ?>
                <a href="/photographer_detail.php?id=<?= (int)$p['id'] ?>" class="media-tile h-[360px] rounded-[1.75rem]">
                    <img src="<?= h(public_image($p['cover_image'], 'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?auto=format&fit=crop&w=900&q=85')) ?>" alt="">
                    <div class="media-overlay opacity-100">
                        <div class="w-full p-6">
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-neutral-950">ยืนยันแล้ว</span>
                            <h3 class="mt-4 text-2xl font-black"><?= h($p['display_name']) ?></h3>
                            <p class="mt-2 text-sm font-semibold text-white/80"><?= h($p['district_name']) ?> · เริ่มต้น <?= number_format((float)$p['starting_price']) ?> บาท</p>
                            <p class="mt-2 text-sm font-bold text-red-200"><i class="fa-solid fa-star"></i> <?= number_format((float)$p['average_rating'], 1) ?> · <?= (int)$p['total_reviews'] ?> รีวิว</p>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="stock-shell grid gap-5 px-4 py-14 sm:px-6 md:grid-cols-3 lg:px-8">
    <?php foreach ([['fa-magnifying-glass','ค้นหาช่างภาพ','เลือกอำเภอ ประเภทงาน วันที่ และงบประมาณ'],['fa-paper-plane','ส่งคำขอจอง','กรอกรายละเอียดงานและช่องทางติดต่อกลับ'],['fa-comments','ตกลงรายละเอียดโดยตรง','คุยราคาและชำระเงินกับช่างภาพเอง']] as $step): ?>
        <div class="stock-card rounded-[1.75rem] p-7">
            <div class="grid h-12 w-12 place-items-center rounded-2xl bg-red-600 text-white"><i class="fa-solid <?= h($step[0]) ?>"></i></div>
            <h3 class="mt-5 text-xl font-black text-neutral-950"><?= h($step[1]) ?></h3>
            <p class="mt-2 text-sm leading-6 text-neutral-600"><?= h($step[2]) ?></p>
        </div>
    <?php endforeach; ?>
</section>

<section class="border-y border-neutral-200 bg-white py-14">
    <div class="stock-shell grid gap-8 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
        <div>
            <p class="text-sm font-black uppercase tracking-[0.24em] text-red-600">Photo guide</p>
            <h2 class="mt-2 text-3xl font-black tracking-tight">บทความแนะนำ</h2>
            <div class="mt-6 grid gap-4">
                <?php foreach ($articles as $article): ?>
                    <article class="stock-card stock-card-hover rounded-[1.5rem] p-6">
                        <p class="text-sm font-black text-red-600"><?= h($article['display_name']) ?></p>
                        <h3 class="mt-1 text-lg font-black text-neutral-950"><?= h($article['title']) ?></h3>
                        <p class="mt-2 line-clamp-2 text-sm leading-6 text-neutral-600"><?= h(strip_tags($article['content'])) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <p class="text-sm font-black uppercase tracking-[0.24em] text-red-600">Customer voice</p>
            <h2 class="mt-2 text-3xl font-black tracking-tight">รีวิวจากลูกค้า</h2>
            <div class="mt-6 grid gap-4">
                <?php foreach ($reviews as $review): ?>
                    <article class="stock-card rounded-[1.5rem] p-6">
                        <div class="flex items-center justify-between gap-4">
                            <p class="font-black text-neutral-950"><?= h($review['customer_name']) ?></p>
                            <p class="text-red-600"><?= str_repeat('★', (int)$review['rating_overall']) ?></p>
                        </div>
                        <p class="mt-1 text-sm font-semibold text-neutral-500">รีวิว <?= h($review['display_name']) ?></p>
                        <p class="mt-3 text-sm leading-6 text-neutral-700"><?= h($review['comment']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

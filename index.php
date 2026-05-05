<?php
require_once __DIR__ . '/includes/functions.php';

$districts = db_fetch_all('SELECT * FROM districts WHERE is_active = 1 ORDER BY district_name');
$categories = db_fetch_all('SELECT sc.*, COUNT(DISTINCT ps.photographer_id) AS photographer_count
                            FROM service_categories sc
                            LEFT JOIN photographer_services ps ON ps.category_id = sc.id AND ps.is_active = 1
                            LEFT JOIN photographer_profiles p ON p.id = ps.photographer_id AND p.approval_status = "approved" AND p.is_available = 1 AND p.deleted_at IS NULL
                            WHERE sc.is_active = 1
                            GROUP BY sc.id
                            ORDER BY sc.sort_order, sc.name');
$featured = db_fetch_all('SELECT p.*, d.district_name,
                          (SELECT image_path FROM photographer_portfolios pp WHERE pp.photographer_id = p.id AND pp.deleted_at IS NULL ORDER BY pp.is_featured DESC, pp.sort_order ASC LIMIT 1) AS featured_image,
                          (SELECT GROUP_CONCAT(DISTINCT sc.name ORDER BY sc.sort_order SEPARATOR ", ") FROM photographer_services ps JOIN service_categories sc ON sc.id = ps.category_id WHERE ps.photographer_id = p.id AND ps.is_active = 1) AS services
                          FROM photographer_profiles p
                          JOIN users u ON u.id = p.user_id
                          LEFT JOIN districts d ON d.id = p.main_district_id
                          WHERE p.approval_status = "approved"
                            AND p.is_available = 1
                            AND u.status = "active"
                            AND p.deleted_at IS NULL
                            AND u.deleted_at IS NULL
                          ORDER BY p.is_featured DESC, p.featured_until DESC, p.total_reviews DESC, p.profile_views DESC
                          LIMIT 8');
$topRated = db_fetch_all('SELECT p.*, d.district_name,
                          (SELECT image_path FROM photographer_portfolios pp WHERE pp.photographer_id = p.id AND pp.deleted_at IS NULL ORDER BY pp.is_featured DESC, pp.sort_order ASC LIMIT 1) AS featured_image
                          FROM photographer_profiles p
                          JOIN users u ON u.id = p.user_id
                          LEFT JOIN districts d ON d.id = p.main_district_id
                          WHERE p.approval_status = "approved"
                            AND p.is_available = 1
                            AND u.status = "active"
                            AND p.deleted_at IS NULL
                            AND u.deleted_at IS NULL
                          ORDER BY p.average_rating DESC, p.total_reviews DESC
                          LIMIT 10');
$popularDistricts = db_fetch_all('SELECT d.id, d.district_name, COUNT(DISTINCT p.id) AS photographer_count
                                  FROM districts d
                                  LEFT JOIN photographer_service_areas psa ON psa.district_id = d.id AND psa.is_active = 1
                                  LEFT JOIN photographer_profiles p ON p.id = psa.photographer_id AND p.approval_status = "approved" AND p.is_available = 1 AND p.deleted_at IS NULL
                                  WHERE d.is_active = 1
                                  GROUP BY d.id
                                  ORDER BY photographer_count DESC, d.district_name
                                  LIMIT 8');
$portfolioShowcase = db_fetch_all('SELECT pp.*, p.display_name, p.id AS photographer_id
                                   FROM photographer_portfolios pp
                                   JOIN photographer_profiles p ON p.id = pp.photographer_id
                                   JOIN users u ON u.id = p.user_id
                                   WHERE pp.deleted_at IS NULL
                                     AND p.approval_status = "approved"
                                     AND p.is_available = 1
                                     AND u.status = "active"
                                   ORDER BY pp.created_at DESC, pp.is_featured DESC
                                   LIMIT 12');
$articles = db_fetch_all('SELECT a.*, p.display_name
                          FROM photographer_articles a
                          JOIN photographer_profiles p ON p.id = a.photographer_id
                          WHERE a.status = "published" AND a.deleted_at IS NULL
                          ORDER BY a.published_at DESC
                          LIMIT 6');
$reviews = db_fetch_all('SELECT r.*, u.name customer_name, u.avatar, p.display_name
                         FROM reviews r
                         JOIN users u ON u.id = r.customer_id
                         JOIN photographer_profiles p ON p.id = r.photographer_id
                         WHERE r.status = "visible" AND r.deleted_at IS NULL
                         ORDER BY r.created_at DESC
                         LIMIT 6');
$stats = [
    'photographers' => db_fetch_value('SELECT COUNT(*) FROM photographer_profiles p JOIN users u ON u.id = p.user_id WHERE p.approval_status = "approved" AND p.is_available = 1 AND p.deleted_at IS NULL AND u.status = "active" AND u.deleted_at IS NULL'),
    'reviews' => db_fetch_value('SELECT COUNT(*) FROM reviews WHERE status = "visible" AND deleted_at IS NULL'),
    'bookings' => db_fetch_value('SELECT COUNT(*) FROM bookings WHERE deleted_at IS NULL'),
    'districts' => db_fetch_value('SELECT COUNT(*) FROM districts WHERE is_active = 1'),
    'avg_rating' => db_fetch_value('SELECT AVG(rating_overall) FROM reviews WHERE status = "visible" AND deleted_at IS NULL'),
];

$heroImages = [
    '/assets/uploads/seed/photo-1519741497674-611481863552.jpg',
    '/assets/uploads/seed/photo-1492691527719-9d1e07e534b4.jpg',
    '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg',
    '/assets/uploads/seed/photo-1520854221256-17451cc331bf.jpg',
];

$pageTitle = 'ค้นหาช่างภาพมืออาชีพในจังหวัดเชียงราย';
include __DIR__ . '/includes/header.php';
?>

<section class="hero-frame relative overflow-hidden bg-neutral-950 text-white">
    <div class="absolute inset-0">
        <img class="h-full w-full object-cover opacity-45" src="/assets/uploads/seed/photo-1511285560929-80b456fea0bc.jpg" alt="">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(226,27,45,.38),transparent_28rem),linear-gradient(110deg,rgba(0,0,0,.92),rgba(0,0,0,.62)_48%,rgba(0,0,0,.28))]"></div>
    </div>
    <div class="relative stock-shell grid min-h-[calc(100vh-5rem)] gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[1.05fr_.95fr] lg:items-center lg:px-8">
        <div>
            <div class="inline-flex rounded-full bg-white/12 px-4 py-2 text-xs font-black uppercase tracking-[0.22em] text-white/78 backdrop-blur">
                Chiang Rai Photographer Marketplace
            </div>
            <h1 class="mt-6 max-w-4xl text-4xl font-black leading-tight tracking-tight sm:text-6xl lg:text-7xl">ค้นหาช่างภาพมืออาชีพในจังหวัดเชียงราย</h1>
            <p class="mt-5 max-w-2xl text-lg font-semibold leading-8 text-white/76">เลือกดูผลงานจริง ตรวจวันว่าง ส่งคำขอจอง และติดต่อช่างภาพโดยตรง ไม่มีระบบรับชำระเงินในเว็บไซต์</p>

            <form action="/photographers.php" class="glass-panel mt-9 grid gap-3 rounded-[2rem] p-3 text-neutral-950 md:grid-cols-[1fr_1fr_1fr_auto]">
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
                <?= be_date_input('available_date', '', 'stock-input rounded-[1.4rem] px-5 py-4 font-bold', false, 'วันที่ว่าง พ.ศ.') ?>
                <button class="stock-button rounded-[1.4rem] px-8 py-4 text-base font-black"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
            </form>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="stat-pill rounded-3xl p-4">
                    <p class="text-3xl font-black"><?= number_format((int)$stats['photographers']) ?>+</p>
                    <p class="text-sm font-bold text-white/68">ช่างภาพพร้อมรับงาน</p>
                </div>
                <div class="stat-pill rounded-3xl p-4">
                    <p class="text-3xl font-black"><?= number_format((int)$stats['reviews']) ?>+</p>
                    <p class="text-sm font-bold text-white/68">รีวิวจากลูกค้า</p>
                </div>
                <div class="stat-pill rounded-3xl p-4">
                    <p class="text-3xl font-black"><?= number_format((int)$stats['bookings']) ?>+</p>
                    <p class="text-sm font-bold text-white/68">คำขอจองทั้งหมด</p>
                </div>
                <div class="stat-pill rounded-3xl p-4">
                    <p class="text-3xl font-black"><?= number_format((int)$stats['districts']) ?></p>
                    <p class="text-sm font-bold text-white/68">อำเภอเชียงราย</p>
                </div>
                <div class="stat-pill rounded-3xl p-4">
                    <p class="text-3xl font-black"><?= number_format((float)$stats['avg_rating'], 1) ?></p>
                    <p class="text-sm font-bold text-white/68">คะแนนเฉลี่ยรวม</p>
                </div>
            </div>
        </div>

        <div class="relative min-h-[520px]">
            <?php foreach ($heroImages as $index => $image): ?>
                <?php
                $classes = [
                    'left-0 top-8 h-72 w-56 rotate-[-7deg]',
                    'right-6 top-0 h-80 w-64 rotate-[5deg]',
                    'bottom-10 left-14 h-72 w-64 rotate-[6deg]',
                    'bottom-0 right-0 h-64 w-56 rotate-[-5deg]',
                ];
                ?>
                <div class="media-tile absolute <?= h($classes[$index]) ?> rounded-[2rem] border-4 border-white/18 shadow-2xl">
                    <img src="<?= h($image) ?>" alt="">
                </div>
            <?php endforeach; ?>
            <div class="absolute left-4 top-[46%] rounded-[2rem] bg-white p-5 text-neutral-950 shadow-2xl">
                <p class="text-sm font-black text-neutral-500">เริ่มต้น</p>
                <p class="text-3xl font-black text-red-600">1,800฿</p>
                <p class="mt-1 text-sm font-bold text-neutral-600">ค้นหาไม่เกิน 3 ขั้นตอน</p>
            </div>
        </div>
    </div>
</section>

<section class="stock-shell px-4 py-16 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-5">
        <div>
            <p class="section-kicker">หมวดหมู่แนะนำ</p>
            <h2 class="mt-2 text-3xl font-black tracking-tight text-neutral-950">หมวดหมู่งานถ่ายภาพ</h2>
        </div>
        <a href="/photographers.php" class="rounded-full border border-neutral-200 px-5 py-2.5 text-sm font-black hover:border-neutral-950 hover:bg-neutral-950 hover:text-white"><i class="fa-solid fa-eye mr-2"></i>ดูทั้งหมด</a>
    </div>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <?php foreach ($categories as $category): ?>
            <?php
            $categoryIcon = 'fa-camera';
            if (!empty($category['icon'])) {
                $categoryIcon = $category['icon'];
            }
            ?>
            <a href="/photographers.php?category_id=<?= (int)$category['id'] ?>" class="stock-card stock-card-hover rounded-[1.75rem] p-6">
                <div class="grid h-14 w-14 place-items-center rounded-2xl bg-neutral-950 text-xl text-white"><i class="fa-solid <?= h($categoryIcon) ?>"></i></div>
                <h3 class="mt-5 text-lg font-black text-neutral-950"><?= h($category['name']) ?></h3>
                <p class="mt-2 text-sm font-bold text-red-600"><?= (int)$category['photographer_count'] ?> ช่างภาพ</p>
                <p class="mt-2 line-clamp-2 text-sm leading-6 text-neutral-600"><?= h($category['description']) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="marketplace-band py-16">
    <div class="stock-shell px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="section-kicker">ช่างภาพแนะนำ</p>
                <h2 class="mt-2 text-3xl font-black tracking-tight text-neutral-950">ช่างภาพแนะนำ</h2>
                <p class="mt-2 text-neutral-600">โปรไฟล์ที่ผ่านการอนุมัติ พร้อมผลงานและช่องทางติดต่อชัดเจน</p>
            </div>
            <a href="/photographers.php" class="stock-button rounded-full px-5 py-3 text-sm font-black"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหาช่างภาพ</a>
        </div>
        <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($featured as $p): ?>
                <?php include __DIR__ . '/includes/photographer_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="stock-shell px-4 py-16 sm:px-6 lg:px-8">
    <div class="grid gap-8 lg:grid-cols-[360px_1fr] lg:items-start">
        <div class="lg:sticky lg:top-24">
            <p class="section-kicker">คะแนนสูงสุด</p>
            <h2 class="mt-2 text-3xl font-black text-neutral-950">ช่างภาพคะแนนสูง</h2>
            <p class="mt-3 leading-7 text-neutral-600">เลือกจากคะแนน รีวิว และผลงานจริง เหมาะกับลูกค้าที่ต้องการความมั่นใจตั้งแต่ก่อนส่งคำขอจอง</p>
        </div>
        <div class="flex gap-5 overflow-x-auto pb-4">
            <?php foreach ($topRated as $p): ?>
                <?php
                $topRatedImage = null;
                if (!empty($p['featured_image'])) {
                    $topRatedImage = $p['featured_image'];
                } elseif (!empty($p['cover_image'])) {
                    $topRatedImage = $p['cover_image'];
                }
                ?>
                <a href="/photographer_detail.php?id=<?= (int)$p['id'] ?>" class="stock-card stock-card-hover w-80 shrink-0 rounded-[1.75rem] p-4">
                    <img class="h-48 w-full rounded-[1.35rem] object-cover" src="<?= h(public_image($topRatedImage, '/assets/uploads/seed/photo-1492691527719-9d1e07e534b4.jpg')) ?>" alt="">
                    <div class="p-2">
                        <div class="mt-4 flex items-start justify-between gap-3">
                            <h3 class="font-black text-neutral-950"><?= h($p['display_name']) ?></h3>
                            <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-600"><i class="fa-solid fa-star"></i> <?= number_format((float)$p['average_rating'], 1) ?></span>
                        </div>
                        <p class="mt-2 text-sm font-bold text-neutral-500"><?= h($p['district_name']) ?> · <?= (int)$p['total_reviews'] ?> รีวิว</p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="bg-neutral-950 py-16 text-white">
    <div class="stock-shell grid gap-10 px-4 sm:px-6 lg:grid-cols-[.9fr_1.1fr] lg:px-8">
        <div>
            <p class="text-sm font-black uppercase tracking-[0.22em] text-red-400">How it works</p>
            <h2 class="mt-2 text-4xl font-black">จองง่ายใน 4 ขั้นตอน</h2>
            <p class="mt-4 leading-8 text-white/62">ระบบช่วยให้ค้นหาและส่งคำขอจองได้เร็ว ส่วนการคุยราคาและชำระเงินเป็นเรื่องระหว่างลูกค้ากับช่างภาพโดยตรง</p>
        </div>
        <div class="grid gap-4">
            <?php foreach ([['fa-magnifying-glass','ค้นหาช่างภาพ','เลือกอำเภอ ประเภทงาน วันที่ คะแนน และงบประมาณ'],['fa-images','ดูผลงาน','ดูผลงาน รีวิว พื้นที่ให้บริการ และวันว่าง'],['fa-paper-plane','ส่งคำขอจอง','กรอกฟอร์มสั้น ๆ พร้อมรายละเอียดงาน'],['fa-comments','ติดต่อโดยตรง','โทร LINE Facebook Instagram หรือเว็บไซต์ของช่างภาพ']] as $step): ?>
                <div class="timeline-dot flex gap-4 rounded-[1.5rem] bg-white/8 p-5">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-red-600 text-white"><i class="fa-solid <?= h($step[0]) ?>"></i></div>
                    <div>
                        <h3 class="font-black"><?= h($step[1]) ?></h3>
                        <p class="mt-1 text-sm leading-6 text-white/62"><?= h($step[2]) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="stock-shell px-4 py-16 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-5">
        <div>
            <p class="section-kicker">Popular Districts</p>
            <h2 class="mt-2 text-3xl font-black text-neutral-950">อำเภอยอดนิยมในเชียงราย</h2>
        </div>
    </div>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ($popularDistricts as $district): ?>
            <a href="/photographers.php?district_id=<?= (int)$district['id'] ?>" class="stock-card stock-card-hover rounded-[1.6rem] p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-black text-neutral-950"><?= h($district['district_name']) ?></h3>
                        <p class="mt-1 text-sm font-bold text-neutral-500"><?= (int)$district['photographer_count'] ?> ช่างภาพ</p>
                    </div>
                    <div class="grid h-12 w-12 place-items-center rounded-2xl bg-red-50 text-red-600"><i class="fa-solid fa-location-dot"></i></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="marketplace-band py-16">
    <div class="stock-shell px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="section-kicker">ตัวอย่างผลงาน</p>
                <h2 class="mt-2 text-3xl font-black text-neutral-950">ผลงานล่าสุด</h2>
            </div>
        </div>
        <div class="masonry-gallery mt-8">
            <?php foreach ($portfolioShowcase as $item): ?>
                <a href="/photographer_detail.php?id=<?= (int)$item['photographer_id'] ?>" class="media-tile block rounded-[1.5rem] shadow-xl">
                    <img class="min-h-[220px]" src="<?= h(public_image($item['image_path'], '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg')) ?>" alt="">
                    <div class="media-overlay p-5 opacity-100">
                        <div>
                            <b><?= h($item['title']) ?></b>
                            <p class="mt-1 text-sm text-white/72"><?= h($item['display_name']) ?></p>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="stock-shell grid gap-8 px-4 py-16 sm:px-6 lg:grid-cols-[1.1fr_.9fr] lg:px-8">
    <div>
        <p class="section-kicker">รีวิวจากลูกค้า</p>
        <h2 class="mt-2 text-3xl font-black text-neutral-950">เสียงจากลูกค้า</h2>
        <div class="mt-8 grid gap-4 md:grid-cols-2">
            <?php foreach ($reviews as $review): ?>
                <article class="stock-card rounded-[1.5rem] p-6">
                    <div class="flex items-center gap-3">
                        <img class="h-12 w-12 rounded-2xl object-cover" src="<?= h(public_image($review['avatar'], '/assets/uploads/seed/photo-1494790108377-be9c29b29330.jpg')) ?>" alt="">
                        <div>
                            <p class="font-black text-neutral-950"><?= h($review['customer_name']) ?></p>
                            <p class="text-sm font-bold text-neutral-500">รีวิว <?= h($review['display_name']) ?></p>
                        </div>
                    </div>
                    <p class="mt-4 text-red-600"><?= str_repeat('★', (int)$review['rating_overall']) ?></p>
                    <p class="mt-3 line-clamp-3 text-sm leading-7 text-neutral-700"><?= h($review['comment']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="dashboard-hero rounded-[2rem] p-8 text-white">
        <p class="text-sm font-black uppercase tracking-[0.22em] text-white/58">Join as photographer</p>
        <h2 class="mt-3 text-4xl font-black">มีผลงานดี ให้ลูกค้าเชียงรายค้นเจอ</h2>
        <p class="mt-4 leading-8 text-white/68">สร้างโปรไฟล์ อัปโหลดผลงาน กำหนดพื้นที่ วันว่าง และรับคำขอจองผ่านระบบเดียว</p>
        <a href="/register.php?role=photographer" class="mt-8 inline-flex rounded-full bg-white px-6 py-3 font-black text-neutral-950 hover:bg-red-600 hover:text-white"><i class="fa-solid fa-user-plus mr-2"></i>สมัครเป็นช่างภาพ</a>
    </div>
</section>

<section class="marketplace-band py-16">
    <div class="stock-shell px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="section-kicker">Photo Articles</p>
                <h2 class="mt-2 text-3xl font-black text-neutral-950">บทความแนะนำการถ่ายภาพ</h2>
            </div>
        </div>
        <div class="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($articles as $article): ?>
                <article class="stock-card stock-card-hover rounded-[1.75rem]">
                    <img class="h-48 w-full object-cover" src="<?= h(public_image($article['cover_image'], '/assets/uploads/seed/photo-1487412720507-e7ab37603c6f.jpg')) ?>" alt="">
                    <div class="p-6">
                        <p class="text-sm font-black text-red-600"><?= h($article['display_name']) ?></p>
                        <h3 class="mt-2 text-xl font-black text-neutral-950"><?= h($article['title']) ?></h3>
                        <p class="mt-3 line-clamp-3 text-sm leading-7 text-neutral-600"><?= h(strip_tags($article['content'])) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="stock-shell px-4 py-16 sm:px-6 lg:px-8">
    <div class="grid gap-8 lg:grid-cols-[360px_1fr]">
        <div>
            <p class="section-kicker">คำถามที่พบบ่อย</p>
            <h2 class="mt-2 text-3xl font-black text-neutral-950">คำถามที่พบบ่อย</h2>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <?php foreach ([['เว็บไซต์รับชำระเงินไหม','ไม่รับชำระเงิน เว็บไซต์เป็นเพียงตัวกลางค้นหาและติดต่อช่างภาพเท่านั้น'],['ติดต่อช่างภาพอย่างไร','กดโทร LINE Facebook Instagram หรือเว็บไซต์ในหน้าโปรไฟล์ช่างภาพ'],['รีวิวได้เมื่อไหร่','ลูกค้ารีวิวได้เฉพาะงานที่เสร็จสิ้นแล้ว และ 1 รายการจองรีวิวได้ 1 ครั้ง'],['ถ้าไม่พบช่างภาพในพื้นที่ทำอย่างไร','ระบบจะแนะนำช่างภาพอำเภอใกล้เคียงโดยคำนวณจากพิกัด latitude/longitude']] as $faq): ?>
                <div class="stock-card rounded-[1.5rem] p-6">
                    <h3 class="font-black text-neutral-950"><?= h($faq[0]) ?></h3>
                    <p class="mt-3 text-sm leading-7 text-neutral-600"><?= h($faq[1]) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT p.*, d.district_name, u.phone AS user_phone FROM photographer_profiles p JOIN users u ON u.id = p.user_id LEFT JOIN districts d ON d.id = p.main_district_id WHERE p.id = ? AND p.approval_status = "approved" AND p.deleted_at IS NULL LIMIT 1');
$stmt->execute([$id]);
$profile = $stmt->fetch();
if (!$profile) {
    http_response_code(404);
    exit('Photographer not found');
}
db()->prepare('UPDATE photographer_profiles SET profile_views = profile_views + 1 WHERE id = ?')->execute([$id]);

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

$pageTitle = $profile['display_name'];
include __DIR__ . '/includes/header.php';
?>

<section class="relative bg-neutral-950 text-white">
    <div class="absolute inset-0">
        <img class="h-full w-full object-cover opacity-55" src="<?= h(public_image($profile['cover_image'], 'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?auto=format&fit=crop&w=1800&q=85')) ?>" alt="">
        <div class="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/65 to-neutral-950/25"></div>
    </div>
    <div class="relative stock-shell px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
        <div class="grid gap-8 lg:grid-cols-[1fr_360px] lg:items-end">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-neutral-950"><i class="fa-solid fa-shield-halved mr-1 text-red-600"></i>ยืนยันแล้ว</span>
                    <span class="rounded-full bg-red-600 px-3 py-1 text-xs font-black text-white"><i class="fa-solid fa-star mr-1"></i><?= number_format((float)$profile['average_rating'], 1) ?> / <?= (int)$profile['total_reviews'] ?> รีวิว</span>
                </div>
                <h1 class="mt-5 text-4xl font-black tracking-tight sm:text-6xl"><?= h($profile['display_name']) ?></h1>
                <p class="mt-4 max-w-3xl text-lg leading-8 text-white/78"><?= nl2br(h($profile['bio'])) ?></p>
                <div class="mt-6 flex flex-wrap gap-3 text-sm font-black">
                    <span class="rounded-full bg-white/12 px-4 py-2"><i class="fa-solid fa-location-dot mr-2 text-red-300"></i><?= h($profile['district_name']) ?></span>
                    <span class="rounded-full bg-white/12 px-4 py-2"><i class="fa-solid fa-briefcase mr-2 text-red-300"></i><?= (int)$profile['experience_years'] ?> ปี</span>
                    <span class="rounded-full bg-white/12 px-4 py-2"><i class="fa-solid fa-tag mr-2 text-red-300"></i>เริ่มต้น <?= number_format((float)$profile['starting_price']) ?> บาท</span>
                </div>
            </div>
            <div class="glass-panel rounded-[2rem] p-5 text-neutral-950">
                <div class="flex items-center gap-4">
                    <img class="h-20 w-20 rounded-3xl object-cover" src="<?= h(public_image($profile['profile_image'], 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=400&q=80')) ?>" alt="">
                    <div>
                        <p class="font-black"><?= h($profile['display_name']) ?></p>
                        <p class="mt-1 text-sm font-semibold text-neutral-500"><?= h($profile['district_name']) ?></p>
                    </div>
                </div>
                <p class="mt-5 rounded-2xl bg-red-50 p-4 text-sm font-black text-red-700"><?= h(PAYMENT_DISCLAIMER) ?></p>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <?php if ($profile['phone_public']): ?>
                        <a class="rounded-full bg-neutral-950 px-4 py-3 text-center text-sm font-black text-white hover:bg-red-600" href="tel:<?= h($profile['phone_public']) ?>">
                            <i class="fa-solid fa-phone mr-1"></i>โทร
                        </a>
                    <?php endif; ?>

                    <?php if ($profile['line_id']): ?>
                        <a class="rounded-full bg-[#06c755] px-4 py-3 text-center text-sm font-black text-white" href="https://line.me/ti/p/~<?= h($profile['line_id']) ?>">
                            LINE
                        </a>
                    <?php endif; ?>

                    <?php if ($profile['facebook_url']): ?>
                        <a class="rounded-full bg-[#1877f2] px-4 py-3 text-center text-sm font-black text-white" href="<?= h($profile['facebook_url']) ?>" target="_blank">
                            Facebook
                        </a>
                    <?php endif; ?>

                    <?php if ($profile['instagram_url']): ?>
                        <a class="rounded-full bg-[#d62976] px-4 py-3 text-center text-sm font-black text-white" href="<?= h($profile['instagram_url']) ?>" target="_blank">
                            Instagram
                        </a>
                    <?php endif; ?>

                    <?php if ($profile['website_url']): ?>
                        <a class="rounded-full border border-neutral-200 px-4 py-3 text-center text-sm font-black hover:bg-neutral-950 hover:text-white" href="<?= h($profile['website_url']) ?>" target="_blank">
                            Website
                        </a>
                    <?php endif; ?>
                </div>
                <a class="stock-button mt-4 block rounded-full px-5 py-3 text-center font-black" href="/customer/create_booking.php?photographer_id=<?= (int)$profile['id'] ?>">ส่งคำขอจอง</a>
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
                        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Portfolio gallery</p>
                        <h2 class="mt-1 text-3xl font-black text-neutral-950">ผลงานภาพถ่าย</h2>
                    </div>
                </div>
                <div class="mt-6 grid auto-rows-[220px] gap-4 md:grid-cols-3">
                    <?php foreach ($portfolio as $i => $item): ?>
                        <figure class="media-tile rounded-[1.5rem] <?= $i === 0 ? 'md:col-span-2 md:row-span-2' : '' ?>">
                            <img src="<?= h(public_image($item['image_path'], 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=900&q=85')) ?>" alt="">
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
                <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Services</p>
                <h2 class="mt-1 text-3xl font-black text-neutral-950">ข้อมูลบริการ</h2>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <?php foreach ($services as $s): ?>
                        <div class="stock-card stock-card-hover rounded-[1.5rem] p-6">
                            <div class="flex items-start justify-between gap-4">
                                <h3 class="font-black text-neutral-950"><i class="fa-solid <?= h($s['icon'] ?: 'fa-camera') ?> mr-2 text-red-600"></i><?= h($s['name']) ?></h3>
                                <span class="rounded-full bg-neutral-950 px-3 py-1 text-xs font-black text-white"><?= number_format((float)$s['starting_price']) ?> ฿</span>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-neutral-600"><?= h($s['description']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Reviews</p>
                <h2 class="mt-1 text-3xl font-black text-neutral-950">รีวิวจากลูกค้า</h2>
                <div class="mt-6 grid gap-4">
                    <?php foreach ($reviews as $r): ?>
                        <article class="stock-card rounded-[1.5rem] p-6">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <b class="text-neutral-950"><?= h($r['customer_name']) ?></b>
                                <span class="text-red-600"><?= str_repeat('★', (int)$r['rating_overall']) ?></span>
                            </div>
                            <p class="mt-3 leading-7 text-neutral-700"><?= nl2br(h($r['comment'])) ?></p>
                        </article>
                    <?php endforeach; ?>
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
                            <?= h($a['available_date']) ?> · <?= h(time_slot_label($a['time_slot'])) ?> · <?= h($a['status']) ?>
                        </div>
                    <?php endforeach; ?>
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
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

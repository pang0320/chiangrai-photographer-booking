<?php
require_once __DIR__ . '/includes/functions.php';
$categories = db_fetch_all('SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order');
$districts = db_fetch_all('SELECT * FROM districts WHERE is_active = 1 ORDER BY district_name');
$blogs = db_fetch_all('SELECT title, slug FROM blogs WHERE status = "published" AND deleted_at IS NULL ORDER BY published_at DESC LIMIT 20');
$photographers = db_fetch_all('SELECT id, display_name FROM photographer_profiles WHERE approval_status = "approved" AND is_featured = 1 AND deleted_at IS NULL ORDER BY display_name LIMIT 20');
$pageTitle = 'แผนผังเว็บไซต์';
include __DIR__ . '/includes/header.php';
?>
<section class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
    <p class="section-kicker">แผนผังเว็บไซต์</p>
    <h1 class="mt-2 text-4xl font-black">แผนผังเว็บไซต์</h1>
    <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-5">
        <div class="stock-card rounded-[1.75rem] p-6"><h2 class="font-black"><i class="fa-solid fa-link mr-2 text-red-600"></i>เมนูหลัก</h2><div class="mt-4 grid gap-2 text-sm font-bold"><a href="/about.php"><i class="fa-solid fa-circle-info mr-1 text-red-600"></i>เกี่ยวกับเรา</a><a href="/photographers.php"><i class="fa-solid fa-camera mr-1 text-red-600"></i>ช่างภาพ</a><a href="/blog.php"><i class="fa-solid fa-newspaper mr-1 text-red-600"></i>บทความ</a><a href="/faq.php"><i class="fa-solid fa-circle-question mr-1 text-red-600"></i>คำถามที่พบบ่อย</a><a href="/contact.php"><i class="fa-solid fa-envelope mr-1 text-red-600"></i>ติดต่อเรา</a></div></div>
        <div class="stock-card rounded-[1.75rem] p-6"><h2 class="font-black"><i class="fa-solid fa-layer-group mr-2 text-red-600"></i>หมวดหมู่</h2><div class="mt-4 grid gap-2 text-sm font-bold"><?php foreach ($categories as $c): ?><?= clean_context_button('/photographers.php', ['category_id' => (int)$c['id']], '<i class="fa-solid fa-tag mr-1 text-red-600"></i>' . h($c['name']), 'text-left') ?><?php endforeach; ?></div></div>
        <div class="stock-card rounded-[1.75rem] p-6"><h2 class="font-black"><i class="fa-solid fa-map mr-2 text-red-600"></i>อำเภอ</h2><div class="mt-4 grid gap-2 text-sm font-bold"><?php foreach ($districts as $d): ?><?= clean_context_button('/photographers.php', ['district_id' => (int)$d['id']], '<i class="fa-solid fa-location-dot mr-1 text-red-600"></i>' . h($d['district_name']), 'text-left') ?><?php endforeach; ?></div></div>
        <div class="stock-card rounded-[1.75rem] p-6"><h2 class="font-black"><i class="fa-solid fa-newspaper mr-2 text-red-600"></i>บทความ</h2><div class="mt-4 grid gap-2 text-sm font-bold"><?php foreach ($blogs as $blog): ?><?= clean_context_button('/blog_detail.php', ['slug' => $blog['slug']], '<i class="fa-solid fa-file-lines mr-1 text-red-600"></i>' . h($blog['title']), 'text-left') ?><?php endforeach; ?></div></div>
        <div class="stock-card rounded-[1.75rem] p-6"><h2 class="font-black"><i class="fa-solid fa-camera-retro mr-2 text-red-600"></i>ช่างภาพแนะนำ</h2><div class="mt-4 grid gap-2 text-sm font-bold"><?php foreach ($photographers as $p): ?><?= clean_context_button('/photographer_detail.php', ['id' => (int)$p['id']], '<i class="fa-solid fa-circle-check mr-1 text-red-600"></i>' . h($p['display_name']), 'text-left') ?><?php endforeach; ?></div></div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/functions.php';
$blogs = db_fetch_all('SELECT b.*, u.name AS admin_name
                       FROM blogs b
                       JOIN users u ON u.id = b.admin_id
                       WHERE b.status = "published" AND b.deleted_at IS NULL
                       ORDER BY b.published_at DESC, b.created_at DESC');
$pageTitle = 'Blog';
include __DIR__ . '/includes/header.php';
?>
<section class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="section-kicker">Blog</p>
            <h1 class="mt-2 text-4xl font-black">บทความจากระบบ</h1>
            <p class="mt-2 text-neutral-600">คำแนะนำการเลือกช่างภาพ การเตรียมตัว และเช็กลิสต์ก่อนวันถ่าย</p>
        </div>
        <a href="/photographers.php" class="stock-button rounded-full px-5 py-3 font-black"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหาช่างภาพ</a>
    </div>
    <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($blogs as $blog): ?>
            <article class="stock-card stock-card-hover rounded-[1.75rem]">
                <img class="h-56 w-full object-cover" src="<?= h(public_image($blog['cover_image'], 'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?auto=format&fit=crop&w=900&q=80')) ?>" alt="">
                <div class="p-6">
                    <p class="text-sm font-black text-red-600"><i class="fa-solid fa-user-shield mr-1"></i><?= h($blog['admin_name']) ?></p>
                    <h2 class="mt-2 text-xl font-black"><?= h($blog['title']) ?></h2>
                    <p class="mt-3 line-clamp-3 text-sm leading-7 text-neutral-600"><?= h($blog['excerpt']) ?></p>
                    <a href="/blog_detail.php?slug=<?= h($blog['slug']) ?>" class="mt-5 inline-flex rounded-full bg-neutral-950 px-4 py-2 text-sm font-black text-white hover:bg-red-600"><i class="fa-solid fa-eye mr-2"></i>อ่านต่อ</a>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$blogs): ?>
            <div class="empty-state rounded-[2rem] p-10 text-center md:col-span-2 xl:col-span-3">
                <i class="fa-solid fa-newspaper text-5xl text-red-600"></i>
                <h2 class="mt-4 text-2xl font-black">ยังไม่มีบทความ</h2>
                <p class="mt-2 text-neutral-600">บทความที่เผยแพร่จะแสดงที่นี่</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

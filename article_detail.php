<?php
require_once __DIR__ . '/includes/functions.php';

$cleanContext = clean_context_init(['slug']);
$slug = trim((string)clean_context_value($cleanContext, 'slug', ''));

$stmt = db()->prepare('SELECT a.*, p.display_name, p.slug AS photographer_slug, p.id AS photographer_id
                       FROM photographer_articles a
                       JOIN photographer_profiles p ON p.id = a.photographer_id
                       JOIN users u ON u.id = p.user_id
                       WHERE a.slug = ?
                         AND a.status = "published"
                         AND a.deleted_at IS NULL
                         AND p.deleted_at IS NULL
                         AND p.approval_status = "approved"
                         AND u.status = "active"
                         AND u.deleted_at IS NULL
                       LIMIT 1');
$stmt->execute([$slug]);
$article = $stmt->fetch();
if (!$article) {
    http_response_code(404);
    exit('ไม่พบบทความ');
}

$pageTitle = $article['title'];
include __DIR__ . '/includes/header.php';
?>

<article class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
    <div class="stock-card overflow-hidden rounded-[2rem]">
        <img class="h-[320px] w-full object-cover" src="<?= h(public_image($article['cover_image'], '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg')) ?>" alt="">
        <div class="p-6 sm:p-10">
            <div class="flex flex-wrap items-center gap-3 text-sm font-black text-red-600">
                <span class="rounded-full bg-amber-50 px-3 py-1 text-amber-700"><i class="fa-solid fa-camera-retro mr-1"></i>บทความจากช่างภาพ</span>
                <?= clean_context_button('/photographer_detail.php', ['slug' => $article['photographer_slug']], '<i class="fa-solid fa-camera mr-1"></i>' . h($article['display_name']), 'hover:text-neutral-950') ?>
                <span class="text-neutral-300">/</span>
                <span class="text-neutral-500"><?= h(format_be_datetime($article['published_at'] ?: $article['created_at'])) ?></span>
            </div>
            <h1 class="mt-4 max-w-4xl text-3xl font-black leading-tight text-neutral-950 sm:text-5xl"><?= h($article['title']) ?></h1>
            <div class="mt-8 max-w-4xl whitespace-pre-line text-base font-medium leading-8 text-neutral-700"><?= h($article['content']) ?></div>
            <div class="mt-8">
                <div class="flex flex-wrap gap-3">
                    <a href="/blog.php" class="rounded-full border border-neutral-200 px-5 py-3 font-black hover:bg-neutral-950 hover:text-white">
                        <i class="fa-solid fa-newspaper mr-2"></i>กลับไปหน้ารวมบทความ
                    </a>
                    <?= clean_context_button('/photographer_detail.php', ['slug' => $article['photographer_slug']], '<i class="fa-solid fa-camera mr-2"></i>ดูโปรไฟล์ช่างภาพ', 'rounded-full border border-neutral-200 px-5 py-3 font-black hover:bg-neutral-950 hover:text-white') ?>
                </div>
            </div>
        </div>
    </div>
</article>

<?php include __DIR__ . '/includes/footer.php'; ?>

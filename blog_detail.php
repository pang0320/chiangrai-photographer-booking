<?php
require_once __DIR__ . '/includes/functions.php';
$cleanContext = clean_context_init(['slug']);
$slug = trim((string)clean_context_value($cleanContext, 'slug', ''));
$stmt = db()->prepare('SELECT b.*, u.name AS admin_name
                       FROM blogs b
                       JOIN users u ON u.id = b.admin_id
                       WHERE b.slug = ? AND b.status = "published" AND b.deleted_at IS NULL
                       LIMIT 1');
$stmt->execute([$slug]);
$blog = $stmt->fetch();
if (!$blog) {
    http_response_code(404);
    exit('ไม่พบบทความ');
}
$tags = db_fetch_all('SELECT t.* FROM blog_tags bt JOIN tags t ON t.id = bt.tag_id WHERE bt.blog_id = ? ORDER BY t.name', [(int)$blog['id']]);
$pageTitle = $blog['title'];
include __DIR__ . '/includes/header.php';
?>
<article class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
    <div class="media-tile h-[420px] rounded-[2rem]">
        <img src="<?= h(public_image($blog['cover_image'], '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg')) ?>" alt="">
        <div class="media-overlay opacity-100 p-8">
            <div>
                <p class="section-kicker text-red-300">บทความ</p>
                <h1 class="mt-3 max-w-4xl text-4xl font-black text-white sm:text-6xl"><?= h($blog['title']) ?></h1>
            </div>
        </div>
    </div>
    <div class="mx-auto mt-8 max-w-4xl">
        <div class="flex flex-wrap gap-2">
            <?php foreach ($tags as $tag): ?>
                <span class="premium-chip"><i class="fa-solid fa-tag text-red-600"></i><?= h($tag['name']) ?></span>
            <?php endforeach; ?>
        </div>
        <div class="stock-card mt-6 rounded-[1.75rem] p-8 leading-8 text-neutral-700">
            <?= nl2br(h($blog['content'])) ?>
        </div>
    </div>
</article>
<?php include __DIR__ . '/includes/footer.php'; ?>

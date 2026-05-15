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

$tags = db_fetch_all('SELECT t.* FROM article_tags atg JOIN tags t ON t.id = atg.tag_id WHERE atg.article_id = ? ORDER BY t.name', [(int)$article['id']]);

if (is_post()) {
    verify_csrf();
    requireLogin();
    $user = current_user();
    $reason = trim((string)($_POST['reason'] ?? ''));
    $detail = trim((string)($_POST['detail'] ?? ''));
    if ($reason === '' || $detail === '') {
        flash('error', 'กรุณากรอกเหตุผลและรายละเอียดในการรายงาน');
    } elseif (text_length($reason) > 180) {
        flash('error', 'เหตุผลในการรายงานต้องไม่เกิน 180 ตัวอักษร');
    } elseif (text_length($detail) > 2000) {
        flash('error', 'รายละเอียดในการรายงานต้องไม่เกิน 2,000 ตัวอักษร');
    } else {
        $stmt = db()->prepare('INSERT INTO reports (reporter_id, target_type, target_id, reason, detail, status, created_at, updated_at) VALUES (?, "article", ?, ?, ?, "pending", NOW(), NOW())');
        $stmt->execute([(int)$user['id'], (int)$article['id'], $reason, $detail]);
        $reportId = (int)db()->lastInsertId();
        notify_admins('มีรายงานบทความใหม่', $article['title'], 'report', $reportId);
        flash('success', 'ส่งรายงานบทความให้ Admin ตรวจสอบแล้ว');
    }
    clean_redirect('/article_detail.php', ['slug' => $article['slug']]);
}

$pageTitle = $article['title'];
include __DIR__ . '/includes/header.php';
$allowedArticleTags = '<p><br><strong><b><em><i><u><ol><ul><li><h2><h3><blockquote>';
$safeArticleContent = strip_tags((string)$article['content'], $allowedArticleTags);
$safeArticleContent = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $safeArticleContent);
$safeArticleContent = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $safeArticleContent);
$articleDate = $article['published_at'] ?: $article['created_at'];
$visibleTags = array_slice($tags, 0, 4);
$hiddenTagCount = max(0, count($tags) - count($visibleTags));
?>

<article class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
    <div class="stock-card overflow-hidden rounded-[2rem]">
        <img class="h-[320px] w-full object-cover" src="<?= h(public_image($article['cover_image'], '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg')) ?>" alt="">
        <div class="p-6 sm:p-10">
            <div class="flex flex-wrap items-center gap-3 text-sm font-black text-red-600">
                <span class="rounded-full bg-amber-50 px-3 py-1 text-amber-700"><i class="fa-solid fa-camera-retro mr-1"></i>บทความจากช่างภาพ</span>
                <?= new_content_badge($articleDate) ?>
                <?= clean_context_button('/photographer_detail.php', ['slug' => $article['photographer_slug']], '<i class="fa-solid fa-camera mr-1"></i>' . h($article['display_name']), 'hover:text-neutral-950') ?>
                <span class="text-neutral-300">/</span>
                <span class="text-neutral-500"><?= h(format_be_datetime($articleDate)) ?></span>
            </div>
            <h1 class="mt-4 max-w-4xl text-3xl font-black leading-tight text-neutral-950 sm:text-5xl"><?= h($article['title']) ?></h1>
            <?php if ($tags): ?>
                <div class="mt-5 flex flex-wrap gap-2">
                    <?php foreach ($visibleTags as $tag): ?>
                        <span class="premium-chip"><i class="fa-solid fa-tag text-red-600"></i><?= h($tag['name']) ?></span>
                    <?php endforeach; ?>
                    <?php if ($hiddenTagCount > 0): ?>
                        <span class="premium-chip bg-neutral-950 text-white">+<?= number_format($hiddenTagCount) ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="prose prose-neutral mt-8 max-w-4xl text-base font-medium leading-8 text-neutral-700"><?= $safeArticleContent ?></div>
            <div class="mt-8">
                <div class="flex flex-wrap gap-3">
                    <a href="/blog.php" class="rounded-full border border-neutral-200 px-5 py-3 font-black hover:bg-neutral-950 hover:text-white">
                        <i class="fa-solid fa-newspaper mr-2"></i>กลับไปหน้ารวมบทความ
                    </a>
                    <?= clean_context_button('/photographer_detail.php', ['slug' => $article['photographer_slug']], '<i class="fa-solid fa-camera mr-2"></i>ดูโปรไฟล์ช่างภาพ', 'rounded-full border border-neutral-200 px-5 py-3 font-black hover:bg-neutral-950 hover:text-white') ?>
                </div>
            </div>
            <form method="post" class="mt-8 grid gap-3 rounded-[1.5rem] bg-neutral-50 p-5">
                <?= csrf_field() ?>
                <h2 class="font-black text-neutral-950"><i class="fa-solid fa-triangle-exclamation mr-2 text-red-600"></i>รายงานบทความนี้</h2>
                <input name="reason" required maxlength="180" placeholder="เหตุผล เช่น เนื้อหาไม่เหมาะสม" class="stock-input rounded-2xl px-4 py-3 font-semibold">
                <textarea name="detail" required maxlength="2000" rows="3" placeholder="รายละเอียดเพิ่มเติม" class="stock-input rounded-2xl px-4 py-3 font-semibold"></textarea>
                <button class="btn-danger btn-md justify-self-start"><i class="fa-solid fa-paper-plane"></i>ส่งรายงาน</button>
            </form>
        </div>
    </div>
</article>

<?php include __DIR__ . '/includes/footer.php'; ?>

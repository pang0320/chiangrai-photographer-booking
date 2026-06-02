<?php
require_once __DIR__ . '/includes/functions.php';
ensure_tags_status_column();
ensure_photographer_articles_excerpt_column();

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

$tags = db_fetch_all('SELECT t.*
                      FROM article_tags atg
                      JOIN tags t ON t.id = atg.tag_id
                      WHERE atg.article_id = ?
                        AND t.is_active = 1
                      ORDER BY t.name', [(int)$article['id']]);

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
    <div class="stock-card rounded-[2rem]" style="overflow: visible;">
        <img class="h-[320px] w-full rounded-t-[2rem] object-cover" src="<?= h(public_image($article['cover_image'], '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg')) ?>" alt="">
        <div class="p-6 sm:p-10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3 text-sm font-black text-red-600">
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-amber-700"><i class="fa-solid fa-camera-retro mr-1"></i>บทความจากช่างภาพ</span>
                    <?= new_content_badge($articleDate) ?>
                    <?= clean_context_button('/photographer_detail.php', ['slug' => $article['photographer_slug']], '<i class="fa-solid fa-camera mr-1"></i>' . h($article['display_name']), 'hover:text-neutral-950') ?>
                    <span class="text-neutral-300">/</span>
                    <span class="text-neutral-500"><?= h(format_be_datetime($articleDate)) ?></span>
                </div>

                <?php if (current_user()): ?>
                    <details class="group relative z-20 open:z-50 hover:z-50 focus-within:z-50">
                        <summary class="grid h-10 w-10 cursor-pointer list-none place-items-center rounded-full bg-white border border-neutral-200 text-neutral-600 shadow-sm transition hover:bg-neutral-950 hover:text-white group-open:bg-neutral-950 group-open:text-white" title="เมนูเพิ่มเติม">
                            <i class="fa-solid fa-ellipsis"></i>
                        </summary>
                        <div class="absolute right-0 top-12 w-72 rounded-2xl border border-neutral-200 bg-white p-2 text-left shadow-2xl shadow-neutral-950/15 ring-1 ring-black/5">
                            <details class="group/report">
                                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-black text-neutral-700 hover:bg-neutral-50 hover:text-red-600 group-open/report:bg-neutral-50 group-open/report:text-red-600">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    รายงานบทความนี้
                                </summary>
                                <div class="mt-2 border-t border-neutral-100 pt-3">
                                    <p class="mb-3 text-[11px] font-bold leading-5 text-neutral-500">ส่งให้ผู้ดูแลระบบตรวจสอบปัญหาที่พบในบทความนี้</p>
                                    <form method="post" class="grid gap-3">
                                        <?= csrf_field() ?>
                                        <label class="grid gap-1 text-[11px] font-black text-neutral-600">
                                            <span>เหตุผลในการรายงาน <?= required_mark() ?></span>
                                            <input name="reason" required maxlength="180" placeholder="เช่น เนื้อหาไม่เหมาะสม" class="stock-input rounded-xl px-3 py-2 text-sm">
                                        </label>
                                        <label class="grid gap-1 text-[11px] font-black text-neutral-600">
                                            <span>รายละเอียดเพิ่มเติม <?= required_mark() ?></span>
                                            <textarea name="detail" required maxlength="2000" rows="3" placeholder="พิมพ์รายละเอียดปัญหาที่ต้องการให้ผู้ดูแลตรวจสอบ" class="stock-input rounded-xl px-3 py-2 text-sm"></textarea>
                                        </label>
                                        <button class="btn-danger btn-sm w-full rounded-xl">
                                            <i class="fa-solid fa-paper-plane mr-2"></i>ส่งรายงาน
                                        </button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    </details>
                <?php endif; ?>
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
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="/blog.php" class="rounded-full border border-neutral-200 px-5 py-3 font-black hover:bg-neutral-950 hover:text-white">
                    <i class="fa-solid fa-newspaper mr-2"></i>กลับไปหน้ารวมบทความ
                </a>
                <?= clean_context_button('/photographer_detail.php', ['slug' => $article['photographer_slug']], '<i class="fa-solid fa-camera mr-2"></i>ดูโปรไฟล์ช่างภาพ', 'rounded-full border border-neutral-200 px-5 py-3 font-black hover:bg-neutral-950 hover:text-white') ?>
            </div>
        </div>
    </div>
</article>

<?php include __DIR__ . '/includes/footer.php'; ?>

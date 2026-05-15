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
$currentUser = current_user();

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
        $stmt->execute([(int)$user['id'], (int)$blog['id'], $reason, $detail]);
        flash('success', 'ส่งรายงานบทความให้ Admin ตรวจสอบแล้ว');
    }
    clean_redirect('/blog_detail.php', ['slug' => $blog['slug']]);
}

$articleDate = $blog['published_at'] ?: $blog['created_at'];
$visibleTags = array_slice($tags, 0, 4);
$hiddenTagCount = max(0, count($tags) - count($visibleTags));
$pageTitle = $blog['title'];
include __DIR__ . '/includes/header.php';
?>
<article class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
    <div class="media-tile h-[420px] rounded-[2rem]">
        <img src="<?= h(public_image($blog['cover_image'], '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg')) ?>" alt="">
        <div class="media-overlay opacity-100 p-8">
            <div>
                <p class="section-kicker text-red-300"><i class="fa-solid fa-user-shield mr-2"></i>บทความจากระบบ</p>
                <?= new_content_badge($articleDate) ?>
                <h1 class="mt-3 max-w-4xl text-4xl font-black text-white sm:text-6xl"><?= h($blog['title']) ?></h1>
                <p class="mt-4 text-sm font-black text-white/70">
                    <i class="fa-solid fa-user mr-1"></i><?= h($blog['admin_name']) ?>
                    <span class="mx-2 text-white/35">/</span>
                    <i class="fa-solid fa-calendar-day mr-1"></i><?= h(format_be_datetime($articleDate)) ?>
                </p>
            </div>
        </div>
    </div>
    <div class="mx-auto mt-8 max-w-4xl">
        <div class="flex flex-wrap gap-2">
            <?php foreach ($visibleTags as $tag): ?>
                <span class="premium-chip"><i class="fa-solid fa-tag text-red-600"></i><?= h($tag['name']) ?></span>
            <?php endforeach; ?>
            <?php if ($hiddenTagCount > 0): ?>
                <span class="premium-chip bg-neutral-950 text-white">+<?= number_format($hiddenTagCount) ?></span>
            <?php endif; ?>
        </div>
        <div class="stock-card mt-6 rounded-[1.75rem] p-8 leading-8 text-neutral-700">
            <?= nl2br(h($blog['content'])) ?>
        </div>
        <div class="mt-6">
            <a href="/blog.php" class="inline-flex rounded-full border border-neutral-200 px-5 py-3 font-black hover:bg-neutral-950 hover:text-white">
                <i class="fa-solid fa-newspaper mr-2"></i>กลับไปหน้ารวมบทความ
            </a>
        </div>
        <form method="post" class="stock-card mt-6 grid gap-3 rounded-[1.5rem] p-5">
            <?= csrf_field() ?>
            <h2 class="font-black text-neutral-950"><i class="fa-solid fa-triangle-exclamation mr-2 text-red-600"></i>รายงานบทความนี้</h2>
            <input name="reason" required maxlength="180" placeholder="เหตุผล เช่น เนื้อหาไม่เหมาะสม" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <textarea name="detail" required maxlength="2000" rows="3" placeholder="รายละเอียดเพิ่มเติม" class="stock-input rounded-2xl px-4 py-3 font-semibold"></textarea>
            <button class="btn-danger btn-md justify-self-start"><i class="fa-solid fa-paper-plane"></i>ส่งรายงาน</button>
        </form>
    </div>
</article>
<?php include __DIR__ . '/includes/footer.php'; ?>

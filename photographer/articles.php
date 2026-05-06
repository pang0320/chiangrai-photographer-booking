<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');

$profile = photographer_profile_by_user((int)current_user()['id']);
$pid = (int)$profile['id'];

if (is_post()) {
    verify_csrf();

    try {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = db()->prepare('UPDATE photographer_articles SET deleted_at = NOW() WHERE id = ? AND photographer_id = ?');
            $stmt->execute([$id, $pid]);
            flash('success', 'ลบบทความแล้ว');
        } else {
            $cover = upload_image($_FILES['cover_image'] ?? [], 'articles');
            $title = trim((string)($_POST['title'] ?? ''));
            $status = (string)($_POST['status'] ?? 'draft');
            $content = trim((string)($_POST['content'] ?? ''));

            $stmt = db()->prepare('INSERT INTO photographer_articles (photographer_id, title, slug, cover_image, content, status, published_at, created_at, updated_at)
                                   VALUES (?, ?, ?, ?, ?, ?, IF(? = "published", NOW(), NULL), NOW(), NOW())');
            $stmt->execute([$pid, $title, unique_slug('photographer_articles', $title), $cover, $content, $status, $status]);
            flash('success', 'บันทึกบทความแล้ว');
        }

        log_activity('manage_articles', 'photographer_articles', $pid);
        redirect('/photographer/articles.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
}

$stmt = db()->prepare('SELECT * FROM photographer_articles WHERE photographer_id = ? AND deleted_at IS NULL ORDER BY created_at DESC');
$stmt->execute([$pid]);
$items = $stmt->fetchAll();

$pageTitle = 'บทความ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">สตูดิโอช่างภาพ</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">บทความ/คำแนะนำ</h1>
    </div>

    <form method="post" enctype="multipart/form-data" class="stock-card mt-6 grid gap-4 rounded-[1.5rem] p-5">
        <?= csrf_field() ?>
        <input name="title" required placeholder="หัวข้อ" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <label class="grid gap-2 text-sm font-black text-neutral-700">
            <span><i class="fa-solid fa-image mr-2 text-red-600"></i>รูปปกบทความ</span>
            <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <span class="text-xs font-bold leading-6 text-neutral-500"><?= h(UPLOAD_IMAGE_HELP_TEXT) ?></span>
        </label>
        <textarea name="content" rows="8" required placeholder="เนื้อหา" class="stock-input rounded-2xl px-4 py-3 font-semibold"></textarea>
        <select name="status" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <option value="draft">ฉบับร่าง</option>
            <option value="published">เผยแพร่</option>
            <option value="hidden">ซ่อน</option>
        </select>
        <button class="stock-button rounded-full px-5 py-3 font-black"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกบทความ</button>
    </form>

    <div class="mt-6 grid gap-3">
        <?php foreach ($items as $item): ?>
            <div class="stock-card flex flex-wrap justify-between gap-3 rounded-[1.35rem] p-4">
                <div>
                    <b><?= h($item['title']) ?></b>
                    <p class="mt-1 text-sm"><?= status_badge($item['status']) ?></p>
                </div>

                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                    <button data-confirm="ลบบทความนี้?" class="rounded-full bg-red-50 px-3 py-2 text-sm font-black text-red-700">
                        <i class="fa-solid fa-trash mr-1"></i>ลบ
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

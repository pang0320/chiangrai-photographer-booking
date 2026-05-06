<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$adminUser = current_user();
$editId = 0;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
}

function save_blog_tags(int $blogId, string $tagText): void
{
    $stmt = db()->prepare('DELETE FROM blog_tags WHERE blog_id = ?');
    $stmt->execute([$blogId]);

    $rawTags = explode(',', $tagText);
    foreach ($rawTags as $rawTag) {
        $tagName = trim($rawTag);
        if ($tagName === '') {
            continue;
        }

        $slug = unique_slug('tags', $tagName);
        $existingId = db_fetch_value('SELECT id FROM tags WHERE name = ? OR slug = ? LIMIT 1', [$tagName, slugify($tagName)]);
        if ($existingId) {
            $tagId = (int)$existingId;
        } else {
            $stmt = db()->prepare('INSERT INTO tags (name, slug, created_at) VALUES (?, ?, NOW())');
            $stmt->execute([$tagName, $slug]);
            $tagId = (int)db()->lastInsertId();
        }

        $stmt = db()->prepare('INSERT IGNORE INTO blog_tags (blog_id, tag_id) VALUES (?, ?)');
        $stmt->execute([$blogId, $tagId]);
    }
}

if (is_post()) {
    verify_csrf();

    $action = (string)($_POST['action'] ?? 'save');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $stmt = db()->prepare('UPDATE blogs SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
        log_activity('delete_blog', 'blogs', $id);
        flash('success', 'ลบบทความแล้ว');
        redirect('/admin/blogs.php');
    }

    if ($action === 'status') {
        $status = (string)($_POST['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'published', 'hidden'], true)) {
            $status = 'draft';
        }
        $publishedSql = 'published_at';
        if ($status === 'published') {
            $publishedSql = 'IFNULL(published_at, NOW())';
        }
        $stmt = db()->prepare('UPDATE blogs SET status = ?, published_at = ' . $publishedSql . ', updated_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $id]);
        log_activity('update_blog_status', 'blogs', $id);
        flash('success', 'อัปเดตสถานะบทความแล้ว');
        redirect('/admin/blogs.php');
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $excerpt = trim((string)($_POST['excerpt'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $status = (string)($_POST['status'] ?? 'draft');
    $tagText = trim((string)($_POST['tags'] ?? ''));

    if (!in_array($status, ['draft', 'published', 'hidden'], true)) {
        $status = 'draft';
    }

    if ($title === '' || $content === '') {
        flash('error', 'กรุณากรอกหัวข้อและเนื้อหาบทความ');
        redirect('/admin/blogs.php');
    }

    $currentCover = '';
    if ($id > 0) {
        $currentCover = (string)db_fetch_value('SELECT cover_image FROM blogs WHERE id = ? LIMIT 1', [$id]);
    }

    try {
        $coverImage = null;
        if (isset($_FILES['cover_image'])) {
            $coverImage = upload_image($_FILES['cover_image'], 'articles');
        }
        if (!$coverImage) {
            $coverImage = $currentCover;
        }

        $slug = unique_slug('blogs', $title, $id > 0 ? $id : null);
        $publishedAt = null;
        if ($status === 'published') {
            $publishedAt = date('Y-m-d H:i:s');
        }

        if ($id > 0) {
            $stmt = db()->prepare('UPDATE blogs SET title = ?, slug = ?, cover_image = ?, excerpt = ?, content = ?, status = ?, published_at = IF(? = "published", IFNULL(published_at, NOW()), published_at), updated_at = NOW() WHERE id = ?');
            $stmt->execute([$title, $slug, $coverImage, $excerpt, $content, $status, $status, $id]);
            save_blog_tags($id, $tagText);
            log_activity('update_blog', 'blogs', $id);
        } else {
            $stmt = db()->prepare('INSERT INTO blogs (admin_id, title, slug, cover_image, excerpt, content, status, published_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([(int)$adminUser['id'], $title, $slug, $coverImage, $excerpt, $content, $status, $publishedAt]);
            $id = (int)db()->lastInsertId();
            save_blog_tags($id, $tagText);
            log_activity('create_blog', 'blogs', $id);
        }

        flash('success', 'บันทึกบทความแล้ว');
        redirect('/admin/blogs.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('/admin/blogs.php');
    }
}

$editBlog = null;
$editTags = '';
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM blogs WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$editId]);
    $editBlog = $stmt->fetch();
    if ($editBlog) {
        $editTags = (string)db_fetch_value('SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ", ") FROM blog_tags bt JOIN tags t ON t.id = bt.tag_id WHERE bt.blog_id = ?', [$editId]);
    }
}

$items = db_fetch_all('SELECT b.*, u.name AS admin_name,
                       (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ", ") FROM blog_tags bt JOIN tags t ON t.id = bt.tag_id WHERE bt.blog_id = b.id) AS tags
                       FROM blogs b
                       JOIN users u ON u.id = b.admin_id
                       WHERE b.deleted_at IS NULL
                       ORDER BY b.created_at DESC');

$pageTitle = 'จัดการบทความเว็บ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="section-kicker">บทความส่วนกลาง</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950"><i class="fa-solid fa-newspaper mr-2 text-red-600"></i>จัดการบทความเว็บ</h1>
        </div>
        <a href="/blog.php" target="_blank" class="rounded-full border border-neutral-200 px-5 py-3 text-sm font-black hover:bg-neutral-950 hover:text-white"><i class="fa-solid fa-eye mr-2"></i>ดูหน้าบทความ</a>
    </div>

    <form method="post" enctype="multipart/form-data" class="stock-card mt-6 grid gap-4 rounded-[1.75rem] p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?php if ($editBlog): ?><?= (int)$editBlog['id'] ?><?php endif; ?>">
        <div class="grid gap-4 lg:grid-cols-3">
            <label class="grid gap-2 text-sm font-black text-neutral-700 lg:col-span-2">
                <span><i class="fa-solid fa-heading mr-1 text-red-600"></i>หัวข้อ</span>
                <input name="title" required value="<?php if ($editBlog): ?><?= h($editBlog['title']) ?><?php endif; ?>" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            </label>
            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-signal mr-1 text-red-600"></i>สถานะ</span>
                <select name="status" class="stock-input rounded-2xl px-4 py-3 font-semibold">
                    <?php foreach (['draft', 'published', 'hidden'] as $status): ?>
                        <option value="<?= h($status) ?>" <?php if ($editBlog && $editBlog['status'] === $status): ?>selected<?php endif; ?>><?= h(booking_status_label($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label class="grid gap-2 text-sm font-black text-neutral-700">
            <span><i class="fa-solid fa-align-left mr-1 text-red-600"></i>คำโปรย</span>
            <textarea name="excerpt" rows="2" class="stock-input rounded-2xl px-4 py-3 font-semibold"><?php if ($editBlog): ?><?= h($editBlog['excerpt']) ?><?php endif; ?></textarea>
        </label>
        <label class="grid gap-2 text-sm font-black text-neutral-700">
            <span><i class="fa-solid fa-pen-nib mr-1 text-red-600"></i>เนื้อหา</span>
            <textarea name="content" rows="8" required class="stock-input rounded-2xl px-4 py-3 font-semibold"><?php if ($editBlog): ?><?= h($editBlog['content']) ?><?php endif; ?></textarea>
        </label>
        <div class="grid gap-4 lg:grid-cols-2">
            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-tags mr-1 text-red-600"></i>แท็ก คั่นด้วย comma</span>
                <input name="tags" value="<?= h($editTags) ?>" class="stock-input rounded-2xl px-4 py-3 font-semibold" placeholder="งานแต่ง, พอร์ตเทรต, เชียงราย">
            </label>
            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-image mr-1 text-red-600"></i>รูปปก</span>
                <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" class="stock-input rounded-2xl px-4 py-3 font-semibold">
                <span class="text-xs font-bold leading-6 text-neutral-500"><?= h(UPLOAD_IMAGE_HELP_TEXT) ?></span>
            </label>
        </div>
        <button class="stock-button rounded-2xl px-5 py-3 font-black"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกบทความ</button>
    </form>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.75rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>หัวข้อ</th>
                    <th>ผู้ดูแล</th>
                    <th>แท็ก</th>
                    <th>สถานะ</th>
                    <th>วันที่</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <a class="font-black text-red-600" href="/blog_detail.php?slug=<?= h($item['slug']) ?>" target="_blank"><?= h($item['title']) ?></a>
                        </td>
                        <td><?= h($item['admin_name']) ?></td>
                        <td><?= h($item['tags']) ?></td>
                        <td><?= status_badge($item['status']) ?></td>
                        <td><?= h(format_be_datetime($item['created_at'])) ?></td>
                        <td>
                            <div class="flex flex-wrap gap-2">
                                <a href="/admin/blogs.php?edit=<?= (int)$item['id'] ?>" class="rounded-full bg-amber-50 px-3 py-1.5 font-black text-amber-700"><i class="fa-solid fa-pen mr-1"></i>แก้ไข</a>
                                <form method="post" class="flex flex-wrap gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                    <button name="status" value="published" class="rounded-full bg-emerald-50 px-3 py-1.5 font-black text-emerald-700"><i class="fa-solid fa-check mr-1"></i>เผยแพร่</button>
                                    <button name="status" value="hidden" class="rounded-full bg-slate-100 px-3 py-1.5 font-black text-slate-700"><i class="fa-solid fa-eye-slash mr-1"></i>ซ่อน</button>
                                </form>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                    <button data-confirm="ลบบทความนี้?" class="rounded-full bg-red-50 px-3 py-1.5 font-black text-red-700"><i class="fa-solid fa-trash mr-1"></i>ลบ</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$adminUser = current_user();
$cleanContext = clean_context_init(['edit', 'q', 'status']);
$editId = 0;
if (isset($cleanContext['edit'])) {
    $editId = (int)$cleanContext['edit'];
}

/**
 * บันทึกแท็กของบทความเว็บ โดยการซิงค์ข้อมูลความสัมพันธ์ในฐานข้อมูล
 *
 * @param int $blogId รหัสบทความเว็บ
 * @param array $tagIds รายการรหัสแท็กที่ต้องการบันทึก
 * @return void
 */
function save_blog_tags(int $blogId, array $tagIds): void
{
    sync_article_tag_relations('blog_tags', 'blog_id', $blogId, $tagIds);
}

if (is_post()) {
    verify_csrf();

    $action = (string)($_POST['action'] ?? 'save');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $stmt = db()->prepare('UPDATE blogs SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
        log_activity('hide_blog', 'blogs', $id);
        flash('success', 'ลบบทความออกจากรายการแล้ว ข้อมูลเดิมยังอยู่');
        clean_redirect('/admin/blogs.php', []);
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
        clean_redirect('/admin/blogs.php', []);
    }

    $currentBlog = null;
    $currentCover = '';
    if ($id > 0) {
        $stmt = db()->prepare('SELECT * FROM blogs WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $currentBlog = $stmt->fetch();
        if ($currentBlog) {
            $currentCover = (string)($currentBlog['cover_image'] ?? '');
        } else {
            flash('error', 'ไม่พบบทความที่ต้องการแก้ไข');
            clean_redirect('/admin/blogs.php', []);
        }
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $excerpt = trim((string)($_POST['excerpt'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $contentFallback = trim((string)($_POST['content_fallback'] ?? ''));
    $status = (string)($_POST['status'] ?? 'draft');
    $tagIds = selected_article_tag_ids_from_post();

    if ($content === '' && $contentFallback !== '') {
        $fallbackParagraphs = [];
        $fallbackLines = preg_split('/\R+/', $contentFallback);
        if (!is_array($fallbackLines)) {
            $fallbackLines = [$contentFallback];
        }

        foreach ($fallbackLines as $fallbackLine) {
            $fallbackLine = trim((string)$fallbackLine);
            if ($fallbackLine !== '') {
                $fallbackParagraphs[] = '<p>' . h($fallbackLine) . '</p>';
            }
        }

        $content = implode('', $fallbackParagraphs);
    }

    if ($content === '' && $currentBlog && !empty($currentBlog['content'])) {
        $content = (string)$currentBlog['content'];
    }

    if (!in_array($status, ['draft', 'published', 'hidden'], true)) {
        $status = 'draft';
    }

    $plainContent = trim(strip_tags($content));
    if ($title === '' || $plainContent === '') {
        flash('error', 'กรุณากรอกหัวข้อและเนื้อหาบทความ');
        clean_redirect('/admin/blogs.php', $id > 0 ? ['edit' => $id] : []);
    }

    try {
        $coverImage = upload_image($_FILES['cover_image'] ?? [], 'articles');
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
            save_blog_tags($id, $tagIds);
            log_activity('update_blog', 'blogs', $id);
        } else {
            $stmt = db()->prepare('INSERT INTO blogs (admin_id, title, slug, cover_image, excerpt, content, status, published_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([(int)$adminUser['id'], $title, $slug, $coverImage, $excerpt, $content, $status, $publishedAt]);
            $id = (int)db()->lastInsertId();
            save_blog_tags($id, $tagIds);
            log_activity('create_blog', 'blogs', $id);
        }

        flash('success', 'บันทึกบทความแล้ว');
        clean_redirect('/admin/blogs.php', []);
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        clean_redirect('/admin/blogs.php', []);
    }
}

$editBlog = null;
$editTagIds = [];
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM blogs WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$editId]);
    $editBlog = $stmt->fetch();
    if ($editBlog) {
        $editTagIds = selected_article_tag_ids('blog_tags', 'blog_id', $editId);
    }
}
$tagSelectorHtml = article_tag_selector_html($editTagIds, 'tag_ids', null);
$allowedBlogTagNames = [];
foreach (article_tag_options(null) as $allowedTagGroup) {
    foreach ($allowedTagGroup as $allowedTag) {
        $allowedBlogTagNames[(string)$allowedTag['name']] = true;
    }
}
$editStatus = '';
if ($editBlog) {
    $editStatus = (string)$editBlog['status'];
}

$q = trim((string)clean_context_value($cleanContext, 'q', ''));
$statusFilter = (string)clean_context_value($cleanContext, 'status', '');
$where = ['b.deleted_at IS NULL'];
$params = [];

if ($q !== '') {
    $where[] = '(b.title LIKE ? OR u.name LIKE ? OR b.status LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($statusFilter !== '') {
    $where[] = 'b.status = ?';
    $params[] = $statusFilter;
}

$items = db_fetch_all('SELECT b.*, u.name AS admin_name,
                       (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ", ")
                        FROM blog_tags bt
                        JOIN tags t ON t.id = bt.tag_id
                        WHERE bt.blog_id = b.id
                          AND t.is_active = 1) AS tags
                       FROM blogs b
                       JOIN users u ON u.id = b.admin_id
                       WHERE ' . implode(' AND ', $where) . '
                       ORDER BY COALESCE(b.published_at, b.created_at) DESC', $params);

$pageTitle = 'จัดการบทความเว็บ';
include __DIR__ . '/../includes/header.php';
?>

<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="section-kicker">บทความส่วนกลาง</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950"><i class="fa-solid fa-newspaper mr-2 text-red-600"></i>จัดการบทความเว็บ</h1>
            <p class="mt-2 max-w-3xl text-sm font-bold leading-7 text-neutral-500">บทความจากระบบ แสดงลำดับ วันที่โพสต์ ผู้เขียน และแหล่งที่มาให้ชัดเจน ช่องค้นหาค้นจากหัวข้อ/ผู้เขียน/สถานะ</p>
        </div>
        <a href="/blog.php" target="_blank" class="rounded-full border border-neutral-200 px-5 py-3 text-sm font-black hover:bg-neutral-950 hover:text-white"><i class="fa-solid fa-eye mr-2"></i>ดูหน้าบทความ</a>
    </div>

    <form id="article-form" method="post" enctype="multipart/form-data" class="mt-6 grid gap-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php if ($editBlog): ?><?= (int)$editBlog['id'] ?><?php endif; ?>">
        <input id="article-content" type="hidden" name="content" value="<?php if ($editBlog): ?><?= h($editBlog['content']) ?><?php endif; ?>">

        <div class="stock-card grid gap-4 rounded-[1.75rem] p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="section-kicker"><i class="fa-solid fa-pen-to-square mr-2"></i><?= $editBlog ? 'แก้ไขบทความเว็บ' : 'เพิ่มบทความเว็บใหม่' ?></p>
                    <h2 class="mt-1 text-2xl font-black text-neutral-950"><?= $editBlog ? h($editBlog['title']) : 'เขียนบทความใหม่' ?></h2>
                    <p class="mt-2 text-sm font-bold leading-7 text-neutral-500">รูปแบบการลงบทความหน้านี้ใช้ชุดเดียวกับบทความของช่างภาพ เพื่อให้แก้ไขและเผยแพร่ได้เหมือนกัน</p>
                </div>
                <?php if ($editBlog): ?>
                    <button type="submit" form="article-reset-form" class="btn-muted btn-md">
                        <i class="fa-solid fa-plus mr-2"></i>เพิ่มบทความใหม่
                    </button>
                <?php endif; ?>
            </div>

            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-heading mr-2 text-red-600"></i>หัวข้อบทความ <?= required_mark() ?></span>
                <input name="title" required value="<?php if ($editBlog): ?><?= h($editBlog['title']) ?><?php endif; ?>" placeholder="หัวข้อบทความ" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            </label>

            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-image mr-2 text-red-600"></i>รูปปกบทความ</span>
                <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" class="stock-input rounded-2xl px-4 py-3 font-semibold">
                <span class="text-xs font-bold leading-6 text-neutral-500"><?= h(UPLOAD_IMAGE_HELP_TEXT) ?> <?php if ($editBlog && !empty($editBlog['cover_image'])): ?>ถ้าไม่เลือกไฟล์ใหม่ ระบบจะใช้รูปเดิม<?php endif; ?></span>
            </label>

            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-align-left mr-2 text-red-600"></i>คำโปรยบทความ</span>
                <textarea name="excerpt" rows="2" placeholder="สรุปสั้น ๆ ที่จะแสดงบนการ์ดบทความ" class="stock-input rounded-2xl px-4 py-3 font-semibold"><?php if ($editBlog): ?><?= h($editBlog['excerpt']) ?><?php endif; ?></textarea>
            </label>

            <div class="article-editor-panel">
                <label class="mb-2 block text-sm font-black text-neutral-700"><i class="fa-solid fa-file-lines mr-2 text-red-600"></i>เนื้อหาบทความ <?= required_mark() ?></label>
                <div class="article-editor-shell overflow-hidden rounded-[1.35rem] border border-neutral-200 bg-white">
                    <div id="article-editor" class="hidden"></div>
                    <textarea
                        id="article-content-fallback"
                        name="content_fallback"
                        rows="10"
                        placeholder="พิมพ์เนื้อหาบทความ"
                        class="min-h-[280px] w-full resize-y border-0 bg-white px-4 py-4 text-base font-semibold leading-8 text-neutral-800 outline-none focus:ring-0"><?php if ($editBlog): ?><?= h(strip_tags((string)$editBlog['content'])) ?><?php endif; ?></textarea>
                </div>
                <p class="mt-2 text-xs font-bold leading-6 text-neutral-500">ถ้า editor แบบ Word โหลดไม่ขึ้น ระบบจะใช้ช่องพิมพ์สำรองนี้เพื่อให้ยังบันทึกบทความได้</p>
            </div>
        </div>

        <div class="article-tags-panel stock-card grid gap-3 rounded-[1.75rem] border-red-100 bg-red-50/45 p-6">
            <div>
                <label class="block text-sm font-black text-neutral-700"><i class="fa-solid fa-tags mr-2 text-red-600"></i>แท็กบทความ</label>
                <p class="mt-1 text-xs font-bold leading-6 text-neutral-500">ประเภทงานดึงจากหมวดหมู่งานในฐานข้อมูล และพื้นที่ดึงจากอำเภอในระบบเท่านั้น ไม่ใช้กลุ่มสถานที่เชียงรายหรือแลนด์มาร์กแยกต่างหาก</p>
            </div>
            <?= $tagSelectorHtml ?>
        </div>

        <div class="stock-card grid gap-4 rounded-[1.75rem] p-6 md:grid-cols-[1fr_auto] md:items-end">
            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-toggle-on mr-2 text-red-600"></i>สถานะบทความ</span>
                <select name="status" class="stock-input rounded-2xl px-4 py-3 font-semibold">
                    <?php foreach (['draft' => 'ฉบับร่าง', 'published' => 'เผยแพร่', 'hidden' => 'ซ่อน'] as $status => $label): ?>
                        <option value="<?= h($status) ?>" <?php if ($editStatus === $status): ?>selected<?php endif; ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="stock-button rounded-full px-5 py-3 font-black"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกบทความ</button>
        </div>
    </form>
    <?php if ($editBlog): ?>
        <form id="article-reset-form" method="post" action="/admin/blogs.php" class="hidden">
            <?= clean_context_inputs([]) ?>
        </form>
    <?php endif; ?>

    <form method="post" action="/admin/blogs.php" class="stock-card mt-6 grid gap-3 rounded-[1.5rem] p-5 md:grid-cols-4">
        <?= clean_context_inputs([]) ?>
        <input name="q" value="<?= h($q) ?>" placeholder="ค้นหาหัวข้อ/ผู้เขียน/สถานะ" class="stock-input rounded-2xl px-4 py-3 font-semibold md:col-span-2">
        <select name="status" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <option value="">ทุกสถานะ</option>
            <?php foreach (['draft', 'published', 'hidden'] as $statusName): ?>
                <option value="<?= h($statusName) ?>" <?php if ($statusFilter === $statusName): ?>selected<?php endif; ?>><?= h(booking_status_label($statusName)) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="stock-button rounded-2xl px-5 py-3 font-black"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
    </form>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.75rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>หัวข้อ</th>
                    <th>ผู้เขียน</th>
                    <th>แหล่งที่มา</th>
                    <th>แท็ก</th>
                    <th>สถานะ</th>
                    <th>วันที่โพสต์</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                    <?php
                    $blogDate = $item['published_at'];
                    if (empty($blogDate)) {
                        $blogDate = $item['created_at'];
                    }
                    ?>
                    <tr>
                        <td class="font-black text-neutral-500"><?= $index + 1 ?></td>
                        <td>
                            <?= clean_context_button('/blog_detail.php', ['slug' => $item['slug']], h($item['title']), 'font-black text-red-600', 'inline', 'target="_blank"') ?>
                        </td>
                        <td><?= h($item['admin_name']) ?></td>
                        <td><span class="rounded-full bg-neutral-950 px-3 py-1 text-xs font-black text-white"><i class="fa-solid fa-user-shield mr-1"></i>จากระบบ</span></td>
                        <?php
                        $blogTagNames = [];
                        if (!empty($item['tags'])) {
                            foreach (array_filter(array_map('trim', explode(',', (string)$item['tags']))) as $rawTagName) {
                                if (isset($allowedBlogTagNames[$rawTagName])) {
                                    $blogTagNames[] = $rawTagName;
                                }
                            }
                        }
                        ?>
                        <td><?= h($blogTagNames ? implode(', ', $blogTagNames) : '-') ?></td>
                        <td><?= status_badge($item['status']) ?></td>
                        <td><?= h(format_be_datetime($blogDate)) ?></td>
                        <td>
                            <div class="flex flex-wrap gap-2">
                                <?= clean_context_button('/admin/blogs.php', ['edit' => (int)$item['id']], '<i class="fa-solid fa-pen"></i>แก้ไข', 'btn-warning btn-sm') ?>
                                <form method="post" class="flex flex-wrap gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                    <?php if ($item['status'] !== 'published'): ?>
                                        <button name="status" value="published" class="btn-success btn-sm"><i class="fa-solid fa-check"></i>เผยแพร่</button>
                                    <?php endif; ?>
                                    <?php if ($item['status'] !== 'hidden'): ?>
                                        <button name="status" value="hidden" class="btn-muted btn-sm"><i class="fa-solid fa-eye-slash"></i>ซ่อน</button>
                                    <?php endif; ?>
                                </form>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                    <button data-confirm="ลบบทความออกจากรายการ?" data-confirm-text="บทความจะหายจากหน้าจัดการและหน้าสาธารณะ แต่ข้อมูลเดิมยังอยู่ในฐานข้อมูล" data-confirm-button="ลบออกจากรายการ" class="btn-danger btn-sm"><i class="fa-solid fa-trash"></i>ลบ</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var hiddenInput = document.getElementById('article-content');
    var editorElement = document.getElementById('article-editor');
    var fallbackElement = document.getElementById('article-content-fallback');
    var form = document.getElementById('article-form');
    if (!hiddenInput || !editorElement || !form) return;

    if (!window.Quill) {
        form.addEventListener('submit', function () {
            hiddenInput.value = '';
        });
        return;
    }

    editorElement.classList.remove('hidden');

    if (fallbackElement) {
        fallbackElement.classList.add('hidden');
    }

    var quill = new Quill(editorElement, {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote'],
                ['clean']
            ]
        }
    });

    quill.root.innerHTML = hiddenInput.value || '';

    form.addEventListener('submit', function () {
        hiddenInput.value = quill.root.innerHTML;
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

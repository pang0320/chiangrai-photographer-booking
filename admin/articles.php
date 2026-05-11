<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');
$cleanContext = clean_context_init(['q', 'status']);

if (is_post()) {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'publish') {
        $stmt = db()->prepare('UPDATE photographer_articles SET status = "published", published_at = IFNULL(published_at, NOW()), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    if ($action === 'hide') {
        $stmt = db()->prepare('UPDATE photographer_articles SET status = "hidden", updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    if ($action === 'delete') {
        $stmt = db()->prepare('UPDATE photographer_articles SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    log_activity('admin_' . $action . '_article', 'photographer_articles', $id);
    flash('success', 'อัปเดตบทความแล้ว');
    redirect('/admin/articles.php');
}

$q = trim((string)clean_context_value($cleanContext, 'q', ''));
$statusFilter = (string)clean_context_value($cleanContext, 'status', '');
$where = ['a.deleted_at IS NULL'];
$params = [];

if ($q !== '') {
    $where[] = '(a.title LIKE ? OR p.display_name LIKE ? OR a.status LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($statusFilter !== '') {
    $where[] = 'a.status = ?';
    $params[] = $statusFilter;
}

$stmt = db()->prepare('SELECT a.*, COALESCE(p.display_name, CONCAT("ช่างภาพ #", a.photographer_id)) AS display_name
                       FROM photographer_articles a
                       LEFT JOIN photographer_profiles p ON p.id = a.photographer_id
                       WHERE ' . implode(' AND ', $where) . '
                       ORDER BY COALESCE(a.published_at, a.created_at) DESC, a.id DESC');
$stmt->execute($params);
$items = $stmt->fetchAll();

$pageTitle = 'จัดการบทความ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">ผู้ดูแลระบบ</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">จัดการบทความ</h1>
        <p class="mt-2 max-w-3xl text-sm font-bold leading-7 text-neutral-500">บทความจากช่างภาพ แสดงลำดับ วันที่โพสต์ ผู้เขียน และแหล่งที่มาให้ชัดเจน</p>
    </div>

    <form method="post" action="/admin/articles.php" class="stock-card mt-6 grid gap-3 rounded-[1.5rem] p-5 md:grid-cols-4">
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

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>หัวข้อ</th>
                    <th>ผู้เขียน</th>
                    <th>แหล่งที่มา</th>
                    <th>สถานะ</th>
                    <th>วันที่โพสต์</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $article): ?>
                    <?php
                    $articleDate = $article['published_at'];
                    if (empty($articleDate)) {
                        $articleDate = $article['created_at'];
                    }
                    ?>
                    <tr>
                        <td class="font-black text-neutral-500"><?= $index + 1 ?></td>
                        <td>
                            <?php if ($article['status'] === 'published'): ?>
                                <?= clean_context_button('/article_detail.php', ['slug' => $article['slug']], h($article['title']), 'font-black text-red-600 hover:text-neutral-950', 'inline', 'target="_blank"') ?>
                            <?php else: ?>
                                <span class="font-black"><?= h($article['title']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($article['display_name']) ?></td>
                        <td><span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700"><i class="fa-solid fa-camera mr-1"></i>จากช่างภาพ</span></td>
                        <td><?= status_badge($article['status']) ?></td>
                        <td><?= h(format_be_datetime($articleDate)) ?></td>
                        <td>
                            <form method="post" class="flex flex-wrap gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                                <button name="action" value="publish" class="btn-success btn-sm"><i class="fa-solid fa-check"></i>เผยแพร่</button>
                                <button name="action" value="hide" class="btn-muted btn-sm"><i class="fa-solid fa-eye-slash"></i>ซ่อน</button>
                                <button data-confirm="ลบบทความนี้?" name="action" value="delete" class="btn-danger btn-sm"><i class="fa-solid fa-trash"></i>ลบ</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

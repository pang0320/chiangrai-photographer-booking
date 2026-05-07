<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

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

$stmt = db()->prepare('SELECT a.*, COALESCE(p.display_name, CONCAT("Photographer #", a.photographer_id)) AS display_name
                       FROM photographer_articles a
                       LEFT JOIN photographer_profiles p ON p.id = a.photographer_id
                       WHERE a.deleted_at IS NULL
                       ORDER BY a.created_at DESC, a.id DESC');
$stmt->execute();
$items = $stmt->fetchAll();

$pageTitle = 'จัดการบทความ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">ผู้ดูแลระบบ</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">จัดการบทความ</h1>
    </div>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>หัวข้อ</th>
                    <th>ช่างภาพ</th>
                    <th>สถานะ</th>
                    <th>วันที่</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $article): ?>
                    <tr>
                        <td>
                            <?php if ($article['status'] === 'published'): ?>
                                <?= clean_context_button('/article_detail.php', ['slug' => $article['slug']], h($article['title']), 'font-black text-red-600 hover:text-neutral-950', 'inline', 'target="_blank"') ?>
                            <?php else: ?>
                                <span class="font-black"><?= h($article['title']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($article['display_name']) ?></td>
                        <td><?= status_badge($article['status']) ?></td>
                        <td><?= h(format_be_datetime($article['created_at'])) ?></td>
                        <td>
                            <form method="post" class="flex flex-wrap gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                                <button name="action" value="publish" class="rounded-full bg-emerald-50 px-3 py-1.5 font-black text-emerald-700"><i class="fa-solid fa-check mr-1"></i>เผยแพร่</button>
                                <button name="action" value="hide" class="rounded-full bg-amber-50 px-3 py-1.5 font-black text-amber-700"><i class="fa-solid fa-eye-slash mr-1"></i>ซ่อน</button>
                                <button data-confirm="ลบบทความนี้?" name="action" value="delete" class="rounded-full bg-red-50 px-3 py-1.5 font-black text-red-700"><i class="fa-solid fa-trash mr-1"></i>ลบ</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

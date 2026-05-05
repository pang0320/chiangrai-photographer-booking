<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

if (is_post()) {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    $stmt = db()->prepare('SELECT photographer_id FROM reviews WHERE id = ?');
    $stmt->execute([$id]);
    $photographerId = (int)$stmt->fetchColumn();

    if ($action === 'hide') {
        $stmt = db()->prepare('UPDATE reviews SET status = "hidden", updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    if ($action === 'show') {
        $stmt = db()->prepare('UPDATE reviews SET status = "visible", updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    if ($action === 'delete') {
        $stmt = db()->prepare('UPDATE reviews SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    if ($photographerId > 0) {
        update_photographer_rating($photographerId);
    }

    log_activity('admin_' . $action . '_review', 'reviews', $id);
    flash('success', 'อัปเดตรีวิวแล้ว');
    redirect('/admin/reviews.php');
}

$rating = (int)($_GET['rating'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$where = ['r.deleted_at IS NULL'];
$params = [];

if ($rating > 0) {
    $where[] = 'r.rating_overall = ?';
    $params[] = $rating;
}

if ($q !== '') {
    $where[] = '(u.name LIKE ? OR p.display_name LIKE ? OR r.comment LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$sql = 'SELECT r.*, u.name AS customer_name, p.display_name
        FROM reviews r
        JOIN users u ON u.id = r.customer_id
        JOIN photographer_profiles p ON p.id = r.photographer_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY r.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$pageTitle = 'จัดการรีวิว';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">ผู้ดูแลระบบ</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">จัดการรีวิว</h1>
    </div>

    <form class="stock-card mt-6 grid gap-3 rounded-[1.5rem] p-5 md:grid-cols-3">
        <input name="q" value="<?= h($q) ?>" placeholder="ค้นหา" class="stock-input rounded-2xl px-4 py-3 font-semibold">

        <select name="rating" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <option value="0">ทุกคะแนน</option>
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <option value="<?= $i ?>" <?php if ($rating === $i): ?>selected<?php endif; ?>>
                    <?= $i ?>
                </option>
            <?php endfor; ?>
        </select>

        <button class="stock-button rounded-2xl px-5 py-3 font-black"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
    </form>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>ลูกค้า</th>
                    <th>ช่างภาพ</th>
                    <th>คะแนน</th>
                    <th>คอมเมนต์</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $review): ?>
                    <tr>
                        <td><?= h($review['customer_name']) ?></td>
                        <td><?= h($review['display_name']) ?></td>
                        <td><?= (int)$review['rating_overall'] ?></td>
                        <td><?= h($review['comment']) ?></td>
                        <td><?= status_badge($review['status']) ?></td>
                        <td>
                            <form method="post" class="flex flex-wrap gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$review['id'] ?>">
                                <button name="action" value="show" class="rounded-full bg-emerald-50 px-3 py-1.5 font-black text-emerald-700"><i class="fa-solid fa-eye mr-1"></i>แสดง</button>
                                <button name="action" value="hide" class="rounded-full bg-amber-50 px-3 py-1.5 font-black text-amber-700"><i class="fa-solid fa-eye-slash mr-1"></i>ซ่อน</button>
                                <button data-confirm="ลบรีวิวนี้?" name="action" value="delete" class="rounded-full bg-red-50 px-3 py-1.5 font-black text-red-700"><i class="fa-solid fa-trash mr-1"></i>ลบ</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

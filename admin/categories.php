<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

if (is_post()) {
    verify_csrf();

    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('UPDATE service_categories SET is_active = 0 WHERE id = ?');
        $stmt->execute([$id]);
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $icon = trim((string)($_POST['icon'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $isActive = 0;

        if (isset($_POST['is_active'])) {
            $isActive = 1;
        }

        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $slug = unique_slug('service_categories', $name, $id ?: null);

        if ($id > 0) {
            $stmt = db()->prepare('UPDATE service_categories
                                   SET name = ?, slug = ?, icon = ?, description = ?, is_active = ?, sort_order = ?, updated_at = NOW()
                                   WHERE id = ?');
            $stmt->execute([$name, $slug, $icon, $description, $isActive, $sortOrder, $id]);
        } else {
            $stmt = db()->prepare('INSERT INTO service_categories (name, slug, icon, description, is_active, sort_order, created_at, updated_at)
                                   VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$name, $slug, $icon, $description, $isActive, $sortOrder]);
        }
    }

    log_activity('manage_categories', 'service_categories', (int)($_POST['id'] ?? 0));
    flash('success', 'บันทึกหมวดหมู่แล้ว');
    redirect('/admin/categories.php');
}

$items = db_fetch_all('SELECT * FROM service_categories ORDER BY sort_order, name');

$pageTitle = 'ประเภทงาน';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Admin</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">ประเภทงาน</h1>
    </div>

    <form method="post" class="stock-card mt-6 grid gap-3 rounded-[1.5rem] p-5 md:grid-cols-6">
        <?= csrf_field() ?>
        <input name="name" required placeholder="ชื่อ" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input name="icon" placeholder="fa-camera" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input name="sort_order" type="number" value="0" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input name="description" placeholder="รายละเอียด" class="stock-input rounded-2xl px-4 py-3 font-semibold md:col-span-2">
        <label class="rounded-2xl bg-neutral-50 px-4 py-3 font-bold">
            <input type="checkbox" name="is_active" checked>
            active
        </label>
        <button class="stock-button rounded-2xl px-5 py-3 font-black md:col-span-6"><i class="fa-solid fa-plus mr-2"></i>เพิ่ม</button>
    </form>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ชื่อ</th>
                    <th>Slug</th>
                    <th>Icon</th>
                    <th>Active</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= (int)$item['id'] ?></td>
                        <td class="font-black"><?= h($item['name']) ?></td>
                        <td><?= h($item['slug']) ?></td>
                        <td><?= h($item['icon']) ?></td>
                        <td><?= (int)$item['is_active'] ?></td>
                        <td>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <button data-confirm="ปิดใช้งานหมวดหมู่นี้?" class="rounded-full bg-red-50 px-3 py-1.5 font-black text-red-700">
                                    <i class="fa-solid fa-trash mr-1"></i>delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

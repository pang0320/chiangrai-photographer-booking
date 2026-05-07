<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

if (is_post()) {
    verify_csrf();

    try {
        $action = (string)($_POST['action'] ?? 'save');

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = db()->prepare('DELETE FROM banners WHERE id = ?');
            $stmt->execute([$id]);
        } else {
            $image = upload_image($_FILES['image'] ?? [], 'banners');
            $title = trim((string)($_POST['title'] ?? ''));
            $subtitle = trim((string)($_POST['subtitle'] ?? ''));
            $buttonText = trim((string)($_POST['button_text'] ?? ''));
            $buttonUrl = trim((string)($_POST['button_url'] ?? ''));
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $isActive = 0;

            if (isset($_POST['is_active'])) {
                $isActive = 1;
            }

            $stmt = db()->prepare('INSERT INTO banners (title, subtitle, image_path, button_text, button_url, is_active, sort_order, created_at, updated_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$title, $subtitle, $image, $buttonText, $buttonUrl, $isActive, $sortOrder]);
        }

        log_activity('manage_banners', 'banners', (int)($_POST['id'] ?? 0));
        flash('success', 'บันทึก banner แล้ว');
        redirect('/admin/banners.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
}

$items = db_fetch_all('SELECT * FROM banners ORDER BY sort_order, id DESC');

$pageTitle = 'Banner';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">ผู้ดูแลระบบ</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">จัดการ Banner</h1>
    </div>

    <form method="post" enctype="multipart/form-data" class="stock-card mt-6 grid gap-3 rounded-[1.5rem] p-5 md:grid-cols-3">
        <?= csrf_field() ?>
        <input name="title" required placeholder="Title" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input name="subtitle" placeholder="Subtitle" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <label class="grid gap-2 text-sm font-black text-neutral-700">
            <span><i class="fa-solid fa-image mr-2 text-red-600"></i>รูป Banner</span>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <span class="text-xs font-bold leading-6 text-neutral-500"><?= h(UPLOAD_IMAGE_HELP_TEXT) ?></span>
        </label>
        <input name="button_text" placeholder="Button text" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input name="button_url" placeholder="Button URL" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input type="number" name="sort_order" value="0" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <label class="rounded-2xl bg-neutral-50 px-4 py-3 font-bold">
            <input type="checkbox" name="is_active" checked>
            เปิดใช้งาน
        </label>
        <button class="stock-button rounded-2xl px-5 py-3 font-black md:col-span-2"><i class="fa-solid fa-plus mr-2"></i>เพิ่ม Banner</button>
    </form>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Active</th>
                    <th>Sort</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $banner): ?>
                    <tr>
                        <td class="font-black"><?= h($banner['title']) ?></td>
                        <td><?= (int)$banner['is_active'] ?></td>
                        <td><?= (int)$banner['sort_order'] ?></td>
                        <td>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$banner['id'] ?>">
                                <button data-confirm="ลบ banner นี้?" class="btn-danger btn-sm">
                                    <i class="fa-solid fa-trash mr-1"></i>ลบ
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

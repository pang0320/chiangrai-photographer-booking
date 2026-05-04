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
            $stmt = db()->prepare('UPDATE photographer_portfolios SET deleted_at = NOW() WHERE id = ? AND photographer_id = ?');
            $stmt->execute([$id, $pid]);
            flash('success', 'ลบผลงานแล้ว');
        } else {
            $path = upload_image($_FILES['image'] ?? [], 'portfolios');

            if (!$path) {
                throw new RuntimeException('กรุณาเลือกรูปภาพ');
            }

            $isFeatured = 0;
            if (isset($_POST['is_featured'])) {
                $isFeatured = 1;
            }

            $stmt = db()->prepare('INSERT INTO photographer_portfolios (photographer_id, title, description, image_path, is_featured, sort_order, created_at, updated_at)
                                   VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([
                $pid,
                trim((string)($_POST['title'] ?? '')),
                trim((string)($_POST['description'] ?? '')),
                $path,
                $isFeatured,
                (int)($_POST['sort_order'] ?? 0),
            ]);
            flash('success', 'เพิ่มผลงานแล้ว');
        }

        log_activity('manage_portfolio', 'photographer_portfolios', $pid);
        redirect('/photographer/portfolio.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
}

$stmt = db()->prepare('SELECT * FROM photographer_portfolios WHERE photographer_id = ? AND deleted_at IS NULL ORDER BY is_featured DESC, sort_order ASC, id DESC');
$stmt->execute([$pid]);
$items = $stmt->fetchAll();

$pageTitle = 'Portfolio';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Photographer Studio</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">Portfolio</h1>
    </div>

    <form method="post" enctype="multipart/form-data" class="stock-card mt-6 grid gap-4 rounded-[1.5rem] p-5 md:grid-cols-2">
        <?= csrf_field() ?>
        <input name="title" required placeholder="ชื่อผลงาน" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input type="number" name="sort_order" value="0" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <textarea name="description" placeholder="คำอธิบาย" class="stock-input rounded-2xl px-4 py-3 font-semibold md:col-span-2"></textarea>
        <input type="file" name="image" required accept="image/jpeg,image/png,image/webp" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <label class="rounded-2xl bg-neutral-50 px-4 py-3 font-bold">
            <input type="checkbox" name="is_featured">
            Featured
        </label>
        <button class="stock-button rounded-2xl px-5 py-3 font-black md:col-span-2">เพิ่มรูป</button>
    </form>

    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($items as $item): ?>
            <article class="stock-card overflow-hidden rounded-[1.5rem]">
                <img class="h-56 w-full object-cover" src="<?= h(public_image($item['image_path'], 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=900&q=80')) ?>" alt="">
                <div class="p-4">
                    <b><?= h($item['title']) ?></b>
                    <p class="text-sm text-neutral-600"><?= h($item['description']) ?></p>
                    <form method="post" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                        <button data-confirm="ลบผลงานนี้?" class="rounded-full bg-red-50 px-3 py-2 text-sm font-black text-red-700">
                            ลบ
                        </button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

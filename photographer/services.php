<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');

$profile = photographer_profile_by_user((int)current_user()['id']);
$pid = (int)$profile['id'];
$categories = db_fetch_all('SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order');

if (is_post()) {
    verify_csrf();

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM photographer_services WHERE id = ? AND photographer_id = ?');
        $stmt->execute([$id, $pid]);
        flash('success', 'ลบบริการแล้ว');
    } else {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $description = trim((string)($_POST['description'] ?? ''));
        $startingPrice = (float)($_POST['starting_price'] ?? 0);
        $isActive = 0;

        if (isset($_POST['is_active'])) {
            $isActive = 1;
        }

        $stmt = db()->prepare('INSERT INTO photographer_services (photographer_id, category_id, description, starting_price, is_active, created_at, updated_at)
                               VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                               ON DUPLICATE KEY UPDATE description = VALUES(description), starting_price = VALUES(starting_price), is_active = VALUES(is_active), updated_at = NOW()');
        $stmt->execute([$pid, $categoryId, $description, $startingPrice, $isActive]);
        flash('success', 'บันทึกบริการแล้ว');
    }

    log_activity('manage_services', 'photographer_services', $pid);
    redirect('/photographer/services.php');
}

$stmt = db()->prepare('SELECT ps.*, sc.name
                       FROM photographer_services ps
                       JOIN service_categories sc ON sc.id = ps.category_id
                       WHERE ps.photographer_id = ?
                       ORDER BY sc.sort_order');
$stmt->execute([$pid]);
$services = $stmt->fetchAll();

$pageTitle = 'บริการ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">สตูดิโอช่างภาพ</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">ประเภทงานที่รับ</h1>
    </div>

    <form method="post" class="stock-card mt-6 grid gap-4 rounded-[1.5rem] p-5 md:grid-cols-5">
        <?= csrf_field() ?>

        <select name="category_id" required class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>"><?= h($category['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <input name="description" placeholder="รายละเอียด" class="stock-input rounded-2xl px-4 py-3 font-semibold md:col-span-2">
        <input type="number" step="0.01" name="starting_price" placeholder="ราคาเริ่มต้น" class="stock-input rounded-2xl px-4 py-3 font-semibold">

        <label class="rounded-2xl bg-neutral-50 px-4 py-3 font-bold">
            <input type="checkbox" name="is_active" checked>
            เปิด
        </label>

        <button class="stock-button rounded-2xl px-5 py-3 font-black md:col-span-5"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกบริการ</button>
    </form>

    <div class="mt-6 grid gap-3">
        <?php foreach ($services as $service): ?>
            <div class="stock-card flex flex-wrap items-center justify-between gap-3 rounded-[1.35rem] p-4">
                <div>
                    <b><?= h($service['name']) ?></b>
                    <p class="text-sm text-neutral-600">
                        <?= h($service['description']) ?> · <?= number_format((float)$service['starting_price']) ?> บาท
                    </p>
                </div>

                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$service['id'] ?>">
                    <button data-confirm="ลบบริการนี้?" class="rounded-full bg-red-50 px-3 py-2 text-sm font-black text-red-700">
                        <i class="fa-solid fa-trash mr-1"></i>ลบ
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

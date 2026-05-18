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
            $stmt = db()->prepare('SELECT id, image_path FROM photographer_portfolios WHERE id = ? AND photographer_id = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([$id, $pid]);
            $portfolioItem = $stmt->fetch();

            if (!$portfolioItem) {
                throw new RuntimeException('ไม่พบตัวอย่างงานถ่ายภาพที่ต้องการซ่อน');
            }

            db()->beginTransaction();

            $stmt = db()->prepare('UPDATE photographer_portfolios SET deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND photographer_id = ?');
            $stmt->execute([$id, $pid]);

            $imagePath = trim((string)$portfolioItem['image_path']);

            db()->commit();
            log_activity('hide_portfolio', 'photographer_portfolios', $id, json_encode([
                'image_path' => $imagePath,
                'file_deleted' => false,
                'note' => 'soft_deleted_only_keep_file',
            ], JSON_UNESCAPED_UNICODE));
            flash('success', 'ซ่อนตัวอย่างงานถ่ายภาพแล้ว ข้อมูลและไฟล์รูปเดิมยังอยู่');
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
            flash('success', 'เพิ่มตัวอย่างงานถ่ายภาพแล้ว');
        }

        log_activity('manage_portfolio', 'photographer_portfolios', $pid);
        redirect('/photographer/portfolio.php');
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('error', $e->getMessage());
    }
}

$stmt = db()->prepare('SELECT * FROM photographer_portfolios WHERE photographer_id = ? AND deleted_at IS NULL ORDER BY is_featured DESC, sort_order ASC, id DESC');
$stmt->execute([$pid]);
$items = $stmt->fetchAll();

$pageTitle = 'ตัวอย่างงานถ่ายภาพ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">สตูดิโอช่างภาพ</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">ตัวอย่างงานถ่ายภาพ</h1>
    </div>

    <form method="post" enctype="multipart/form-data" class="stock-card mt-6 grid gap-4 rounded-[1.5rem] p-5 md:grid-cols-2">
        <?= csrf_field() ?>
        <label class="grid gap-2">
            <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-heading mr-2 text-red-600"></i>ชื่ออัลบั้มตัวอย่างงาน</span>
            <input name="title" required placeholder="เช่น งานแต่งริมโขง / รับปริญญาแม่ฟ้าหลวง" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        </label>
        <label class="grid gap-2">
            <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-arrow-down-1-9 mr-2 text-red-600"></i>ลำดับการแสดงผล (sort_order)</span>
            <input type="number" name="sort_order" value="0" min="0" step="1" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <span class="text-xs font-bold leading-6 text-neutral-500">ค่านี้เป็นตัวเลข config สำหรับจัดเรียงตัวอย่างงานถ่ายภาพ: เลขน้อยจะแสดงก่อน เช่น 0, 1, 2, 3</span>
        </label>
        <label class="grid gap-2 md:col-span-2">
            <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-align-left mr-2 text-red-600"></i>คำอธิบายตัวอย่างงาน</span>
            <textarea name="description" placeholder="รายละเอียดสั้น ๆ ของงานนี้" class="stock-input rounded-2xl px-4 py-3 font-semibold"></textarea>
        </label>
        <label class="grid gap-2">
            <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-image mr-2 text-red-600"></i>ไฟล์รูปตัวอย่างงาน</span>
            <input type="file" name="image" required accept="image/jpeg,image/png,image/webp" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <span class="text-xs font-bold leading-6 text-neutral-500"><?= h(UPLOAD_IMAGE_HELP_TEXT) ?></span>
        </label>
        <label class="grid gap-2 rounded-2xl bg-neutral-50 px-4 py-3 font-bold">
            <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-star mr-2 text-red-600"></i>ตั้งค่า featured image</span>
            <span class="flex items-center gap-3">
                <input type="checkbox" name="is_featured" class="h-5 w-5 rounded border-neutral-300 text-red-600">
                <span>ตั้งเป็นรูปเด่น</span>
            </span>
            <span class="text-xs font-bold leading-6 text-neutral-500">รูปเด่นจะแสดงก่อนรูปทั่วไป และใช้ประกอบหน้าโปรไฟล์/การ์ดช่างภาพ</span>
        </label>
        <button class="stock-button rounded-2xl px-5 py-3 font-black md:col-span-2"><i class="fa-solid fa-plus mr-2"></i>เพิ่มรูป</button>
    </form>

    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($items as $item): ?>
            <article class="stock-card overflow-hidden rounded-[1.5rem]">
                <img class="h-56 w-full object-cover" src="<?= h(public_image($item['image_path'], '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg')) ?>" alt="">
                <div class="p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <b><?= h($item['title']) ?></b>
                        <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-black text-neutral-600">
                            <i class="fa-solid fa-arrow-down-1-9 mr-1 text-red-600"></i>ลำดับ <?= (int)$item['sort_order'] ?>
                        </span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <?php if ((int)$item['is_featured'] === 1): ?>
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-700"><i class="fa-solid fa-star mr-1"></i>รูปเด่น</span>
                        <?php else: ?>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600"><i class="fa-regular fa-image mr-1"></i>รูปทั่วไป</span>
                        <?php endif; ?>
                        <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700"><i class="fa-solid fa-gear mr-1"></i>ค่า config: sort_order</span>
                    </div>
                    <p class="text-sm text-neutral-600"><?= h($item['description']) ?></p>
                    <form method="post" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                        <button data-confirm="ซ่อนตัวอย่างงานนี้?" data-confirm-text="ตัวอย่างงานจะหายจากหน้าโปรไฟล์ แต่ข้อมูลเดิมยังอยู่ในฐานข้อมูล" data-confirm-button="ซ่อนตัวอย่างงาน" class="btn-warning btn-sm">
                            <i class="fa-solid fa-eye-slash mr-1"></i>ซ่อน
                        </button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

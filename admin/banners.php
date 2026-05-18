<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

if (is_post()) {
    verify_csrf();

    try {
        $action = (string)($_POST['action'] ?? 'save');

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = db()->prepare('UPDATE banners SET is_active = 0, updated_at = NOW() WHERE id = ?');
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

$pageTitle = 'แบนเนอร์';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">ผู้ดูแลระบบ</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">จัดการแบนเนอร์</h1>
        <p class="mt-2 max-w-3xl text-sm font-bold leading-7 text-neutral-500">แบนเนอร์ใช้จัดภาพโปรโมตบนหน้าแรก เช่น ภาพ hero หรือ section โปรโมตพร้อมปุ่ม CTA ผู้ใช้จะเห็นตามลำดับแสดงผลที่ตั้งไว้</p>
    </div>

    <form method="post" enctype="multipart/form-data" class="stock-card mt-6 grid gap-3 rounded-[1.5rem] p-5 md:grid-cols-3">
        <?= csrf_field() ?>
        <input name="title" required placeholder="หัวข้อแบนเนอร์" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input name="subtitle" placeholder="คำอธิบายสั้น" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <label class="grid gap-2 text-sm font-black text-neutral-700">
            <span><i class="fa-solid fa-image mr-2 text-red-600"></i>รูปแบนเนอร์</span>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <span class="text-xs font-bold leading-6 text-neutral-500"><?= h(UPLOAD_IMAGE_HELP_TEXT) ?></span>
        </label>
        <input name="button_text" placeholder="ข้อความปุ่ม เช่น ค้นหาช่างภาพ" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input name="button_url" placeholder="ลิงก์ปุ่ม เช่น /photographers.php" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input type="number" name="sort_order" value="0" class="stock-input rounded-2xl px-4 py-3 font-semibold" placeholder="ลำดับแสดงผล">
        <label class="rounded-2xl bg-neutral-50 px-4 py-3 font-bold">
            <input type="checkbox" name="is_active" checked>
            เปิดใช้งาน
        </label>
        <button class="stock-button rounded-2xl px-5 py-3 font-black md:col-span-2"><i class="fa-solid fa-plus mr-2"></i>เพิ่มแบนเนอร์</button>
    </form>

    <div class="mt-6 grid gap-4 lg:grid-cols-[1fr_380px]">
        <div class="stock-card rounded-[1.5rem] p-5">
            <h2 class="text-xl font-black text-neutral-950"><i class="fa-solid fa-display mr-2 text-red-600"></i>ตัวอย่างตำแหน่งแสดงผล</h2>
            <p class="mt-2 text-sm font-bold leading-7 text-neutral-500">แบนเนอร์ที่เปิดใช้งานจะถูกใช้เป็นภาพโปรโมตในหน้าแรก บริเวณ hero/section โปรโมต แล้วเรียงตามลำดับแสดงผล</p>
            <div class="mt-4 rounded-[1.5rem] bg-gradient-to-r from-neutral-950 to-red-700 p-5 text-white">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-white/50">ตัวอย่างพื้นที่แบนเนอร์หน้าแรก</p>
                <h3 class="mt-2 text-2xl font-black">ภาพโปรโมตหน้าแรก</h3>
                <p class="mt-2 text-sm font-bold text-white/70">หัวข้อ คำอธิบาย และปุ่ม CTA จะแสดงในพื้นที่นี้</p>
            </div>
        </div>
    </div>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>หัวข้อ</th>
                    <th>สถานะ</th>
                    <th>ลำดับแสดงผล</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $banner): ?>
                    <tr>
                        <td class="font-black text-neutral-500"><?= $index + 1 ?></td>
                        <td class="font-black"><?= h($banner['title']) ?></td>
                        <td>
                            <?php if ((int)$banner['is_active'] === 1): ?>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i>เปิดใช้งาน</span>
                            <?php else: ?>
                                <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-black text-neutral-600"><i class="fa-solid fa-eye-slash mr-1"></i>ปิดใช้งาน</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$banner['sort_order'] ?></td>
                        <td>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$banner['id'] ?>">
                                <button data-confirm="ซ่อนแบนเนอร์นี้?" data-confirm-text="แบนเนอร์จะไม่แสดงบนหน้าเว็บ แต่ข้อมูลเดิมยังอยู่ในระบบ" data-confirm-button="ซ่อนแบนเนอร์" class="btn-warning btn-sm">
                                    <i class="fa-solid fa-eye-slash mr-1"></i>ซ่อน
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

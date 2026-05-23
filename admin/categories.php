<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');
ensure_service_categories_deleted_at_column();

if (is_post()) {
    verify_csrf();

    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('UPDATE service_categories SET is_active = 0, deleted_at = NOW(), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'ลบหมวดหมู่งานออกจากรายการแล้ว ข้อมูลเดิมยังอยู่ในฐานข้อมูล');
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $currentStatus = db_fetch_value('SELECT is_active FROM service_categories WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$id]);

        if ($currentStatus === false) {
            flash('error', 'ไม่พบหมวดหมู่งานที่ต้องการเปลี่ยนสถานะ');
            redirect('/admin/categories.php');
        }

        $nextStatus = 1;
        if ((int)$currentStatus === 1) {
            $nextStatus = 0;
        }

        $stmt = db()->prepare('UPDATE service_categories SET is_active = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$nextStatus, $id]);

        if ($nextStatus === 1) {
            flash('success', 'เปิดใช้งานหมวดหมู่งานแล้ว');
        } else {
            flash('success', 'ปิดใช้งานหมวดหมู่งานแล้ว');
        }
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

        if ($name === '') {
            flash('error', 'กรุณากรอกชื่อหมวดหมู่งาน');
            redirect('/admin/categories.php');
        }

        if ($icon === '') {
            $icon = 'fa-camera';
        }

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

        flash('success', 'บันทึกหมวดหมู่งานแล้ว');
    }

    cache_clear_all();
    log_activity('manage_categories', 'service_categories', (int)($_POST['id'] ?? 0));
    redirect('/admin/categories.php');
}

$items = db_fetch_all('SELECT * FROM service_categories WHERE deleted_at IS NULL ORDER BY sort_order, name');
$activeCount = 0;
$inactiveCount = 0;

foreach ($items as $item) {
    if ((int)$item['is_active'] === 1) {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
}

$pageTitle = 'ประเภทงาน';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="dashboard-hero overflow-hidden rounded-[2rem] p-6 text-white md:p-8">
        <div class="grid gap-6 lg:grid-cols-[1fr_420px] lg:items-end">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/60">
                    <i class="fa-solid fa-layer-group mr-2"></i>ผู้ดูแลระบบ
                </p>
                <h1 class="mt-2 text-3xl font-black md:text-4xl">จัดการหมวดหมู่งานถ่ายภาพ</h1>
                <p class="mt-3 max-w-3xl text-base font-semibold leading-8 text-white/75 md:text-lg">
                    หมวดหมู่นี้จะถูกใช้ในหน้าค้นหาช่างภาพ ฟอร์มคำขอจอง และหน้าช่างภาพเลือกประเภทงานที่รับ
                </p>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-layer-group text-2xl text-red-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format(count($items)) ?></div>
                    <div class="text-xs font-black text-white/55">ทั้งหมด</div>
                </div>
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-circle-check text-2xl text-emerald-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format($activeCount) ?></div>
                    <div class="text-xs font-black text-white/55">เปิดใช้งาน</div>
                </div>
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-eye-slash text-2xl text-amber-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format($inactiveCount) ?></div>
                    <div class="text-xs font-black text-white/55">ปิดไว้</div>
                </div>
            </div>
        </div>
    </div>

    <form method="post" class="stock-card mt-6 rounded-[1.75rem] p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">

        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="section-kicker">
                    <i class="fa-solid fa-plus mr-2"></i>เพิ่มหมวดหมู่งาน
                </p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">สร้างประเภทงานให้ช่างภาพเลือก</h2>
                <p class="mt-2 text-base font-semibold leading-7 text-neutral-600">
                    ตั้งชื่อให้ลูกค้าเข้าใจง่าย เช่น งานแต่งงาน รับปริญญา ครอบครัว หรือสินค้า
                </p>
            </div>
            <button class="stock-button rounded-2xl px-5 py-3 font-black">
                <i class="fa-solid fa-plus mr-2"></i>เพิ่มหมวดหมู่
            </button>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2">
                <label class="block text-sm font-black text-neutral-700" for="name">
                    <i class="fa-solid fa-tag mr-2 text-red-600"></i>ชื่อหมวดหมู่งาน
                </label>
                <input id="name" name="name" required placeholder="เช่น งานแต่งงาน" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
            </div>

            <div>
                <label class="block text-sm font-black text-neutral-700" for="icon">
                    <i class="fa-solid fa-icons mr-2 text-red-600"></i>Font Awesome icon
                </label>
                <input id="icon" name="icon" placeholder="fa-camera" value="fa-camera" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
            </div>

            <div>
                <label class="block text-sm font-black text-neutral-700" for="sort_order">
                    <i class="fa-solid fa-arrow-down-1-9 mr-2 text-red-600"></i>ลำดับแสดงผล
                </label>
                <input id="sort_order" name="sort_order" type="number" value="0" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
            </div>

            <div class="xl:col-span-2">
                <label class="block text-sm font-black text-neutral-700" for="description">
                    <i class="fa-solid fa-align-left mr-2 text-red-600"></i>คำอธิบาย
                </label>
                <input id="description" name="description" placeholder="ใช้ในหน้าเว็บเพื่ออธิบายหมวดหมู่" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
            </div>
        </div>

        <label class="mt-4 flex items-center justify-between rounded-2xl border border-neutral-100 bg-neutral-50 px-4 py-3 font-black text-neutral-800">
            <span><i class="fa-solid fa-toggle-on mr-2 text-emerald-600"></i>เปิดให้แสดงในหน้าค้นหาและฟอร์มจอง</span>
            <input type="checkbox" name="is_active" checked class="h-5 w-5 accent-red-600">
        </label>
    </form>

    <div class="stock-card mt-6 rounded-[1.75rem] p-5">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="section-kicker">
                    <i class="fa-solid fa-pen-to-square mr-2"></i>แก้ไขหมวดหมู่งาน
                </p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">รายการหมวดหมู่ทั้งหมด</h2>
                <p class="mt-2 text-sm font-bold leading-7 text-neutral-500">
                    <i class="fa-solid fa-circle-info mr-1 text-red-600"></i>
                    ปุ่มเหลืองคือปิด/เปิดใช้งานชั่วคราว ส่วนปุ่มลบคือ soft delete ให้หายจากรายการและตัวเลือกค้นหา แต่ข้อมูลเดิมยังอยู่ในฐานข้อมูล
                </p>
            </div>
            <div class="rounded-full bg-red-50 px-4 py-2 text-sm font-black text-red-700">
                <i class="fa-solid fa-circle-info mr-1"></i>ตารางแสดงทีละ 5 รายการ
            </div>
        </div>

        <?php if (!$items): ?>
            <div class="empty-state rounded-[1.5rem] p-8 text-center">
                <i class="fa-solid fa-layer-group text-4xl text-red-600"></i>
                <h3 class="mt-3 text-xl font-black text-neutral-950">ยังไม่มีหมวดหมู่งาน</h3>
                <p class="mt-2 text-base font-semibold text-neutral-600">เพิ่มหมวดหมู่แรกจากฟอร์มด้านบน</p>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <form id="category_form_<?= (int)$item['id'] ?>" method="post" class="hidden">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                </form>
                <form id="category_toggle_<?= (int)$item['id'] ?>" method="post" class="hidden">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                    <input type="hidden" name="is_active" value="<?= (int)$item['is_active'] ?>">
                </form>
            <?php endforeach; ?>

            <div class="overflow-x-auto">
                <table class="datatable w-full text-base">
                    <thead>
                        <tr>
                            <th>หมวดหมู่</th>
                            <th>คำอธิบาย</th>
                            <th>ลำดับ</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $isActive = (int)$item['is_active'] === 1;
                            $statusClass = 'bg-neutral-100 text-neutral-600';
                            $statusIcon = 'fa-eye-slash';
                            $statusText = 'ปิดใช้งาน';

                            if ($isActive) {
                                $statusClass = 'bg-emerald-50 text-emerald-700';
                                $statusIcon = 'fa-circle-check';
                                $statusText = 'เปิดใช้งาน';
                            }
                            ?>
                            <tr>
                                <td class="min-w-[280px]">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-1 grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-red-50 text-red-600">
                                            <i class="fa-solid <?= h($item['icon'] ?: 'fa-camera') ?>"></i>
                                        </span>
                                        <div class="grid gap-2">
                                            <input form="category_form_<?= (int)$item['id'] ?>" name="name" required value="<?= h($item['name']) ?>" class="stock-input w-full rounded-2xl px-4 py-2 font-black">
                                            <input form="category_form_<?= (int)$item['id'] ?>" name="icon" value="<?= h($item['icon']) ?>" class="stock-input w-full rounded-2xl px-4 py-2 font-semibold" placeholder="fa-camera">
                                            <p class="text-sm font-bold text-neutral-500">Slug: <?= h($item['slug']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="min-w-[300px]">
                                    <textarea form="category_form_<?= (int)$item['id'] ?>" name="description" rows="3" class="stock-input w-full rounded-2xl px-4 py-2 font-semibold" placeholder="คำอธิบายหมวดหมู่"><?= h($item['description']) ?></textarea>
                                </td>
                                <td class="min-w-[120px]">
                                    <input form="category_form_<?= (int)$item['id'] ?>" name="sort_order" type="number" value="<?= (int)$item['sort_order'] ?>" class="stock-input w-full rounded-2xl px-4 py-2 font-black">
                                </td>
                                <td class="min-w-[170px]">
                                    <label class="flex items-center gap-2 rounded-2xl bg-neutral-50 px-3 py-2 font-black">
                                        <input form="category_form_<?= (int)$item['id'] ?>" type="checkbox" name="is_active" class="h-5 w-5 accent-red-600" <?= $isActive ? 'checked' : '' ?>>
                                        <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-black <?= h($statusClass) ?>">
                                            <i class="fa-solid <?= h($statusIcon) ?>"></i><?= h($statusText) ?>
                                        </span>
                                    </label>
                                </td>
                                <td class="min-w-[220px]">
                                    <div class="flex flex-wrap gap-2">
                                        <button form="category_form_<?= (int)$item['id'] ?>" class="btn-success btn-sm" type="submit">
                                            <i class="fa-solid fa-floppy-disk"></i>บันทึก
                                        </button>
                                        <button form="category_toggle_<?= (int)$item['id'] ?>" class="<?= $isActive ? 'btn-warning' : 'btn-success' ?> btn-sm" type="submit">
                                            <?php if ($isActive): ?>
                                                <i class="fa-solid fa-eye-slash"></i>ปิด
                                            <?php else: ?>
                                                <i class="fa-solid fa-eye"></i>เปิด
                                            <?php endif; ?>
                                        </button>
                                        <form method="post" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                            <button data-confirm="ยืนยันลบหมวดหมู่นี้?" data-confirm-text="หมวดนี้จะหายจากหน้าจัดการและตัวเลือกค้นหา แต่ข้อมูลเดิมยังอยู่ในฐานข้อมูลเพื่อไม่กระทบข้อมูลเก่า" data-confirm-button="ลบหมวดหมู่" class="btn-danger btn-sm" type="submit">
                                                <i class="fa-solid fa-trash"></i>ลบ
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

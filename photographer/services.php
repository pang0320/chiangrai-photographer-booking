<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');
ensure_service_categories_deleted_at_column();

$profile = photographer_profile_by_user((int)current_user()['id']);
$pid = (int)$profile['id'];
$categories = db_fetch_all('SELECT * FROM service_categories WHERE is_active = 1 AND deleted_at IS NULL ORDER BY sort_order');

if (is_post()) {
    verify_csrf();

    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('UPDATE photographer_services SET is_active = 0, updated_at = NOW() WHERE id = ? AND photographer_id = ?');
        $stmt->execute([$id, $pid]);
        flash('success', 'ซ่อนประเภทงานนี้จากโปรไฟล์แล้ว ข้อมูลเดิมยังอยู่');
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 0);
        $nextStatus = 0;

        if ($isActive === 0) {
            $nextStatus = 1;
        }

        $stmt = db()->prepare('UPDATE photographer_services SET is_active = ?, updated_at = NOW() WHERE id = ? AND photographer_id = ?');
        $stmt->execute([$nextStatus, $id, $pid]);
        flash('success', 'อัปเดตสถานะประเภทงานแล้ว');
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $description = trim((string)($_POST['description'] ?? ''));
        $startingPrice = (float)($_POST['starting_price'] ?? 0);
        $isActive = 0;

        if (isset($_POST['is_active'])) {
            $isActive = 1;
        }

        if ($startingPrice < 0) {
            $startingPrice = 0;
        }

        $stmt = db()->prepare('UPDATE photographer_services
                               SET description = ?, starting_price = ?, is_active = ?, updated_at = NOW()
                               WHERE id = ? AND photographer_id = ?');
        $stmt->execute([$description, $startingPrice, $isActive, $id, $pid]);
        flash('success', 'แก้ไขรายละเอียดประเภทงานแล้ว');
    } else {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $description = trim((string)($_POST['description'] ?? ''));
        $startingPrice = (float)($_POST['starting_price'] ?? 0);
        $isActive = 0;

        if (isset($_POST['is_active'])) {
            $isActive = 1;
        }

        if ($startingPrice < 0) {
            $startingPrice = 0;
        }

        if ($categoryId <= 0) {
            flash('error', 'กรุณาเลือกประเภทงาน');
            redirect('/photographer/services.php');
        }

        $stmt = db()->prepare('INSERT INTO photographer_services (photographer_id, category_id, description, starting_price, is_active, created_at, updated_at)
                               VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                               ON DUPLICATE KEY UPDATE description = VALUES(description), starting_price = VALUES(starting_price), is_active = VALUES(is_active), updated_at = NOW()');
        $stmt->execute([$pid, $categoryId, $description, $startingPrice, $isActive]);
        flash('success', 'บันทึกประเภทงานที่รับแล้ว');
    }

    log_activity('manage_services', 'photographer_services', $pid);
    redirect('/photographer/services.php');
}

$stmt = db()->prepare('SELECT ps.*, sc.name
                       FROM photographer_services ps
                       JOIN service_categories sc ON sc.id = ps.category_id
                       WHERE ps.photographer_id = ?
                         AND sc.deleted_at IS NULL
                       ORDER BY sc.sort_order');
$stmt->execute([$pid]);
$services = $stmt->fetchAll();
$activeServiceCount = 0;
$inactiveServiceCount = 0;

foreach ($services as $service) {
    if ((int)$service['is_active'] === 1) {
        $activeServiceCount++;
    } else {
        $inactiveServiceCount++;
    }
}

$pageTitle = 'บริการ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="dashboard-hero overflow-hidden rounded-[2rem] p-6 text-white md:p-8">
        <div class="grid gap-6 lg:grid-cols-[1fr_360px] lg:items-end">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/60">
                    <i class="fa-solid fa-layer-group mr-2"></i>สตูดิโอช่างภาพ
                </p>
                <h1 class="mt-2 text-3xl font-black md:text-4xl">ประเภทงานที่รับ</h1>
                <p class="mt-3 max-w-2xl text-base font-semibold leading-8 text-white/75 md:text-lg">
                    เลือกหมวดงานที่รับจริง ใส่รายละเอียดให้ลูกค้าเข้าใจ และเปิดเฉพาะประเภทงานที่พร้อมรับจ้าง
                </p>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-list-check text-2xl text-red-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format(count($services)) ?></div>
                    <div class="text-xs font-black text-white/55">ทั้งหมด</div>
                </div>
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-circle-check text-2xl text-emerald-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format($activeServiceCount) ?></div>
                    <div class="text-xs font-black text-white/55">เปิดรับงาน</div>
                </div>
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-eye-slash text-2xl text-amber-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format($inactiveServiceCount) ?></div>
                    <div class="text-xs font-black text-white/55">ปิดไว้</div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[420px_1fr]">
        <form method="post" class="stock-card rounded-[1.75rem] p-6">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">

            <div class="mb-5">
                <p class="section-kicker">
                    <i class="fa-solid fa-plus mr-2"></i>เพิ่มหรืออัปเดต
                </p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">เพิ่มประเภทงานที่รับ</h2>
                <p class="mt-2 text-base font-semibold leading-7 text-neutral-600">
                    ถ้าเลือกหมวดเดิม ระบบจะอัปเดตรายละเอียดและราคาให้ทันที
                </p>
            </div>

            <label class="block text-sm font-black text-neutral-700" for="category_id">
                <i class="fa-solid fa-layer-group mr-2 text-red-600"></i>หมวดหมู่งาน <?= required_mark() ?>
            </label>
            <select id="category_id" name="category_id" required class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
                <option value="">เลือกประเภทงานที่รับ</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int)$category['id'] ?>"><?= h($category['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="mt-4 block text-sm font-black text-neutral-700" for="description">
                <i class="fa-solid fa-pen mr-2 text-red-600"></i>รายละเอียดงานที่รับ
            </label>
            <textarea id="description" name="description" rows="4" maxlength="500" placeholder="เช่น ถ่ายรับปริญญา ครึ่งวัน/เต็มวัน ส่งไฟล์ภาพที่แต่งสีแล้ว พร้อมให้คำแนะนำก่อนถ่าย" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold"></textarea>
            <p class="mt-2 text-sm font-bold text-neutral-500">แนะนำ 300-500 ตัวอักษร เขียนให้ชัดว่ารวมอะไรบ้างและเหมาะกับงานแบบไหน</p>

            <label class="mt-4 block text-sm font-black text-neutral-700" for="starting_price">
                <i class="fa-solid fa-baht-sign mr-2 text-red-600"></i>ราคาเริ่มต้นโดยประมาณ
            </label>
            <input id="starting_price" type="number" step="0.01" min="0" name="starting_price" placeholder="เช่น 2500" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
            <p class="mt-2 text-sm font-bold text-neutral-500">กรอกเป็นบาท ราคาเป็นข้อมูลอ้างอิง ลูกค้าและช่างภาพตกลงจริงภายนอกระบบ</p>

            <label class="mt-4 flex items-center justify-between rounded-2xl border border-neutral-100 bg-neutral-50 px-4 py-3 font-black text-neutral-800">
                <span><i class="fa-solid fa-toggle-on mr-2 text-emerald-600"></i>เปิดให้ลูกค้าเห็นและเลือกจอง</span>
                <input type="checkbox" name="is_active" checked class="h-5 w-5 accent-red-600">
            </label>

            <button class="stock-button mt-5 w-full rounded-2xl px-5 py-3 font-black">
                <i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกประเภทงาน
            </button>
        </form>

        <div class="space-y-4">
            <div class="stock-card rounded-[1.75rem] p-6">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="section-kicker">
                            <i class="fa-solid fa-sliders mr-2"></i>จัดการประเภทงาน
                        </p>
                        <h2 class="mt-1 text-2xl font-black text-neutral-950">ประเภทงานที่เปิดไว้ในโปรไฟล์</h2>
                    </div>
                    <div class="rounded-full bg-red-50 px-4 py-2 text-sm font-black text-red-700">
                        <i class="fa-solid fa-circle-info mr-1"></i>เปิดอยู่จึงจะแสดงในหน้าโปรไฟล์และค้นหา
                    </div>
                </div>

                <?php if (!$services): ?>
                    <div class="empty-state mt-5 rounded-[1.5rem] p-8 text-center">
                        <i class="fa-solid fa-layer-group text-4xl text-red-600"></i>
                        <h3 class="mt-3 text-xl font-black text-neutral-950">ยังไม่ได้เพิ่มประเภทงาน</h3>
                        <p class="mt-2 text-base font-semibold text-neutral-600">เริ่มจากเลือกหมวดงาน ใส่รายละเอียด แล้วกดบันทึก</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php foreach ($services as $service): ?>
                <?php
                $isActive = (int)$service['is_active'] === 1;
                $statusClass = 'bg-neutral-100 text-neutral-600';
                $statusIcon = 'fa-eye-slash';
                $statusText = 'ปิดไว้';

                if ($isActive) {
                    $statusClass = 'bg-emerald-50 text-emerald-700';
                    $statusIcon = 'fa-circle-check';
                    $statusText = 'เปิดรับงาน';
                }
                ?>
                <div class="stock-card rounded-[1.75rem] p-5">
                    <form method="post" class="grid gap-4 lg:grid-cols-[1fr_190px]">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= (int)$service['id'] ?>">

                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="grid h-11 w-11 place-items-center rounded-2xl bg-red-50 text-red-600">
                                    <i class="fa-solid fa-camera-retro"></i>
                                </span>
                                <div>
                                    <h3 class="text-xl font-black text-neutral-950"><?= h($service['name']) ?></h3>
                                    <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-black <?= h($statusClass) ?>">
                                        <i class="fa-solid <?= h($statusIcon) ?>"></i><?= h($statusText) ?>
                                    </span>
                                </div>
                            </div>

                            <label class="mt-4 block text-sm font-black text-neutral-700" for="description_<?= (int)$service['id'] ?>">รายละเอียดประเภทงาน</label>
                            <textarea id="description_<?= (int)$service['id'] ?>" name="description" rows="3" maxlength="500" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold"><?= h($service['description']) ?></textarea>
                            <p class="mt-2 text-sm font-bold text-neutral-500">แนะนำ 300-500 ตัวอักษร ถ้าข้อความยาว หน้าโปรไฟล์จะตัดแสดงบางส่วนเพื่อให้การ์ดอ่านง่าย</p>

                            <label class="mt-4 block text-sm font-black text-neutral-700" for="price_<?= (int)$service['id'] ?>">ราคาเริ่มต้นโดยประมาณ (บาท)</label>
                            <input id="price_<?= (int)$service['id'] ?>" type="number" step="0.01" min="0" name="starting_price" value="<?= h((string)$service['starting_price']) ?>" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
                        </div>

                        <div class="flex flex-col justify-between gap-3 rounded-[1.5rem] bg-neutral-50 p-4">
                            <label class="flex items-center justify-between gap-3 rounded-2xl bg-white px-4 py-3 text-sm font-black text-neutral-800">
                                <span><i class="fa-solid fa-eye mr-2 text-red-600"></i>แสดงในโปรไฟล์</span>
                                <input type="checkbox" name="is_active" class="h-5 w-5 accent-red-600" <?= $isActive ? 'checked' : '' ?>>
                            </label>

                            <div class="grid gap-2">
                                <button class="btn-success btn-md w-full" type="submit">
                                    <i class="fa-solid fa-floppy-disk"></i>บันทึก
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="mt-3 flex flex-wrap gap-2 border-t border-neutral-100 pt-3">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$service['id'] ?>">
                            <input type="hidden" name="is_active" value="<?= $isActive ? 1 : 0 ?>">
                            <button class="<?= $isActive ? 'btn-danger' : 'btn-success' ?> btn-sm" type="submit">
                                <?php if ($isActive): ?>
                                    <i class="fa-solid fa-eye-slash"></i>ปิดประเภทงานนี้
                                <?php else: ?>
                                    <i class="fa-solid fa-eye"></i>เปิดประเภทงานนี้
                                <?php endif; ?>
                            </button>
                        </form>

                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$service['id'] ?>">
                            <button data-confirm="ซ่อนประเภทงานนี้จากโปรไฟล์?" data-confirm-text="ข้อมูลบริการเดิมยังอยู่ สามารถเปิดกลับมาแสดงได้ภายหลัง" data-confirm-button="ซ่อนรายการ" class="btn-muted btn-sm" type="submit">
                                <i class="fa-solid fa-eye-slash"></i>ซ่อน
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="mt-6 rounded-[1.5rem] bg-red-50 p-5 text-base font-black leading-8 text-red-700">
        <i class="fa-solid fa-circle-info mr-2"></i>ราคาที่กรอกเป็นราคาเริ่มต้นโดยประมาณเท่านั้น เว็บไซต์ไม่รับชำระเงิน ลูกค้าและช่างภาพตกลงราคาและชำระเงินกันเองภายนอกระบบ
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

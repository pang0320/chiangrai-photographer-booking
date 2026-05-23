<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

function banner_active_label(int $isActive): array
{
    if ($isActive === 1) {
        return [
            'text' => 'เปิดใช้งาน',
            'icon' => 'fa-circle-check',
            'class' => 'bg-emerald-50 text-emerald-700',
        ];
    }

    return [
        'text' => 'ปิดใช้งาน',
        'icon' => 'fa-eye-slash',
        'class' => 'bg-neutral-100 text-neutral-600',
    ];
}

function banner_clean_button_url(string $url): string
{
    $url = trim($url);

    if ($url === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    if ($url[0] !== '/') {
        $url = '/' . $url;
    }

    return $url;
}

if (is_post()) {
    verify_csrf();

    $action = (string)($_POST['action'] ?? '');
    $recordId = (int)($_POST['id'] ?? 0);

    try {
        if ($action === 'save') {
            $title = trim((string)($_POST['title'] ?? ''));
            $subtitle = trim((string)($_POST['subtitle'] ?? ''));
            $buttonText = trim((string)($_POST['button_text'] ?? ''));
            $buttonUrl = banner_clean_button_url((string)($_POST['button_url'] ?? ''));
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $isActive = 0;

            if (isset($_POST['is_active'])) {
                $isActive = 1;
            }

            if ($title === '') {
                flash('error', 'กรุณากรอกหัวข้อแบนเนอร์');
                redirect('/admin/banners.php');
            }

            $uploadedImage = upload_image($_FILES['image'] ?? [], 'banners');

            if ($recordId > 0) {
                $existingImage = db_fetch_value('SELECT image_path FROM banners WHERE id = ? LIMIT 1', [$recordId]);

                if ($existingImage === false) {
                    flash('error', 'ไม่พบแบนเนอร์ที่ต้องการแก้ไข');
                    redirect('/admin/banners.php');
                }

                $imagePath = $existingImage;
                if ($uploadedImage !== null) {
                    $imagePath = $uploadedImage;
                }

                $stmt = db()->prepare('UPDATE banners
                                       SET title = ?, subtitle = ?, image_path = ?, button_text = ?, button_url = ?, is_active = ?, sort_order = ?, updated_at = NOW()
                                       WHERE id = ?');
                $stmt->execute([$title, $subtitle, $imagePath, $buttonText, $buttonUrl, $isActive, $sortOrder, $recordId]);

                flash('success', 'แก้ไขแบนเนอร์แล้ว');
            } else {
                $stmt = db()->prepare('INSERT INTO banners (title, subtitle, image_path, button_text, button_url, is_active, sort_order, created_at, updated_at)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
                $stmt->execute([$title, $subtitle, $uploadedImage, $buttonText, $buttonUrl, $isActive, $sortOrder]);
                $recordId = (int)db()->lastInsertId();

                flash('success', 'สร้างแบนเนอร์ใหม่แล้ว');
            }
        } elseif ($action === 'toggle') {
            if ($recordId <= 0) {
                flash('error', 'ไม่พบแบนเนอร์ที่ต้องการเปลี่ยนสถานะ');
                redirect('/admin/banners.php');
            }

            $currentStatus = db_fetch_value('SELECT is_active FROM banners WHERE id = ? LIMIT 1', [$recordId]);
            if ($currentStatus === false) {
                flash('error', 'ไม่พบแบนเนอร์ที่ต้องการเปลี่ยนสถานะ');
                redirect('/admin/banners.php');
            }

            $nextStatus = 1;
            if ((int)$currentStatus === 1) {
                $nextStatus = 0;
            }

            $stmt = db()->prepare('UPDATE banners SET is_active = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$nextStatus, $recordId]);

            if ($nextStatus === 1) {
                flash('success', 'เปิดใช้งานแบนเนอร์แล้ว แสดงบนหน้าแรกทันที');
            } else {
                flash('success', 'ปิดใช้งานแบนเนอร์แล้ว');
            }
        } elseif ($action === 'delete') {
            if ($recordId <= 0) {
                flash('error', 'ไม่พบแบนเนอร์ที่ต้องการลบ');
                redirect('/admin/banners.php');
            }

            $stmt = db()->prepare('DELETE FROM banners WHERE id = ?');
            $stmt->execute([$recordId]);
            flash('success', 'ลบแบนเนอร์ออกจากระบบแล้ว');
        } else {
            flash('error', 'ไม่พบคำสั่งที่ต้องการทำรายการ');
            redirect('/admin/banners.php');
        }

        cache_clear_all();
        log_activity('manage_banners', 'banners', $recordId);
        redirect('/admin/banners.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
}

$items = db_fetch_all('SELECT * FROM banners ORDER BY sort_order, id DESC');
$activeItems = db_fetch_all('SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order, id DESC LIMIT 3');
$activeCount = 0;
$inactiveCount = 0;

foreach ($items as $item) {
    if ((int)$item['is_active'] === 1) {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
}

$pageTitle = 'แบนเนอร์';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="dashboard-hero overflow-hidden rounded-[2rem] p-6 text-white md:p-8">
        <div class="grid gap-6 lg:grid-cols-[1fr_420px] lg:items-end">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/60">
                    <i class="fa-solid fa-images mr-2"></i>ผู้ดูแลระบบ
                </p>
                <h1 class="mt-2 text-3xl font-black md:text-4xl">จัดการแบนเนอร์หน้าแรก</h1>
                <p class="mt-3 max-w-3xl text-base font-semibold leading-8 text-white/75 md:text-lg">
                    แบนเนอร์ที่เปิดใช้งานจะแสดงในหน้าแรก โดยรายการลำดับน้อยสุดจะใช้เป็นภาพและข้อความ Hero หลัก ส่วนรายการอื่นจะแสดงในบล็อกโปรโมตใต้ Hero
                </p>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-images text-2xl text-red-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format(count($items)) ?></div>
                    <div class="text-xs font-black text-white/55">ทั้งหมด</div>
                </div>
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-eye text-2xl text-emerald-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format($activeCount) ?></div>
                    <div class="text-xs font-black text-white/55">กำลังแสดง</div>
                </div>
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-eye-slash text-2xl text-amber-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format($inactiveCount) ?></div>
                    <div class="text-xs font-black text-white/55">ปิดไว้</div>
                </div>
            </div>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" class="stock-card mt-6 rounded-[1.75rem] p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">

        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="section-kicker">
                    <i class="fa-solid fa-plus mr-2"></i>สร้างแบนเนอร์ใหม่
                </p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">กำหนดข้อความ ภาพ และปุ่มที่จะโผล่หน้าแรก</h2>
                <p class="mt-2 text-base font-semibold leading-7 text-neutral-600">
                    ปุ่มนี้ใช้สร้างแบนเนอร์ใหม่เท่านั้น ส่วนปุ่มแก้ไข/เปิด/ปิด/ลบ อยู่ในตารางรายการด้านล่าง
                </p>
            </div>
            <button type="submit" class="stock-button rounded-2xl px-5 py-3 font-black">
                <i class="fa-solid fa-plus mr-2"></i>สร้างแบนเนอร์ใหม่
            </button>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2">
                <label class="block text-sm font-black text-neutral-700" for="title">
                    <i class="fa-solid fa-heading mr-2 text-red-600"></i>หัวข้อแบนเนอร์
                </label>
                <input id="title" name="title" required placeholder="เช่น ค้นหาช่างภาพเชียงราย" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
            </div>
            <div class="xl:col-span-2">
                <label class="block text-sm font-black text-neutral-700" for="subtitle">
                    <i class="fa-solid fa-align-left mr-2 text-red-600"></i>คำอธิบาย
                </label>
                <input id="subtitle" name="subtitle" placeholder="ข้อความสั้นใต้หัวข้อ" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
            </div>
            <div>
                <label class="block text-sm font-black text-neutral-700" for="button_text">
                    <i class="fa-solid fa-hand-pointer mr-2 text-red-600"></i>ข้อความปุ่ม
                </label>
                <input id="button_text" name="button_text" placeholder="เช่น ค้นหาช่างภาพ" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
            </div>
            <div>
                <label class="block text-sm font-black text-neutral-700" for="button_url">
                    <i class="fa-solid fa-link mr-2 text-red-600"></i>ลิงก์ปุ่ม
                </label>
                <input id="button_url" name="button_url" placeholder="/photographers.php" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
            </div>
            <div class="xl:col-span-2">
                <label class="block text-sm font-black text-neutral-700" for="image">
                    <i class="fa-solid fa-image mr-2 text-red-600"></i>รูปแบนเนอร์
                </label>
                <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
                <p class="mt-2 text-xs font-bold leading-6 text-neutral-500"><?= h(UPLOAD_IMAGE_HELP_TEXT) ?></p>
            </div>
            <div>
                <label class="block text-sm font-black text-neutral-700" for="sort_order">
                    <i class="fa-solid fa-arrow-down-1-9 mr-2 text-red-600"></i>ลำดับแสดงผล
                </label>
                <input id="sort_order" type="number" name="sort_order" value="0" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
            </div>
            <label class="flex items-center justify-between rounded-2xl border border-neutral-100 bg-neutral-50 px-4 py-3 font-black text-neutral-800 xl:col-span-3">
                <span><i class="fa-solid fa-toggle-on mr-2 text-emerald-600"></i>เปิดให้แสดงบนหน้าแรกทันที</span>
                <input type="checkbox" name="is_active" checked class="h-5 w-5 accent-red-600">
            </label>
        </div>
    </form>

    <div class="mt-6 grid gap-4 lg:grid-cols-[1fr_420px]">
        <div class="stock-card rounded-[1.75rem] p-5">
            <h2 class="text-xl font-black text-neutral-950">
                <i class="fa-solid fa-display mr-2 text-red-600"></i>ตำแหน่งที่แบนเนอร์จะแสดง
            </h2>
            <p class="mt-2 text-base font-semibold leading-8 text-neutral-600">
                แบนเนอร์จะแสดงที่หน้าแรก: รายการแรกที่เปิดใช้งานเป็น Hero ด้านบน และรายการที่เปิดใช้งานจะแสดงซ้ำเป็นการ์ดโปรโมตใต้ Hero เพื่อให้ผู้ใช้เห็นข้อความกับปุ่ม CTA ชัดเจน
            </p>

            <div class="mt-5 rounded-[1.5rem] bg-gradient-to-r from-neutral-950 to-red-700 p-5 text-white">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-white/55">ตัวอย่าง Hero หน้าแรก</p>
                <h3 class="mt-2 text-2xl font-black">หัวข้อแบนเนอร์</h3>
                <p class="mt-2 text-sm font-bold text-white/70">คำอธิบายและปุ่มจะใช้ตามข้อมูลที่กรอกในตาราง</p>
                <span class="mt-4 inline-flex rounded-full bg-white px-4 py-2 text-sm font-black text-neutral-950">
                    <i class="fa-solid fa-arrow-right mr-2"></i>ข้อความปุ่ม
                </span>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-5">
            <h2 class="text-xl font-black text-neutral-950">
                <i class="fa-solid fa-eye mr-2 text-red-600"></i>แบนเนอร์ที่กำลังแสดง
            </h2>
            <div class="mt-4 grid gap-3">
                <?php if (!$activeItems): ?>
                    <div class="empty-state rounded-[1.5rem] p-6 text-center">
                        <i class="fa-solid fa-image text-4xl text-red-600"></i>
                        <h3 class="mt-3 text-lg font-black text-neutral-950">ยังไม่มีแบนเนอร์ที่เปิดใช้งาน</h3>
                        <p class="mt-2 text-sm font-bold text-neutral-500">สร้างหรือกดเปิดจากรายการด้านล่าง</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($activeItems as $activeItem): ?>
                        <div class="rounded-[1.25rem] border border-neutral-100 bg-neutral-50 p-3">
                            <div class="flex items-center gap-3">
                                <img class="h-16 w-20 rounded-2xl object-cover" loading="lazy" decoding="async" src="<?= h(public_image($activeItem['image_path'], '/assets/uploads/seed/photo-1511285560929-80b456fea0bc.jpg')) ?>" alt="">
                                <div class="min-w-0">
                                    <p class="truncate font-black text-neutral-950"><?= h($activeItem['title']) ?></p>
                                    <p class="truncate text-sm font-bold text-neutral-500"><?= h($activeItem['button_text'] ?: 'ไม่มีปุ่ม') ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stock-card mt-6 rounded-[1.75rem] p-5">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="section-kicker">
                    <i class="fa-solid fa-list mr-2"></i>รายการแบนเนอร์
                </p>
                <h2 class="mt-1 text-2xl font-black text-neutral-950">แก้ไขข้อความปุ่มและสถานะแบนเนอร์</h2>
                <p class="mt-2 text-sm font-bold leading-7 text-neutral-500">
                    <i class="fa-solid fa-circle-info mr-1 text-red-600"></i>
                    ปุ่ม “บันทึก” ใช้แก้ไขข้อมูลแถวนั้น, ปุ่ม “เปิด/ปิด” ใช้สลับการแสดงผล, ปุ่ม “ลบ” ใช้ลบรายการแบนเนอร์ออกจากระบบ
                </p>
            </div>
            <div class="rounded-full bg-red-50 px-4 py-2 text-sm font-black text-red-700">
                <i class="fa-solid fa-circle-info mr-1"></i>ตารางแสดงทีละ 5 รายการ
            </div>
        </div>

        <?php if (!$items): ?>
            <div class="empty-state rounded-[1.5rem] p-8 text-center">
                <i class="fa-solid fa-images text-4xl text-red-600"></i>
                <h3 class="mt-3 text-xl font-black text-neutral-950">ยังไม่มีแบนเนอร์</h3>
                <p class="mt-2 text-base font-semibold text-neutral-600">สร้างแบนเนอร์แรกจากฟอร์มด้านบน</p>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <form id="banner_save_<?= (int)$item['id'] ?>" method="post" enctype="multipart/form-data" class="hidden">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                </form>
                <form id="banner_toggle_<?= (int)$item['id'] ?>" method="post" class="hidden">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                </form>
            <?php endforeach; ?>

            <div class="overflow-x-auto">
                <table class="datatable w-full text-base">
                    <thead>
                        <tr>
                            <th>แบนเนอร์</th>
                            <th>ข้อความปุ่ม</th>
                            <th>รูป / ลำดับ</th>
                            <th>สถานะ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $itemId = (int)$item['id'];
                            $isActive = (int)$item['is_active'] === 1;
                            $status = banner_active_label((int)$item['is_active']);
                            ?>
                            <tr>
                                <td class="min-w-[360px]">
                                    <div class="flex items-start gap-3">
                                        <img class="h-24 w-28 shrink-0 rounded-[1.25rem] object-cover" loading="lazy" decoding="async" src="<?= h(public_image($item['image_path'], '/assets/uploads/seed/photo-1511285560929-80b456fea0bc.jpg')) ?>" alt="">
                                        <div class="grid flex-1 gap-2">
                                            <label class="text-xs font-black text-neutral-500" for="title_<?= $itemId ?>">หัวข้อแบนเนอร์</label>
                                            <input id="title_<?= $itemId ?>" form="banner_save_<?= $itemId ?>" name="title" required value="<?= h($item['title']) ?>" class="stock-input w-full rounded-2xl px-4 py-2 font-black">
                                            <label class="text-xs font-black text-neutral-500" for="subtitle_<?= $itemId ?>">คำอธิบาย</label>
                                            <input id="subtitle_<?= $itemId ?>" form="banner_save_<?= $itemId ?>" name="subtitle" value="<?= h($item['subtitle']) ?>" class="stock-input w-full rounded-2xl px-4 py-2 font-semibold">
                                        </div>
                                    </div>
                                </td>
                                <td class="min-w-[280px]">
                                    <div class="grid gap-2">
                                        <label class="text-xs font-black text-neutral-500" for="button_text_<?= $itemId ?>">ข้อความบนปุ่ม</label>
                                        <input id="button_text_<?= $itemId ?>" form="banner_save_<?= $itemId ?>" name="button_text" value="<?= h($item['button_text']) ?>" class="stock-input w-full rounded-2xl px-4 py-2 font-black" placeholder="เช่น ค้นหาช่างภาพ">
                                        <label class="text-xs font-black text-neutral-500" for="button_url_<?= $itemId ?>">ลิงก์เมื่อกดปุ่ม</label>
                                        <input id="button_url_<?= $itemId ?>" form="banner_save_<?= $itemId ?>" name="button_url" value="<?= h($item['button_url']) ?>" class="stock-input w-full rounded-2xl px-4 py-2 font-semibold" placeholder="/photographers.php">
                                        <p class="text-xs font-bold text-neutral-500">หน้าเว็บจะแสดงปุ่มตามข้อความนี้โดยตรง</p>
                                    </div>
                                </td>
                                <td class="min-w-[240px]">
                                    <div class="grid gap-2">
                                        <label class="text-xs font-black text-neutral-500" for="image_<?= $itemId ?>">เปลี่ยนรูปแบนเนอร์</label>
                                        <input id="image_<?= $itemId ?>" form="banner_save_<?= $itemId ?>" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="stock-input w-full rounded-2xl px-4 py-2 text-sm font-semibold">
                                        <span class="text-xs font-bold text-neutral-500"><?= h(UPLOAD_IMAGE_HELP_TEXT) ?></span>
                                        <label class="text-xs font-black text-neutral-500" for="sort_order_<?= $itemId ?>">ลำดับแสดงผล</label>
                                        <input id="sort_order_<?= $itemId ?>" form="banner_save_<?= $itemId ?>" name="sort_order" type="number" value="<?= (int)$item['sort_order'] ?>" class="stock-input w-full rounded-2xl px-4 py-2 font-black">
                                    </div>
                                </td>
                                <td class="min-w-[180px]">
                                    <label class="flex items-center gap-2 rounded-2xl bg-neutral-50 px-3 py-2 font-black">
                                        <input form="banner_save_<?= $itemId ?>" type="checkbox" name="is_active" class="h-5 w-5 accent-red-600" <?= $isActive ? 'checked' : '' ?>>
                                        <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-black <?= h($status['class']) ?>">
                                            <i class="fa-solid <?= h($status['icon']) ?>"></i><?= h($status['text']) ?>
                                        </span>
                                    </label>
                                </td>
                                <td class="min-w-[220px]">
                                    <div class="flex flex-wrap gap-2">
                                        <button form="banner_save_<?= $itemId ?>" class="btn-success btn-sm" type="submit">
                                            <i class="fa-solid fa-floppy-disk"></i>บันทึก
                                        </button>
                                        <button form="banner_toggle_<?= $itemId ?>" class="<?= $isActive ? 'btn-warning' : 'btn-success' ?> btn-sm" type="submit">
                                            <?php if ($isActive): ?>
                                                <i class="fa-solid fa-eye-slash"></i>ปิด
                                            <?php else: ?>
                                                <i class="fa-solid fa-eye"></i>เปิด
                                            <?php endif; ?>
                                        </button>
                                        <form method="post" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $itemId ?>">
                                            <button data-confirm="ยืนยันลบแบนเนอร์นี้?" data-confirm-text="แบนเนอร์นี้จะถูกลบออกจากระบบและไม่แสดงบนหน้าแรก" data-confirm-button="ลบแบนเนอร์" class="btn-danger btn-sm" type="submit">
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

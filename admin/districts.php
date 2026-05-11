<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

if (is_post()) {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'toggle') {
        $stmt = db()->prepare('UPDATE districts SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([$id]);
    } else {
        $districtName = trim((string)($_POST['district_name'] ?? ''));
        $latitude = (float)($_POST['latitude'] ?? 0);
        $longitude = (float)($_POST['longitude'] ?? 0);
        $isActive = 0;

        if (isset($_POST['is_active'])) {
            $isActive = 1;
        }

        $stmt = db()->prepare('INSERT INTO districts (province_name, district_name, latitude, longitude, is_active, created_at, updated_at)
                               VALUES ("เชียงราย", ?, ?, ?, ?, NOW(), NOW())
                               ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), is_active = VALUES(is_active), updated_at = NOW()');
        $stmt->execute([$districtName, $latitude, $longitude, $isActive]);
    }

    log_activity('manage_districts', 'districts', $id);
    flash('success', 'บันทึกอำเภอแล้ว');
    redirect('/admin/districts.php');
}

$items = db_fetch_all('SELECT * FROM districts ORDER BY district_name');

$pageTitle = 'อำเภอ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">ผู้ดูแลระบบ</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">จัดการอำเภอ</h1>
        <p class="mt-2 max-w-3xl text-sm font-bold leading-7 text-neutral-500">พิกัดละติจูด/ลองจิจูดใช้คำนวณช่างภาพใกล้เคียงด้วย Haversine Formula เมื่อไม่พบช่างภาพในอำเภอที่ลูกค้าเลือก</p>
    </div>

    <form method="post" class="stock-card mt-6 grid gap-3 rounded-[1.5rem] p-5 md:grid-cols-5">
        <?= csrf_field() ?>
        <input name="district_name" required placeholder="ชื่ออำเภอ" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input name="latitude" required placeholder="ละติจูด" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <input name="longitude" required placeholder="ลองจิจูด" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        <label class="rounded-2xl bg-neutral-50 px-4 py-3 font-bold">
            <input type="checkbox" name="is_active" checked>
            เปิดใช้งาน
        </label>
        <button class="stock-button rounded-2xl px-5 py-3 font-black"><i class="fa-solid fa-plus mr-2"></i>เพิ่ม/อัปเดต</button>
    </form>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>อำเภอ</th>
                    <th>ละติจูด</th>
                    <th>ลองจิจูด</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td class="font-black text-neutral-500"><?= $index + 1 ?></td>
                        <td class="font-black"><?= h($item['district_name']) ?></td>
                        <td><?= h($item['latitude']) ?></td>
                        <td><?= h($item['longitude']) ?></td>
                        <td>
                            <?php if ((int)$item['is_active'] === 1): ?>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i>เปิดใช้งาน</span>
                            <?php else: ?>
                                <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-black text-neutral-600"><i class="fa-solid fa-eye-slash mr-1"></i>ปิดใช้งาน</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <button class="rounded-full bg-neutral-100 px-3 py-1.5 font-black text-neutral-700">
                                    <i class="fa-solid fa-arrows-rotate mr-1"></i>สลับสถานะ
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

<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');

$profile = photographer_profile_by_user((int)current_user()['id']);
$pid = (int)$profile['id'];

$districts = db_fetch_all('SELECT * FROM districts WHERE is_active = 1 ORDER BY district_name');

$stmt = db()->prepare('SELECT * FROM photographer_service_areas WHERE photographer_id = ?');
$stmt->execute([$pid]);
$currentAreas = $stmt->fetchAll();

$areas = [];
foreach ($currentAreas as $row) {
    $areas[(int)$row['district_id']] = $row;
}

if (is_post()) {
    verify_csrf();

    $selectedDistrictIds = array_map('intval', $_POST['district_ids'] ?? []);
    $primaryDistrictId = (int)($_POST['primary_district_id'] ?? 0);

    db()->beginTransaction();

    $stmt = db()->prepare('UPDATE photographer_service_areas SET is_active = 0, is_primary = 0 WHERE photographer_id = ?');
    $stmt->execute([$pid]);

    foreach ($selectedDistrictIds as $districtId) {
        $isPrimary = 0;

        if ($districtId === $primaryDistrictId) {
            $isPrimary = 1;
        }

        $stmt = db()->prepare('INSERT INTO photographer_service_areas (photographer_id, district_id, is_primary, is_active, created_at, updated_at)
                               VALUES (?, ?, ?, 1, NOW(), NOW())
                               ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary), is_active = 1, updated_at = NOW()');
        $stmt->execute([$pid, $districtId, $isPrimary]);
    }

    if ($primaryDistrictId > 0) {
        $stmt = db()->prepare('UPDATE photographer_profiles SET main_district_id = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$primaryDistrictId, $pid]);
    }

    log_activity('update_service_areas', 'photographer_service_areas', $pid);
    db()->commit();

    flash('success', 'บันทึกพื้นที่แล้ว');
    redirect('/photographer/service_areas.php');
}

$pageTitle = 'พื้นที่ให้บริการ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Photographer Studio</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">พื้นที่ให้บริการ</h1>
    </div>

    <form method="post" class="stock-card mt-6 rounded-[1.5rem] p-6">
        <?= csrf_field() ?>

        <label class="grid gap-2 text-sm font-black text-neutral-700">
            อำเภอหลัก
            <select name="primary_district_id" class="stock-input rounded-2xl px-4 py-3 font-semibold">
                <option value="">อำเภอหลัก</option>
                <?php foreach ($districts as $district): ?>
                    <option value="<?= (int)$district['id'] ?>" <?php if ((int)$profile['main_district_id'] === (int)$district['id']): ?>selected<?php endif; ?>>
                        <?= h($district['district_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($districts as $district): ?>
                <?php
                $districtId = (int)$district['id'];
                $row = $areas[$districtId] ?? null;
                $isChecked = false;

                if ($row && (int)$row['is_active'] === 1) {
                    $isChecked = true;
                }
                ?>
                <label class="flex items-center gap-3 rounded-2xl border border-neutral-100 bg-white px-4 py-3 font-bold shadow-sm">
                    <input type="checkbox" name="district_ids[]" value="<?= $districtId ?>" <?php if ($isChecked): ?>checked<?php endif; ?>>
                    <span><?= h($district['district_name']) ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <button class="stock-button mt-6 rounded-full px-6 py-3 font-black"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึก</button>
    </form>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

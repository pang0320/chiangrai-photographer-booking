<?php
require_once __DIR__ . '/includes/functions.php';

$districts = db_fetch_all('SELECT * FROM districts WHERE is_active = 1 ORDER BY district_name');
$categories = db_fetch_all('SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order');
$allowPhotographer = setting('allow_photographer_registration', '1') === '1';

if (is_post()) {
    verify_csrf();
    $accountType = 'customer';
    if (isset($_POST['account_type']) && $_POST['account_type'] === 'photographer') {
        $accountType = 'photographer';
    }
    if ($accountType === 'photographer' && !$allowPhotographer) {
        flash('error', 'ยังไม่เปิดรับสมัครช่างภาพ');
        redirect('/register.php');
    }

    $name = '';
    if (isset($_POST['name'])) {
        $name = trim((string)$_POST['name']);
    }
    $email = '';
    if (isset($_POST['email'])) {
        $email = trim((string)$_POST['email']);
    }
    $phone = '';
    if (isset($_POST['phone'])) {
        $phone = trim((string)$_POST['phone']);
    }
    $password = '';
    if (isset($_POST['password'])) {
        $password = (string)$_POST['password'];
    }
    $confirm = '';
    if (isset($_POST['password_confirmation'])) {
        $confirm = (string)$_POST['password_confirmation'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8 || $password !== $confirm || $name === '') {
        flash('error', 'ข้อมูลไม่ถูกต้อง หรือรหัสผ่านไม่ตรงกัน');
        redirect('/register.php');
    }

    try {
        db()->beginTransaction();
        $status = 'active';
        if ($accountType === 'photographer') {
            $status = 'pending';
        }
        $stmt = db()->prepare('INSERT INTO users (role_id, name, email, phone, password, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([role_id($accountType), $name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $status]);
        $userId = (int)db()->lastInsertId();

        if ($accountType === 'photographer') {
            $display = $name;
            if (isset($_POST['display_name']) && trim((string)$_POST['display_name']) !== '') {
                $display = trim((string)$_POST['display_name']);
            }
            $districtId = 0;
            if (isset($_POST['main_district_id'])) {
                $districtId = (int)$_POST['main_district_id'];
            }
            $lineId = '';
            if (isset($_POST['line_id'])) {
                $lineId = trim((string)$_POST['line_id']);
            }
            $categoryIds = [];
            if (isset($_POST['category_ids']) && is_array($_POST['category_ids'])) {
                $categoryIds = array_map('intval', $_POST['category_ids']);
            }
            $stmt = db()->prepare('INSERT INTO photographer_profiles (user_id, display_name, slug, phone_public, line_id, main_district_id, approval_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, "pending", NOW(), NOW())');
            $mainDistrictValue = null;
            if ($districtId > 0) {
                $mainDistrictValue = $districtId;
            }
            $stmt->execute([$userId, $display, unique_slug('photographer_profiles', $display), $phone, $lineId, $mainDistrictValue]);
            $photographerId = (int)db()->lastInsertId();
            if ($districtId) {
                db()->prepare('INSERT INTO photographer_service_areas (photographer_id, district_id, is_primary, is_active) VALUES (?, ?, 1, 1)')->execute([$photographerId, $districtId]);
            }
            foreach ($categoryIds as $categoryId) {
                db()->prepare('INSERT IGNORE INTO photographer_services (photographer_id, category_id, is_active, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())')->execute([$photographerId, $categoryId]);
            }
        }

        log_activity('register', 'users', $userId, 'New account registered');
        db()->commit();
        $successMessage = 'สมัครสำเร็จ กรุณาเข้าสู่ระบบ';
        if ($accountType === 'photographer') {
            $successMessage = 'สมัครสำเร็จ กรุณารอ Admin อนุมัติ';
        }
        flash('success', $successMessage);
        redirect('/login.php');
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        flash('error', 'ไม่สามารถสมัครได้ อีเมลอาจถูกใช้งานแล้ว');
    }
}

$pageTitle = 'สมัครสมาชิก';
include __DIR__ . '/includes/header.php';
?>
<section class="mx-auto max-w-3xl px-4 py-12">
    <div class="rounded-3xl bg-white p-8 shadow-xl ring-1 ring-slate-200">
        <h1 class="text-2xl font-extrabold">สมัครสมาชิก</h1>
        <form method="post" class="mt-6 grid gap-4">
            <?= csrf_field() ?>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="rounded-2xl border p-4"><input type="radio" name="account_type" value="customer" checked> <i class="fa-solid fa-user mr-2 text-red-600"></i>ลูกค้า</label>
                <label class="rounded-2xl border p-4"><input type="radio" name="account_type" value="photographer"> <i class="fa-solid fa-camera mr-2 text-red-600"></i>ช่างภาพ</label>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="icon-input block"><i class="fa-solid fa-user"></i><input name="name" required placeholder="ชื่อ" class="w-full rounded-2xl border px-4 py-3"></label>
                <label class="icon-input block"><i class="fa-solid fa-envelope"></i><input type="email" name="email" required placeholder="อีเมล" class="w-full rounded-2xl border px-4 py-3"></label>
                <label class="icon-input block"><i class="fa-solid fa-phone"></i><input name="phone" required placeholder="เบอร์โทร" class="w-full rounded-2xl border px-4 py-3"></label>
                <label class="icon-input block"><i class="fa-solid fa-camera"></i><input name="display_name" placeholder="ชื่อช่างภาพ/ชื่อทีม" class="w-full rounded-2xl border px-4 py-3"></label>
                <label class="icon-input block"><i class="fa-solid fa-lock"></i><input type="password" name="password" required minlength="8" placeholder="รหัสผ่าน" class="w-full rounded-2xl border px-4 py-3"></label>
                <label class="icon-input block"><i class="fa-solid fa-lock"></i><input type="password" name="password_confirmation" required minlength="8" placeholder="ยืนยันรหัสผ่าน" class="w-full rounded-2xl border px-4 py-3"></label>
                <label class="icon-input block">
                    <i class="fa-solid fa-location-dot"></i>
                    <select name="main_district_id" class="w-full rounded-2xl border px-4 py-3">
                        <option value="">อำเภอหลักของช่างภาพ</option>
                        <?php foreach ($districts as $district): ?>
                            <option value="<?= (int)$district['id'] ?>">
                                <?= h($district['district_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="icon-input block"><i class="fa-brands fa-line"></i><input name="line_id" placeholder="LINE ID / ช่องทางติดต่อ" class="w-full rounded-2xl border px-4 py-3"></label>
            </div>
            <div>
                <p class="mb-2 text-sm font-bold">ประเภทงานที่รับเบื้องต้น</p>
                <div class="grid gap-2 sm:grid-cols-3">
                    <?php foreach ($categories as $category): ?>
                        <label class="rounded-2xl bg-slate-50 px-4 py-3 text-sm">
                            <input type="checkbox" name="category_ids[]" value="<?= (int)$category['id'] ?>">
                            <?= h($category['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="rounded-2xl bg-indigo-600 px-5 py-3 font-bold text-white"><i class="fa-solid fa-user-plus mr-2"></i>สมัครสมาชิก</button>
        </form>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

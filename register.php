<?php
require_once __DIR__ . '/includes/functions.php';

$districts = db()->query('SELECT * FROM districts WHERE is_active = 1 ORDER BY district_name')->fetchAll();
$categories = db()->query('SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
$allowPhotographer = setting('allow_photographer_registration', '1') === '1';

if (is_post()) {
    verify_csrf();
    $accountType = $_POST['account_type'] === 'photographer' ? 'photographer' : 'customer';
    if ($accountType === 'photographer' && !$allowPhotographer) {
        flash('error', 'ยังไม่เปิดรับสมัครช่างภาพ');
        redirect('/register.php');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirmation'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8 || $password !== $confirm || $name === '') {
        flash('error', 'ข้อมูลไม่ถูกต้อง หรือรหัสผ่านไม่ตรงกัน');
        redirect('/register.php');
    }

    try {
        db()->beginTransaction();
        $status = $accountType === 'photographer' ? 'pending' : 'active';
        $stmt = db()->prepare('INSERT INTO users (role_id, name, email, phone, password, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([role_id($accountType), $name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $status]);
        $userId = (int)db()->lastInsertId();

        if ($accountType === 'photographer') {
            $display = trim((string)($_POST['display_name'] ?? $name));
            $districtId = (int)($_POST['main_district_id'] ?? 0);
            $lineId = trim((string)($_POST['line_id'] ?? ''));
            $categoryIds = array_map('intval', $_POST['category_ids'] ?? []);
            $stmt = db()->prepare('INSERT INTO photographer_profiles (user_id, display_name, slug, phone_public, line_id, main_district_id, approval_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, "pending", NOW(), NOW())');
            $stmt->execute([$userId, $display, unique_slug('photographer_profiles', $display), $phone, $lineId, $districtId ?: null]);
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
        flash('success', $accountType === 'photographer' ? 'สมัครสำเร็จ กรุณารอ Admin อนุมัติ' : 'สมัครสำเร็จ กรุณาเข้าสู่ระบบ');
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
                <label class="rounded-2xl border p-4"><input type="radio" name="account_type" value="customer" checked> ลูกค้า</label>
                <label class="rounded-2xl border p-4"><input type="radio" name="account_type" value="photographer"> ช่างภาพ</label>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <input name="name" required placeholder="ชื่อ" class="rounded-2xl border px-4 py-3">
                <input type="email" name="email" required placeholder="อีเมล" class="rounded-2xl border px-4 py-3">
                <input name="phone" required placeholder="เบอร์โทร" class="rounded-2xl border px-4 py-3">
                <input name="display_name" placeholder="ชื่อช่างภาพ/ชื่อทีม" class="rounded-2xl border px-4 py-3">
                <input type="password" name="password" required minlength="8" placeholder="รหัสผ่าน" class="rounded-2xl border px-4 py-3">
                <input type="password" name="password_confirmation" required minlength="8" placeholder="ยืนยันรหัสผ่าน" class="rounded-2xl border px-4 py-3">
                <select name="main_district_id" class="rounded-2xl border px-4 py-3">
                    <option value="">อำเภอหลักของช่างภาพ</option>
                    <?php foreach ($districts as $district): ?>
                        <option value="<?= (int)$district['id'] ?>">
                            <?= h($district['district_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input name="line_id" placeholder="LINE ID / ช่องทางติดต่อ" class="rounded-2xl border px-4 py-3">
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
            <button class="rounded-2xl bg-indigo-600 px-5 py-3 font-bold text-white">สมัครสมาชิก</button>
        </form>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

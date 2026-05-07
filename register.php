<?php
require_once __DIR__ . '/includes/functions.php';

$districts = db_fetch_all('SELECT * FROM districts WHERE is_active = 1 ORDER BY district_name');
$categories = db_fetch_all('SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order');
$allowPhotographer = setting('allow_photographer_registration', '1') === '1';
$cleanContext = clean_context_init(['role']);
$selectedAccountType = 'customer';
if (isset($cleanContext['role']) && $cleanContext['role'] === 'photographer' && $allowPhotographer) {
    $selectedAccountType = 'photographer';
}

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

    if ($phone === '') {
        flash('error', 'กรุณากรอกเบอร์โทร');
        redirect('/register.php');
    }

    $display = $name;
    $districtId = 0;
    $lineId = '';
    $categoryIds = [];

    if ($accountType === 'photographer') {
        if (isset($_POST['display_name']) && trim((string)$_POST['display_name']) !== '') {
            $display = trim((string)$_POST['display_name']);
        }
        if (isset($_POST['main_district_id'])) {
            $districtId = (int)$_POST['main_district_id'];
        }
        if (isset($_POST['line_id'])) {
            $lineId = trim((string)$_POST['line_id']);
        }
        if (isset($_POST['category_ids']) && is_array($_POST['category_ids'])) {
            $categoryIds = array_map('intval', $_POST['category_ids']);
        }

        if ($display === '' || $districtId <= 0 || count($categoryIds) === 0) {
            flash('error', 'กรุณากรอกข้อมูลช่างภาพให้ครบ เลือกอำเภอหลักและประเภทงานอย่างน้อย 1 รายการ');
            clean_redirect('/register.php', ['role' => 'photographer']);
        }
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
            $successMessage = 'สมัครสำเร็จ กรุณารอผู้ดูแลระบบอนุมัติ';
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
<section class="relative overflow-hidden bg-neutral-950 text-white">
    <div class="absolute inset-0">
        <img class="h-full w-full object-cover opacity-35" src="/assets/uploads/seed/photo-1492691527719-9d1e07e534b4.jpg" alt="">
        <div class="absolute inset-0 bg-gradient-to-r from-neutral-950 via-neutral-950/82 to-neutral-950/35"></div>
    </div>
    <div class="relative stock-shell grid gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[.85fr_1.15fr] lg:px-8 lg:py-16">
        <div class="flex flex-col justify-center">
            <p class="section-kicker text-red-300">เข้าร่วมระบบ</p>
            <h1 class="mt-4 text-4xl font-black leading-tight sm:text-6xl">สมัครใช้งานในไม่กี่ขั้นตอน</h1>
            <p class="mt-5 max-w-xl leading-8 text-white/72">ลูกค้าค้นหาช่างภาพได้ทันที ส่วนช่างภาพจะสร้างโปรไฟล์เบื้องต้นและรอผู้ดูแลระบบอนุมัติก่อนแสดงในหน้าค้นหา</p>
            <div class="mt-7 grid gap-3 sm:grid-cols-3">
                <div class="stat-pill rounded-3xl p-4">
                    <i class="fa-solid fa-user-plus text-red-300"></i>
                    <p class="mt-2 text-sm font-black">สมัครฟรี</p>
                </div>
                <div class="stat-pill rounded-3xl p-4">
                    <i class="fa-solid fa-shield-halved text-red-300"></i>
                    <p class="mt-2 text-sm font-black">ปลอดภัย</p>
                </div>
                <div class="stat-pill rounded-3xl p-4">
                    <i class="fa-solid fa-ban text-red-300"></i>
                    <p class="mt-2 text-sm font-black">ไม่มีชำระเงินในเว็บ</p>
                </div>
            </div>
        </div>

        <form method="post" id="registerForm" class="glass-panel rounded-[2rem] p-4 text-neutral-950 sm:p-6" data-initial-role="<?= h($selectedAccountType) ?>">
            <?= csrf_field() ?>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-black uppercase tracking-[0.2em] text-red-600">Create Account</p>
                    <h2 class="mt-1 text-2xl font-black">เลือกประเภทบัญชี</h2>
                </div>
                <a href="/login.php" class="rounded-full border border-neutral-200 bg-white px-4 py-2 text-sm font-black hover:bg-neutral-950 hover:text-white">
                    <i class="fa-solid fa-right-to-bracket mr-1"></i>มีบัญชีแล้ว
                </a>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <label class="account-choice cursor-pointer rounded-[1.5rem] border-2 bg-white p-4 transition hover:-translate-y-1 hover:shadow-xl" data-account-card="customer">
                    <input class="sr-only" type="radio" name="account_type" value="customer" <?php if ($selectedAccountType === 'customer'): ?>checked<?php endif; ?>>
                    <span class="flex items-start gap-3">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-neutral-950 text-white"><i class="fa-solid fa-user"></i></span>
                        <span>
                            <b class="block text-lg">ลูกค้า</b>
                            <span class="mt-1 block text-sm font-semibold text-neutral-500">ค้นหา ส่งคำขอจอง และรีวิวช่างภาพ</span>
                        </span>
                    </span>
                </label>

                <label class="account-choice cursor-pointer rounded-[1.5rem] border-2 bg-white p-4 transition hover:-translate-y-1 hover:shadow-xl <?php if (!$allowPhotographer): ?>opacity-50<?php endif; ?>" data-account-card="photographer">
                    <input class="sr-only" type="radio" name="account_type" value="photographer" <?php if ($selectedAccountType === 'photographer'): ?>checked<?php endif; ?> <?php if (!$allowPhotographer): ?>disabled<?php endif; ?>>
                    <span class="flex items-start gap-3">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-red-600 text-white"><i class="fa-solid fa-camera"></i></span>
                        <span>
                            <b class="block text-lg">ช่างภาพ</b>
                            <span class="mt-1 block text-sm font-semibold text-neutral-500">สร้างโปรไฟล์ ตัวอย่างงานถ่ายภาพ และพื้นที่ให้บริการ</span>
                        </span>
                    </span>
                </label>
            </div>

            <?php if (!$allowPhotographer): ?>
                <div class="mt-4 rounded-2xl bg-amber-50 p-4 text-sm font-black text-amber-700">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i>ขณะนี้ยังไม่เปิดรับสมัครช่างภาพ
                </div>
            <?php endif; ?>

            <div class="mt-6 grid gap-5">
                <div class="rounded-[1.5rem] bg-neutral-50 p-4">
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-neutral-950 text-white text-sm"><i class="fa-solid fa-id-card"></i></span>
                        <h3 class="font-black">ข้อมูลบัญชี</h3>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-2 text-sm font-black text-neutral-700">
                            ชื่อ-นามสกุล
                            <span class="icon-input block"><i class="fa-solid fa-user"></i><input name="name" required placeholder="เช่น กานต์ เชียงราย" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></span>
                        </label>
                        <label class="grid gap-2 text-sm font-black text-neutral-700">
                            อีเมล
                            <span class="icon-input block"><i class="fa-solid fa-envelope"></i><input type="email" name="email" required placeholder="you@example.com" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></span>
                        </label>
                        <label class="grid gap-2 text-sm font-black text-neutral-700">
                            เบอร์โทร
                            <span class="icon-input block"><i class="fa-solid fa-phone"></i><input name="phone" required placeholder="08xxxxxxxx" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></span>
                        </label>
                        <div class="hidden sm:block"></div>
                        <label class="grid gap-2 text-sm font-black text-neutral-700">
                            รหัสผ่าน
                            <span class="icon-input block"><i class="fa-solid fa-lock"></i><input type="password" name="password" required minlength="8" placeholder="อย่างน้อย 8 ตัวอักษร" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></span>
                        </label>
                        <label class="grid gap-2 text-sm font-black text-neutral-700">
                            ยืนยันรหัสผ่าน
                            <span class="icon-input block"><i class="fa-solid fa-lock"></i><input type="password" name="password_confirmation" required minlength="8" placeholder="กรอกรหัสผ่านอีกครั้ง" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></span>
                        </label>
                    </div>
                </div>

                <div id="photographerFields" class="rounded-[1.5rem] bg-red-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-red-600 text-white text-sm"><i class="fa-solid fa-camera-retro"></i></span>
                            <h3 class="font-black">ข้อมูลเริ่มต้นสำหรับช่างภาพ</h3>
                        </div>
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-red-700"><i class="fa-solid fa-hourglass-half mr-1"></i>รอผู้ดูแลระบบอนุมัติ</span>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-2 text-sm font-black text-neutral-700">
                            ชื่อช่างภาพ / ชื่อทีม
                            <span class="icon-input block"><i class="fa-solid fa-camera"></i><input name="display_name" data-required-when-photographer placeholder="เช่น North Studio" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></span>
                        </label>
                        <label class="grid gap-2 text-sm font-black text-neutral-700">
                            LINE ID
                            <span class="icon-input block"><i class="fa-brands fa-line"></i><input name="line_id" placeholder="ช่องทางติดต่อหลัก" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></span>
                        </label>
                        <label class="grid gap-2 text-sm font-black text-neutral-700 sm:col-span-2">
                            อำเภอหลัก
                            <span class="icon-input block">
                                <i class="fa-solid fa-location-dot"></i>
                                <select name="main_district_id" data-required-when-photographer class="stock-input w-full rounded-2xl px-4 py-3 font-semibold">
                                    <option value="">เลือกอำเภอหลักที่รับงาน</option>
                                    <?php foreach ($districts as $district): ?>
                                        <option value="<?= (int)$district['id'] ?>"><?= h($district['district_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </span>
                        </label>
                    </div>

                    <div class="mt-4">
                        <p class="text-sm font-black text-neutral-700"><i class="fa-solid fa-layer-group mr-1 text-red-600"></i>ประเภทงานที่รับเบื้องต้น</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            <?php foreach ($categories as $category): ?>
                                <?php
                                $categoryIcon = 'fa-camera';
                                if (!empty($category['icon'])) {
                                    $categoryIcon = $category['icon'];
                                }
                                ?>
                                <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-white bg-white px-4 py-3 text-sm font-black text-neutral-700 shadow-sm hover:border-red-200">
                                    <input type="checkbox" name="category_ids[]" value="<?= (int)$category['id'] ?>">
                                    <i class="fa-solid <?= h($categoryIcon) ?> text-red-600"></i>
                                    <span><?= h($category['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-neutral-950 p-4 text-sm font-bold leading-7 text-white">
                    <i class="fa-solid fa-circle-info mr-2 text-red-300"></i><?= h(PAYMENT_DISCLAIMER) ?>
                </div>

                <button class="stock-button rounded-2xl px-5 py-4 text-base font-black">
                    <i class="fa-solid fa-user-plus mr-2"></i>สมัครสมาชิก
                </button>
            </div>
        </form>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registerForm');
    if (!form) return;

    const photographerFields = document.getElementById('photographerFields');
    const accountInputs = form.querySelectorAll('input[name="account_type"]');
    const accountCards = form.querySelectorAll('[data-account-card]');
    const photographerRequiredFields = form.querySelectorAll('[data-required-when-photographer]');

    function syncAccountUI() {
        let selected = 'customer';
        accountInputs.forEach(function (input) {
            if (input.checked) {
                selected = input.value;
            }
        });

        accountCards.forEach(function (card) {
            const isActive = card.getAttribute('data-account-card') === selected;
            card.classList.toggle('border-red-600', isActive);
            card.classList.toggle('shadow-2xl', isActive);
            card.classList.toggle('ring-4', isActive);
            card.classList.toggle('ring-red-100', isActive);
            card.classList.toggle('border-neutral-100', !isActive);
        });

        if (photographerFields) {
            photographerFields.classList.toggle('hidden', selected !== 'photographer');
        }

        photographerRequiredFields.forEach(function (field) {
            field.required = selected === 'photographer';
        });
    }

    accountInputs.forEach(function (input) {
        input.addEventListener('change', syncAccountUI);
    });

    syncAccountUI();
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect(user_workspace_path(current_user()));
}

if (is_post()) {
    verify_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        record_login_attempt($email, false);
        flash('error', 'รูปแบบอีเมลไม่ถูกต้อง');
        redirect('/login.php');
    }

    if (is_login_blocked($email)) {
        flash('error', 'พยายามเข้าสู่ระบบหลายครั้งเกินไป กรุณารอ 15 นาที');
        redirect('/login.php');
    }

    $stmt = db()->prepare('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ? AND u.deleted_at IS NULL LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['status'] !== 'suspended' && password_verify($password, $user['password'])) {
        record_login_attempt($email, true);
        clear_failed_login_attempts($email);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['last_activity_at'] = time();
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$user['id']]);
        log_activity('login', 'users', (int)$user['id'], 'User logged in');
        flash('success', 'เข้าสู่ระบบสำเร็จ');
        $intendedUrl = (string)($_SESSION['intended_url'] ?? '');
        unset($_SESSION['intended_url']);
        if ($intendedUrl !== '' && strpos($intendedUrl, '/login.php') !== 0) {
            redirect($intendedUrl);
        }
        redirect(user_workspace_path($user));
    }

    record_login_attempt($email, false);
    flash('error', 'อีเมลหรือรหัสผ่านไม่ถูกต้อง');
}

$pageTitle = 'เข้าสู่ระบบ';
include __DIR__ . '/includes/header.php';
$loginHeroImage = public_image('seed/photo-1519741497674-611481863552.jpg', '/assets/uploads/seed/photo-1519741497674-611481863552.jpg');
?>
<section class="mx-auto grid min-h-[calc(100vh-8rem)] w-full max-w-[1880px] items-center gap-6 px-4 py-10 sm:px-6 lg:grid-cols-[1.08fr_.92fr] lg:px-10">
    <div class="relative hidden min-h-[720px] overflow-hidden rounded-[2.25rem] bg-neutral-950 shadow-2xl shadow-neutral-950/15 lg:block">
        <img class="absolute inset-0 h-full w-full object-cover opacity-78" src="<?= h($loginHeroImage) ?>" alt="">
        <div class="absolute inset-0 bg-gradient-to-br from-neutral-950/80 via-neutral-950/28 to-red-900/72"></div>
        <div class="absolute inset-x-0 top-0 p-8">
            <a href="/index.php" class="inline-flex items-center gap-3 rounded-full border border-white/15 bg-white/12 px-5 py-3 text-sm font-black text-white backdrop-blur-xl transition hover:bg-white hover:text-neutral-950">
                <i class="fa-solid fa-camera-retro"></i>Chiang Rai Photo
            </a>
        </div>
        <div class="absolute inset-x-0 bottom-0 p-8">
            <p class="text-sm font-black uppercase tracking-[0.24em] text-red-200">ตลาดช่างภาพเชียงราย</p>
            <h1 class="mt-4 max-w-3xl text-5xl font-black leading-tight text-white">เข้าสู่พื้นที่จัดการงานถ่ายภาพของคุณ</h1>
            <p class="mt-5 max-w-2xl text-lg font-semibold leading-8 text-white/78">ดูคำขอจอง ติดตามสถานะ ตรวจแจ้งเตือน และจัดการโปรไฟล์ในที่เดียว</p>
            <div class="mt-8 grid max-w-3xl grid-cols-3 gap-3">
                <div class="rounded-[1.35rem] border border-white/14 bg-white/12 p-4 text-white backdrop-blur-xl">
                    <i class="fa-solid fa-calendar-check text-xl text-red-200"></i>
                    <p class="mt-3 text-2xl font-black">จอง</p>
                    <p class="text-sm font-bold text-white/65">ส่งคำขอได้เร็ว</p>
                </div>
                <div class="rounded-[1.35rem] border border-white/14 bg-white/12 p-4 text-white backdrop-blur-xl">
                    <i class="fa-solid fa-bell text-xl text-red-200"></i>
                    <p class="mt-3 text-2xl font-black">แจ้งเตือน</p>
                    <p class="text-sm font-bold text-white/65">ตามงานทันที</p>
                </div>
                <div class="rounded-[1.35rem] border border-white/14 bg-white/12 p-4 text-white backdrop-blur-xl">
                    <i class="fa-solid fa-shield-halved text-xl text-red-200"></i>
                    <p class="mt-3 text-2xl font-black">ปลอดภัย</p>
                    <p class="text-sm font-bold text-white/65">ป้องกัน session</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto w-full max-w-[620px]">
        <div class="stock-card rounded-[2rem] p-6 sm:p-8 lg:p-10">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="section-kicker">เข้าสู่ระบบ</p>
                    <h1 class="mt-2 text-3xl font-black text-neutral-950 sm:text-4xl">ยินดีต้อนรับกลับ</h1>
                    <p class="mt-2 text-sm font-bold leading-7 text-neutral-500">กรอกบัญชีของคุณเพื่อเข้าใช้งาน dashboard ตามบทบาท</p>
                </div>
                <span class="grid h-14 w-14 place-items-center rounded-2xl bg-red-50 text-xl text-red-600">
                    <i class="fa-solid fa-right-to-bracket"></i>
                </span>
            </div>

            <form method="post" class="mt-8 grid gap-4">
                <?= csrf_field() ?>
                <label class="grid gap-2 text-sm font-black text-neutral-700">
                    <span><i class="fa-solid fa-envelope mr-2 text-red-600"></i>อีเมล</span>
                    <span class="icon-input block">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" name="email" required autocomplete="email" placeholder="name@example.com" class="stock-input w-full rounded-2xl px-4 py-3.5 font-semibold">
                    </span>
                </label>
                <label class="grid gap-2 text-sm font-black text-neutral-700">
                    <span><i class="fa-solid fa-lock mr-2 text-red-600"></i>รหัสผ่าน</span>
                    <span class="relative block">
                        <span class="icon-input block">
                            <i class="fa-solid fa-lock"></i>
                            <input id="login-password" type="password" name="password" required autocomplete="current-password" placeholder="กรอกรหัสผ่าน" class="stock-input w-full rounded-2xl px-4 py-3.5 pr-14 font-semibold">
                        </span>
                        <button type="button" id="toggle-password" class="absolute right-2 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full text-neutral-500 transition hover:bg-neutral-100 hover:text-red-600" aria-label="แสดงหรือซ่อนรหัสผ่าน">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </span>
                </label>

                <div class="flex flex-wrap items-center justify-between gap-3 text-sm font-black">
                    <a class="text-red-600 hover:text-neutral-950" href="/register.php"><i class="fa-solid fa-user-plus mr-1"></i>สมัครสมาชิก</a>
                    <a class="text-neutral-500 hover:text-red-600" href="/forgot_password.php"><i class="fa-solid fa-key mr-1"></i>ลืมรหัสผ่าน</a>
                </div>

                <button class="mt-2 rounded-2xl bg-red-600 px-5 py-4 font-black text-white shadow-xl shadow-red-600/20 transition hover:-translate-y-0.5 hover:bg-neutral-950 hover:shadow-neutral-950/20">
                    <i class="fa-solid fa-right-to-bracket mr-2"></i>เข้าสู่ระบบ
                </button>
            </form>

        </div>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var passwordInput = document.getElementById('login-password');
    var toggleButton = document.getElementById('toggle-password');
    if (passwordInput && toggleButton) {
        toggleButton.addEventListener('click', function () {
            var shouldShow = passwordInput.type === 'password';
            passwordInput.type = shouldShow ? 'text' : 'password';
            toggleButton.innerHTML = '<i class="fa-solid ' + (shouldShow ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
        });
    }
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect(dashboard_path(current_user()['role_name']));
}

if (is_post()) {
    verify_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ? AND u.deleted_at IS NULL LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['status'] !== 'suspended' && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$user['id']]);
        log_activity('login', 'users', (int)$user['id'], 'User logged in');
        flash('success', 'เข้าสู่ระบบสำเร็จ');
        redirect(dashboard_path($user['role_name']));
    }
    flash('error', 'อีเมลหรือรหัสผ่านไม่ถูกต้อง');
}

$pageTitle = 'เข้าสู่ระบบ';
include __DIR__ . '/includes/header.php';
?>
<section class="mx-auto max-w-md px-4 py-12">
    <div class="rounded-3xl bg-white p-8 shadow-xl ring-1 ring-slate-200">
        <h1 class="text-2xl font-extrabold">เข้าสู่ระบบ</h1>
        <form method="post" class="mt-6 grid gap-4">
            <?= csrf_field() ?>
            <input type="email" name="email" required placeholder="อีเมล" class="rounded-2xl border px-4 py-3">
            <input type="password" name="password" required placeholder="รหัสผ่าน" class="rounded-2xl border px-4 py-3">
            <button class="rounded-2xl bg-indigo-600 px-5 py-3 font-bold text-white">เข้าสู่ระบบ</button>
        </form>
        <div class="mt-5 flex justify-between text-sm font-semibold">
            <a class="text-indigo-600" href="/register.php">สมัครสมาชิก</a>
            <a class="text-slate-600" href="/forgot_password.php">ลืมรหัสผ่าน</a>
        </div>
        <p class="mt-5 rounded-2xl bg-slate-50 p-4 text-xs text-slate-600">Demo: admin@example.com, customer@example.com, northstudio@example.com / password: password</p>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>


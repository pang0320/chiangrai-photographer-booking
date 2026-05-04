<?php
require_once __DIR__ . '/includes/functions.php';
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$stmt = db()->prepare('SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1');
$stmt->execute([$token]);
$reset = $stmt->fetch();
if (!$reset) {
    http_response_code(404);
    exit('Reset token invalid or expired');
}
if (is_post()) {
    verify_csrf();
    $password = (string)($_POST['password'] ?? '');
    if (mb_strlen($password) < 8 || $password !== (string)($_POST['password_confirmation'] ?? '')) {
        flash('error', 'รหัสผ่านไม่ถูกต้อง');
        redirect('/reset_password.php?token=' . urlencode($token));
    }
    db()->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE email = ? AND deleted_at IS NULL')->execute([password_hash($password, PASSWORD_DEFAULT), $reset['email']]);
    db()->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$reset['email']]);
    flash('success', 'ตั้งรหัสผ่านใหม่สำเร็จ');
    redirect('/login.php');
}
$pageTitle = 'ตั้งรหัสผ่านใหม่';
include __DIR__ . '/includes/header.php';
?>
<section class="mx-auto max-w-md px-4 py-12">
    <div class="rounded-3xl bg-white p-8 shadow-xl ring-1 ring-slate-200">
        <h1 class="text-2xl font-extrabold">ตั้งรหัสผ่านใหม่</h1>
        <form method="post" class="mt-6 grid gap-4">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <input type="password" name="password" required minlength="8" placeholder="รหัสผ่านใหม่" class="rounded-2xl border px-4 py-3">
            <input type="password" name="password_confirmation" required minlength="8" placeholder="ยืนยันรหัสผ่าน" class="rounded-2xl border px-4 py-3">
            <button class="rounded-2xl bg-indigo-600 px-5 py-3 font-bold text-white">บันทึกรหัสผ่าน</button>
        </form>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>


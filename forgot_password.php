<?php
require_once __DIR__ . '/includes/functions.php';
$resetLink = '';
if (is_post()) {
    verify_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $token = bin2hex(random_bytes(32));
        db()->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);
        db()->prepare('INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())')->execute([$email, $token]);
        $resetLink = $token;
        flash('success', 'สร้างลิงก์รีเซ็ตรหัสผ่านแล้ว');
    }
}
$pageTitle = 'ลืมรหัสผ่าน';
include __DIR__ . '/includes/header.php';
?>
<section class="mx-auto max-w-md px-4 py-12">
    <div class="rounded-3xl bg-white p-8 shadow-xl ring-1 ring-slate-200">
        <h1 class="text-2xl font-extrabold">ลืมรหัสผ่าน</h1>
        <form method="post" class="mt-6 grid gap-4">
            <?= csrf_field() ?>
            <input type="email" name="email" required placeholder="อีเมล" class="rounded-2xl border px-4 py-3">
            <button class="rounded-2xl bg-indigo-600 px-5 py-3 font-bold text-white"><i class="fa-solid fa-key mr-2"></i>สร้างลิงก์รีเซ็ต</button>
        </form>
        <?php if ($resetLink): ?>
            <p class="mt-5 break-all rounded-2xl bg-slate-50 p-4 text-sm">
                Development reset:
                <?= clean_context_button('/reset_password.php', ['token' => $resetLink], '<i class="fa-solid fa-key mr-2"></i>เปิดหน้าตั้งรหัสผ่านใหม่', 'mt-3 rounded-full bg-red-600 px-4 py-2 font-black text-white hover:bg-neutral-950') ?>
            </p>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

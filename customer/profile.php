<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('customer');
$user = current_user();

if (is_post()) {
    verify_csrf();
    try {
        $avatar = upload_image($_FILES['avatar'] ?? [], 'avatars') ?: $user['avatar'];
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('ข้อมูลไม่ถูกต้อง');
        }
        if (!empty($_POST['password'])) {
            if (mb_strlen((string)$_POST['password']) < 8 || $_POST['password'] !== ($_POST['password_confirmation'] ?? '')) {
                throw new RuntimeException('รหัสผ่านไม่ถูกต้อง');
            }
            db()->prepare('UPDATE users SET name=?, email=?, phone=?, avatar=?, password=?, updated_at=NOW() WHERE id=?')->execute([$name, $email, $phone, $avatar, password_hash((string)$_POST['password'], PASSWORD_DEFAULT), (int)$user['id']]);
        } else {
            db()->prepare('UPDATE users SET name=?, email=?, phone=?, avatar=?, updated_at=NOW() WHERE id=?')->execute([$name, $email, $phone, $avatar, (int)$user['id']]);
        }
        log_activity('update_profile', 'users', (int)$user['id']);
        flash('success', 'บันทึกโปรไฟล์แล้ว');
        redirect('/customer/profile.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
}

$pageTitle = 'โปรไฟล์ลูกค้า';
include __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-3xl px-4 py-10">
    <div class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
        <h1 class="text-2xl font-extrabold">โปรไฟล์ลูกค้า</h1>
        <form method="post" enctype="multipart/form-data" class="mt-6 grid gap-4">
            <?= csrf_field() ?>
            <input name="name" value="<?= h($user['name']) ?>" required class="rounded-2xl border px-4 py-3">
            <input type="email" name="email" value="<?= h($user['email']) ?>" required class="rounded-2xl border px-4 py-3">
            <input name="phone" value="<?= h($user['phone']) ?>" class="rounded-2xl border px-4 py-3">
            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="rounded-2xl border px-4 py-3">
            <div class="grid gap-4 sm:grid-cols-2">
                <input type="password" name="password" placeholder="รหัสผ่านใหม่ (ไม่บังคับ)" class="rounded-2xl border px-4 py-3">
                <input type="password" name="password_confirmation" placeholder="ยืนยันรหัสผ่าน" class="rounded-2xl border px-4 py-3">
            </div>
            <button class="rounded-2xl bg-indigo-600 px-5 py-3 font-bold text-white">บันทึก</button>
        </form>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>


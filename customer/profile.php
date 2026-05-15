<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('customer');
$user = current_user();

if (is_post()) {
    verify_csrf();
    try {
        $avatarFile = [];
        if (isset($_FILES['avatar'])) {
            $avatarFile = $_FILES['avatar'];
        }
        $avatar = upload_image($avatarFile, 'avatars');
        if (!$avatar) {
            $avatar = $user['avatar'];
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
        if ($name === '') {
            throw new RuntimeException('กรุณากรอกชื่อผู้ใช้');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('รูปแบบอีเมลไม่ถูกต้อง');
        }
        if (!empty($_POST['password'])) {
            $passwordConfirmation = '';
            if (isset($_POST['password_confirmation'])) {
                $passwordConfirmation = (string)$_POST['password_confirmation'];
            }
            if (mb_strlen((string)$_POST['password']) < 8 || $_POST['password'] !== $passwordConfirmation) {
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
<section class="mx-auto max-w-5xl px-4 py-10">
    <div class="rounded-[2rem] bg-gradient-to-r from-neutral-950 via-slate-900 to-red-700 p-7 text-white shadow-xl">
        <div class="flex flex-wrap items-center gap-5">
            <img id="avatar-preview" class="h-24 w-24 rounded-[1.6rem] object-cover ring-4 ring-white/20" src="<?= h(public_image($user['avatar'], '/assets/uploads/seed/photo-1494790108377-be9c29b29330.jpg')) ?>" alt="<?= h($user['name']) ?>">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-red-200"><i class="fa-solid fa-user mr-2"></i>โปรไฟล์ลูกค้า</p>
                <h1 class="mt-1 text-3xl font-black"><?= h($user['name']) ?></h1>
                <p class="mt-2 text-sm font-semibold text-white/70">แก้ไขข้อมูลติดต่อและรูปโปรไฟล์ที่ใช้แสดงในรีวิว/บัญชีของคุณ</p>
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
        <form method="post" enctype="multipart/form-data" class="grid gap-5">
            <?= csrf_field() ?>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    <span><i class="fa-solid fa-user mr-2 text-red-600"></i>ชื่อผู้ใช้ <?= required_mark() ?></span>
                    <input name="name" value="<?= h($user['name']) ?>" required class="rounded-2xl border px-4 py-3 font-semibold">
                </label>
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    <span><i class="fa-solid fa-envelope mr-2 text-red-600"></i>อีเมล <?= required_mark() ?></span>
                    <input type="email" name="email" value="<?= h($user['email']) ?>" required class="rounded-2xl border px-4 py-3 font-semibold">
                </label>
                <label class="grid gap-2 text-sm font-black text-slate-700 sm:col-span-2">
                    <span><i class="fa-solid fa-phone mr-2 text-red-600"></i>เบอร์โทรศัพท์</span>
                    <input name="phone" value="<?= h($user['phone']) ?>" class="rounded-2xl border px-4 py-3 font-semibold">
                </label>
            </div>

            <label class="grid gap-3 rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50 p-5">
                <span class="font-black text-slate-800"><i class="fa-solid fa-image mr-2 text-red-600"></i>อัปโหลดรูปโปรไฟล์ลูกค้า</span>
                <span class="text-sm font-semibold text-slate-500"><?= h(UPLOAD_IMAGE_HELP_TEXT) ?></span>
                <input id="avatar-input" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="rounded-2xl border bg-white px-4 py-3 font-semibold">
            </label>

            <div class="rounded-[1.5rem] bg-slate-50 p-5">
                <h2 class="text-lg font-black text-slate-900"><i class="fa-solid fa-lock mr-2 text-red-600"></i>เปลี่ยนรหัสผ่าน</h2>
                <p class="mt-1 text-sm font-bold text-slate-500">เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    <span><i class="fa-solid fa-lock mr-2 text-red-600"></i>รหัสผ่านใหม่</span>
                    <input type="password" name="password" placeholder="อย่างน้อย 8 ตัวอักษร" class="rounded-2xl border px-4 py-3 font-semibold">
                </label>
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    <span><i class="fa-solid fa-key mr-2 text-red-600"></i>ยืนยันรหัสผ่าน</span>
                    <input type="password" name="password_confirmation" placeholder="กรอกซ้ำอีกครั้ง" class="rounded-2xl border px-4 py-3 font-semibold">
                </label>
                </div>
            </div>
            <button class="rounded-2xl bg-neutral-950 px-5 py-3 font-black text-white transition hover:bg-red-600"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกโปรไฟล์</button>
        </form>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('avatar-input');
    var preview = document.getElementById('avatar-preview');
    if (!input || !preview) return;
    input.addEventListener('change', function () {
        if (!input.files || !input.files[0]) return;
        preview.src = URL.createObjectURL(input.files[0]);
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/functions.php';

function password_policy_errors(string $password, string $confirmation): array
{
    $errors = [];

    if (text_length($password) < 8) {
        $errors['length'] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors['lower'] = 'ต้องมีตัวอักษรพิมพ์เล็กอย่างน้อย 1 ตัว';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors['upper'] = 'ต้องมีตัวอักษรพิมพ์ใหญ่อย่างน้อย 1 ตัว';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors['number'] = 'ต้องมีตัวเลขอย่างน้อย 1 ตัว';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors['special'] = 'ต้องมีอักขระพิเศษอย่างน้อย 1 ตัว เช่น ! @ #';
    }

    if ($password === '' || $confirmation === '' || $password !== $confirmation) {
        $errors['match'] = 'รหัสผ่านและช่องยืนยันต้องตรงกัน';
    }

    return $errors;
}

$cleanContext = clean_context_init(['token']);
$token = (string)clean_context_value($cleanContext, 'token', ($_POST['token'] ?? ''));
ensure_password_resets_audit_columns();
$stmt = db()->prepare('SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used_at IS NULL AND invalidated_at IS NULL LIMIT 1');
$stmt->execute([$token]);
$reset = $stmt->fetch();
$passwordErrors = [];

if (!$reset) {
    http_response_code(404);
    $pageTitle = 'ลิงก์หมดอายุ';
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-xl stock-card rounded-[2rem] p-8 text-center">
            <div class="mx-auto grid h-16 w-16 place-items-center rounded-3xl bg-red-50 text-3xl text-red-600">
                <i class="fa-solid fa-link-slash"></i>
            </div>
            <h1 class="mt-4 text-2xl font-black text-neutral-950">ลิงก์ตั้งรหัสผ่านใหม่ไม่ถูกต้องหรือหมดอายุ</h1>
            <p class="mt-3 text-sm font-bold leading-7 text-neutral-600">ลิงก์ตั้งรหัสผ่านใหม่ใช้ได้ครั้งเดียวและหมดอายุภายใน 1 ชั่วโมง กรุณาขอลิงก์ใหม่อีกครั้ง</p>
            <a href="/forgot_password.php" class="btn-cta btn-lg mt-6">
                <i class="fa-solid fa-paper-plane"></i>ขอลิงก์ใหม่
            </a>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

if (is_post()) {
    verify_csrf();
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');
    $passwordErrors = password_policy_errors($password, $confirmation);

    if ($passwordErrors) {
        flash('error', 'รหัสผ่านยังไม่ผ่านเงื่อนไข กรุณาตรวจรายการที่ขาด');
    } else {
        db()->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE email = ? AND deleted_at IS NULL')->execute([password_hash($password, PASSWORD_DEFAULT), $reset['email']]);
        db()->prepare('UPDATE password_resets
                       SET used_at = NOW(),
                           used_ip = ?,
                           used_user_agent = ?
                       WHERE id = ?')->execute([
            client_ip(),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            (int)$reset['id'],
        ]);
        log_activity('reset_password', 'users', isset($reset['user_id']) ? (int)$reset['user_id'] : null, 'email=' . (string)$reset['email']);
        flash('success', 'ตั้งรหัสผ่านใหม่สำเร็จ');
        redirect('/login.php');
    }
}

$pageTitle = 'ตั้งรหัสผ่านใหม่';
include __DIR__ . '/includes/header.php';
?>
<section class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl">
        <div class="dashboard-hero rounded-[2rem] p-6 text-white md:p-8">
            <p class="text-sm font-black uppercase tracking-[0.22em] text-white/60"><i class="fa-solid fa-key mr-2"></i>Reset Password</p>
            <h1 class="mt-2 text-3xl font-black">ตั้งรหัสผ่านใหม่</h1>
            <p class="mt-3 text-sm font-bold leading-7 text-white/75">ตั้งรหัสผ่านใหม่ให้เดายากขึ้น ระบบจะแสดงทันทีว่ายังขาดเงื่อนไขอะไรบ้าง</p>
        </div>

        <div class="stock-card mt-6 rounded-[2rem] p-6">
            <form method="post" class="grid gap-5" novalidate data-password-reset-form>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= h($token) ?>">

                <label class="grid gap-2 text-sm font-black text-neutral-700">
                    <span><i class="fa-solid fa-lock mr-2 text-red-600"></i>รหัสผ่านใหม่ <?= required_mark() ?></span>
                    <input
                        type="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="อย่างน้อย 8 ตัว มี A-Z, a-z, ตัวเลข และอักขระพิเศษ"
                        class="stock-input rounded-2xl px-4 py-3 font-semibold <?php if ($passwordErrors): ?>border-red-300 bg-red-50 ring-2 ring-red-100<?php endif; ?>"
                        data-password-input>
                </label>

                <label class="grid gap-2 text-sm font-black text-neutral-700">
                    <span><i class="fa-solid fa-circle-check mr-2 text-red-600"></i>ยืนยันรหัสผ่าน <?= required_mark() ?></span>
                    <input
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="พิมพ์รหัสผ่านใหม่อีกครั้ง"
                        class="stock-input rounded-2xl px-4 py-3 font-semibold <?php if (isset($passwordErrors['match'])): ?>border-red-300 bg-red-50 ring-2 ring-red-100<?php endif; ?>"
                        data-password-confirmation>
                </label>

                <div class="rounded-[1.5rem] bg-neutral-50 p-4">
                    <p class="text-sm font-black text-neutral-950"><i class="fa-solid fa-list-check mr-2 text-red-600"></i>รหัสผ่านต้องมี</p>
                    <div class="mt-3 grid gap-2 text-sm font-bold" data-password-checklist>
                        <p data-rule="length"><i class="fa-solid fa-circle-xmark mr-2"></i>อย่างน้อย 8 ตัวอักษร</p>
                        <p data-rule="lower"><i class="fa-solid fa-circle-xmark mr-2"></i>ตัวอักษรพิมพ์เล็ก a-z</p>
                        <p data-rule="upper"><i class="fa-solid fa-circle-xmark mr-2"></i>ตัวอักษรพิมพ์ใหญ่ A-Z</p>
                        <p data-rule="number"><i class="fa-solid fa-circle-xmark mr-2"></i>ตัวเลข 0-9</p>
                        <p data-rule="special"><i class="fa-solid fa-circle-xmark mr-2"></i>อักขระพิเศษ เช่น ! @ #</p>
                        <p data-rule="match"><i class="fa-solid fa-circle-xmark mr-2"></i>ช่องยืนยันรหัสผ่านตรงกัน</p>
                    </div>
                </div>

                <?php if ($passwordErrors): ?>
                    <div class="rounded-[1.5rem] border border-red-200 bg-red-50 p-4 text-sm font-bold leading-7 text-red-700">
                        <p class="font-black"><i class="fa-solid fa-circle-exclamation mr-2"></i>ยังขาดเงื่อนไขต่อไปนี้</p>
                        <ul class="mt-2 list-inside list-disc">
                            <?php foreach ($passwordErrors as $message): ?>
                                <li><?= h($message) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <button class="btn-cta btn-lg rounded-2xl">
                    <i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกรหัสผ่านใหม่
                </button>
            </form>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-password-reset-form]');
    if (!form) {
        return;
    }

    var password = form.querySelector('[data-password-input]');
    var confirmation = form.querySelector('[data-password-confirmation]');
    var rules = form.querySelectorAll('[data-password-checklist] [data-rule]');

    function setRule(name, passed) {
        rules.forEach(function (rule) {
            if (rule.getAttribute('data-rule') !== name) {
                return;
            }

            rule.classList.remove('text-emerald-700', 'text-red-600');
            rule.classList.add(passed ? 'text-emerald-700' : 'text-red-600');

            var icon = rule.querySelector('i');
            if (icon) {
                icon.className = 'fa-solid mr-2 ' + (passed ? 'fa-circle-check' : 'fa-circle-xmark');
            }
        });
    }

    function refreshChecklist() {
        var value = password.value || '';
        var confirmValue = confirmation.value || '';

        setRule('length', value.length >= 8);
        setRule('lower', /[a-z]/.test(value));
        setRule('upper', /[A-Z]/.test(value));
        setRule('number', /[0-9]/.test(value));
        setRule('special', /[^A-Za-z0-9]/.test(value));
        setRule('match', value !== '' && confirmValue !== '' && value === confirmValue);
    }

    password.addEventListener('input', refreshChecklist);
    confirmation.addEventListener('input', refreshChecklist);
    refreshChecklist();
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

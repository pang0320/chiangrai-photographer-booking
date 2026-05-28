<?php
require_once __DIR__ . '/includes/functions.php';

/**
 * สร้าง URL สำหรับการตั้งรหัสผ่านใหม่ (Password Reset)
 *
 * @param string $token โทเค็นความปลอดภัยที่ใช้ยืนยันตัวตน
 * @return string URL สมบูรณ์สำหรับหน้าตั้งรหัสผ่านใหม่
 */
function password_reset_url(string $token): string
{
    return rtrim(APP_URL, '/') . '/reset_password.php?token=' . rawurlencode($token);
}

/**
 * ส่งอีเมลแจ้งลิงก์ตั้งรหัสผ่านใหม่ไปยังอีเมลของผู้ใช้
 *
 * @param string $email อีเมลผู้รับ
 * @param string $resetUrl URL สำหรับตั้งรหัสผ่านใหม่
 * @return bool คืนค่า true หากส่งอีเมลสำเร็จ (ผ่านฟังก์ชัน mail ของ PHP)
 */
function send_password_reset_email(string $email, string $resetUrl): bool
{
    $siteName = setting('site_name', APP_NAME);
    $subject = 'ตั้งรหัสผ่านใหม่ - ' . $siteName;
    $message = "สวัสดีครับ/ค่ะ\n\n";
    $message .= "มีการขอตั้งรหัสผ่านใหม่สำหรับบัญชีของคุณในระบบ " . $siteName . "\n";
    $message .= "กดลิงก์นี้เพื่อตั้งรหัสผ่านใหม่ภายใน 1 ชั่วโมง:\n";
    $message .= $resetUrl . "\n\n";
    $message .= "ถ้าคุณไม่ได้เป็นคนขอ สามารถละเว้นอีเมลนี้ได้ รหัสผ่านเดิมจะยังใช้ได้ตามปกติ\n";
    $message .= "ระบบจะไม่เปิดเผยว่ามีบัญชีอีเมลนี้อยู่หรือไม่ในหน้าเว็บ เพื่อป้องกันการเดาบัญชีผู้ใช้\n";

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $siteName . ' <' . setting('admin_email', 'admin@example.com') . '>',
    ];

    if (!function_exists('mail')) {
        return false;
    }

    return @mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $message, implode("\r\n", $headers));
}

/**
 * บันทึกข้อมูลอีเมลจำลองลงในไฟล์ (ใช้สำหรับการพัฒนาเมื่อไม่มีระบบ SMTP)
 *
 * @param string $email อีเมลผู้รับ
 * @param string $resetUrl URL สำหรับตั้งรหัสผ่านใหม่
 * @return string เส้นทางไฟล์ที่บันทึกข้อมูลอีเมล
 */
function write_password_reset_dev_mail(string $email, string $resetUrl): string
{
    $dir = __DIR__ . '/storage/mail_logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $file = $dir . '/password_reset_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.txt';
    $content = "TO: " . $email . "\n";
    $content .= "SUBJECT: ตั้งรหัสผ่านใหม่\n\n";
    $content .= $resetUrl . "\n";
    file_put_contents($file, $content);

    return $file;
}

$email = '';
$devResetUrl = '';
$devMailFile = '';
$emailError = '';

if (is_post()) {
    verify_csrf();
    $email = trim((string)($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailError = 'กรุณากรอกอีเมลให้ถูกต้อง';
        flash('error', $emailError);
    } else {
        $stmt = db()->prepare('SELECT id, email FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            ensure_password_resets_audit_columns();
            $email = (string)$user['email'];
            $userId = (int)$user['id'];
            $token = bin2hex(random_bytes(32));
            db()->prepare('UPDATE password_resets
                           SET invalidated_at = NOW()
                           WHERE email = ?
                             AND used_at IS NULL
                             AND invalidated_at IS NULL
                             AND expires_at > NOW()')->execute([$email]);
            db()->prepare('INSERT INTO password_resets (email, user_id, token, expires_at, requested_ip, requested_user_agent, created_at)
                           VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), ?, ?, NOW())')->execute([
                $email,
                $userId,
                $token,
                client_ip(),
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
            $resetUrl = password_reset_url($token);

            if (!send_password_reset_email($email, $resetUrl)) {
                $devMailFile = write_password_reset_dev_mail($email, $resetUrl);
                if (strpos(APP_URL, 'localhost') !== false || strpos(APP_URL, '127.0.0.1') !== false) {
                    $devResetUrl = $resetUrl;
                }
            }

            log_activity('request_password_reset', 'users', $userId);
        }

        flash('success', 'ระบบได้ส่งลิงก์สำหรับรีเซ็ตรหัสผ่านไปยังอีเมลของคุณแล้ว (หากไม่พบ กรุณาตรวจสอบในกล่องจดหมายขยะ)');
    }
}

$pageTitle = 'ลืมรหัสผ่าน';
include __DIR__ . '/includes/header.php';
?>
<section class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl">
        <div class="dashboard-hero rounded-[2rem] p-6 text-white md:p-8">
            <p class="text-sm font-black uppercase tracking-[0.22em] text-white/60"><i class="fa-solid fa-shield-halved mr-2"></i>ความปลอดภัยบัญชี</p>
            <h1 class="mt-2 text-3xl font-black">ลืมรหัสผ่าน</h1>
            <p class="mt-3 text-sm font-bold leading-7 text-white/75">ระบบจะส่งลิงก์ตั้งรหัสผ่านใหม่ไปยังอีเมลที่ลงทะเบียนไว้เท่านั้น และลิงก์ใช้ได้ครั้งเดียวภายใน 1 ชั่วโมง</p>
        </div>

        <div class="stock-card mt-6 rounded-[2rem] p-6">
            <div class="rounded-[1.5rem] bg-sky-50 p-4 text-sm font-bold leading-7 text-sky-800">
                <i class="fa-solid fa-circle-info mr-2"></i>
                เพื่อป้องกันการเดาบัญชี ระบบจะแสดงข้อความเหมือนกันเสมอ ไม่ว่าอีเมลนั้นจะมีอยู่ในระบบหรือไม่ เจ้าของตัวจริงต้องเข้าถึงกล่องอีเมลนั้นเพื่อกดลิงก์ตั้งรหัสผ่านใหม่
            </div>

            <form method="post" class="mt-6 grid gap-4" novalidate>
                <?= csrf_field() ?>
                <label class="grid gap-2 text-sm font-black text-neutral-700">
                    <span><i class="fa-solid fa-envelope mr-2 text-red-600"></i>อีเมลที่ใช้สมัคร <?= required_mark() ?></span>
                    <input type="email" name="email" value="<?= h($email) ?>" required placeholder="you@example.com" class="stock-input rounded-2xl px-4 py-3 font-semibold <?php if ($emailError !== ''): ?>border-red-300 bg-red-50 ring-2 ring-red-100<?php endif; ?>">
                    <?php if ($emailError !== ''): ?>
                        <span class="text-sm font-black text-red-600"><i class="fa-solid fa-circle-exclamation mr-1"></i><?= h($emailError) ?></span>
                    <?php endif; ?>
                </label>

                <button class="btn-cta btn-lg rounded-2xl"><i class="fa-solid fa-paper-plane mr-2"></i>ส่งลิงก์ตั้งรหัสผ่านใหม่</button>
            </form>

            <?php if ($devResetUrl !== ''): ?>
                <div class="mt-5 rounded-[1.5rem] border border-amber-200 bg-amber-50 p-4 text-sm font-bold leading-7 text-amber-800">
                    <p class="font-black"><i class="fa-solid fa-code mr-2"></i>Development mode</p>
                    <p>เครื่อง local ยังไม่ได้ตั้ง SMTP ระบบจึงบันทึกเมลจำลองไว้ที่:</p>
                    <?php if ($devMailFile !== ''): ?>
                        <p class="break-all text-xs"><?= h($devMailFile) ?></p>
                    <?php endif; ?>
                    <a href="<?= h($devResetUrl) ?>" class="btn-primary btn-md mt-3">
                        <i class="fa-solid fa-key"></i>เปิดลิงก์ตั้งรหัสผ่านใหม่
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

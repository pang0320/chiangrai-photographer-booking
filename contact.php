<?php
require_once __DIR__ . '/includes/functions.php';

if (is_post()) {
    verify_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $subject === '' || $message === '') {
        flash('error', 'กรุณากรอกข้อมูลให้ครบ');
        redirect('/contact.php');
    }

    $stmt = db()->prepare('INSERT INTO contact_messages (name, email, phone, subject, message, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, "unread", NOW(), NOW())');
    $stmt->execute([$name, $email, $phone, $subject, $message]);
    log_activity('send_contact_message', 'contact_messages', (int)db()->lastInsertId());
    flash('success', 'ส่งข้อความถึงผู้ดูแลระบบแล้ว');
    redirect('/contact.php');
}

$pageTitle = 'ติดต่อเรา';
include __DIR__ . '/includes/header.php';
?>
<section class="stock-shell grid gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[.9fr_1.1fr] lg:px-8">
    <div class="dashboard-hero rounded-[2rem] p-8 text-white">
        <p class="section-kicker text-red-300">Contact Us</p>
        <h1 class="mt-3 text-4xl font-black">ติดต่อผู้ดูแลระบบ</h1>
        <p class="mt-4 leading-8 text-white/70">สอบถามการใช้งาน แจ้งปัญหา หรือเสนอแนะระบบค้นหาช่างภาพเชียงราย</p>
        <div class="mt-8 grid gap-3 text-sm font-bold">
            <p><i class="fa-solid fa-envelope mr-2 text-red-300"></i><?= h(setting('admin_email', 'admin@example.com')) ?></p>
            <p><i class="fa-solid fa-phone mr-2 text-red-300"></i><?= h(setting('admin_phone', '')) ?></p>
            <p><i class="fa-solid fa-circle-info mr-2 text-red-300"></i><?= h(PAYMENT_DISCLAIMER) ?></p>
        </div>
    </div>
    <form method="post" class="stock-card grid gap-4 rounded-[2rem] p-6">
        <?= csrf_field() ?>
        <label class="icon-input block"><i class="fa-solid fa-user"></i><input name="name" required placeholder="ชื่อ" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></label>
        <label class="icon-input block"><i class="fa-solid fa-envelope"></i><input type="email" name="email" required placeholder="อีเมล" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></label>
        <label class="icon-input block"><i class="fa-solid fa-phone"></i><input name="phone" placeholder="เบอร์โทร" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></label>
        <label class="icon-input block"><i class="fa-solid fa-heading"></i><input name="subject" required placeholder="หัวข้อ" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></label>
        <textarea name="message" required rows="6" placeholder="ข้อความ" class="stock-input rounded-2xl px-4 py-3 font-semibold"></textarea>
        <button class="stock-button rounded-full px-5 py-3 font-black"><i class="fa-solid fa-paper-plane mr-2"></i>ส่งข้อความ</button>
    </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

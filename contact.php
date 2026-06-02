<?php
require_once __DIR__ . '/includes/functions.php';

$contactSiteName = setting('site_name', APP_NAME);
$contactAdminEmail = setting('admin_email', 'admin@example.com');
$contactAdminPhone = setting('admin_phone', '099-4344335');
$contactAdminPhoneHref = preg_replace('/[^0-9+]/', '', $contactAdminPhone);
if ($contactAdminPhoneHref === '') {
    $contactAdminPhoneHref = '0994344335';
}

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

$pageTitle = 'ติดต่อเว็บไซต์';
include __DIR__ . '/includes/header.php';
?>
<section class="stock-shell grid gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[.9fr_1.1fr] lg:px-8">
    <div class="dashboard-hero rounded-[2rem] p-8 text-white">
        <p class="section-kicker text-red-300">ติดต่อเว็บไซต์</p>
        <h1 class="mt-3 text-4xl font-black">ติดต่อผู้ดูแลระบบ</h1>
        <p class="mt-4 leading-8 text-white/70">สอบถามการใช้งาน แจ้งปัญหา เสนอแนะระบบ หรือสนใจติดต่องานกับผู้พัฒนา</p>

        <div class="mt-8 rounded-[1.5rem] border border-white/15 bg-white/10 p-5">
            <p class="text-xs font-black uppercase tracking-[0.22em] text-red-300">ผู้ดูแลและผู้พัฒนา</p>
            <h2 class="mt-2 text-2xl font-black">Creepygame / Game</h2>
            <p class="mt-2 text-sm font-bold text-white/70"><i class="fa-solid fa-camera-retro mr-2 text-red-300"></i>ช่างภาพจากเชียงราย / ประเทศไทย</p>
            <p class="mt-3 leading-7 text-white/70">สนใจติดต่องาน ทักแชท หรือโทรตามเบอร์ด้านล่างได้เลยครับ</p>
        </div>

        <div class="mt-8 grid gap-3 sm:grid-cols-2">
            <a href="tel:<?= h($contactAdminPhoneHref) ?>" class="rounded-2xl bg-white px-4 py-4 font-black text-neutral-950 transition hover:bg-red-600 hover:text-white">
                <i class="fa-solid fa-phone mr-2"></i>โทร <?= h($contactAdminPhone) ?>
            </a>
            <button type="button" data-developer-modal-open class="rounded-2xl bg-white/12 px-4 py-4 text-left font-black text-white transition hover:bg-white hover:text-neutral-950">
                <i class="fa-solid fa-circle-info mr-2"></i>ดูข้อมูลผู้พัฒนา
            </button>
        </div>

        <div class="mt-6 rounded-[1.25rem] bg-white/10 p-4 text-sm font-bold leading-7 text-white/68">
            <i class="fa-solid fa-circle-info mr-2 text-red-300"></i><?= h(PAYMENT_DISCLAIMER) ?>
        </div>
    </div>
    <div class="grid gap-5">
        <form method="post" class="stock-card grid gap-4 rounded-[2rem] p-6">
            <?= csrf_field() ?>
            <div>
                <p class="section-kicker">ติดต่อเว็บไซต์</p>
                <h2 class="mt-2 text-2xl font-black text-neutral-950"><i class="fa-solid fa-paper-plane mr-2 text-red-600"></i>ส่งข้อความถึงผู้ดูแลระบบ</h2>
            </div>
            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-user mr-2 text-red-600"></i>ชื่อ <?= required_mark() ?></span>
                <input name="name" required placeholder="ชื่อ" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold">
            </label>
            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-envelope mr-2 text-red-600"></i>อีเมล <?= required_mark() ?></span>
                <input type="email" name="email" required placeholder="อีเมล" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold">
            </label>
            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-phone mr-2 text-red-600"></i>เบอร์โทร</span>
                <input name="phone" placeholder="เบอร์โทร" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold">
            </label>
            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-heading mr-2 text-red-600"></i>หัวข้อ <?= required_mark() ?></span>
                <input name="subject" required placeholder="หัวข้อ" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold">
            </label>
            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-message mr-2 text-red-600"></i>ข้อความ <?= required_mark() ?></span>
                <textarea name="message" required rows="6" placeholder="ข้อความ" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></textarea>
            </label>
            <button class="stock-button rounded-full px-5 py-3 font-black"><i class="fa-solid fa-paper-plane mr-2"></i>ส่งข้อความ</button>
        </form>

        <div class="stock-card rounded-[2rem] p-6">
            <p class="section-kicker">ติดต่อผู้ดูแลระบบ</p>
            <h2 class="mt-2 text-2xl font-black text-neutral-950">ช่องทางติดต่อ <?= h($contactSiteName) ?></h2>
            <p class="mt-2 text-sm font-bold leading-7 text-neutral-500">ข้อมูลส่วนนี้ดึงจากเมนูผู้ดูแลระบบ &gt; ตั้งค่า</p>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <a href="mailto:<?= h($contactAdminEmail) ?>" class="rounded-2xl bg-red-50 p-4 font-black text-red-700 transition hover:bg-red-600 hover:text-white">
                    <i class="fa-solid fa-envelope mb-3 block text-2xl"></i><?= h($contactAdminEmail) ?>
                </a>
                <a href="tel:<?= h($contactAdminPhoneHref) ?>" class="rounded-2xl bg-red-50 p-4 font-black text-red-700 transition hover:bg-red-600 hover:text-white">
                    <i class="fa-solid fa-phone mb-3 block text-2xl"></i><?= h($contactAdminPhone) ?>
                </a>
                <button type="button" data-developer-modal-open class="rounded-2xl bg-neutral-950 p-4 text-left font-black text-white transition hover:bg-red-600">
                    <i class="fa-solid fa-user mb-3 block text-2xl text-red-300"></i>ข้อมูลผู้พัฒนา
                </button>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

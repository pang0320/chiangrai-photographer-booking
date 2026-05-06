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
        <p class="section-kicker text-red-300">ติดต่อเรา</p>
        <h1 class="mt-3 text-4xl font-black">ติดต่อผู้ดูแลระบบ</h1>
        <p class="mt-4 leading-8 text-white/70">สอบถามการใช้งาน แจ้งปัญหา เสนอแนะระบบ หรือสนใจติดต่องานกับผู้พัฒนา</p>

        <div class="mt-8 rounded-[1.5rem] border border-white/15 bg-white/10 p-5">
            <p class="text-xs font-black uppercase tracking-[0.22em] text-red-300">ผู้ดูแลและผู้พัฒนา</p>
            <h2 class="mt-2 text-2xl font-black">Creepygame / Game</h2>
            <p class="mt-2 text-sm font-bold text-white/70"><i class="fa-solid fa-camera-retro mr-2 text-red-300"></i>Photographer from Chiang Rai / Thailand</p>
            <p class="mt-3 leading-7 text-white/70">สนใจติดต่องาน ทัก IB หรือโทรตามเบอร์ด้านล่างได้เลยครับ</p>
        </div>

        <div class="mt-8 grid gap-3 sm:grid-cols-2">
            <a href="tel:0994344335" class="rounded-2xl bg-white px-4 py-4 font-black text-neutral-950 transition hover:bg-red-600 hover:text-white">
                <i class="fa-solid fa-phone mr-2"></i>โทร 099-4344335
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
                <p class="section-kicker">Contact Form</p>
                <h2 class="mt-2 text-2xl font-black text-neutral-950"><i class="fa-solid fa-paper-plane mr-2 text-red-600"></i>ส่งข้อความถึงผู้ดูแลระบบ</h2>
            </div>
            <label class="icon-input block"><i class="fa-solid fa-user"></i><input name="name" required placeholder="ชื่อ" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></label>
            <label class="icon-input block"><i class="fa-solid fa-envelope"></i><input type="email" name="email" required placeholder="อีเมล" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></label>
            <label class="icon-input block"><i class="fa-solid fa-phone"></i><input name="phone" placeholder="เบอร์โทร" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></label>
            <label class="icon-input block"><i class="fa-solid fa-heading"></i><input name="subject" required placeholder="หัวข้อ" class="stock-input w-full rounded-2xl px-4 py-3 font-semibold"></label>
            <textarea name="message" required rows="6" placeholder="ข้อความ" class="stock-input rounded-2xl px-4 py-3 font-semibold"></textarea>
            <button class="stock-button rounded-full px-5 py-3 font-black"><i class="fa-solid fa-paper-plane mr-2"></i>ส่งข้อความ</button>
        </form>

        <div class="stock-card rounded-[2rem] p-6">
            <p class="section-kicker">Quick Contact</p>
            <h2 class="mt-2 text-2xl font-black text-neutral-950">ช่องทางติดต่อด่วน</h2>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <a href="tel:0994344335" class="rounded-2xl bg-red-50 p-4 font-black text-red-700 transition hover:bg-red-600 hover:text-white">
                    <i class="fa-solid fa-phone mb-3 block text-2xl"></i>099-4344335
                </a>
                <button type="button" data-developer-modal-open class="rounded-2xl bg-neutral-950 p-4 text-left font-black text-white transition hover:bg-red-600">
                    <i class="fa-solid fa-user mb-3 block text-2xl text-red-300"></i>ข้อมูลผู้พัฒนา
                </button>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

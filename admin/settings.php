<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$keys = [
    'site_name',
    'home_page_title',
    'home_hero_title',
    'home_hero_subtitle',
    'home_hero_button_text',
    'home_hero_button_url',
    'footer_text',
    'payment_disclaimer',
    'admin_email',
    'admin_phone',
    'allow_photographer_registration',
    'nearby_radius_km',
];

if (is_post()) {
    verify_csrf();

    try {
        foreach ($keys as $key) {
            $value = (string)($_POST[$key] ?? '');
            if ($key === 'home_hero_button_url') {
                $value = trim($value);
                if ($value !== '' && !preg_match('#^https?://#i', $value) && $value[0] !== '/') {
                    $value = '/' . $value;
                }
            }
            set_setting($key, $value);
        }

        if (isset($_POST['remove_logo']) && (string)$_POST['remove_logo'] === '1') {
            set_setting('logo', '');
        } else {
            $logo = upload_image($_FILES['logo_file'] ?? [], 'logos');
            if ($logo) {
                set_setting('logo', $logo);
            }
        }

        log_activity('update_settings', 'settings', null);
        flash('success', 'บันทึกการตั้งค่าระบบแล้ว');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    redirect('/admin/settings.php');
}

$settingLabels = [
    'site_name' => 'ชื่อเว็บไซต์',
    'home_page_title' => 'ชื่อ Title หน้าแรกในแท็บเบราว์เซอร์',
    'home_hero_title' => 'หัวข้อใหญ่หน้าแรก',
    'home_hero_subtitle' => 'คำอธิบายใต้หัวข้อหน้าแรก',
    'home_hero_button_text' => 'ข้อความปุ่มหน้าแรก',
    'home_hero_button_url' => 'ลิงก์ปุ่มหน้าแรก',
    'footer_text' => 'ข้อความท้ายเว็บไซต์',
    'payment_disclaimer' => 'ข้อความแจ้งเรื่องไม่มีระบบชำระเงิน',
    'admin_email' => 'อีเมลผู้ดูแลระบบ',
    'admin_phone' => 'เบอร์โทรผู้ดูแลระบบ',
    'allow_photographer_registration' => 'เปิดรับสมัครช่างภาพ',
    'nearby_radius_km' => 'รัศมีแนะนำช่างภาพใกล้เคียง (กม.)',
];
$settingHelp = [
    'site_name' => 'ใช้แสดงชื่อระบบใน Navbar, Footer และ metadata ของหน้าเว็บ',
    'home_page_title' => 'ข้อความในแท็บเบราว์เซอร์และ HTML head ของหน้าแรก',
    'home_hero_title' => 'ข้อความหัวใหญ่บนหน้าแรก เดิมเคยผูกกับแบนเนอร์ ตอนนี้แก้จากหน้านี้โดยตรง',
    'home_hero_subtitle' => 'ข้อความอธิบายใต้หัวข้อใหญ่บนหน้าแรก',
    'home_hero_button_text' => 'ข้อความบนปุ่ม CTA หน้าแรก ถ้าไม่ต้องการปุ่มให้ลบข้อความออก',
    'home_hero_button_url' => 'ลิงก์ปลายทางของปุ่มหน้าแรก เช่น /photographers.php',
    'footer_text' => 'ข้อความอธิบายสั้น ๆ ใต้โลโก้ใน Footer ถ้าไม่ต้องการแสดงให้ลบข้อความออก',
    'payment_disclaimer' => 'ข้อความที่วงใน Footer ถ้าไม่ต้องการแสดงใน Footer ให้ลบช่องนี้ให้ว่าง',
    'admin_email' => 'ใช้เป็นอีเมลติดต่อเว็บไซต์ในหน้า contact.php และข้อมูลท้ายเว็บ',
    'admin_phone' => 'ใช้เป็นเบอร์โทรติดต่อเว็บไซต์ในหน้า contact.php และข้อมูลท้ายเว็บ',
    'allow_photographer_registration' => 'ใส่ 1 = เปิดรับสมัคร, 0 = ปิดรับสมัคร',
    'nearby_radius_km' => 'ใช้กับระบบแนะนำช่างภาพใกล้เคียงเมื่อไม่พบในอำเภอที่เลือก',
];
$settingDefaults = [
    'site_name' => APP_NAME,
    'home_page_title' => 'ค้นหาช่างภาพเชียงราย | จองช่างภาพมืออาชีพ งานแต่ง รับปริญญา โปรไฟล์',
    'home_hero_title' => 'ค้นหาช่างภาพมืออาชีพในจังหวัดเชียงราย',
    'home_hero_subtitle' => 'เลือกดูตัวอย่างงานถ่ายภาพจริง ตรวจวันว่าง ส่งคำขอจอง และติดต่อช่างภาพโดยตรง ไม่มีระบบรับชำระเงินในเว็บไซต์',
    'home_hero_button_text' => 'ค้นหาช่างภาพ',
    'home_hero_button_url' => '/photographers.php',
    'footer_text' => '',
    'payment_disclaimer' => PAYMENT_DISCLAIMER,
    'admin_email' => '',
    'admin_phone' => '',
    'allow_photographer_registration' => '1',
    'nearby_radius_km' => '30',
];
$currentLogo = setting('logo', '');
$currentLogoUrl = '';
if ($currentLogo !== '') {
    $currentLogoUrl = public_image($currentLogo, '');
}

$pageTitle = 'ตั้งค่าระบบ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-4xl">
        <div>
            <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">ผู้ดูแลระบบ</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950">ตั้งค่าระบบ</h1>
            <p class="mt-2 text-sm font-bold leading-7 text-neutral-500">
                เปลี่ยนข้อมูลพื้นฐานของเว็บไซต์ เช่น ชื่อเว็บ โลโก้ ข้อความท้ายเว็บ และข้อมูลติดต่อผู้ดูแลระบบ
            </p>
        </div>

        <form method="post" enctype="multipart/form-data" class="mt-6 grid gap-6">
            <?= csrf_field() ?>

            <div class="stock-card grid gap-5 rounded-[1.75rem] p-6">
                <div>
                    <p class="section-kicker"><i class="fa-solid fa-image mr-2"></i>โลโก้เว็บไซต์</p>
                    <h2 class="mt-1 text-2xl font-black text-neutral-950">อัปโหลดโลโก้เป็นรูปภาพ</h2>
                    <p class="mt-2 text-sm font-bold leading-7 text-neutral-500">
                        ไม่ต้องกรอกชื่อไฟล์เอง ให้เลือกไฟล์จากเครื่อง ระบบจะบันทึกไว้ที่ <span class="font-black text-neutral-800">assets/uploads/logos</span> แล้วนำไปแสดงที่ Navbar และ Footer อัตโนมัติ
                    </p>
                </div>

                <div class="grid gap-5 md:grid-cols-[220px_1fr] md:items-center">
                    <div class="rounded-[1.5rem] border border-neutral-200 bg-neutral-50 p-5 text-center">
                        <?php if ($currentLogoUrl !== ''): ?>
                            <img src="<?= h($currentLogoUrl) ?>" alt="โลโก้ปัจจุบัน" class="mx-auto h-28 w-28 rounded-3xl object-contain shadow-sm">
                            <p class="mt-3 text-xs font-black text-neutral-500">โลโก้ปัจจุบัน</p>
                            <p class="mt-1 break-all text-[11px] font-bold text-neutral-400"><?= h($currentLogo) ?></p>
                        <?php else: ?>
                            <div class="mx-auto grid h-28 w-28 place-items-center rounded-3xl bg-neutral-950 text-4xl text-white shadow-sm">
                                <i class="fa-solid fa-camera-retro"></i>
                            </div>
                            <p class="mt-3 text-xs font-black text-neutral-500">ยังไม่ได้อัปโหลดโลโก้</p>
                        <?php endif; ?>
                    </div>

                    <div class="grid gap-3">
                        <label class="grid gap-2 text-sm font-black text-neutral-700">
                            <span><i class="fa-solid fa-upload mr-2 text-red-600"></i>เลือกไฟล์โลโก้ใหม่</span>
                            <input type="file" name="logo_file" accept="image/jpeg,image/png,image/webp" class="stock-input rounded-2xl px-4 py-3 font-semibold">
                            <span class="text-xs font-bold leading-6 text-neutral-500"><?= h(UPLOAD_IMAGE_HELP_TEXT) ?> ถ้าไม่เลือกไฟล์ใหม่ ระบบจะใช้โลโก้เดิม</span>
                        </label>

                        <?php if ($currentLogoUrl !== ''): ?>
                            <label class="inline-flex w-fit items-center gap-2 rounded-full border border-red-100 bg-red-50 px-4 py-2 text-sm font-black text-red-700">
                                <input type="checkbox" name="remove_logo" value="1" class="h-4 w-4 rounded border-red-300 text-red-600">
                                ลบโลโก้ปัจจุบันและกลับไปใช้ไอคอนกล้อง
                            </label>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="rounded-[1.25rem] border border-neutral-100 bg-white p-4 text-sm font-bold leading-7 text-neutral-600">
                        <i class="fa-solid fa-location-dot mr-2 text-red-600"></i>
                        โลโก้จะแสดงที่แถบบนซ้ายของทุกหน้า
                    </div>
                    <div class="rounded-[1.25rem] border border-neutral-100 bg-white p-4 text-sm font-bold leading-7 text-neutral-600">
                        <i class="fa-solid fa-location-dot mr-2 text-red-600"></i>
                        โลโก้จะแสดงซ้ำใน Footer ด้านล่างเว็บไซต์
                    </div>
                </div>
            </div>

            <div class="stock-card grid gap-4 rounded-[1.75rem] p-6">
                <div>
                    <p class="section-kicker"><i class="fa-solid fa-gear mr-2"></i>ข้อมูลทั่วไป</p>
                    <h2 class="mt-1 text-2xl font-black text-neutral-950">ข้อความและข้อมูลติดต่อ</h2>
                </div>

                <?php foreach ($keys as $key): ?>
                    <label class="grid gap-2 text-sm font-black text-neutral-700">
                        <span><?= h($settingLabels[$key] ?? $key) ?></span>
                        <?php if (in_array($key, ['home_hero_subtitle', 'footer_text', 'payment_disclaimer'], true)): ?>
                            <textarea name="<?= h($key) ?>" rows="<?= $key === 'payment_disclaimer' ? '3' : '2' ?>" class="stock-input min-h-[96px] rounded-2xl px-4 py-3 font-semibold leading-7"><?= h(setting($key, $settingDefaults[$key] ?? '')) ?></textarea>
                        <?php else: ?>
                            <input name="<?= h($key) ?>" value="<?= h(setting($key, $settingDefaults[$key] ?? '')) ?>" class="stock-input rounded-2xl px-4 py-3 font-semibold">
                        <?php endif; ?>
                        <?php if (!empty($settingHelp[$key])): ?>
                            <span class="text-xs font-bold leading-6 text-neutral-500"><?= h($settingHelp[$key]) ?></span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <button class="stock-button rounded-full px-5 py-3 font-black"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกการตั้งค่า</button>
        </form>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$keys = [
    'site_name',
    'logo',
    'footer_text',
    'admin_email',
    'admin_phone',
    'allow_photographer_registration',
    'nearby_radius_km',
];

if (is_post()) {
    verify_csrf();

    foreach ($keys as $key) {
        $value = (string)($_POST[$key] ?? '');
        set_setting($key, $value);
    }

    log_activity('update_settings', 'settings', null);
    flash('success', 'บันทึก settings แล้ว');
    redirect('/admin/settings.php');
}

$pageTitle = 'Settings';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <div>
            <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Admin</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950">ตั้งค่าระบบ</h1>
        </div>

        <form method="post" class="stock-card mt-6 grid gap-4 rounded-[1.5rem] p-6">
            <?= csrf_field() ?>

            <?php foreach ($keys as $key): ?>
                <label class="grid gap-2 text-sm font-black text-neutral-700">
                    <?= h($key) ?>
                    <input name="<?= h($key) ?>" value="<?= h(setting($key, '')) ?>" class="stock-input rounded-2xl px-4 py-3 font-semibold">
                </label>
            <?php endforeach; ?>

            <button class="stock-button rounded-full px-5 py-3 font-black"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึก</button>
        </form>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

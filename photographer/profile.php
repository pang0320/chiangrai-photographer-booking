<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');
$user = current_user();
$profile = photographer_profile_by_user((int)$user['id']);
if (!$profile) exit('Profile not found');
if (is_post()) {
    verify_csrf();
    try {
        $profileImage = upload_image($_FILES['profile_image'] ?? [], 'avatars') ?: $profile['profile_image'];
        $coverImage = upload_image($_FILES['cover_image'] ?? [], 'covers') ?: $profile['cover_image'];
        db()->prepare('UPDATE photographer_profiles SET display_name=?, slug=?, bio=?, experience_years=?, starting_price=?, profile_image=?, cover_image=?, phone_public=?, line_id=?, facebook_url=?, instagram_url=?, website_url=?, is_available=?, updated_at=NOW() WHERE id=?')
            ->execute([trim((string)$_POST['display_name']), unique_slug('photographer_profiles', (string)$_POST['display_name'], (int)$profile['id']), trim((string)$_POST['bio']), (int)$_POST['experience_years'], (float)$_POST['starting_price'], $profileImage, $coverImage, trim((string)$_POST['phone_public']), trim((string)$_POST['line_id']), trim((string)$_POST['facebook_url']), trim((string)$_POST['instagram_url']), trim((string)$_POST['website_url']), isset($_POST['is_available']) ? 1 : 0, (int)$profile['id']]);
        log_activity('update_photographer_profile', 'photographer_profiles', (int)$profile['id']);
        flash('success', 'บันทึกโปรไฟล์แล้ว');
        redirect('/photographer/profile.php');
    } catch (Throwable $e) { flash('error', $e->getMessage()); }
}
$pageTitle = 'จัดการโปรไฟล์ช่างภาพ';
include __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-4xl px-4 py-10">
    <div class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
        <h1 class="text-2xl font-extrabold">จัดการโปรไฟล์</h1>
        <form method="post" enctype="multipart/form-data" class="mt-6 grid gap-4">
            <?= csrf_field() ?>
            <input name="display_name" value="<?= h($profile['display_name']) ?>" required class="rounded-2xl border px-4 py-3">
            <textarea name="bio" rows="5" class="rounded-2xl border px-4 py-3"><?= h($profile['bio']) ?></textarea>
            <div class="grid gap-4 sm:grid-cols-2"><input type="number" name="experience_years" value="<?= (int)$profile['experience_years'] ?>" class="rounded-2xl border px-4 py-3"><input type="number" step="0.01" name="starting_price" value="<?= h($profile['starting_price']) ?>" class="rounded-2xl border px-4 py-3"></div>
            <div class="grid gap-4 sm:grid-cols-2"><input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp" class="rounded-2xl border px-4 py-3"><input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" class="rounded-2xl border px-4 py-3"></div>
            <div class="grid gap-4 sm:grid-cols-2"><input name="phone_public" value="<?= h($profile['phone_public']) ?>" placeholder="เบอร์โทร public" class="rounded-2xl border px-4 py-3"><input name="line_id" value="<?= h($profile['line_id']) ?>" placeholder="LINE ID" class="rounded-2xl border px-4 py-3"><input name="facebook_url" value="<?= h($profile['facebook_url']) ?>" placeholder="Facebook URL" class="rounded-2xl border px-4 py-3"><input name="instagram_url" value="<?= h($profile['instagram_url']) ?>" placeholder="Instagram URL" class="rounded-2xl border px-4 py-3"><input name="website_url" value="<?= h($profile['website_url']) ?>" placeholder="Website URL" class="rounded-2xl border px-4 py-3"></div>
            <label class="rounded-2xl bg-slate-50 p-4 font-bold"><input type="checkbox" name="is_available" <?= $profile['is_available'] ? 'checked' : '' ?>> เปิดรับงาน</label>
            <button class="rounded-2xl bg-indigo-600 px-5 py-3 font-bold text-white">บันทึก</button>
        </form>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>


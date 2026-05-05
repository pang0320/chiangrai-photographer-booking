<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');
$user = current_user();
$profile = photographer_profile_by_user((int)$user['id']);
if (!$profile) exit('Profile not found');
if (is_post()) {
    verify_csrf();
    try {
        $profileImageFile = [];
        if (isset($_FILES['profile_image'])) {
            $profileImageFile = $_FILES['profile_image'];
        }
        $coverImageFile = [];
        if (isset($_FILES['cover_image'])) {
            $coverImageFile = $_FILES['cover_image'];
        }
        $profileImage = upload_image($profileImageFile, 'avatars');
        if (!$profileImage) {
            $profileImage = $profile['profile_image'];
        }
        $coverImage = upload_image($coverImageFile, 'covers');
        if (!$coverImage) {
            $coverImage = $profile['cover_image'];
        }
        $isAvailableValue = 0;
        if (isset($_POST['is_available'])) {
            $isAvailableValue = 1;
        }
        db()->prepare('UPDATE photographer_profiles SET display_name=?, slug=?, bio=?, experience_years=?, starting_price=?, profile_image=?, cover_image=?, phone_public=?, line_id=?, facebook_url=?, instagram_url=?, website_url=?, is_available=?, updated_at=NOW() WHERE id=?')
            ->execute([trim((string)$_POST['display_name']), unique_slug('photographer_profiles', (string)$_POST['display_name'], (int)$profile['id']), trim((string)$_POST['bio']), (int)$_POST['experience_years'], (float)$_POST['starting_price'], $profileImage, $coverImage, trim((string)$_POST['phone_public']), trim((string)$_POST['line_id']), trim((string)$_POST['facebook_url']), trim((string)$_POST['instagram_url']), trim((string)$_POST['website_url']), $isAvailableValue, (int)$profile['id']]);
        log_activity('update_photographer_profile', 'photographer_profiles', (int)$profile['id']);
        flash('success', 'บันทึกโปรไฟล์แล้ว');
        redirect('/photographer/profile.php');
    } catch (Throwable $e) { flash('error', $e->getMessage()); }
}
$pageTitle = 'จัดการโปรไฟล์ช่างภาพ';
include __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-6xl px-4 py-10">
    <div class="overflow-hidden rounded-[2rem] bg-white shadow-sm ring-1 ring-slate-200">
        <div class="relative h-64 bg-slate-900">
            <img id="cover-preview" class="h-full w-full object-cover opacity-80" src="<?= h(public_image($profile['cover_image'], '/assets/uploads/seed/photo-1492691527719-9d1e07e534b4.jpg')) ?>" alt="<?= h($profile['display_name']) ?>">
            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
            <div class="absolute bottom-6 left-6 flex items-end gap-5">
                <img id="profile-image-preview" class="h-28 w-28 rounded-[1.8rem] object-cover ring-4 ring-white shadow-xl" src="<?= h(public_image($profile['profile_image'], '/assets/uploads/seed/photo-1500648767791-00dcc994a43e.jpg')) ?>" alt="<?= h($profile['display_name']) ?>">
                <div class="pb-2 text-white">
                    <p class="text-sm font-black uppercase tracking-[0.22em] text-red-200"><i class="fa-solid fa-camera-retro mr-2"></i>โปรไฟล์ช่างภาพ</p>
                    <h1 class="mt-1 text-3xl font-black"><?= h($profile['display_name']) ?></h1>
                    <p class="mt-2 text-sm font-semibold text-white/70">รูปโปรไฟล์และรูปหน้าปกนี้จะแสดงในหน้าค้นหาและหน้าโปรไฟล์สาธารณะ</p>
                </div>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" class="grid gap-5 p-8">
            <?= csrf_field() ?>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    <span><i class="fa-solid fa-signature mr-2 text-red-600"></i>ชื่อช่างภาพ / ชื่อทีม</span>
                    <input name="display_name" value="<?= h($profile['display_name']) ?>" required class="rounded-2xl border px-4 py-3 font-semibold">
                </label>
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    <span><i class="fa-solid fa-briefcase mr-2 text-red-600"></i>ประสบการณ์ (ปี)</span>
                    <input type="number" min="0" name="experience_years" value="<?= (int)$profile['experience_years'] ?>" class="rounded-2xl border px-4 py-3 font-semibold">
                </label>
                <label class="grid gap-2 text-sm font-black text-slate-700 sm:col-span-2">
                    <span><i class="fa-solid fa-comment mr-2 text-red-600"></i>แนะนำตัว / Bio</span>
                    <textarea name="bio" rows="5" class="rounded-2xl border px-4 py-3 font-semibold"><?= h($profile['bio']) ?></textarea>
                </label>
                <label class="grid gap-2 text-sm font-black text-slate-700 sm:col-span-2">
                    <span><i class="fa-solid fa-tag mr-2 text-red-600"></i>ราคาเริ่มต้น</span>
                    <input type="number" min="0" step="0.01" name="starting_price" value="<?= h($profile['starting_price']) ?>" class="rounded-2xl border px-4 py-3 font-semibold">
                </label>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-3 rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50 p-5">
                    <span class="font-black text-slate-800"><i class="fa-solid fa-user-circle mr-2 text-red-600"></i>อัปโหลดรูปโปรไฟล์ช่างภาพ</span>
                    <span class="text-sm font-semibold text-slate-500">ใช้เป็นรูปหน้าช่างภาพใน card, navbar และหน้าโปรไฟล์</span>
                    <input id="profile-image-input" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp" class="rounded-2xl border bg-white px-4 py-3 font-semibold">
                </label>

                <label class="grid gap-3 rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50 p-5">
                    <span class="font-black text-slate-800"><i class="fa-solid fa-panorama mr-2 text-red-600"></i>อัปโหลดรูปหน้าปกโปรไฟล์</span>
                    <span class="text-sm font-semibold text-slate-500">ใช้เป็นภาพ cover ขนาดใหญ่บนหน้าโปรไฟล์ช่างภาพ</span>
                    <input id="cover-image-input" type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" class="rounded-2xl border bg-white px-4 py-3 font-semibold">
                </label>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    <span><i class="fa-solid fa-phone mr-2 text-red-600"></i>เบอร์โทรที่ให้ลูกค้าเห็น</span>
                    <input name="phone_public" value="<?= h($profile['phone_public']) ?>" placeholder="เบอร์โทร public" class="rounded-2xl border px-4 py-3 font-semibold">
                </label>
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    <span><i class="fa-brands fa-line mr-2 text-red-600"></i>LINE ID</span>
                    <input name="line_id" value="<?= h($profile['line_id']) ?>" placeholder="LINE ID" class="rounded-2xl border px-4 py-3 font-semibold">
                </label>
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    <span><i class="fa-brands fa-facebook mr-2 text-red-600"></i>Facebook URL</span>
                    <input name="facebook_url" value="<?= h($profile['facebook_url']) ?>" placeholder="Facebook URL" class="rounded-2xl border px-4 py-3 font-semibold">
                </label>
                <label class="grid gap-2 text-sm font-black text-slate-700">
                    <span><i class="fa-brands fa-instagram mr-2 text-red-600"></i>Instagram URL</span>
                    <input name="instagram_url" value="<?= h($profile['instagram_url']) ?>" placeholder="Instagram URL" class="rounded-2xl border px-4 py-3 font-semibold">
                </label>
                <label class="grid gap-2 text-sm font-black text-slate-700 sm:col-span-2">
                    <span><i class="fa-solid fa-globe mr-2 text-red-600"></i>Website URL</span>
                    <input name="website_url" value="<?= h($profile['website_url']) ?>" placeholder="Website URL" class="rounded-2xl border px-4 py-3 font-semibold">
                </label>
            </div>

            <label class="rounded-2xl bg-slate-50 p-4 font-bold">
                <input type="checkbox" name="is_available" <?php if ($profile['is_available']): ?>checked<?php endif; ?>>
                <i class="fa-solid fa-toggle-on mx-2 text-red-600"></i>เปิดรับงาน
            </label>
            <button class="rounded-2xl bg-neutral-950 px-5 py-3 font-black text-white transition hover:bg-red-600"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกโปรไฟล์ช่างภาพ</button>
        </form>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function bindImagePreview(inputId, previewId) {
        var input = document.getElementById(inputId);
        var preview = document.getElementById(previewId);
        if (!input || !preview) return;
        input.addEventListener('change', function () {
            if (!input.files || !input.files[0]) return;
            preview.src = URL.createObjectURL(input.files[0]);
        });
    }

    bindImagePreview('profile-image-input', 'profile-image-preview');
    bindImagePreview('cover-image-input', 'cover-preview');
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>

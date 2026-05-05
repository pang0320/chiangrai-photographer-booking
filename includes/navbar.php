<?php
$me = current_user();
$unreadCount = 0;
$roleIcon = 'fa-user';
$roleLabel = '';
$roleClass = 'bg-neutral-100 text-neutral-700';
$userInitial = '';
$userAvatarUrl = '';
$workspacePath = '/login.php';
$workspaceLabel = 'เมนูของฉัน';
$workspaceIcon = 'fa-gauge';

if ($me) {
    $unreadCount = unread_notifications_count((int)$me['id']);
    $workspacePath = user_workspace_path($me);
    if ($workspacePath === '/photographer/onboarding.php') {
        $workspaceLabel = 'ตั้งค่าโปรไฟล์';
        $workspaceIcon = 'fa-list-check';
    }
    $roleLabel = (string)$me['role_display'];
    if ($roleLabel === '') {
        $roleLabel = (string)$me['role_name'];
    }
    $userInitial = mb_substr((string)$me['name'], 0, 1, 'UTF-8');
    if (!empty($me['avatar'])) {
        $userAvatarUrl = public_image($me['avatar'], '/assets/uploads/seed/photo-1494790108377-be9c29b29330.jpg');
    }

    if ($me['role_name'] === 'admin') {
        $roleIcon = 'fa-user-shield';
        $roleClass = 'bg-red-50 text-red-700';
    }

    if ($me['role_name'] === 'photographer') {
        $roleIcon = 'fa-camera-retro';
        $roleClass = 'bg-amber-50 text-amber-700';
        $navbarPhotographerProfile = photographer_profile_by_user((int)$me['id']);
        if ($navbarPhotographerProfile && !empty($navbarPhotographerProfile['profile_image'])) {
            $userAvatarUrl = public_image($navbarPhotographerProfile['profile_image'], '/assets/uploads/seed/photo-1500648767791-00dcc994a43e.jpg');
        }
    }

    if ($me['role_name'] === 'customer') {
        $roleIcon = 'fa-user';
        $roleClass = 'bg-emerald-50 text-emerald-700';
    }
}
?>
<nav class="sticky top-0 z-40 border-b border-black/10 bg-white/90 shadow-sm backdrop-blur-xl">
    <div class="stock-shell flex items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <a href="/index.php" class="flex min-w-fit items-center gap-3 font-black tracking-tight text-neutral-950">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-neutral-950 text-white shadow-lg shadow-neutral-950/15"><i class="fa-solid fa-camera-retro"></i></span>
            <span class="leading-tight">
                <span class="block text-lg">Chiang Rai<span class="text-red-600">Photo</span></span>
                <span class="hidden text-[10px] font-black uppercase tracking-[0.22em] text-neutral-400 lg:block">ตลาดช่างภาพ</span>
            </span>
        </a>

        <div class="hidden min-w-0 flex-1 justify-center lg:flex">
            <div class="flex items-center gap-1 rounded-full border border-neutral-200 bg-neutral-50/80 p-1 text-sm font-black text-neutral-600 shadow-inner">
                <a class="rounded-full px-4 py-2 transition hover:bg-white hover:text-red-600 hover:shadow-sm" href="/index.php"><i class="fa-solid fa-home mr-2"></i>หน้าแรก</a>
                <a class="rounded-full px-4 py-2 transition hover:bg-white hover:text-red-600 hover:shadow-sm" href="/photographers.php"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหาช่างภาพ</a>
                <a class="rounded-full px-4 py-2 transition hover:bg-white hover:text-red-600 hover:shadow-sm" href="/blog.php"><i class="fa-solid fa-newspaper mr-2"></i>บทความ</a>
                <a class="rounded-full px-4 py-2 transition hover:bg-white hover:text-red-600 hover:shadow-sm" href="/faq.php"><i class="fa-solid fa-circle-question mr-2"></i>คำถามที่พบบ่อย</a>
                <a class="rounded-full px-4 py-2 transition hover:bg-white hover:text-red-600 hover:shadow-sm" href="/contact.php"><i class="fa-solid fa-envelope mr-2"></i>ติดต่อ</a>
            </div>
        </div>

        <div class="ml-auto hidden items-center gap-3 md:flex">
            <?php if ($me): ?>
                <a class="inline-flex h-11 items-center rounded-full bg-neutral-950 px-5 text-sm font-black text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-red-600 hover:shadow-lg" href="<?= h($workspacePath) ?>" title="<?= h($workspaceLabel) ?>">
                    <i class="fa-solid <?= h($workspaceIcon) ?> mr-2"></i><?= h($workspaceLabel) ?>
                </a>
                <a class="relative grid h-11 w-11 place-items-center rounded-full border border-neutral-200 bg-white text-neutral-700 shadow-sm transition hover:bg-neutral-950 hover:text-white" href="/notifications.php" title="แจ้งเตือน">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="absolute -right-1 -top-1 rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-black text-white">
                            <?= $unreadCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a class="flex min-w-0 items-center gap-3 rounded-full border border-neutral-200 bg-white py-1.5 pl-1.5 pr-4 shadow-sm transition hover:-translate-y-0.5 hover:border-neutral-300 hover:shadow-lg" href="<?= h($workspacePath) ?>" title="กลับไป<?= h($workspaceLabel) ?>">
                    <?php if ($userAvatarUrl !== ''): ?>
                        <img class="h-10 w-10 shrink-0 rounded-full object-cover ring-2 ring-neutral-100" src="<?= h($userAvatarUrl) ?>" alt="<?= h($me['name']) ?>">
                    <?php else: ?>
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-neutral-950 text-sm font-black text-white">
                            <?= h($userInitial) ?>
                        </span>
                    <?php endif; ?>
                    <span class="min-w-0 leading-tight">
                        <span class="block max-w-[170px] truncate text-sm font-black text-neutral-950"><?= h($me['name']) ?></span>
                        <span class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-black <?= h($roleClass) ?>">
                            <i class="fa-solid <?= h($roleIcon) ?> mr-1"></i><?= h($roleLabel) ?>
                        </span>
                    </span>
                </a>
                <a class="grid h-11 w-11 place-items-center rounded-full border border-red-100 bg-red-50 text-red-700 shadow-sm transition hover:bg-red-600 hover:text-white" href="/logout.php" title="ออกจากระบบ">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            <?php else: ?>
                <a class="rounded-full border border-neutral-200 bg-white px-5 py-2.5 text-sm font-black text-neutral-700 shadow-sm transition hover:bg-neutral-950 hover:text-white" href="/login.php"><i class="fa-solid fa-right-to-bracket mr-2"></i>เข้าสู่ระบบ</a>
                <a class="rounded-full bg-neutral-950 px-5 py-2.5 text-sm font-black text-white shadow-sm transition hover:bg-red-600" href="/register.php"><i class="fa-solid fa-user-plus mr-2"></i>สมัครสมาชิก</a>
            <?php endif; ?>
        </div>

        <div class="ml-auto md:hidden">
            <?php if ($me): ?>
                <a class="inline-flex max-w-[300px] items-center gap-2 rounded-full bg-neutral-950 px-2 py-2 text-sm font-bold text-white shadow-sm" href="<?= h($workspacePath) ?>">
                    <span class="grid h-7 w-7 place-items-center rounded-full bg-white text-xs font-black text-neutral-950"><i class="fa-solid <?= h($workspaceIcon) ?>"></i></span>
                    <span class="max-w-[100px] truncate"><?= h($workspaceLabel) ?></span>
                    <?php if ($userAvatarUrl !== ''): ?>
                        <img class="h-7 w-7 rounded-full object-cover ring-1 ring-white/40" src="<?= h($userAvatarUrl) ?>" alt="<?= h($me['name']) ?>">
                    <?php else: ?>
                        <span class="grid h-7 w-7 place-items-center rounded-full bg-white text-xs font-black text-neutral-950"><?= h($userInitial) ?></span>
                    <?php endif; ?>
                    <span class="hidden rounded-full bg-white/15 px-2 py-0.5 text-[10px] min-[390px]:inline-flex"><i class="fa-solid <?= h($roleIcon) ?> mr-1"></i><?= h($roleLabel) ?></span>
                </a>
            <?php else: ?>
                <a class="rounded-full bg-neutral-950 px-4 py-2 text-sm font-bold text-white" href="/login.php"><i class="fa-solid fa-right-to-bracket mr-2"></i>เข้าสู่ระบบ</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

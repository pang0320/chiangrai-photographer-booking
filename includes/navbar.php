<?php
$me = current_user();
$unreadCount = 0;

if ($me) {
    $unreadCount = unread_notifications_count((int)$me['id']);
}
?>
<nav class="sticky top-0 z-40 border-b border-black/10 bg-white/95 backdrop-blur-xl">
    <div class="stock-shell flex items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <a href="/index.php" class="flex items-center gap-3 font-black tracking-tight text-neutral-950">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-neutral-950 text-white shadow-sm"><i class="fa-solid fa-camera-retro"></i></span>
            <span class="text-lg">Chiang Rai<span class="text-red-600">Photo</span></span>
        </a>
        <div class="hidden items-center gap-7 text-sm font-bold text-neutral-700 md:flex">
            <a class="hover:text-red-600" href="/index.php"><i class="fa-solid fa-home mr-2"></i>หน้าแรก</a>
            <a class="hover:text-red-600" href="/photographers.php"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหาช่างภาพ</a>
            <a class="hover:text-red-600" href="/blog.php"><i class="fa-solid fa-newspaper mr-2"></i>Blog</a>
            <a class="hover:text-red-600" href="/faq.php"><i class="fa-solid fa-circle-question mr-2"></i>FAQ</a>
            <a class="hover:text-red-600" href="/contact.php"><i class="fa-solid fa-envelope mr-2"></i>ติดต่อ</a>
            <?php if ($me): ?>
                <a class="hover:text-red-600" href="<?= h(dashboard_path($me['role_name'])) ?>"><i class="fa-solid fa-gauge mr-2"></i>แดชบอร์ด</a>
                <a class="relative hover:text-red-600" href="/notifications.php">
                    <i class="fa-solid fa-bell mr-2"></i>แจ้งเตือน
                    <?php if ($unreadCount > 0): ?>
                        <span class="absolute -right-4 -top-2 rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-black text-white">
                            <?= $unreadCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a class="hover:text-red-600" href="/logout.php"><i class="fa-solid fa-right-from-bracket mr-2"></i>ออกจากระบบ</a>
            <?php else: ?>
                <a class="hover:text-red-600" href="/login.php"><i class="fa-solid fa-right-to-bracket mr-2"></i>เข้าสู่ระบบ</a>
                <a class="rounded-full bg-neutral-950 px-5 py-2.5 text-white shadow-sm hover:bg-red-600" href="/register.php"><i class="fa-solid fa-user-plus mr-2"></i>สมัครสมาชิก</a>
            <?php endif; ?>
        </div>
        <div class="md:hidden">
            <?php if ($me): ?>
                <a class="rounded-full bg-neutral-950 px-4 py-2 text-sm font-bold text-white" href="<?= h(dashboard_path($me['role_name'])) ?>"><i class="fa-solid fa-gauge mr-2"></i>Dashboard</a>
            <?php else: ?>
                <a class="rounded-full bg-neutral-950 px-4 py-2 text-sm font-bold text-white" href="/login.php"><i class="fa-solid fa-right-to-bracket mr-2"></i>Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

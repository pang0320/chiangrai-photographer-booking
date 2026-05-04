<?php $me = current_user(); ?>
<nav class="sticky top-0 z-40 border-b border-black/10 bg-white/95 backdrop-blur-xl">
    <div class="stock-shell flex items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <a href="/index.php" class="flex items-center gap-3 font-black tracking-tight text-neutral-950">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-neutral-950 text-white shadow-sm"><i class="fa-solid fa-camera-retro"></i></span>
            <span class="text-lg">Chiang Rai<span class="text-red-600">Photo</span></span>
        </a>
        <div class="hidden items-center gap-7 text-sm font-bold text-neutral-700 md:flex">
            <a class="hover:text-red-600" href="/photographers.php">ค้นหาช่างภาพ</a>
            <?php if ($me): ?>
                <a class="hover:text-red-600" href="<?= h(dashboard_path($me['role_name'])) ?>">แดชบอร์ด</a>
                <a class="hover:text-red-600" href="/logout.php">ออกจากระบบ</a>
            <?php else: ?>
                <a class="hover:text-red-600" href="/login.php">เข้าสู่ระบบ</a>
                <a class="rounded-full bg-neutral-950 px-5 py-2.5 text-white shadow-sm hover:bg-red-600" href="/register.php">สมัครสมาชิก</a>
            <?php endif; ?>
        </div>
        <div class="md:hidden">
            <?php if ($me): ?>
                <a class="rounded-full bg-neutral-950 px-4 py-2 text-sm font-bold text-white" href="<?= h(dashboard_path($me['role_name'])) ?>">Dashboard</a>
            <?php else: ?>
                <a class="rounded-full bg-neutral-950 px-4 py-2 text-sm font-bold text-white" href="/login.php">Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

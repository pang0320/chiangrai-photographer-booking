<?php
$me = current_user();
$sidebarRequestUri = '/';
if (isset($_SERVER['REQUEST_URI'])) {
    $sidebarRequestUri = $_SERVER['REQUEST_URI'];
}
$currentPath = parse_url($sidebarRequestUri, PHP_URL_PATH);
if (!$currentPath) {
    $currentPath = '/';
}
$navItems = [];
$roleTitle = 'พื้นที่ทำงาน';
$roleIcon = 'fa-grid-2';

if ($me) {
    if ($me['role_name'] === 'admin') {
        $roleTitle = 'ผู้ดูแลระบบ';
        $roleIcon = 'fa-user-shield';
        $navItems = [
            ['/admin/dashboard.php', 'แดชบอร์ด', 'fa-gauge'],
            ['/admin/users.php', 'ผู้ใช้งาน', 'fa-users'],
            ['/admin/photographers.php', 'ช่างภาพ', 'fa-camera'],
            ['/admin/categories.php', 'หมวดหมู่งาน', 'fa-layer-group'],
            ['/admin/districts.php', 'อำเภอ', 'fa-map'],
            ['/admin/bookings.php', 'คำขอจอง', 'fa-calendar-check'],
            ['/admin/reviews.php', 'รีวิว', 'fa-star'],
            ['/admin/articles.php', 'บทความช่างภาพ', 'fa-newspaper'],
            ['/admin/blogs.php', 'บทความเว็บ', 'fa-blog'],
            ['/admin/faqs.php', 'คำถามที่พบบ่อย', 'fa-circle-question'],
            ['/admin/banners.php', 'แบนเนอร์', 'fa-images'],
            ['/admin/reports.php', 'รายงานสรุป', 'fa-chart-line'],
            ['/admin/reports_moderation.php', 'ตรวจรายงานปัญหา', 'fa-shield-halved'],
            ['/admin/contact_messages.php', 'ข้อความติดต่อ', 'fa-envelope-open-text'],
            ['/admin/activity_logs.php', 'ประวัติการใช้งาน', 'fa-clipboard-list'],
            ['/admin/settings.php', 'ตั้งค่า', 'fa-gear'],
            ['/notifications.php', 'แจ้งเตือน', 'fa-bell'],
        ];
    }

    if ($me['role_name'] === 'photographer') {
        $roleTitle = 'สตูดิโอช่างภาพ';
        $roleIcon = 'fa-camera-retro';
        $navItems = [
            ['/photographer/dashboard.php', 'แดชบอร์ด', 'fa-gauge'],
            ['/photographer/onboarding.php', 'ตั้งค่าเริ่มต้น', 'fa-list-check'],
            ['/photographer/profile.php', 'โปรไฟล์', 'fa-id-card'],
            ['/photographer/service_areas.php', 'พื้นที่ให้บริการ', 'fa-map-location-dot'],
            ['/photographer/services.php', 'ประเภทงานที่รับ', 'fa-list-check'],
            ['/photographer/portfolio.php', 'ตัวอย่างงาน', 'fa-images'],
            ['/photographer/availability.php', 'วันว่าง', 'fa-calendar'],
            ['/photographer/bookings.php', 'คำขอจอง', 'fa-calendar-check'],
            ['/photographer/articles.php', 'บทความ', 'fa-newspaper'],
            ['/photographer/reviews.php', 'รีวิว', 'fa-star'],
            ['/notifications.php', 'แจ้งเตือน', 'fa-bell'],
        ];
    }

    if ($me['role_name'] === 'customer') {
        $roleTitle = 'พื้นที่ลูกค้า';
        $roleIcon = 'fa-user';
        $navItems = [
            ['/customer/dashboard.php', 'แดชบอร์ด', 'fa-gauge'],
            ['/customer/photographers.php', 'ค้นหาช่างภาพ', 'fa-magnifying-glass'],
            ['/customer/bookings.php', 'รายการจองของฉัน', 'fa-calendar-check'],
            ['/customer/reviews.php', 'รีวิวของฉัน', 'fa-star'],
            ['/customer/reports.php', 'รายงานปัญหาของฉัน', 'fa-shield-halved'],
            ['/customer/favorites.php', 'รายการโปรด', 'fa-heart'],
            ['/customer/recently_viewed.php', 'ช่างภาพที่เคยดู', 'fa-clock-rotate-left'],
            ['/customer/profile.php', 'โปรไฟล์', 'fa-id-card'],
            ['/notifications.php', 'แจ้งเตือน', 'fa-bell'],
        ];
    }
}
?>
<aside class="workspace-sidebar">
    <div class="rounded-[1.5rem] bg-neutral-950 p-5 text-white shadow-xl">
        <div class="flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-red-600">
                <i class="fa-solid <?= h($roleIcon) ?>"></i>
            </span>
            <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-white/45">เมนูจัดการ</p>
                <h2 class="font-black"><?= h($roleTitle) ?></h2>
            </div>
        </div>
        <?php if ($me): ?>
            <p class="mt-4 truncate rounded-2xl bg-white/10 px-4 py-3 text-sm font-bold text-white/80">
                <?= h($me['name']) ?>
            </p>
        <?php endif; ?>
    </div>

    <nav class="mt-4 grid gap-2">
        <?php foreach ($navItems as $item): ?>
            <?php
            $url = $item[0];
            $label = $item[1];
            $icon = $item[2];
            if ($me && $me['role_name'] === 'customer' && $url === '/notifications.php') {
                $url = '/customer/notifications.php';
            }
            $isActive = $currentPath === $url;
            $className = 'workspace-nav-link';

            if ($isActive) {
                $className .= ' workspace-nav-link-active';
            }
            ?>
            <a href="<?= h($url) ?>" class="<?= h($className) ?>">
                <i class="fa-solid <?= h($icon) ?> w-5"></i>
                <span><?= h($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

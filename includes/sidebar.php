<?php
$me = current_user();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navItems = [];
$roleTitle = 'Workspace';
$roleIcon = 'fa-grid-2';

if ($me) {
    if ($me['role_name'] === 'admin') {
        $roleTitle = 'Admin Console';
        $roleIcon = 'fa-user-shield';
        $navItems = [
            ['/admin/dashboard.php', 'Dashboard', 'fa-chart-line'],
            ['/admin/users.php', 'Users', 'fa-users'],
            ['/admin/photographers.php', 'Photographers', 'fa-camera'],
            ['/admin/categories.php', 'Categories', 'fa-layer-group'],
            ['/admin/districts.php', 'Districts', 'fa-location-dot'],
            ['/admin/bookings.php', 'Bookings', 'fa-calendar-check'],
            ['/admin/reviews.php', 'Reviews', 'fa-star'],
            ['/admin/articles.php', 'Articles', 'fa-newspaper'],
            ['/admin/banners.php', 'Banners', 'fa-images'],
            ['/admin/reports.php', 'Reports', 'fa-chart-pie'],
            ['/admin/activity_logs.php', 'Activity Logs', 'fa-clock-rotate-left'],
            ['/admin/settings.php', 'Settings', 'fa-gear'],
        ];
    }

    if ($me['role_name'] === 'photographer') {
        $roleTitle = 'Photographer Studio';
        $roleIcon = 'fa-camera-retro';
        $navItems = [
            ['/photographer/dashboard.php', 'Dashboard', 'fa-chart-line'],
            ['/photographer/profile.php', 'Profile', 'fa-id-card'],
            ['/photographer/service_areas.php', 'Service Areas', 'fa-map-location-dot'],
            ['/photographer/services.php', 'Services', 'fa-list-check'],
            ['/photographer/portfolio.php', 'Portfolio', 'fa-images'],
            ['/photographer/availability.php', 'Availability', 'fa-calendar-days'],
            ['/photographer/bookings.php', 'Bookings', 'fa-calendar-check'],
            ['/photographer/articles.php', 'Articles', 'fa-newspaper'],
            ['/photographer/reviews.php', 'Reviews', 'fa-star'],
        ];
    }

    if ($me['role_name'] === 'customer') {
        $roleTitle = 'Customer Space';
        $roleIcon = 'fa-user';
        $navItems = [
            ['/customer/dashboard.php', 'Dashboard', 'fa-chart-line'],
            ['/photographers.php', 'Find Photographers', 'fa-magnifying-glass'],
            ['/customer/bookings.php', 'My Bookings', 'fa-calendar-check'],
            ['/customer/profile.php', 'Profile', 'fa-id-card'],
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
                <p class="text-xs font-black uppercase tracking-[0.2em] text-white/45">Control</p>
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

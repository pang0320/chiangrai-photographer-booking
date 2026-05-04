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
$roleTitle = 'Workspace';
$roleIcon = 'fa-grid-2';

if ($me) {
    if ($me['role_name'] === 'admin') {
        $roleTitle = 'Admin Console';
        $roleIcon = 'fa-user-shield';
        $navItems = [
            ['/admin/dashboard.php', 'Dashboard', 'fa-gauge'],
            ['/admin/users.php', 'Users', 'fa-users'],
            ['/admin/photographers.php', 'Photographers', 'fa-camera'],
            ['/admin/categories.php', 'Categories', 'fa-layer-group'],
            ['/admin/districts.php', 'Districts', 'fa-map'],
            ['/admin/bookings.php', 'Bookings', 'fa-calendar-check'],
            ['/admin/reviews.php', 'Reviews', 'fa-star'],
            ['/admin/articles.php', 'Articles', 'fa-newspaper'],
            ['/admin/blogs.php', 'Blogs', 'fa-blog'],
            ['/admin/faqs.php', 'FAQ', 'fa-circle-question'],
            ['/admin/banners.php', 'Banners', 'fa-images'],
            ['/admin/reports.php', 'Reports', 'fa-chart-line'],
            ['/admin/reports_moderation.php', 'Moderation', 'fa-shield-halved'],
            ['/admin/contact_messages.php', 'Contact Messages', 'fa-envelope-open-text'],
            ['/admin/activity_logs.php', 'Activity Logs', 'fa-clipboard-list'],
            ['/admin/settings.php', 'Settings', 'fa-gear'],
            ['/notifications.php', 'Notifications', 'fa-bell'],
        ];
    }

    if ($me['role_name'] === 'photographer') {
        $roleTitle = 'Photographer Studio';
        $roleIcon = 'fa-camera-retro';
        $navItems = [
            ['/photographer/dashboard.php', 'Dashboard', 'fa-gauge'],
            ['/photographer/onboarding.php', 'Onboarding', 'fa-list-check'],
            ['/photographer/profile.php', 'Profile', 'fa-id-card'],
            ['/photographer/service_areas.php', 'Service Areas', 'fa-map-location-dot'],
            ['/photographer/services.php', 'Services', 'fa-list-check'],
            ['/photographer/portfolio.php', 'Portfolio', 'fa-images'],
            ['/photographer/availability.php', 'Availability', 'fa-calendar'],
            ['/photographer/bookings.php', 'Bookings', 'fa-calendar-check'],
            ['/photographer/articles.php', 'Articles', 'fa-newspaper'],
            ['/photographer/reviews.php', 'Reviews', 'fa-star'],
            ['/notifications.php', 'Notifications', 'fa-bell'],
        ];
    }

    if ($me['role_name'] === 'customer') {
        $roleTitle = 'Customer Space';
        $roleIcon = 'fa-user';
        $navItems = [
            ['/customer/dashboard.php', 'Dashboard', 'fa-gauge'],
            ['/photographers.php', 'Find Photographers', 'fa-magnifying-glass'],
            ['/customer/bookings.php', 'My Bookings', 'fa-calendar-check'],
            ['/customer/favorites.php', 'Favorites', 'fa-heart'],
            ['/customer/recently_viewed.php', 'Recently Viewed', 'fa-clock-rotate-left'],
            ['/customer/profile.php', 'Profile', 'fa-id-card'],
            ['/notifications.php', 'Notifications', 'fa-bell'],
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

<?php
$breadcrumbRequestUri = '/';
if (isset($_SERVER['REQUEST_URI'])) {
    $breadcrumbRequestUri = $_SERVER['REQUEST_URI'];
}
$breadcrumbPath = parse_url($breadcrumbRequestUri, PHP_URL_PATH);
if (!$breadcrumbPath) {
    $breadcrumbPath = '/';
}
$breadcrumbLabels = [
    'admin' => 'Admin',
    'customer' => 'Customer',
    'photographer' => 'Photographer',
    'dashboard.php' => 'Dashboard',
    'users.php' => 'Users',
    'photographers.php' => 'Photographers',
    'categories.php' => 'Categories',
    'districts.php' => 'Districts',
    'bookings.php' => 'Bookings',
    'booking_detail.php' => 'Booking Detail',
    'reviews.php' => 'Reviews',
    'articles.php' => 'Articles',
    'banners.php' => 'Banners',
    'reports.php' => 'Reports',
    'activity_logs.php' => 'Activity Logs',
    'settings.php' => 'Settings',
    'profile.php' => 'Profile',
    'portfolio.php' => 'Portfolio',
    'availability.php' => 'Availability',
    'services.php' => 'Services',
    'service_areas.php' => 'Service Areas',
    'notifications.php' => 'Notifications',
];

if ($breadcrumbPath !== '/' && $breadcrumbPath !== '/index.php'):
    $parts = array_values(array_filter(explode('/', trim($breadcrumbPath, '/'))));
    $runningPath = '';
?>
    <div class="stock-shell px-4 pt-4 sm:px-6 lg:px-8">
        <nav class="flex flex-wrap items-center gap-2 text-xs font-black uppercase tracking-[0.12em] text-neutral-500">
            <a href="/index.php" class="hover:text-red-600">Home</a>
            <?php foreach ($parts as $part): ?>
                <?php
                $runningPath .= '/' . $part;
                if (isset($breadcrumbLabels[$part])) {
                    $label = $breadcrumbLabels[$part];
                } else {
                    $label = ucwords(str_replace(['_', '.php'], [' ', ''], $part));
                }
                ?>
                <span class="text-neutral-300">/</span>
                <a href="<?= h($runningPath) ?>" class="hover:text-red-600"><?= h($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
<?php endif; ?>

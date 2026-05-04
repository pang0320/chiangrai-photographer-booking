<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$cards = [
    ['ลูกค้า', 'users', 'role_id = 1 AND deleted_at IS NULL', 'fa-users'],
    ['ช่างภาพ', 'photographer_profiles', 'deleted_at IS NULL', 'fa-camera-retro'],
    ['Pending', 'photographer_profiles', 'approval_status = "pending" AND deleted_at IS NULL', 'fa-user-clock'],
    ['Booking', 'bookings', 'deleted_at IS NULL', 'fa-calendar-check'],
    ['Review', 'reviews', 'deleted_at IS NULL', 'fa-star'],
    ['Article', 'photographer_articles', 'deleted_at IS NULL', 'fa-newspaper'],
];

$latestBookings = db_fetch_all('SELECT b.*, u.name AS customer_name, p.display_name
                                FROM bookings b
                                JOIN users u ON u.id = b.customer_id
                                JOIN photographer_profiles p ON p.id = b.photographer_id
                                WHERE b.deleted_at IS NULL
                                ORDER BY b.created_at DESC
                                LIMIT 8');
$logs = db_fetch_all('SELECT l.*, u.name
                      FROM activity_logs l
                      LEFT JOIN users u ON u.id = l.user_id
                      ORDER BY l.created_at DESC
                      LIMIT 8');
$monthly = db_fetch_all('SELECT DATE_FORMAT(created_at, "%Y-%m") AS ym, COUNT(*) AS total
                         FROM bookings
                         WHERE deleted_at IS NULL
                         GROUP BY ym
                         ORDER BY ym DESC
                         LIMIT 12');
$districts = db_fetch_all('SELECT d.district_name, COUNT(p.id) AS total
                           FROM districts d
                           LEFT JOIN photographer_profiles p ON p.main_district_id = d.id AND p.deleted_at IS NULL
                           GROUP BY d.id
                           ORDER BY total DESC
                           LIMIT 8');
$pendingPhotographers = db_fetch_all('SELECT p.*, u.email, u.phone, d.district_name
                                      FROM photographer_profiles p
                                      JOIN users u ON u.id = p.user_id
                                      LEFT JOIN districts d ON d.id = p.main_district_id
                                      WHERE p.approval_status = "pending" AND p.deleted_at IS NULL
                                      ORDER BY p.created_at DESC
                                      LIMIT 6');
$recentReviews = db_fetch_all('SELECT r.*, u.name AS customer_name, p.display_name
                               FROM reviews r
                               JOIN users u ON u.id = r.customer_id
                               JOIN photographer_profiles p ON p.id = r.photographer_id
                               WHERE r.deleted_at IS NULL
                               ORDER BY r.created_at DESC
                               LIMIT 6');
$topPhotographers = db_fetch_all('SELECT display_name, average_rating, total_reviews, profile_views
                                  FROM photographer_profiles
                                  WHERE deleted_at IS NULL
                                  ORDER BY average_rating DESC, total_reviews DESC
                                  LIMIT 6');
$topCategories = db_fetch_all('SELECT sc.name, COUNT(b.id) AS total
                               FROM service_categories sc
                               LEFT JOIN bookings b ON b.category_id = sc.id AND b.deleted_at IS NULL
                               GROUP BY sc.id
                               ORDER BY total DESC
                               LIMIT 6');
$popularSearchDistricts = db_fetch_all('SELECT d.district_name, COUNT(s.id) AS total
                                        FROM search_logs s
                                        JOIN districts d ON d.id = s.district_id
                                        GROUP BY d.id
                                        ORDER BY total DESC
                                        LIMIT 5');
$popularSearchCategories = db_fetch_all('SELECT sc.name, COUNT(s.id) AS total
                                         FROM search_logs s
                                         JOIN service_categories sc ON sc.id = s.category_id
                                         GROUP BY sc.id
                                         ORDER BY total DESC
                                         LIMIT 5');
$popularKeywords = db_fetch_all('SELECT keyword, COUNT(*) AS total
                                 FROM search_logs
                                 WHERE keyword IS NOT NULL AND keyword <> ""
                                 GROUP BY keyword
                                 ORDER BY total DESC
                                 LIMIT 5');
$maxMonthly = 1;
foreach ($monthly as $row) {
    if ((int)$row['total'] > $maxMonthly) {
        $maxMonthly = (int)$row['total'];
    }
}
$maxDistrict = 1;
foreach ($districts as $row) {
    if ((int)$row['total'] > $maxDistrict) {
        $maxDistrict = (int)$row['total'];
    }
}

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="dashboard-hero rounded-[2rem] p-6 text-white sm:p-8">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/58">Admin Console</p>
                <h1 class="mt-2 text-3xl font-black sm:text-5xl">Admin Dashboard</h1>
                <p class="mt-3 max-w-2xl leading-8 text-white/68">ภาพรวมระบบค้นหาและจองช่างภาพเชียงราย จัดการผู้ใช้ การอนุมัติ รายการจอง รีวิว และข้อมูลระบบ</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="/admin/photographers.php" class="rounded-full bg-white px-5 py-3 font-black text-neutral-950 hover:bg-red-600 hover:text-white"><i class="fa-solid fa-camera-retro mr-2"></i>อนุมัติช่างภาพ</a>
                <a href="/admin/bookings.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-calendar-check mr-2"></i>จัดการ Booking</a>
                <a href="/admin/reports.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-chart-line mr-2"></i>รายงาน</a>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <?php foreach ($cards as $config): ?>
            <div class="metric-card rounded-[1.5rem] p-5">
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm font-bold text-neutral-500"><?= h($config[0]) ?></p>
                    <i class="fa-solid <?= h($config[3]) ?> text-red-600"></i>
                </div>
                <p class="mt-3 text-3xl font-black text-neutral-950"><?= table_count($config[1], $config[2]) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
        <div class="stock-card rounded-[1.75rem] p-6">
            <div class="flex items-center justify-between gap-4">
                <div><p class="section-kicker">Monthly Bookings</p><h2 class="mt-1 text-2xl font-black text-neutral-950">Booking รายเดือน</h2></div>
            </div>
            <div class="mt-6 grid gap-3">
                <?php foreach (array_reverse($monthly) as $month): ?>
                    <?php $width = ((int)$month['total'] / $maxMonthly) * 100; ?>
                    <div>
                        <div class="mb-1 flex justify-between text-sm font-black"><span><?= h($month['ym']) ?></span><span><?= (int)$month['total'] ?></span></div>
                        <div class="rating-bar"><span style="width: <?= number_format($width, 0) ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">System Health</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950">สถานะระบบ</h2>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl bg-emerald-50 p-4"><p class="font-black text-emerald-700">PDO</p><p class="text-sm font-bold text-emerald-700">Prepared Statement</p></div>
                <div class="rounded-2xl bg-emerald-50 p-4"><p class="font-black text-emerald-700">CSRF</p><p class="text-sm font-bold text-emerald-700">Enabled</p></div>
                <div class="rounded-2xl bg-emerald-50 p-4"><p class="font-black text-emerald-700">Upload</p><p class="text-sm font-bold text-emerald-700">MIME validated</p></div>
                <div class="rounded-2xl bg-amber-50 p-4"><p class="font-black text-amber-700">HTTPS</p><p class="text-sm font-bold text-amber-700">ใช้ ENFORCE_HTTPS=1</p></div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[.8fr_1.2fr]">
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">District Chart</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950">ช่างภาพตามอำเภอ</h2>
            <div class="mt-5 grid gap-3">
                <?php foreach ($districts as $district): ?>
                    <?php $width = ((int)$district['total'] / $maxDistrict) * 100; ?>
                    <div>
                        <div class="mb-1 flex justify-between text-sm font-black"><span><?= h($district['district_name']) ?></span><span><?= (int)$district['total'] ?></span></div>
                        <div class="rating-bar"><span style="width: <?= number_format($width, 0) ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div><p class="section-kicker">Pending Approval</p><h2 class="mt-1 text-2xl font-black text-neutral-950">ช่างภาพรออนุมัติ</h2></div>
                <a href="/admin/photographers.php?status=pending" class="rounded-full border border-neutral-200 px-4 py-2 text-sm font-black hover:bg-neutral-950 hover:text-white"><i class="fa-solid fa-eye mr-2"></i>ดูทั้งหมด</a>
            </div>
            <div class="mt-5 grid gap-3">
                <?php foreach ($pendingPhotographers as $p): ?>
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-[1.5rem] bg-neutral-50 p-4">
                        <div>
                            <p class="font-black text-neutral-950"><?= h($p['display_name']) ?></p>
                            <p class="text-sm font-bold text-neutral-500"><?= h($p['email']) ?> · <?= h($p['district_name']) ?></p>
                        </div>
                        <a href="/admin/photographers.php" class="rounded-full bg-red-600 px-4 py-2 text-sm font-black text-white hover:bg-neutral-950"><i class="fa-solid fa-eye mr-1"></i>ตรวจสอบ</a>
                    </div>
                <?php endforeach; ?>
                <?php if (!$pendingPhotographers): ?>
                    <div class="empty-state rounded-[2rem] p-8 text-center font-bold text-neutral-600">ไม่มีช่างภาพรออนุมัติ</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="stock-card rounded-[1.75rem] p-6">
            <div class="flex justify-between gap-4"><div><p class="section-kicker">Recent Bookings</p><h2 class="mt-1 text-2xl font-black">รายการจองล่าสุด</h2></div><a class="text-sm font-black text-red-600" href="/admin/bookings.php"><i class="fa-solid fa-eye mr-1"></i>ดูทั้งหมด</a></div>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody>
                        <?php foreach ($latestBookings as $booking): ?>
                            <tr class="border-t border-neutral-100">
                                <td class="py-4 font-black"><?= h($booking['booking_code']) ?></td>
                                <td><?= h($booking['customer_name']) ?></td>
                                <td><?= h($booking['display_name']) ?></td>
                                <td><?= status_badge($booking['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Recent Reviews</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950">รีวิวล่าสุด</h2>
            <div class="mt-4 grid gap-3">
                <?php foreach ($recentReviews as $review): ?>
                    <div class="rounded-[1.5rem] bg-neutral-50 p-4">
                        <div class="flex justify-between gap-4"><b><?= h($review['customer_name']) ?></b><span class="text-red-600"><?= str_repeat('★', (int)$review['rating_overall']) ?></span></div>
                        <p class="mt-1 text-sm font-bold text-neutral-500"><?= h($review['display_name']) ?> · <?= status_badge($review['status']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Top Photographers</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950">ช่างภาพเด่น</h2>
            <div class="mt-4 grid gap-3">
                <?php foreach ($topPhotographers as $p): ?>
                    <div class="flex justify-between rounded-2xl bg-neutral-50 p-3 text-sm"><b><?= h($p['display_name']) ?></b><span><?= number_format((float)$p['average_rating'], 1) ?> ★</span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Top Categories</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950">หมวดหมู่ยอดนิยม</h2>
            <div class="mt-4 grid gap-3">
                <?php foreach ($topCategories as $category): ?>
                    <div class="flex justify-between rounded-2xl bg-neutral-50 p-3 text-sm"><b><?= h($category['name']) ?></b><span><?= (int)$category['total'] ?> งาน</span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Search Insight</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950">คำค้นยอดนิยม</h2>
            <div class="mt-4 grid gap-3">
                <?php foreach ($popularKeywords as $keyword): ?>
                    <div class="flex justify-between rounded-2xl bg-neutral-50 p-3 text-sm"><b><i class="fa-solid fa-magnifying-glass mr-1 text-red-600"></i><?= h($keyword['keyword']) ?></b><span><?= (int)$keyword['total'] ?></span></div>
                <?php endforeach; ?>
                <?php if (!$popularKeywords): ?>
                    <div class="empty-state rounded-2xl p-5 text-center text-sm font-bold text-neutral-600"><i class="fa-solid fa-magnifying-glass mr-1 text-red-600"></i>ยังไม่มีข้อมูลค้นหา</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Activity Logs</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950">Activity ล่าสุด</h2>
            <div class="mt-4 grid gap-2">
                <?php foreach ($logs as $log): ?>
                    <?php
                    $logName = 'System';
                    if (!empty($log['name'])) {
                        $logName = $log['name'];
                    }
                    ?>
                    <div class="rounded-2xl bg-neutral-50 p-3 text-sm">
                        <b><?= h($log['action']) ?></b>
                        <p class="mt-1 text-xs font-bold text-neutral-500"><?= h($log['created_at']) ?> · <?= h($logName) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Search Districts</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950"><i class="fa-solid fa-map-location-dot mr-2 text-red-600"></i>อำเภอที่ถูกค้นหามากสุด</h2>
            <div class="mt-4 grid gap-3">
                <?php foreach ($popularSearchDistricts as $district): ?>
                    <div class="flex justify-between rounded-2xl bg-neutral-50 p-3 text-sm"><b><?= h($district['district_name']) ?></b><span><?= (int)$district['total'] ?> ครั้ง</span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Search Categories</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950"><i class="fa-solid fa-layer-group mr-2 text-red-600"></i>ประเภทงานที่ถูกค้นหามากสุด</h2>
            <div class="mt-4 grid gap-3">
                <?php foreach ($popularSearchCategories as $category): ?>
                    <div class="flex justify-between rounded-2xl bg-neutral-50 p-3 text-sm"><b><?= h($category['name']) ?></b><span><?= (int)$category['total'] ?> ครั้ง</span></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

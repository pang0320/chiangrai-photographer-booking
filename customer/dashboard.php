<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('customer');
$user = current_user();

$stats = [];
foreach (['all' => '1=1', 'pending' => 'status="pending"', 'accepted' => 'status IN ("accepted","confirmed")', 'completed' => 'status="completed"'] as $key => $where) {
    $stmt = db()->prepare("SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND deleted_at IS NULL AND {$where}");
    $stmt->execute([(int)$user['id']]);
    $stats[$key] = (int)$stmt->fetchColumn();
}

$stmt = db()->prepare('SELECT b.*, p.display_name, sc.name category_name
                       FROM bookings b
                       JOIN photographer_profiles p ON p.id = b.photographer_id
                       JOIN service_categories sc ON sc.id = b.category_id
                       WHERE b.customer_id = ? AND b.deleted_at IS NULL
                       ORDER BY b.created_at DESC
                       LIMIT 8');
$stmt->execute([(int)$user['id']]);
$bookings = $stmt->fetchAll();

$reviewReminders = db_fetch_all('SELECT b.*, p.display_name
                                 FROM bookings b
                                 JOIN photographer_profiles p ON p.id = b.photographer_id
                                 LEFT JOIN reviews r ON r.booking_id = b.id AND r.deleted_at IS NULL
                                 WHERE b.customer_id = ?
                                   AND b.status = "completed"
                                   AND b.deleted_at IS NULL
                                   AND r.id IS NULL
                                 ORDER BY b.completed_at DESC
                                 LIMIT 3', [(int)$user['id']]);

$recommended = db_fetch_all('SELECT p.*, d.district_name,
                             (SELECT image_path FROM photographer_portfolios pp WHERE pp.photographer_id = p.id AND pp.deleted_at IS NULL ORDER BY pp.is_featured DESC, pp.sort_order ASC LIMIT 1) AS featured_image,
                             (SELECT GROUP_CONCAT(DISTINCT sc.name ORDER BY sc.sort_order SEPARATOR ", ") FROM photographer_services ps JOIN service_categories sc ON sc.id = ps.category_id WHERE ps.photographer_id = p.id AND ps.is_active = 1) AS services
                             FROM photographer_profiles p
                             JOIN users u ON u.id = p.user_id
                             LEFT JOIN districts d ON d.id = p.main_district_id
                             WHERE p.approval_status = "approved"
                               AND p.is_available = 1
                               AND u.status = "active"
                               AND p.deleted_at IS NULL
                               AND u.deleted_at IS NULL
                             ORDER BY p.average_rating DESC, p.total_reviews DESC, p.profile_views DESC
                             LIMIT 3');
$recentlyViewed = db_fetch_all('SELECT p.*, rv.viewed_at, d.district_name,
                                (SELECT image_path FROM photographer_portfolios pp WHERE pp.photographer_id = p.id AND pp.deleted_at IS NULL ORDER BY pp.is_featured DESC, pp.sort_order ASC LIMIT 1) AS featured_image
                                FROM recently_viewed_photographers rv
                                JOIN photographer_profiles p ON p.id = rv.photographer_id
                                LEFT JOIN districts d ON d.id = p.main_district_id
                                WHERE rv.user_id = ? AND p.deleted_at IS NULL
                                ORDER BY rv.viewed_at DESC
                                LIMIT 4', [(int)$user['id']]);

$pageTitle = 'แดชบอร์ดลูกค้า';
include __DIR__ . '/../includes/header.php';
?>
<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="dashboard-hero overflow-hidden rounded-[2rem] p-6 text-white sm:p-8">
        <div class="grid gap-6 lg:grid-cols-[1fr_360px] lg:items-center">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/58">พื้นที่ลูกค้า</p>
                <h1 class="mt-2 text-3xl font-black sm:text-5xl">สวัสดี <?= h($user['name']) ?></h1>
                <p class="mt-3 max-w-2xl leading-8 text-white/68">ค้นหาช่างภาพ ส่งคำขอจอง ติดตามสถานะ และรีวิวหลังงานเสร็จจากแดชบอร์ดเดียว</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="/photographers.php" class="rounded-full bg-white px-5 py-3 font-black text-neutral-950 hover:bg-red-600 hover:text-white"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหาช่างภาพ</a>
                    <a href="/customer/bookings.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-calendar-check mr-2"></i>ประวัติการจอง</a>
                    <a href="/customer/profile.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-user-pen mr-2"></i>แก้ไขโปรไฟล์</a>
                    <a href="/customer/favorites.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-heart mr-2"></i>ช่างภาพโปรด</a>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="stat-pill rounded-3xl p-4"><p class="text-3xl font-black"><?= (int)$stats['all'] ?></p><p class="text-sm font-bold text-white/68">คำขอทั้งหมด</p></div>
                <div class="stat-pill rounded-3xl p-4"><p class="text-3xl font-black"><?= (int)$stats['pending'] ?></p><p class="text-sm font-bold text-white/68">รอตอบรับ</p></div>
                <div class="stat-pill rounded-3xl p-4"><p class="text-3xl font-black"><?= (int)$stats['accepted'] ?></p><p class="text-sm font-bold text-white/68">ตอบรับแล้ว</p></div>
                <div class="stat-pill rounded-3xl p-4"><p class="text-3xl font-black"><?= (int)$stats['completed'] ?></p><p class="text-sm font-bold text-white/68">เสร็จสิ้น</p></div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.35fr_.65fr]">
        <div class="stock-card rounded-[1.75rem] p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="section-kicker">รายการจองล่าสุด</p>
                    <h2 class="mt-1 text-2xl font-black text-neutral-950">รายการจองล่าสุด</h2>
                </div>
                <a class="rounded-full border border-neutral-200 px-4 py-2 text-sm font-black hover:bg-neutral-950 hover:text-white" href="/customer/bookings.php"><i class="fa-solid fa-eye mr-2"></i>ดูทั้งหมด</a>
            </div>
            <div class="mt-5 overflow-x-auto">
                <?php if ($bookings): ?>
                    <table class="w-full text-left text-sm">
                        <thead class="text-neutral-500"><tr><th class="py-3">รหัสจอง</th><th>ช่างภาพ</th><th>ประเภท</th><th>วันที่</th><th>สถานะ</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($bookings as $b): ?>
                            <tr class="border-t border-neutral-100">
                                <td class="py-4 font-black"><?= h($b['booking_code']) ?></td>
                                <td class="font-bold"><?= h($b['display_name']) ?></td>
                                <td><?= h($b['category_name']) ?></td>
                                <td><?= h(format_be_date($b['booking_date'])) ?> · <?= h(time_slot_label($b['time_slot'])) ?></td>
                                <td><?= status_badge($b['status']) ?></td>
                                <td><a class="font-black text-red-600" href="/customer/booking_detail.php?id=<?= (int)$b['id'] ?>">ดู</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state rounded-[2rem] p-10 text-center">
                        <i class="fa-solid fa-calendar-plus text-4xl text-red-600"></i>
                        <h3 class="mt-3 text-xl font-black">ยังไม่มีรายการจอง</h3>
                        <p class="mt-2 text-neutral-600">เริ่มจากค้นหาช่างภาพและส่งคำขอจองแรกของคุณ</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid gap-6">
            <div class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker">แจ้งเตือนรีวิว</p>
                <h2 class="mt-1 text-xl font-black text-neutral-950">งานที่รอรีวิว</h2>
                <div class="mt-4 grid gap-3">
                    <?php foreach ($reviewReminders as $item): ?>
                        <a href="/customer/review.php?booking_id=<?= (int)$item['id'] ?>" class="rounded-2xl bg-red-50 p-4 font-bold text-red-700 hover:bg-red-600 hover:text-white">
                            <?= h($item['booking_code']) ?> · <?= h($item['display_name']) ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$reviewReminders): ?>
                        <div class="rounded-2xl bg-neutral-50 p-5 text-sm font-bold text-neutral-600">ยังไม่มีงานเสร็จสิ้นที่รอรีวิว</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker">ลำดับสถานะการจอง</p>
                <h2 class="mt-1 text-xl font-black text-neutral-950">สถานะการจอง</h2>
                <div class="mt-4 grid gap-3">
                    <?php foreach ([['fa-paper-plane','ส่งคำขอจอง','pending'],['fa-handshake','ช่างภาพตอบรับ','accepted'],['fa-circle-check','ยืนยันงาน','confirmed'],['fa-star','รีวิวหลังงานเสร็จ','completed']] as $step): ?>
                        <div class="flex items-center gap-3 rounded-2xl bg-neutral-50 p-3">
                            <div class="grid h-10 w-10 place-items-center rounded-xl bg-neutral-950 text-white"><i class="fa-solid <?= h($step[0]) ?>"></i></div>
                            <div><p class="font-black"><?= h($step[1]) ?></p><p class="text-xs font-bold text-neutral-500"><?= h(booking_status_label($step[2])) ?></p></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_360px]">
        <div>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="section-kicker">แนะนำสำหรับคุณ</p>
                    <h2 class="mt-1 text-2xl font-black text-neutral-950">ช่างภาพแนะนำสำหรับคุณ</h2>
                </div>
            </div>
            <div class="mt-5 grid gap-6 md:grid-cols-3">
                <?php foreach ($recommended as $p): ?>
                    <?php include __DIR__ . '/../includes/photographer_card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">คำแนะนำ</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950">เตรียมตัวก่อนถ่ายภาพ</h2>
            <div class="mt-4 grid gap-3 text-sm leading-7 text-neutral-700">
                <p class="rounded-2xl bg-neutral-50 p-4"><b>1.</b> เตรียม reference ภาพและ mood board ให้ช่างภาพดู</p>
                <p class="rounded-2xl bg-neutral-50 p-4"><b>2.</b> แจ้งจำนวนคน สถานที่ เวลา และช่องทางติดต่อให้ครบ</p>
                <p class="rounded-2xl bg-red-50 p-4 font-bold text-red-700"><?= h(PAYMENT_DISCLAIMER) ?></p>
            </div>
        </div>
    </div>

    <div class="mt-6 stock-card rounded-[1.75rem] p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><p class="section-kicker">ช่างภาพที่เคยดู</p><h2 class="mt-1 text-2xl font-black">ช่างภาพที่เคยดู</h2></div>
            <a href="/customer/recently_viewed.php" class="rounded-full border border-neutral-200 px-4 py-2 text-sm font-black hover:bg-neutral-950 hover:text-white"><i class="fa-solid fa-eye mr-2"></i>ดูทั้งหมด</a>
        </div>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($recentlyViewed as $item): ?>
                <a href="/photographer_detail.php?id=<?= (int)$item['id'] ?>" class="rounded-[1.5rem] bg-neutral-50 p-3 hover:bg-red-50">
                    <img class="h-36 w-full rounded-[1.2rem] object-cover" src="<?= h(public_image($item['featured_image'], '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg')) ?>" alt="">
                    <b class="mt-3 block"><?= h($item['display_name']) ?></b>
                    <span class="text-sm font-bold text-neutral-500"><i class="fa-solid fa-location-dot mr-1 text-red-600"></i><?= h($item['district_name']) ?></span>
                </a>
            <?php endforeach; ?>
            <?php if (!$recentlyViewed): ?>
                <div class="empty-state rounded-[1.5rem] p-8 text-center sm:col-span-2 xl:col-span-4">
                    <i class="fa-solid fa-clock-rotate-left text-4xl text-red-600"></i>
                    <p class="mt-3 font-black">ยังไม่มีประวัติการดู</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>

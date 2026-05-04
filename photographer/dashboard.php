<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');

$user = current_user();
$profile = photographer_profile_by_user((int)$user['id']);

if (!$profile) {
    exit('Profile not found');
}

$pid = (int)$profile['id'];
$stats = [];

foreach (['pending' => 'status = "pending"', 'accepted' => 'status IN ("accepted","confirmed")', 'completed' => 'status = "completed"'] as $key => $where) {
    $stmt = db()->prepare("SELECT COUNT(*) FROM bookings WHERE photographer_id = ? AND deleted_at IS NULL AND {$where}");
    $stmt->execute([$pid]);
    $stats[$key] = (int)$stmt->fetchColumn();
}

$stmt = db()->prepare('SELECT b.*, u.name AS customer_name, sc.name AS category_name
                       FROM bookings b
                       JOIN users u ON u.id = b.customer_id
                       JOIN service_categories sc ON sc.id = b.category_id
                       WHERE b.photographer_id = ? AND b.deleted_at IS NULL
                       ORDER BY b.created_at DESC
                       LIMIT 8');
$stmt->execute([$pid]);
$bookings = $stmt->fetchAll();

$availability = db_fetch_all('SELECT * FROM photographer_availability WHERE photographer_id = ? AND available_date >= CURDATE() ORDER BY available_date, time_slot LIMIT 8', [$pid]);
$latestReviews = db_fetch_all('SELECT r.*, u.name AS customer_name
                               FROM reviews r
                               JOIN users u ON u.id = r.customer_id
                               WHERE r.photographer_id = ? AND r.status = "visible" AND r.deleted_at IS NULL
                               ORDER BY r.created_at DESC
                               LIMIT 4', [$pid]);
$portfolioStats = db_fetch_all('SELECT COUNT(*) AS total, SUM(is_featured = 1) AS featured FROM photographer_portfolios WHERE photographer_id = ? AND deleted_at IS NULL', [$pid]);
$portfolioTotal = 0;
$portfolioFeatured = 0;
if ($portfolioStats) {
    $portfolioTotal = (int)$portfolioStats[0]['total'];
    $portfolioFeatured = (int)$portfolioStats[0]['featured'];
}
$completionPercent = photographer_completion_percent($pid);

$pageTitle = 'Photographer Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="dashboard-hero overflow-hidden rounded-[2rem] p-6 text-white sm:p-8">
        <div class="grid gap-6 xl:grid-cols-[1fr_420px] xl:items-center">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/58">Photographer Studio</p>
                <h1 class="mt-2 text-3xl font-black sm:text-5xl"><?= h($profile['display_name']) ?></h1>
                <div class="mt-3 flex flex-wrap gap-2">
                    <?= status_badge($profile['approval_status']) ?>
                    <span class="rounded-full bg-white/12 px-3 py-1 text-xs font-black text-white"><i class="fa-solid fa-eye mr-1 text-red-300"></i><?= (int)$profile['profile_views'] ?> views</span>
                    <span class="rounded-full bg-white/12 px-3 py-1 text-xs font-black text-white"><i class="fa-solid fa-star mr-1 text-yellow-300"></i><?= number_format((float)$profile['average_rating'], 1) ?></span>
                </div>
                <p class="mt-4 max-w-2xl leading-8 text-white/68">จัดการโปรไฟล์ ผลงาน วันว่าง และคำขอจองของคุณให้พร้อมรับลูกค้าจากทุกอำเภอในเชียงราย</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="/photographer/portfolio.php" class="rounded-full bg-white px-5 py-3 font-black text-neutral-950 hover:bg-red-600 hover:text-white"><i class="fa-solid fa-images mr-2"></i>เพิ่มผลงาน</a>
                    <a href="/photographer/availability.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-calendar-plus mr-2"></i>เพิ่มวันว่าง</a>
                    <a href="/photographer/profile.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-user-pen mr-2"></i>แก้ไขโปรไฟล์</a>
                    <a href="/photographer/articles.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-pen-nib mr-2"></i>เขียนบทความ</a>
                </div>
            </div>
            <div class="stat-pill rounded-[2rem] p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-black uppercase tracking-[0.18em] text-white/52">Profile Completion</p>
                        <p class="mt-2 text-5xl font-black"><?= $completionPercent ?>%</p>
                    </div>
                    <div class="grid h-20 w-20 place-items-center rounded-[1.5rem] bg-white text-3xl text-red-600"><i class="fa-solid fa-gauge-high"></i></div>
                </div>
                <div class="mt-5 h-3 overflow-hidden rounded-full bg-white/18"><div class="h-full rounded-full bg-red-500" style="width: <?= $completionPercent ?>%"></div></div>
                <p class="mt-4 text-sm font-bold text-white/68">กรอกข้อมูลให้ครบเพื่อเพิ่มความน่าเชื่อถือและโอกาสรับงาน</p>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <?php foreach ([['คำขอใหม่', $stats['pending'], 'fa-bell'], ['ตอบรับแล้ว', $stats['accepted'], 'fa-handshake'], ['เสร็จสิ้น', $stats['completed'], 'fa-circle-check'], ['คะแนน', number_format((float)$profile['average_rating'], 1), 'fa-star'], ['รีวิว', $profile['total_reviews'], 'fa-comments'], ['Profile views', $profile['profile_views'], 'fa-eye']] as $card): ?>
            <div class="metric-card rounded-[1.5rem] p-5">
                <div class="flex items-center justify-between gap-4"><p class="text-sm font-bold text-neutral-500"><?= h($card[0]) ?></p><i class="fa-solid <?= h($card[2]) ?> text-red-600"></i></div>
                <p class="mt-3 text-3xl font-black text-neutral-950"><?= h((string)$card[1]) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.3fr_.7fr]">
        <div class="stock-card rounded-[1.75rem] p-6">
            <div class="flex flex-wrap justify-between gap-4">
                <div>
                    <p class="section-kicker">Recent Requests</p>
                    <h2 class="mt-1 text-2xl font-black text-neutral-950">รายการจองล่าสุด</h2>
                </div>
                <a class="rounded-full border border-neutral-200 px-4 py-2 text-sm font-black hover:bg-neutral-950 hover:text-white" href="/photographer/bookings.php"><i class="fa-solid fa-eye mr-2"></i>ดูทั้งหมด</a>
            </div>
            <div class="mt-5 overflow-x-auto">
                <?php if ($bookings): ?>
                    <table class="w-full text-left text-sm">
                        <thead class="text-neutral-500"><tr><th class="py-3">Code</th><th>ลูกค้า</th><th>ประเภท</th><th>วันที่</th><th>สถานะ</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr class="border-t border-neutral-100">
                                    <td class="py-4 font-black"><?= h($booking['booking_code']) ?></td>
                                    <td class="font-bold"><?= h($booking['customer_name']) ?></td>
                                    <td><?= h($booking['category_name']) ?></td>
                                    <td><?= h($booking['booking_date']) ?> · <?= h(time_slot_label($booking['time_slot'])) ?></td>
                                    <td><?= status_badge($booking['status']) ?></td>
                                    <td><a class="font-black text-red-600" href="/photographer/booking_detail.php?id=<?= (int)$booking['id'] ?>">ดู</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state rounded-[2rem] p-10 text-center">
                        <i class="fa-solid fa-inbox text-4xl text-red-600"></i>
                        <h3 class="mt-3 text-xl font-black">ยังไม่มีคำขอจอง</h3>
                        <p class="mt-2 text-neutral-600">เมื่อมีลูกค้าส่งคำขอจอง รายการจะแสดงที่นี่ทันที</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid gap-6">
            <div class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker">Calendar Preview</p>
                <h2 class="mt-1 text-xl font-black text-neutral-950">วันว่างล่าสุด</h2>
                <div class="mt-4 grid gap-2">
                    <?php foreach ($availability as $slot): ?>
                        <div class="flex items-center justify-between rounded-2xl bg-neutral-50 px-4 py-3 text-sm">
                            <span class="font-black"><?= h($slot['available_date']) ?></span>
                            <span class="font-bold text-neutral-500"><?= h(time_slot_label($slot['time_slot'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$availability): ?>
                        <div class="rounded-2xl bg-red-50 p-5 text-sm font-bold text-red-700">ยังไม่มีวันว่าง เพิ่มวันว่างเพื่อให้ลูกค้าจองได้</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stock-card rounded-[1.75rem] p-6">
                <p class="section-kicker">Portfolio Performance</p>
                <h2 class="mt-1 text-xl font-black text-neutral-950">ผลงานในโปรไฟล์</h2>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-neutral-50 p-4"><p class="text-3xl font-black"><?= $portfolioTotal ?></p><p class="text-sm font-bold text-neutral-500">รูปทั้งหมด</p></div>
                    <div class="rounded-2xl bg-neutral-50 p-4"><p class="text-3xl font-black"><?= $portfolioFeatured ?></p><p class="text-sm font-bold text-neutral-500">Featured</p></div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_380px]">
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">Latest Reviews</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950">รีวิวล่าสุด</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <?php foreach ($latestReviews as $review): ?>
                    <article class="rounded-[1.5rem] bg-neutral-50 p-5">
                        <div class="flex items-center justify-between gap-4"><b><?= h($review['customer_name']) ?></b><span class="text-red-600"><?= str_repeat('★', (int)$review['rating_overall']) ?></span></div>
                        <p class="mt-3 line-clamp-3 text-sm leading-7 text-neutral-700"><?= h($review['comment']) ?></p>
                    </article>
                <?php endforeach; ?>
                <?php if (!$latestReviews): ?>
                    <div class="empty-state rounded-[2rem] p-8 text-center md:col-span-2">ยังไม่มีรีวิวจากลูกค้า</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] bg-red-50 p-6">
            <p class="section-kicker">Tips</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950">เพิ่มโอกาสรับงาน</h2>
            <div class="mt-4 grid gap-3 text-sm font-bold leading-7 text-neutral-700">
                <p class="rounded-2xl bg-white p-4">อัปโหลด Portfolio อย่างน้อย 8-12 รูป และตั้งรูปเด่นให้ชัดเจน</p>
                <p class="rounded-2xl bg-white p-4">เปิดวันว่างล่วงหน้า 2-4 สัปดาห์ เพื่อให้ลูกค้าจองได้ง่าย</p>
                <p class="rounded-2xl bg-white p-4">ระบุช่องทางติดต่อและราคาเริ่มต้นให้ครบ ลดคำถามซ้ำก่อนจอง</p>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

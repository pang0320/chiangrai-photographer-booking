<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$cards = [
    ['ลูกค้า', 'users', 'role_id = 1 AND deleted_at IS NULL', 'fa-users'],
    ['ช่างภาพ', 'photographer_profiles', 'deleted_at IS NULL', 'fa-camera-retro'],
    ['รออนุมัติ', 'photographer_profiles', 'approval_status = "pending" AND deleted_at IS NULL', 'fa-user-clock'],
    ['คำขอจอง', 'bookings', 'deleted_at IS NULL', 'fa-calendar-check'],
    ['รีวิว', 'reviews', 'deleted_at IS NULL', 'fa-star'],
    ['บทความ', 'photographer_articles', 'deleted_at IS NULL', 'fa-newspaper'],
];

$latestBookings = db_fetch_all('SELECT b.*,
                                       u.name AS customer_name,
                                       u.email AS customer_email,
                                       u.phone AS customer_phone,
                                       p.display_name,
                                       p.phone_public,
                                       p.line_id,
                                       p.facebook_url,
                                       p.instagram_url,
                                       sc.name AS category_name,
                                       d.district_name
                                FROM bookings b
                                JOIN users u ON u.id = b.customer_id
                                JOIN photographer_profiles p ON p.id = b.photographer_id
                                JOIN service_categories sc ON sc.id = b.category_id
                                JOIN districts d ON d.id = b.district_id
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

$pageTitle = 'แดชบอร์ดผู้ดูแลระบบ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="dashboard-hero rounded-[2rem] p-6 text-white sm:p-8">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/58">ผู้ดูแลระบบ</p>
                <h1 class="mt-2 text-3xl font-black sm:text-5xl">แดชบอร์ดผู้ดูแลระบบ</h1>
                <p class="mt-3 max-w-2xl leading-8 text-white/68">ภาพรวมระบบค้นหาและจองช่างภาพเชียงราย จัดการผู้ใช้ การอนุมัติ รายการจอง รีวิว และข้อมูลระบบ</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="/admin/photographers.php" class="rounded-full bg-white px-5 py-3 font-black text-neutral-950 hover:bg-red-600 hover:text-white"><i class="fa-solid fa-camera-retro mr-2"></i>อนุมัติช่างภาพ</a>
                <a href="/admin/bookings.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-calendar-check mr-2"></i>จัดการคำขอจอง</a>
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
                <div><p class="section-kicker">คำขอจองรายเดือน</p><h2 class="mt-1 text-2xl font-black text-neutral-950">สรุปคำขอจองรายเดือน</h2></div>
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
            <p class="section-kicker">สถานะระบบ</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950">สถานะระบบ</h2>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl bg-emerald-50 p-4"><p class="font-black text-emerald-700">PDO</p><p class="text-sm font-bold text-emerald-700">ใช้ Prepared Statement</p></div>
                <div class="rounded-2xl bg-emerald-50 p-4"><p class="font-black text-emerald-700">CSRF</p><p class="text-sm font-bold text-emerald-700">เปิดใช้งานแล้ว</p></div>
                <div class="rounded-2xl bg-emerald-50 p-4"><p class="font-black text-emerald-700">อัปโหลด</p><p class="text-sm font-bold text-emerald-700">ตรวจ MIME แล้ว</p></div>
                <div class="rounded-2xl bg-amber-50 p-4"><p class="font-black text-amber-700">HTTPS</p><p class="text-sm font-bold text-amber-700">ใช้ ENFORCE_HTTPS=1</p></div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[.8fr_1.2fr]">
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">กราฟอำเภอ</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950">ช่างภาพตามอำเภอ</h2>
            <div class="mt-5 grid gap-3" data-block-paginate="5">
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
                <div><p class="section-kicker">รออนุมัติ</p><h2 class="mt-1 text-2xl font-black text-neutral-950">ช่างภาพรออนุมัติ</h2></div>
                <?= clean_context_button('/admin/photographers.php', ['status' => 'pending'], '<i class="fa-solid fa-eye mr-2"></i>ดูทั้งหมด', 'rounded-full border border-neutral-200 px-4 py-2 text-sm font-black hover:bg-neutral-950 hover:text-white') ?>
            </div>
            <div class="mt-5 grid gap-3" data-block-paginate="5">
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
            <div class="flex justify-between gap-4"><div><p class="section-kicker">คำขอจองล่าสุด</p><h2 class="mt-1 text-2xl font-black">รายการจองล่าสุด</h2></div><a class="text-sm font-black text-red-600" href="/admin/bookings.php"><i class="fa-solid fa-eye mr-1"></i>ดูทั้งหมด</a></div>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full table-fixed text-sm">
                    <colgroup>
                        <col class="w-[28%]">
                        <col class="w-[18%]">
                        <col class="w-[27%]">
                        <col class="w-[27%]">
                    </colgroup>
                    <tbody data-block-paginate="5">
                        <?php foreach ($latestBookings as $booking): ?>
                            <tr class="border-t border-neutral-100 transition hover:bg-red-50/50">
                                <td class="py-4 pr-3 align-middle">
                                    <button type="button" data-booking-modal-open="admin-booking-modal-<?= (int)$booking['id'] ?>" class="text-left font-black text-neutral-950 hover:text-red-600">
                                        <i class="fa-solid fa-calendar-check mr-2 text-red-600"></i><?= h($booking['booking_code']) ?>
                                    </button>
                                    <p class="mt-1 text-xs font-bold text-neutral-500"><?= h(format_be_date($booking['booking_date'])) ?> · <?= h(time_slot_label($booking['time_slot'])) ?></p>
                                </td>
                                <td class="pr-3 align-middle">
                                    <button type="button" data-booking-modal-open="admin-booking-modal-<?= (int)$booking['id'] ?>" class="text-left font-bold hover:text-red-600">
                                        <?= h($booking['customer_name']) ?>
                                    </button>
                                </td>
                                <td class="pr-3 align-middle">
                                    <button type="button" data-booking-modal-open="admin-booking-modal-<?= (int)$booking['id'] ?>" class="text-left font-bold hover:text-red-600">
                                        <?= h($booking['display_name']) ?>
                                    </button>
                                    <p class="mt-1 text-xs font-bold text-neutral-500"><?= h($booking['category_name']) ?> · <?= h($booking['district_name']) ?></p>
                                </td>
                                <td class="align-middle">
                                    <div class="grid min-w-[210px] grid-cols-[96px_104px] items-center justify-end gap-2">
                                        <div class="flex justify-center">
                                            <?= status_badge($booking['status']) ?>
                                        </div>
                                        <button type="button" data-booking-modal-open="admin-booking-modal-<?= (int)$booking['id'] ?>" class="inline-flex w-[104px] items-center justify-center rounded-full bg-neutral-950 px-3 py-1.5 text-xs font-black text-white transition hover:bg-red-600">
                                            <i class="fa-solid fa-eye mr-1"></i>รายละเอียด
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">รีวิวล่าสุด</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950">รีวิวล่าสุด</h2>
            <div class="mt-4 grid gap-3" data-block-paginate="5">
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
            <p class="section-kicker">ช่างภาพยอดนิยม</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950">ช่างภาพเด่น</h2>
            <div class="mt-4 grid gap-3" data-block-paginate="5">
                <?php foreach ($topPhotographers as $p): ?>
                    <div class="flex justify-between rounded-2xl bg-neutral-50 p-3 text-sm"><b><?= h($p['display_name']) ?></b><span><?= number_format((float)$p['average_rating'], 1) ?> ★</span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">หมวดหมู่ยอดนิยม</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950">หมวดหมู่ยอดนิยม</h2>
            <div class="mt-4 grid gap-3" data-block-paginate="5">
                <?php foreach ($topCategories as $category): ?>
                    <div class="flex justify-between rounded-2xl bg-neutral-50 p-3 text-sm"><b><?= h($category['name']) ?></b><span><?= (int)$category['total'] ?> งาน</span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">ข้อมูลการค้นหา</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950">คำค้นยอดนิยม</h2>
            <div class="mt-4 grid gap-3" data-block-paginate="5">
                <?php foreach ($popularKeywords as $keyword): ?>
                    <div class="flex justify-between rounded-2xl bg-neutral-50 p-3 text-sm"><b><i class="fa-solid fa-magnifying-glass mr-1 text-red-600"></i><?= h($keyword['keyword']) ?></b><span><?= (int)$keyword['total'] ?></span></div>
                <?php endforeach; ?>
                <?php if (!$popularKeywords): ?>
                    <div class="empty-state rounded-2xl p-5 text-center text-sm font-bold text-neutral-600"><i class="fa-solid fa-magnifying-glass mr-1 text-red-600"></i>ยังไม่มีข้อมูลค้นหา</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">ประวัติการใช้งาน</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950">กิจกรรมล่าสุด</h2>
            <div class="mt-4 grid gap-2" data-block-paginate="5">
                <?php foreach ($logs as $log): ?>
                    <?php
                    $logName = 'ระบบ';
                    if (!empty($log['name'])) {
                        $logName = $log['name'];
                    }
                    ?>
                    <div class="rounded-2xl bg-neutral-50 p-3 text-sm">
                        <b><?= h($log['action']) ?></b>
                        <p class="mt-1 text-xs font-bold text-neutral-500"><?= h(format_be_datetime($log['created_at'])) ?> · <?= h($logName) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">อำเภอที่ค้นหา</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950"><i class="fa-solid fa-map-location-dot mr-2 text-red-600"></i>อำเภอที่ถูกค้นหามากสุด</h2>
            <div class="mt-4 grid gap-3" data-block-paginate="5">
                <?php foreach ($popularSearchDistricts as $district): ?>
                    <div class="flex justify-between rounded-2xl bg-neutral-50 p-3 text-sm"><b><?= h($district['district_name']) ?></b><span><?= (int)$district['total'] ?> ครั้ง</span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="stock-card rounded-[1.75rem] p-6">
            <p class="section-kicker">ประเภทงานที่ค้นหา</p>
            <h2 class="mt-1 text-xl font-black text-neutral-950"><i class="fa-solid fa-layer-group mr-2 text-red-600"></i>ประเภทงานที่ถูกค้นหามากสุด</h2>
            <div class="mt-4 grid gap-3" data-block-paginate="5">
                <?php foreach ($popularSearchCategories as $category): ?>
                    <div class="flex justify-between rounded-2xl bg-neutral-50 p-3 text-sm"><b><?= h($category['name']) ?></b><span><?= (int)$category['total'] ?> ครั้ง</span></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php foreach ($latestBookings as $booking): ?>
    <?php
    $bookingLogs = db_fetch_all('SELECT l.*, u.name AS changed_by_name
                                 FROM booking_status_logs l
                                 LEFT JOIN users u ON u.id = l.changed_by
                                 WHERE l.booking_id = ?
                                 ORDER BY l.created_at ASC', [(int)$booking['id']]);
    $contactChannel = '-';
    if (!empty($booking['contact_channel'])) {
        $contactChannel = $booking['contact_channel'];
    }
    $bookingNote = '-';
    if (!empty($booking['note'])) {
        $bookingNote = $booking['note'];
    }
    $rejectionReason = '-';
    if (!empty($booking['rejection_reason'])) {
        $rejectionReason = $booking['rejection_reason'];
    }
    $completedAt = '-';
    if (!empty($booking['completed_at'])) {
        $completedAt = format_be_datetime($booking['completed_at']);
    }
    ?>
    <div id="admin-booking-modal-<?= (int)$booking['id'] ?>" class="admin-booking-modal fixed inset-0 z-[95] hidden items-center justify-center bg-neutral-950/75 px-4 py-8 backdrop-blur-sm" aria-hidden="true">
        <div data-booking-modal-close class="absolute inset-0"></div>
        <div class="relative max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-[2rem] bg-white shadow-2xl">
            <div class="sticky top-0 z-10 bg-gradient-to-r from-neutral-950 via-slate-900 to-red-700 p-6 text-white">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.22em] text-red-200"><i class="fa-solid fa-calendar-check mr-2"></i>รายละเอียดคำขอจอง</p>
                        <h2 class="mt-2 text-3xl font-black"><?= h($booking['booking_code']) ?></h2>
                        <p class="mt-2 text-sm font-bold text-white/70">
                            <?= h($booking['customer_name']) ?> จอง <?= h($booking['display_name']) ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <?= status_badge($booking['status']) ?>
                        <button type="button" data-booking-modal-close class="grid h-11 w-11 place-items-center rounded-full bg-white/12 text-white transition hover:bg-white hover:text-neutral-950" aria-label="ปิดรายละเอียดคำขอจอง">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 p-6 lg:grid-cols-[1.2fr_.8fr]">
                <div class="grid gap-5">
                    <div class="rounded-[1.5rem] border border-neutral-200 bg-neutral-50 p-5">
                        <h3 class="text-lg font-black text-neutral-950"><i class="fa-solid fa-camera mr-2 text-red-600"></i>ข้อมูลงาน</h3>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl bg-white p-4">
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">ประเภทงาน</p>
                                <p class="mt-1 font-black text-neutral-950"><?= h($booking['category_name']) ?></p>
                            </div>
                            <div class="rounded-2xl bg-white p-4">
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">อำเภอ</p>
                                <p class="mt-1 font-black text-neutral-950"><?= h($booking['district_name']) ?></p>
                            </div>
                            <div class="rounded-2xl bg-white p-4">
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">วันที่ถ่าย</p>
                                <p class="mt-1 font-black text-neutral-950"><?= h(format_be_date($booking['booking_date'])) ?></p>
                            </div>
                            <div class="rounded-2xl bg-white p-4">
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">ช่วงเวลา</p>
                                <p class="mt-1 font-black text-neutral-950"><?= h(time_slot_label($booking['time_slot'])) ?></p>
                            </div>
                        </div>
                        <div class="mt-4 rounded-2xl bg-white p-4">
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">รายละเอียดงาน</p>
                            <p class="mt-2 whitespace-pre-line leading-7 text-neutral-700"><?= h($booking['job_detail']) ?></p>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl bg-white p-4">
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">หมายเหตุ</p>
                                <p class="mt-1 font-bold text-neutral-700"><?= h($bookingNote) ?></p>
                            </div>
                            <div class="rounded-2xl bg-white p-4">
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">เหตุผลปฏิเสธ</p>
                                <p class="mt-1 font-bold text-neutral-700"><?= h($rejectionReason) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[1.5rem] border border-neutral-200 p-5">
                        <h3 class="text-lg font-black text-neutral-950"><i class="fa-solid fa-clock-rotate-left mr-2 text-red-600"></i>ประวัติสถานะ</h3>
                        <div class="mt-4 grid gap-3">
                            <?php foreach ($bookingLogs as $log): ?>
                                <?php
                                $oldStatusText = '-';
                                if (!empty($log['old_status'])) {
                                    $oldStatusText = booking_status_label((string)$log['old_status']);
                                }
                                $changedBy = 'ระบบ';
                                if (!empty($log['changed_by_name'])) {
                                    $changedBy = $log['changed_by_name'];
                                }
                                ?>
                                <div class="rounded-2xl bg-neutral-50 p-4 text-sm">
                                    <p class="font-black text-neutral-950">
                                        <?= h($oldStatusText) ?> <i class="fa-solid fa-arrow-right mx-2 text-red-600"></i> <?= h(booking_status_label((string)$log['new_status'])) ?>
                                    </p>
                                    <p class="mt-1 font-bold text-neutral-500"><?= h(format_be_datetime($log['created_at'])) ?> · โดย <?= h($changedBy) ?></p>
                                    <?php if (!empty($log['note'])): ?>
                                        <p class="mt-2 text-neutral-600"><?= h($log['note']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$bookingLogs): ?>
                                <div class="empty-state rounded-2xl p-6 text-center text-sm font-bold text-neutral-600">
                                    <i class="fa-solid fa-clipboard-list text-3xl text-red-600"></i>
                                    <p class="mt-2">ยังไม่มีประวัติสถานะ</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5">
                    <div class="rounded-[1.5rem] border border-neutral-200 p-5">
                        <h3 class="text-lg font-black text-neutral-950"><i class="fa-solid fa-user mr-2 text-red-600"></i>ข้อมูลลูกค้า</h3>
                        <div class="mt-4 grid gap-3 text-sm font-bold text-neutral-700">
                            <p><i class="fa-solid fa-user mr-2 text-red-600"></i><?= h($booking['customer_name']) ?></p>
                            <p><i class="fa-solid fa-envelope mr-2 text-red-600"></i><?= h($booking['customer_email']) ?></p>
                            <p><i class="fa-solid fa-phone mr-2 text-red-600"></i><?= h($booking['contact_phone']) ?></p>
                            <p><i class="fa-solid fa-comment mr-2 text-red-600"></i><?= h($contactChannel) ?></p>
                            <p><i class="fa-solid fa-id-card mr-2 text-red-600"></i><?= h($booking['contact_name']) ?></p>
                        </div>
                    </div>

                    <div class="rounded-[1.5rem] border border-neutral-200 p-5">
                        <h3 class="text-lg font-black text-neutral-950"><i class="fa-solid fa-camera-retro mr-2 text-red-600"></i>ข้อมูลช่างภาพ</h3>
                        <div class="mt-4 grid gap-3 text-sm font-bold text-neutral-700">
                            <p><i class="fa-solid fa-camera mr-2 text-red-600"></i><?= h($booking['display_name']) ?></p>
                            <p><i class="fa-solid fa-phone mr-2 text-red-600"></i><?= h($booking['phone_public']) ?></p>
                            <p><i class="fa-brands fa-line mr-2 text-red-600"></i><?= h($booking['line_id']) ?></p>
                            <p><i class="fa-brands fa-facebook mr-2 text-red-600"></i><?= h($booking['facebook_url']) ?></p>
                            <p><i class="fa-brands fa-instagram mr-2 text-red-600"></i><?= h($booking['instagram_url']) ?></p>
                        </div>
                    </div>

                    <div class="rounded-[1.5rem] bg-neutral-950 p-5 text-white">
                        <h3 class="text-lg font-black"><i class="fa-solid fa-circle-info mr-2 text-red-300"></i>สรุประบบ</h3>
                        <div class="mt-4 grid gap-3 text-sm font-bold text-white/72">
                            <p><i class="fa-solid fa-calendar-plus mr-2 text-red-300"></i>สร้างเมื่อ <?= h(format_be_datetime($booking['created_at'])) ?></p>
                            <p><i class="fa-solid fa-pen mr-2 text-red-300"></i>อัปเดตล่าสุด <?= h(format_be_datetime($booking['updated_at'])) ?></p>
                            <p><i class="fa-solid fa-circle-check mr-2 text-red-300"></i>เสร็จสิ้นเมื่อ <?= h($completedAt) ?></p>
                        </div>
                        <a href="/admin/bookings.php" class="mt-5 inline-flex rounded-full bg-white px-4 py-2 text-sm font-black text-neutral-950 transition hover:bg-red-600 hover:text-white">
                            <i class="fa-solid fa-pen mr-2"></i>ไปหน้าจัดการสถานะ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function openBookingModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    }

    function closeBookingModal(modal) {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    }

    document.querySelectorAll('[data-booking-modal-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            openBookingModal(button.getAttribute('data-booking-modal-open'));
        });
    });

    document.querySelectorAll('[data-booking-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeBookingModal(button.closest('.admin-booking-modal'));
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.admin-booking-modal.flex').forEach(function (modal) {
            closeBookingModal(modal);
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

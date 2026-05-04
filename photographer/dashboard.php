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

$pageTitle = 'Photographer Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Photographer Studio</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950">แดชบอร์ดช่างภาพ</h1>
            <p class="text-neutral-600"><?= h($profile['display_name']) ?> · <?= status_badge($profile['approval_status']) ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/photographer/profile.php" class="rounded-full bg-neutral-950 px-4 py-3 font-black text-white hover:bg-red-600">จัดการโปรไฟล์</a>
            <a href="/photographer/portfolio.php" class="rounded-full border border-neutral-200 px-4 py-3 font-black hover:bg-neutral-950 hover:text-white">Portfolio</a>
            <a href="/photographer/availability.php" class="rounded-full border border-neutral-200 px-4 py-3 font-black hover:bg-neutral-950 hover:text-white">วันว่าง</a>
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
        <?php foreach ([['คำขอใหม่', $stats['pending']], ['ตอบรับแล้ว', $stats['accepted']], ['เสร็จสิ้น', $stats['completed']], ['คะแนน', $profile['average_rating']], ['รีวิว', $profile['total_reviews']], ['Profile views', $profile['profile_views']]] as $card): ?>
            <div class="stock-card rounded-[1.5rem] p-5">
                <p class="text-sm font-bold text-neutral-500"><?= h($card[0]) ?></p>
                <p class="mt-2 text-3xl font-black"><?= h($card[1]) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="stock-card mt-6 rounded-[1.5rem] p-6">
        <div class="flex justify-between gap-4">
            <h2 class="text-xl font-black">รายการจองล่าสุด</h2>
            <a class="text-sm font-black text-red-600" href="/photographer/bookings.php">ดูทั้งหมด</a>
        </div>
        <div class="mt-5 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-neutral-500">
                    <tr>
                        <th class="py-3">Code</th>
                        <th>ลูกค้า</th>
                        <th>ประเภท</th>
                        <th>วันที่</th>
                        <th>สถานะ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr class="border-t">
                            <td class="py-3 font-black"><?= h($booking['booking_code']) ?></td>
                            <td><?= h($booking['customer_name']) ?></td>
                            <td><?= h($booking['category_name']) ?></td>
                            <td><?= h($booking['booking_date']) ?> <?= h(time_slot_label($booking['time_slot'])) ?></td>
                            <td><?= status_badge($booking['status']) ?></td>
                            <td>
                                <a class="font-black text-red-600" href="/photographer/booking_detail.php?id=<?= (int)$booking['id'] ?>">
                                    ดู
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

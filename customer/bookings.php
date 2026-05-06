<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('customer');

$user = current_user();

$stmt = db()->prepare('SELECT b.*, p.display_name, sc.name AS category_name, d.district_name, r.id AS review_id
                       FROM bookings b
                       JOIN photographer_profiles p ON p.id = b.photographer_id
                       JOIN service_categories sc ON sc.id = b.category_id
                       JOIN districts d ON d.id = b.district_id
                       LEFT JOIN reviews r ON r.booking_id = b.id AND r.deleted_at IS NULL
                       WHERE b.customer_id = ? AND b.deleted_at IS NULL
                       ORDER BY b.created_at DESC');
$stmt->execute([(int)$user['id']]);
$bookings = $stmt->fetchAll();

$pageTitle = 'รายการจองของฉัน';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">พื้นที่ลูกค้า</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">รายการจองของฉัน</h1>
    </div>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="w-full text-left text-sm">
            <thead class="text-neutral-500">
                <tr>
                    <th class="py-3">รหัสจอง</th>
                    <th>ช่างภาพ</th>
                    <th>ประเภท</th>
                    <th>วันที่</th>
                    <th>อำเภอ</th>
                    <th>สถานะ</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                    <tr class="border-t">
                        <td class="py-3 font-black"><?= h($booking['booking_code']) ?></td>
                        <td><?= h($booking['display_name']) ?></td>
                        <td><?= h($booking['category_name']) ?></td>
                        <td><?= h(format_be_date($booking['booking_date'])) ?> <?= h(time_slot_label($booking['time_slot'])) ?></td>
                        <td><?= h($booking['district_name']) ?></td>
                        <td><?= status_badge($booking['status']) ?></td>
                        <td class="whitespace-nowrap">
                            <?= clean_context_button('/customer/booking_detail.php', ['id' => (int)$booking['id']], 'รายละเอียด', 'font-black text-red-600') ?>

                            <?php if ($booking['status'] === 'completed' && !$booking['review_id']): ?>
                                <span class="text-neutral-300"> · </span>
                                <?= clean_context_button('/customer/review.php', ['booking_id' => (int)$booking['id']], 'รีวิว', 'font-black text-emerald-600') ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

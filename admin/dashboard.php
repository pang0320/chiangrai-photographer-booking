<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$cards = [
    'ลูกค้า' => ['users', 'role_id = 1 AND deleted_at IS NULL'],
    'ช่างภาพ' => ['photographer_profiles', 'deleted_at IS NULL'],
    'ช่างภาพ pending' => ['photographer_profiles', 'approval_status = "pending" AND deleted_at IS NULL'],
    'Booking' => ['bookings', 'deleted_at IS NULL'],
    'Review' => ['reviews', 'deleted_at IS NULL'],
    'Article' => ['photographer_articles', 'deleted_at IS NULL'],
];

$latestBookings = db()->query('SELECT b.*, u.name AS customer_name, p.display_name
                               FROM bookings b
                               JOIN users u ON u.id = b.customer_id
                               JOIN photographer_profiles p ON p.id = b.photographer_id
                               WHERE b.deleted_at IS NULL
                               ORDER BY b.created_at DESC
                               LIMIT 8')->fetchAll();
$logs = db()->query('SELECT l.*, u.name
                     FROM activity_logs l
                     LEFT JOIN users u ON u.id = l.user_id
                     ORDER BY l.created_at DESC
                     LIMIT 8')->fetchAll();
$monthly = db()->query('SELECT DATE_FORMAT(created_at, "%Y-%m") AS ym, COUNT(*) AS total
                        FROM bookings
                        WHERE deleted_at IS NULL
                        GROUP BY ym
                        ORDER BY ym DESC
                        LIMIT 12')->fetchAll();
$districts = db()->query('SELECT d.district_name, COUNT(p.id) AS total
                          FROM districts d
                          LEFT JOIN photographer_profiles p ON p.main_district_id = d.id AND p.deleted_at IS NULL
                          GROUP BY d.id
                          ORDER BY total DESC
                          LIMIT 8')->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Admin Console</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">Admin Dashboard</h1>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
        <?php foreach ($cards as $label => $config): ?>
            <div class="stock-card rounded-[1.5rem] p-5">
                <p class="text-sm font-bold text-neutral-500"><?= h($label) ?></p>
                <p class="mt-2 text-3xl font-black"><?= table_count($config[0], $config[1]) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="stock-card rounded-[1.5rem] p-6">
            <h2 class="font-black">Booking รายเดือน</h2>
            <div class="mt-4 grid gap-2">
                <?php foreach ($monthly as $month): ?>
                    <div class="flex justify-between rounded-xl bg-neutral-50 px-4 py-3">
                        <span><?= h($month['ym']) ?></span>
                        <b><?= (int)$month['total'] ?></b>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stock-card rounded-[1.5rem] p-6">
            <h2 class="font-black">ช่างภาพตามอำเภอ</h2>
            <div class="mt-4 grid gap-2">
                <?php foreach ($districts as $district): ?>
                    <div class="flex justify-between rounded-xl bg-neutral-50 px-4 py-3">
                        <span><?= h($district['district_name']) ?></span>
                        <b><?= (int)$district['total'] ?></b>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="stock-card rounded-[1.5rem] p-6">
            <h2 class="font-black">รายการจองล่าสุด</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody>
                        <?php foreach ($latestBookings as $booking): ?>
                            <tr class="border-t">
                                <td class="py-3 font-black"><?= h($booking['booking_code']) ?></td>
                                <td><?= h($booking['customer_name']) ?></td>
                                <td><?= h($booking['display_name']) ?></td>
                                <td><?= status_badge($booking['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="stock-card rounded-[1.5rem] p-6">
            <h2 class="font-black">Activity Logs ล่าสุด</h2>
            <div class="mt-4 grid gap-2">
                <?php foreach ($logs as $log): ?>
                    <div class="rounded-xl bg-neutral-50 p-3 text-sm">
                        <?= h($log['created_at']) ?> · <?= h($log['name'] ?: 'System') ?> · <?= h($log['action']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

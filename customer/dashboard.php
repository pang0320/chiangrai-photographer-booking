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
$stmt = db()->prepare('SELECT b.*, p.display_name, sc.name category_name FROM bookings b JOIN photographer_profiles p ON p.id = b.photographer_id JOIN service_categories sc ON sc.id = b.category_id WHERE b.customer_id = ? AND b.deleted_at IS NULL ORDER BY b.created_at DESC LIMIT 6');
$stmt->execute([(int)$user['id']]);
$bookings = $stmt->fetchAll();

$pageTitle = 'Customer Dashboard';
include __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-3xl font-extrabold">แดชบอร์ดลูกค้า</h1><p class="text-slate-600">สวัสดี <?= h($user['name']) ?></p></div>
        <a href="/photographers.php" class="rounded-2xl bg-indigo-600 px-5 py-3 font-bold text-white"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหาช่างภาพ</a>
    </div>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ([['ทั้งหมด',$stats['all'],'fa-calendar-days'],['รอตอบรับ',$stats['pending'],'fa-clock'],['ตอบรับแล้ว',$stats['accepted'],'fa-handshake'],['เสร็จสิ้น',$stats['completed'],'fa-circle-check']] as $card): ?>
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200"><i class="fa-solid <?= h($card[2]) ?> text-2xl text-indigo-600"></i><p class="mt-4 text-sm text-slate-500"><?= h($card[0]) ?></p><p class="text-3xl font-extrabold"><?= (int)$card[1] ?></p></div>
        <?php endforeach; ?>
    </div>
    <div class="mt-8 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex justify-between"><h2 class="text-xl font-extrabold">รายการจองล่าสุด</h2><a class="text-sm font-bold text-indigo-600" href="/customer/bookings.php">ดูทั้งหมด</a></div>
        <div class="mt-5 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-slate-500"><tr><th class="py-3">Code</th><th>ช่างภาพ</th><th>ประเภท</th><th>วันที่</th><th>สถานะ</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($bookings as $b): ?>
                    <tr class="border-t"><td class="py-3 font-bold"><?= h($b['booking_code']) ?></td><td><?= h($b['display_name']) ?></td><td><?= h($b['category_name']) ?></td><td><?= h($b['booking_date']) ?> <?= h(time_slot_label($b['time_slot'])) ?></td><td><?= status_badge($b['status']) ?></td><td><a class="font-bold text-indigo-600" href="/customer/booking_detail.php?id=<?= (int)$b['id'] ?>">ดู</a></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>


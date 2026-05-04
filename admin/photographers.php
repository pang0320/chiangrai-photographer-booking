<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

if (is_post()) {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $reason = trim((string)($_POST['rejection_reason'] ?? ''));
    $status = 'pending';

    if ($action === 'approve') {
        $status = 'approved';
    }

    if ($action === 'reject') {
        $status = 'rejected';
    }

    if ($action === 'suspend') {
        $status = 'suspended';
    }

    if ($action === 'activate') {
        $status = 'approved';
    }

    $rejectionReason = null;
    if ($action === 'reject') {
        $rejectionReason = $reason;
    }

    $stmt = db()->prepare('UPDATE photographer_profiles SET approval_status = ?, rejection_reason = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $rejectionReason, $id]);

    $userStatus = 'pending';
    if ($status === 'approved') {
        $userStatus = 'active';
    }

    if ($status === 'suspended') {
        $userStatus = 'suspended';
    }

    $stmt = db()->prepare('UPDATE users u JOIN photographer_profiles p ON p.user_id = u.id SET u.status = ? WHERE p.id = ?');
    $stmt->execute([$userStatus, $id]);

    log_activity('admin_' . $action . '_photographer', 'photographer_profiles', $id);
    flash('success', 'อัปเดตช่างภาพแล้ว');
    redirect('/admin/photographers.php');
}

$filter = (string)($_GET['status'] ?? '');
$where = 'p.deleted_at IS NULL';
$params = [];

if ($filter !== '') {
    $where .= ' AND p.approval_status = ?';
    $params[] = $filter;
}

$sql = "SELECT p.*, u.email, u.phone, d.district_name
        FROM photographer_profiles p
        JOIN users u ON u.id = p.user_id
        LEFT JOIN districts d ON d.id = p.main_district_id
        WHERE {$where}
        ORDER BY p.created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$pageTitle = 'จัดการช่างภาพ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Admin</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950">จัดการช่างภาพ</h1>
        </div>

        <form>
            <select name="status" onchange="this.form.submit()" class="stock-input rounded-2xl px-4 py-3 font-semibold">
                <option value="">ทุกสถานะ</option>
                <?php foreach (['pending', 'approved', 'rejected', 'suspended'] as $status): ?>
                    <option value="<?= h($status) ?>" <?php if ($filter === $status): ?>selected<?php endif; ?>>
                        <?= h($status) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>ชื่อ</th>
                    <th>Email</th>
                    <th>อำเภอ</th>
                    <th>ราคา</th>
                    <th>คะแนน</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $photographer): ?>
                    <tr>
                        <td>
                            <a class="font-black text-red-600" href="/photographer_detail.php?id=<?= (int)$photographer['id'] ?>" target="_blank">
                                <?= h($photographer['display_name']) ?>
                            </a>
                        </td>
                        <td><?= h($photographer['email']) ?></td>
                        <td><?= h($photographer['district_name']) ?></td>
                        <td><?= number_format((float)$photographer['starting_price']) ?></td>
                        <td><?= h($photographer['average_rating']) ?></td>
                        <td><?= status_badge($photographer['approval_status']) ?></td>
                        <td>
                            <form method="post" class="grid gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$photographer['id'] ?>">
                                <input name="rejection_reason" placeholder="เหตุผล reject" class="stock-input rounded-xl px-3 py-2 text-sm">
                                <div class="flex flex-wrap gap-2">
                                    <button name="action" value="approve" class="rounded-full bg-emerald-50 px-3 py-1.5 font-black text-emerald-700">approve</button>
                                    <button name="action" value="reject" class="rounded-full bg-red-50 px-3 py-1.5 font-black text-red-700">reject</button>
                                    <button name="action" value="suspend" class="rounded-full bg-amber-50 px-3 py-1.5 font-black text-amber-700">suspend</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

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

    if ($action === 'verify') {
        $stmt = db()->prepare('UPDATE photographer_profiles SET is_verified = 1, verified_at = NOW(), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
        $photographer = db_fetch_all('SELECT user_id, display_name FROM photographer_profiles WHERE id = ? LIMIT 1', [$id]);
        if ($photographer) {
            notify_user((int)$photographer[0]['user_id'], 'Admin ยืนยันตัวตนแล้ว', 'โปรไฟล์ ' . $photographer[0]['display_name'] . ' ได้รับ Badge ยืนยันตัวตนแล้ว', 'photographer_verified', $id);
        }
        log_activity('admin_verify_photographer', 'photographer_profiles', $id);
        flash('success', 'ยืนยันตัวตนช่างภาพแล้ว');
        redirect('/admin/photographers.php');
    }

    if ($action === 'unverify') {
        $stmt = db()->prepare('UPDATE photographer_profiles SET is_verified = 0, verified_at = NULL, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
        log_activity('admin_unverify_photographer', 'photographer_profiles', $id);
        flash('success', 'ยกเลิก Badge ยืนยันตัวตนแล้ว');
        redirect('/admin/photographers.php');
    }

    if ($action === 'feature') {
        $featuredUntil = trim((string)($_POST['featured_until'] ?? ''));
        $featuredUntilValue = null;
        if ($featuredUntil !== '') {
            $featuredUntilValue = $featuredUntil . ' 23:59:59';
        }
        $stmt = db()->prepare('UPDATE photographer_profiles SET is_featured = 1, featured_until = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$featuredUntilValue, $id]);
        log_activity('admin_feature_photographer', 'photographer_profiles', $id);
        flash('success', 'ตั้งเป็นช่างภาพแนะนำแล้ว');
        redirect('/admin/photographers.php');
    }

    if ($action === 'unfeature') {
        $stmt = db()->prepare('UPDATE photographer_profiles SET is_featured = 0, featured_until = NULL, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
        log_activity('admin_unfeature_photographer', 'photographer_profiles', $id);
        flash('success', 'ยกเลิกช่างภาพแนะนำแล้ว');
        redirect('/admin/photographers.php');
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

    $stmt = db()->prepare('SELECT user_id, display_name FROM photographer_profiles WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $photographer = $stmt->fetch();

    if ($photographer) {
        if ($action === 'approve' || $action === 'activate') {
            notify_user((int)$photographer['user_id'], 'โปรไฟล์ช่างภาพได้รับอนุมัติ', 'โปรไฟล์ ' . $photographer['display_name'] . ' แสดงในหน้าค้นหาแล้ว', 'photographer_approved', $id);
        }

        if ($action === 'reject') {
            notify_user((int)$photographer['user_id'], 'โปรไฟล์ช่างภาพถูกปฏิเสธ', $reason ?: 'กรุณาติดต่อ Admin เพื่อดูรายละเอียด', 'photographer_rejected', $id);
        }

        if ($action === 'suspend') {
            notify_user((int)$photographer['user_id'], 'โปรไฟล์ช่างภาพถูกระงับ', 'Admin ระงับโปรไฟล์ของคุณชั่วคราว', 'photographer_suspended', $id);
        }
    }

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
                    <th>Badge</th>
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
                        <td>
                            <div class="flex flex-wrap gap-1">
                                <?php if ((int)$photographer['is_verified'] === 1): ?>
                                    <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-black text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i>verified</span>
                                <?php endif; ?>
                                <?php if ((int)$photographer['is_featured'] === 1): ?>
                                    <span class="rounded-full bg-yellow-50 px-2 py-1 text-xs font-black text-yellow-700"><i class="fa-solid fa-award mr-1"></i>featured</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= status_badge($photographer['approval_status']) ?></td>
                        <td>
                            <form method="post" class="grid gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$photographer['id'] ?>">
                                <input name="rejection_reason" placeholder="เหตุผล reject" class="stock-input rounded-xl px-3 py-2 text-sm">
                                <input type="date" name="featured_until" class="stock-input rounded-xl px-3 py-2 text-sm">
                                <div class="flex flex-wrap gap-2">
                                    <button data-confirm="ยืนยันอนุมัติช่างภาพนี้?" name="action" value="approve" class="rounded-full bg-emerald-50 px-3 py-1.5 font-black text-emerald-700"><i class="fa-solid fa-check mr-1"></i>approve</button>
                                    <button data-confirm="ยืนยันปฏิเสธช่างภาพนี้?" name="action" value="reject" class="rounded-full bg-red-50 px-3 py-1.5 font-black text-red-700"><i class="fa-solid fa-xmark mr-1"></i>reject</button>
                                    <button data-confirm="ยืนยันระงับช่างภาพนี้?" name="action" value="suspend" class="rounded-full bg-amber-50 px-3 py-1.5 font-black text-amber-700"><i class="fa-solid fa-ban mr-1"></i>suspend</button>
                                    <button name="action" value="verify" class="rounded-full bg-sky-50 px-3 py-1.5 font-black text-sky-700"><i class="fa-solid fa-circle-check mr-1"></i>verify</button>
                                    <button name="action" value="feature" class="rounded-full bg-yellow-50 px-3 py-1.5 font-black text-yellow-700"><i class="fa-solid fa-award mr-1"></i>feature</button>
                                    <button name="action" value="unfeature" class="rounded-full bg-slate-100 px-3 py-1.5 font-black text-slate-700"><i class="fa-solid fa-eye-slash mr-1"></i>unfeature</button>
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

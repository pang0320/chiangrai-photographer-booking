<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');
$cleanContext = clean_context_init(['status']);

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
        $featuredUntil = parse_be_date_to_iso((string)($_POST['featured_until'] ?? ''));
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

$filter = (string)clean_context_value($cleanContext, 'status', '');
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
$statusCounts = [
    '' => (int)db_fetch_value('SELECT COUNT(*) FROM photographer_profiles WHERE deleted_at IS NULL'),
    'pending' => (int)db_fetch_value('SELECT COUNT(*) FROM photographer_profiles WHERE approval_status = "pending" AND deleted_at IS NULL'),
    'approved' => (int)db_fetch_value('SELECT COUNT(*) FROM photographer_profiles WHERE approval_status = "approved" AND deleted_at IS NULL'),
    'rejected' => (int)db_fetch_value('SELECT COUNT(*) FROM photographer_profiles WHERE approval_status = "rejected" AND deleted_at IS NULL'),
    'suspended' => (int)db_fetch_value('SELECT COUNT(*) FROM photographer_profiles WHERE approval_status = "suspended" AND deleted_at IS NULL'),
];

$pageTitle = 'จัดการช่างภาพ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">ผู้ดูแลระบบ</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950">จัดการช่างภาพ</h1>
            <p class="mt-2 max-w-3xl text-sm font-bold leading-7 text-neutral-500">กรองสถานะการอนุมัติได้ชัดเจน ช่างภาพที่ยังรอตรวจสอบ/ถูกปฏิเสธ/ถูกระงับ จะไม่แสดงในหน้าค้นหาสาธารณะ</p>
        </div>

        <div class="rounded-2xl bg-red-50 px-4 py-3 text-sm font-black text-red-700">
            <i class="fa-solid fa-filter mr-2"></i>ตัวกรองเป็น server-side filter และจะโหลดรายการใหม่หลังเลือก
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-2">
        <?php foreach (['' => 'ทั้งหมด', 'pending' => 'รอตรวจสอบ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ไม่อนุมัติ', 'suspended' => 'ระงับ'] as $statusValue => $statusLabel): ?>
            <?php
            $pillClass = 'rounded-full px-4 py-2 text-sm font-black transition ';
            if ($filter === $statusValue) {
                $pillClass .= 'bg-neutral-950 text-white';
            } else {
                $pillClass .= 'bg-white text-neutral-700 hover:bg-red-50 hover:text-red-700';
            }
            ?>
            <?= clean_context_button('/admin/photographers.php', ['status' => $statusValue], '<i class="fa-solid fa-filter mr-1"></i>' . h($statusLabel) . ' <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">' . number_format($statusCounts[$statusValue] ?? 0) . '</span>', $pillClass) ?>
        <?php endforeach; ?>
    </div>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>ชื่อ</th>
                    <th>อีเมล</th>
                    <th>อำเภอ</th>
                    <th>ราคาเริ่มต้นโดยประมาณ</th>
	                    <th>คะแนนเฉลี่ย</th>
                    <th>ป้ายกำกับ</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $photographer): ?>
                    <tr>
                        <td class="font-black text-neutral-500"><?= $index + 1 ?></td>
                        <td>
                            <?= clean_context_button('/photographer_detail.php', ['id' => (int)$photographer['id']], h($photographer['display_name']), 'font-black text-red-600', 'inline', 'target="_blank"') ?>
                        </td>
                        <td><?= h($photographer['email']) ?></td>
                        <td><?= h($photographer['district_name']) ?></td>
                        <td><?= number_format((float)$photographer['starting_price']) ?> บาท</td>
	                        <td><?= number_format((float)$photographer['average_rating'], 1) ?> คะแนน</td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                <?php if ((int)$photographer['is_verified'] === 1): ?>
                                    <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-black text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i>ยืนยันแล้ว</span>
                                <?php endif; ?>
                                <?php if ((int)$photographer['is_featured'] === 1): ?>
                                    <span class="rounded-full bg-yellow-50 px-2 py-1 text-xs font-black text-yellow-700"><i class="fa-solid fa-award mr-1"></i>แนะนำ</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= status_badge($photographer['approval_status']) ?></td>
                        <td>
                            <form method="post" class="grid gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$photographer['id'] ?>">
                                <input name="rejection_reason" placeholder="เหตุผลปฏิเสธ" class="stock-input rounded-xl px-3 py-2 text-sm">
                                <?= be_date_input('featured_until', '', 'stock-input rounded-xl px-3 py-2 text-sm', false, 'แนะนำถึง พ.ศ.') ?>
                                <div class="flex flex-wrap gap-2">
                                    <button data-confirm="ยืนยันอนุมัติช่างภาพนี้?" name="action" value="approve" class="btn-success btn-sm"><i class="fa-solid fa-check"></i>อนุมัติ</button>
                                    <button data-confirm="ยืนยันปฏิเสธช่างภาพนี้?" name="action" value="reject" class="btn-danger btn-sm"><i class="fa-solid fa-xmark"></i>ปฏิเสธ</button>
                                    <button data-confirm="ยืนยันระงับช่างภาพนี้?" name="action" value="suspend" class="btn-warning btn-sm"><i class="fa-solid fa-ban"></i>ระงับ</button>
                                    <button name="action" value="verify" class="btn-success btn-sm"><i class="fa-solid fa-circle-check"></i>ยืนยัน</button>
                                    <button name="action" value="feature" class="btn-warning btn-sm"><i class="fa-solid fa-award"></i>แนะนำ</button>
                                    <button name="action" value="unfeature" class="btn-muted btn-sm"><i class="fa-solid fa-eye-slash"></i>เลิกแนะนำ</button>
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

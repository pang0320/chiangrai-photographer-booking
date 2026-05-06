<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$cleanContext = clean_context_init(['role', 'role_id', 'status', 'q']);

if (is_post()) {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    $stmt = db()->prepare('SELECT u.*, r.name AS role_name
                           FROM users u
                           JOIN roles r ON r.id = u.role_id
                           WHERE u.id = ? AND u.deleted_at IS NULL
                           LIMIT 1');
    $stmt->execute([$id]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        flash('error', 'ไม่พบผู้ใช้');
        redirect('/admin/users.php');
    }

    if ($action === 'activate') {
        $stmt = db()->prepare('UPDATE users SET status = "active", updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);

        if ($targetUser['role_name'] === 'photographer') {
            $stmt = db()->prepare('UPDATE photographer_profiles
                                   SET approval_status = "approved", updated_at = NOW()
                                   WHERE user_id = ? AND approval_status = "suspended" AND deleted_at IS NULL');
            $stmt->execute([$id]);
        }

        notify_user($id, 'บัญชีถูกเปิดใช้งาน', 'Admin เปิดใช้งานบัญชีของคุณแล้ว', 'account', $id);
    }

    if ($action === 'suspend') {
        $stmt = db()->prepare('UPDATE users SET status = "suspended", updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);

        if ($targetUser['role_name'] === 'photographer') {
            $stmt = db()->prepare('UPDATE photographer_profiles
                                   SET approval_status = "suspended", is_available = 0, updated_at = NOW()
                                   WHERE user_id = ? AND deleted_at IS NULL');
            $stmt->execute([$id]);
        }

        notify_user($id, 'บัญชีถูกระงับ', 'Admin ระงับบัญชีของคุณ กรุณาติดต่อผู้ดูแลระบบ', 'account', $id);
    }

    if ($action === 'delete') {
        $stmt = db()->prepare('UPDATE users SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);

        if ($targetUser['role_name'] === 'photographer') {
            $stmt = db()->prepare('UPDATE photographer_profiles SET deleted_at = NOW(), updated_at = NOW() WHERE user_id = ? AND deleted_at IS NULL');
            $stmt->execute([$id]);
        }
    }

    log_activity('admin_' . $action . '_user', 'users', $id);
    flash('success', 'อัปเดตผู้ใช้แล้ว');
    redirect('/admin/users.php');
}

$role = (string)clean_context_value($cleanContext, 'role', '');
if ($role === '' && isset($cleanContext['role_id'])) {
    $roleIdFilter = (int)$cleanContext['role_id'];
    if ($roleIdFilter === 1) {
        $role = 'customer';
    }
    if ($roleIdFilter === 2) {
        $role = 'photographer';
    }
    if ($roleIdFilter === 3) {
        $role = 'admin';
    }
}
$status = (string)clean_context_value($cleanContext, 'status', '');
$q = trim((string)clean_context_value($cleanContext, 'q', ''));
$where = ['u.deleted_at IS NULL'];
$params = [];

if ($role !== '') {
    $where[] = 'r.name = ?';
    $params[] = $role;
}

if ($status !== '') {
    $where[] = 'u.status = ?';
    $params[] = $status;
}

if ($q !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$sql = 'SELECT u.*, r.display_name AS role_display, r.name AS role_name
        FROM users u
        JOIN roles r ON r.id = u.role_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY u.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'จัดการผู้ใช้';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">ผู้ดูแลระบบ</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950">จัดการผู้ใช้</h1>
        </div>
        <a href="/admin/dashboard.php" class="rounded-full bg-neutral-950 px-5 py-3 text-sm font-black text-white hover:bg-red-600">
            <i class="fa-solid fa-gauge mr-2"></i>แดชบอร์ด
        </a>
    </div>

    <form method="post" action="/admin/users.php" class="stock-card mt-6 grid gap-3 rounded-[1.5rem] p-5 md:grid-cols-4">
        <?= clean_context_inputs([]) ?>
        <input name="q" value="<?= h($q) ?>" placeholder="ค้นหา" class="stock-input rounded-2xl px-4 py-3 font-semibold">

        <select name="role" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <option value="">ทุกบทบาท</option>
            <?php foreach (['customer', 'photographer', 'admin'] as $roleName): ?>
                <option value="<?= h($roleName) ?>" <?php if ($role === $roleName): ?>selected<?php endif; ?>>
                    <?= h(role_display_name($roleName)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <option value="">ทุกสถานะ</option>
            <?php foreach (['active', 'pending', 'suspended'] as $statusName): ?>
                <option value="<?= h($statusName) ?>" <?php if ($status === $statusName): ?>selected<?php endif; ?>>
                    <?= h(booking_status_label($statusName)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="stock-button rounded-2xl px-5 py-3 font-black"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
    </form>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ชื่อ</th>
                    <th>อีเมล</th>
                    <th>บทบาท</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= (int)$user['id'] ?></td>
                        <td class="font-bold"><?= h($user['name']) ?></td>
                        <td><?= h($user['email']) ?></td>
                        <td><?= h($user['role_display']) ?></td>
                        <td><?= status_badge($user['status']) ?></td>
                        <td>
                            <form method="post" class="flex flex-wrap gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">

                                <button data-confirm="ยืนยันเปิดใช้งานบัญชีนี้?" name="action" value="activate" class="rounded-full bg-emerald-50 px-3 py-1.5 font-black text-emerald-700">
                                    <i class="fa-solid fa-check mr-1"></i>เปิดใช้งาน
                                </button>
                                <button data-confirm="ยืนยันระงับบัญชีนี้?" name="action" value="suspend" class="rounded-full bg-amber-50 px-3 py-1.5 font-black text-amber-700">
                                    <i class="fa-solid fa-ban mr-1"></i>ระงับ
                                </button>
                                <button data-confirm="ลบผู้ใช้นี้?" name="action" value="delete" class="rounded-full bg-red-50 px-3 py-1.5 font-black text-red-700">
                                    <i class="fa-solid fa-trash mr-1"></i>ลบ
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

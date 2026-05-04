<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

if (is_post()) {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'activate') {
        $stmt = db()->prepare('UPDATE users SET status = "active", updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    if ($action === 'suspend') {
        $stmt = db()->prepare('UPDATE users SET status = "suspended", updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    if ($action === 'delete') {
        $stmt = db()->prepare('UPDATE users SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    log_activity('admin_' . $action . '_user', 'users', $id);
    flash('success', 'อัปเดตผู้ใช้แล้ว');
    redirect('/admin/users.php');
}

$role = (string)($_GET['role'] ?? '');
$status = (string)($_GET['status'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
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
            <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Admin</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950">จัดการผู้ใช้</h1>
        </div>
        <a href="/admin/dashboard.php" class="rounded-full bg-neutral-950 px-5 py-3 text-sm font-black text-white hover:bg-red-600">
            Dashboard
        </a>
    </div>

    <form class="stock-card mt-6 grid gap-3 rounded-[1.5rem] p-5 md:grid-cols-4">
        <input name="q" value="<?= h($q) ?>" placeholder="ค้นหา" class="stock-input rounded-2xl px-4 py-3 font-semibold">

        <select name="role" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <option value="">ทุก role</option>
            <?php foreach (['customer', 'photographer', 'admin'] as $roleName): ?>
                <option value="<?= h($roleName) ?>" <?php if ($role === $roleName): ?>selected<?php endif; ?>>
                    <?= h($roleName) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <option value="">ทุกสถานะ</option>
            <?php foreach (['active', 'pending', 'suspended'] as $statusName): ?>
                <option value="<?= h($statusName) ?>" <?php if ($status === $statusName): ?>selected<?php endif; ?>>
                    <?= h($statusName) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="stock-button rounded-2xl px-5 py-3 font-black">ค้นหา</button>
    </form>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ชื่อ</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Action</th>
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

                                <button name="action" value="activate" class="rounded-full bg-emerald-50 px-3 py-1.5 font-black text-emerald-700">
                                    activate
                                </button>
                                <button name="action" value="suspend" class="rounded-full bg-amber-50 px-3 py-1.5 font-black text-amber-700">
                                    suspend
                                </button>
                                <button data-confirm="ลบผู้ใช้นี้?" name="action" value="delete" class="rounded-full bg-red-50 px-3 py-1.5 font-black text-red-700">
                                    delete
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

<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

if (is_post()) {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? 'read');
    $allowed = ['unread', 'read', 'replied'];
    if (!in_array($status, $allowed, true)) {
        $status = 'read';
    }

    $stmt = db()->prepare('UPDATE contact_messages SET status = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $id]);
    log_activity('update_contact_message', 'contact_messages', $id);
    flash('success', 'อัปเดตข้อความติดต่อแล้ว');
    redirect('/admin/contact_messages.php');
}

$items = db_fetch_all('SELECT * FROM contact_messages ORDER BY created_at DESC');

$pageTitle = 'ข้อความติดต่อ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
            <p class="section-kicker">กล่องข้อความติดต่อ</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950"><i class="fa-solid fa-envelope-open-text mr-2 text-red-600"></i>ข้อความจากหน้าติดต่อเว็บไซต์</h1>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-3">
        <div class="metric-card rounded-[1.5rem] p-5"><p class="text-sm font-bold text-neutral-500"><i class="fa-solid fa-inbox mr-1 text-red-600"></i>ทั้งหมด</p><b class="mt-2 block text-3xl"><?= count($items) ?></b></div>
        <div class="metric-card rounded-[1.5rem] p-5"><p class="text-sm font-bold text-neutral-500"><i class="fa-solid fa-circle-exclamation mr-1 text-red-600"></i>ยังไม่อ่าน</p><b class="mt-2 block text-3xl"><?= table_count('contact_messages', 'status = "unread"') ?></b></div>
        <div class="metric-card rounded-[1.5rem] p-5"><p class="text-sm font-bold text-neutral-500"><i class="fa-solid fa-reply mr-1 text-red-600"></i>ตอบแล้ว</p><b class="mt-2 block text-3xl"><?= table_count('contact_messages', 'status = "replied"') ?></b></div>
    </div>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.75rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>ผู้ติดต่อ</th>
                    <th>หัวข้อ</th>
                    <th>ข้อความ</th>
                    <th>สถานะ</th>
                    <th>วันที่</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <b><?= h($item['name']) ?></b>
                            <p class="text-xs text-neutral-500"><?= h($item['email']) ?> · <?= h($item['phone']) ?></p>
                        </td>
                        <td class="font-black"><?= h($item['subject']) ?></td>
                        <td class="max-w-md"><?= nl2br(h($item['message'])) ?></td>
                        <td><?= status_badge($item['status'] === 'unread' ? 'pending' : ($item['status'] === 'replied' ? 'completed' : 'visible')) ?></td>
                        <td><?= h(format_be_datetime($item['created_at'])) ?></td>
                        <td>
                            <form method="post" class="flex flex-wrap gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <button name="status" value="read" class="btn-muted btn-sm"><i class="fa-solid fa-eye"></i>อ่านแล้ว</button>
                                <button name="status" value="replied" class="btn-success btn-sm"><i class="fa-solid fa-reply"></i>ตอบแล้ว</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

if (is_post()) {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? 'reviewed');
    $adminNote = trim((string)($_POST['admin_note'] ?? ''));
    $allowed = ['pending', 'reviewed', 'resolved', 'rejected'];
    if (!in_array($status, $allowed, true)) {
        $status = 'reviewed';
    }

    $stmt = db()->prepare('UPDATE reports SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $adminNote, $id]);
    log_activity('moderate_report', 'reports', $id, $adminNote);
    flash('success', 'อัปเดตรายงานปัญหาแล้ว');
    redirect('/admin/reports_moderation.php');
}

$items = db_fetch_all('SELECT r.*, u.name AS reporter_name, u.email AS reporter_email
                       FROM reports r
                       LEFT JOIN users u ON u.id = r.reporter_id
                       ORDER BY r.created_at DESC');

$pageTitle = 'ตรวจสอบรายงานปัญหา';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="section-kicker">ตรวจสอบรายงาน</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950"><i class="fa-solid fa-shield-halved mr-2 text-red-600"></i>รายงานปัญหา</h1>
        </div>
        <div class="rounded-full bg-red-50 px-5 py-3 text-sm font-black text-red-700"><i class="fa-solid fa-hourglass-half mr-2"></i><?= table_count('reports', 'status = "pending"') ?> รอตรวจสอบ</div>
    </div>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.75rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>ผู้รายงาน</th>
                    <th>เป้าหมาย</th>
                    <th>เหตุผล</th>
                    <th>รายละเอียด</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <b><?php if ($item['reporter_name']): ?><?= h($item['reporter_name']) ?><?php else: ?>ผู้เยี่ยมชม<?php endif; ?></b>
                            <p class="text-xs text-neutral-500"><?= h($item['reporter_email']) ?></p>
                        </td>
                        <td><span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-black"><i class="fa-solid fa-bullseye mr-1 text-red-600"></i><?= h($item['target_type']) ?> #<?= (int)$item['target_id'] ?></span></td>
                        <td class="font-black"><?= h($item['reason']) ?></td>
                        <td class="max-w-md"><?= nl2br(h($item['detail'])) ?></td>
                        <td><?= status_badge($item['status'] === 'pending' ? 'pending' : ($item['status'] === 'resolved' ? 'completed' : ($item['status'] === 'rejected' ? 'rejected' : 'visible'))) ?></td>
                        <td>
                            <form method="post" class="grid min-w-[260px] gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <input name="admin_note" value="<?= h($item['admin_note']) ?>" placeholder="บันทึกผู้ดูแล" class="stock-input rounded-xl px-3 py-2 text-sm">
                                <div class="flex flex-wrap gap-2">
                                    <button name="status" value="reviewed" class="rounded-full bg-sky-50 px-3 py-1.5 font-black text-sky-700"><i class="fa-solid fa-eye mr-1"></i>ตรวจแล้ว</button>
                                    <button name="status" value="resolved" class="rounded-full bg-emerald-50 px-3 py-1.5 font-black text-emerald-700"><i class="fa-solid fa-check mr-1"></i>แก้ไขแล้ว</button>
                                    <button name="status" value="rejected" class="rounded-full bg-red-50 px-3 py-1.5 font-black text-red-700"><i class="fa-solid fa-xmark mr-1"></i>ปฏิเสธ</button>
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

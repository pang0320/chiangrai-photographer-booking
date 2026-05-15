<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');
$cleanContext = clean_context_init(['status']);

if (is_post()) {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? 'reviewed');
    $adminNote = trim((string)($_POST['admin_note'] ?? ''));
    $allowed = ['pending', 'reviewed', 'resolved', 'rejected'];
    if (!in_array($status, $allowed, true)) {
        $status = 'reviewed';
    }

    $report = db_fetch_all('SELECT reporter_id, target_type, target_id FROM reports WHERE id = ? LIMIT 1', [$id]);
    $stmt = db()->prepare('UPDATE reports SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $adminNote, $id]);
    if ($report && !empty($report[0]['reporter_id'])) {
        notify_user((int)$report[0]['reporter_id'], 'ผลตรวจรายงานปัญหา', 'รายงาน #' . $id . ' เป็น ' . report_status_label($status), 'report', $id);
    }
    log_activity('moderate_report', 'reports', $id, $adminNote);
    flash('success', 'อัปเดตรายงานปัญหาแล้ว');
    clean_redirect('/admin/reports_moderation.php', []);
}

$statusFilter = (string)clean_context_value($cleanContext, 'status', '');
$whereSql = '1=1';
$params = [];
if (in_array($statusFilter, ['pending', 'reviewed', 'resolved', 'rejected'], true)) {
    $whereSql = 'r.status = ?';
    $params[] = $statusFilter;
}

$items = db_fetch_all('SELECT r.*, u.name AS reporter_name, u.email AS reporter_email
                       FROM reports r
                       LEFT JOIN users u ON u.id = r.reporter_id
                       WHERE ' . $whereSql . '
                       ORDER BY r.created_at DESC', $params);

$pageTitle = 'ตรวจสอบรายงานปัญหา';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="section-kicker">ตรวจสอบรายงาน</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950"><i class="fa-solid fa-shield-halved mr-2 text-red-600"></i>รายงานปัญหา</h1>
            <p class="mt-2 max-w-3xl text-sm font-bold leading-7 text-neutral-500">รายงานมาจากปุ่มรายงานในหน้าโปรไฟล์ช่างภาพและจุดที่แสดงรีวิว ผู้ใช้กรอกเหตุผลและรายละเอียดเอง แล้วผู้ดูแลตรวจสอบต่อที่หน้านี้</p>
        </div>
        <div class="rounded-full bg-red-50 px-5 py-3 text-sm font-black text-red-700"><i class="fa-solid fa-hourglass-half mr-2"></i><?= table_count('reports', 'status = "pending"') ?> รอตรวจสอบ</div>
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
        <?php foreach (['' => 'ทั้งหมด', 'pending' => 'รอตรวจสอบ', 'reviewed' => 'ตรวจแล้ว', 'resolved' => 'แก้ไขแล้ว', 'rejected' => 'ปฏิเสธ'] as $filterValue => $filterLabel): ?>
            <?= clean_context_button('/admin/reports_moderation.php', ['status' => $filterValue], h($filterLabel), 'rounded-full px-4 py-2 text-sm font-black ' . ($statusFilter === $filterValue ? 'bg-neutral-950 text-white' : 'bg-white text-neutral-700')) ?>
        <?php endforeach; ?>
    </div>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.75rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>ผู้รายงาน</th>
                    <th>เป้าหมาย</th>
                    <th>เหตุผล</th>
                    <th>รายละเอียด</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td class="font-black text-neutral-500"><?= $index + 1 ?></td>
                        <td>
                            <b><?php if ($item['reporter_name']): ?><?= h($item['reporter_name']) ?><?php else: ?>ผู้เยี่ยมชม<?php endif; ?></b>
                            <p class="text-xs text-neutral-500"><?= h($item['reporter_email']) ?></p>
                        </td>
                        <td><span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-black"><i class="fa-solid fa-bullseye mr-1 text-red-600"></i><?= h($item['target_type']) ?> รหัสอ้างอิง <?= (int)$item['target_id'] ?></span></td>
                        <td class="font-black"><?= h($item['reason']) ?></td>
                        <td class="max-w-md"><?= nl2br(h($item['detail'])) ?></td>
                        <td><?= status_badge($item['status'] === 'pending' ? 'pending' : ($item['status'] === 'resolved' ? 'completed' : ($item['status'] === 'rejected' ? 'rejected' : 'visible'))) ?></td>
                        <td>
                            <form method="post" class="grid min-w-[260px] gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <input name="admin_note" value="<?= h($item['admin_note']) ?>" placeholder="บันทึกโดยผู้ดูแล" class="stock-input rounded-xl px-3 py-2 text-sm">
                                <div class="flex flex-wrap gap-2">
                                    <button name="status" value="reviewed" class="btn-warning btn-sm"><i class="fa-solid fa-eye"></i>ตรวจแล้ว</button>
                                    <button name="status" value="resolved" class="btn-success btn-sm"><i class="fa-solid fa-check"></i>แก้ไขแล้ว</button>
                                    <button name="status" value="rejected" class="btn-danger btn-sm"><i class="fa-solid fa-xmark"></i>ปฏิเสธ</button>
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

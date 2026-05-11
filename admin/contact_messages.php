<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');
$cleanContext = clean_context_init(['q', 'status', 'date_from', 'date_to']);

function contact_message_status_label(string $status): string
{
    $map = [
        'unread' => 'ยังไม่อ่าน',
        'read' => 'อ่านแล้ว',
        'replied' => 'ตอบแล้ว',
    ];

    return $map[$status] ?? $status;
}

function contact_message_status_badge(string $status): string
{
    $class = 'bg-slate-100 text-slate-700';
    $icon = 'fa-eye';

    if ($status === 'unread') {
        $class = 'bg-amber-100 text-amber-700';
        $icon = 'fa-circle-exclamation';
    }

    if ($status === 'replied') {
        $class = 'bg-emerald-100 text-emerald-700';
        $icon = 'fa-reply';
    }

    return '<span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ' . h($class) . '"><i class="fa-solid ' . h($icon) . '"></i>' . h(contact_message_status_label($status)) . '</span>';
}

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

$q = trim((string)clean_context_value($cleanContext, 'q', ''));
$statusFilter = (string)clean_context_value($cleanContext, 'status', '');
$dateFrom = parse_be_date_to_iso((string)clean_context_value($cleanContext, 'date_from', ''));
$dateTo = parse_be_date_to_iso((string)clean_context_value($cleanContext, 'date_to', ''));
$where = ['1=1'];
$params = [];

if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR subject LIKE ? OR message LIKE ?)';
    for ($i = 0; $i < 5; $i++) {
        $params[] = '%' . $q . '%';
    }
}

if (in_array($statusFilter, ['unread', 'read', 'replied'], true)) {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}

if ($dateFrom !== '') {
    $where[] = 'DATE(created_at) >= ?';
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'DATE(created_at) <= ?';
    $params[] = $dateTo;
}

$items = db_fetch_all('SELECT * FROM contact_messages WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC', $params);

$pageTitle = 'ข้อความติดต่อ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
            <p class="section-kicker">กล่องข้อความติดต่อ</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950"><i class="fa-solid fa-envelope-open-text mr-2 text-red-600"></i>ข้อความจากหน้าติดต่อเว็บไซต์</h1>
            <p class="mt-2 max-w-3xl text-sm font-bold leading-7 text-neutral-500">ใช้ดูข้อความที่ส่งจากหน้า ติดต่อเว็บไซต์ กรองได้ตามสถานะ วันที่ และคำค้น</p>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-3">
        <div class="metric-card rounded-[1.5rem] p-5"><p class="text-sm font-bold text-neutral-500"><i class="fa-solid fa-inbox mr-1 text-red-600"></i>ทั้งหมด</p><b class="mt-2 block text-3xl"><?= table_count('contact_messages') ?></b></div>
        <div class="metric-card rounded-[1.5rem] p-5"><p class="text-sm font-bold text-neutral-500"><i class="fa-solid fa-circle-exclamation mr-1 text-red-600"></i>ยังไม่อ่าน</p><b class="mt-2 block text-3xl"><?= table_count('contact_messages', 'status = "unread"') ?></b></div>
        <div class="metric-card rounded-[1.5rem] p-5"><p class="text-sm font-bold text-neutral-500"><i class="fa-solid fa-reply mr-1 text-red-600"></i>ตอบแล้ว</p><b class="mt-2 block text-3xl"><?= table_count('contact_messages', 'status = "replied"') ?></b></div>
    </div>

    <form method="post" action="/admin/contact_messages.php" class="stock-card mt-6 grid gap-3 rounded-[1.5rem] p-5 lg:grid-cols-5">
        <?= clean_context_inputs([]) ?>
        <input name="q" value="<?= h($q) ?>" placeholder="ค้นหาชื่อ/อีเมล/เบอร์/หัวข้อ/ข้อความ" class="stock-input rounded-2xl px-4 py-3 font-semibold lg:col-span-2">
        <select name="status" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            <option value="">ทุกสถานะ</option>
            <?php foreach (['unread', 'read', 'replied'] as $statusName): ?>
                <option value="<?= h($statusName) ?>" <?php if ($statusFilter === $statusName): ?>selected<?php endif; ?>><?= h(contact_message_status_label($statusName)) ?></option>
            <?php endforeach; ?>
        </select>
        <?= be_date_input('date_from', $dateFrom, 'stock-input rounded-2xl px-4 py-3 font-semibold', false, 'วันที่เริ่ม พ.ศ.') ?>
        <?= be_date_input('date_to', $dateTo, 'stock-input rounded-2xl px-4 py-3 font-semibold', false, 'วันที่สิ้นสุด พ.ศ.') ?>
        <button class="stock-button rounded-2xl px-5 py-3 font-black lg:col-span-5"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหาข้อความ</button>
    </form>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.75rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>ผู้ติดต่อ</th>
                    <th>หัวข้อ</th>
                    <th>ข้อความ</th>
                    <th>สถานะ</th>
                    <th>วันที่</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td class="font-black text-neutral-500"><?= $index + 1 ?></td>
                        <td>
                            <b><?= h($item['name']) ?></b>
                            <p class="text-xs text-neutral-500"><?= h($item['email']) ?> · <?= h($item['phone']) ?></p>
                        </td>
                        <td class="font-black"><?= h($item['subject']) ?></td>
                        <td class="max-w-md"><?= nl2br(h($item['message'])) ?></td>
                        <td><?= contact_message_status_badge((string)$item['status']) ?></td>
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

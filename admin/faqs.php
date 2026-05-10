<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$cleanContext = clean_context_init(['edit']);
$editId = 0;
if (isset($cleanContext['edit'])) {
    $editId = (int)$cleanContext['edit'];
}

if (is_post()) {
    verify_csrf();

    $action = 'save';
    if (isset($_POST['action'])) {
        $action = (string)$_POST['action'];
    }

    $id = 0;
    if (isset($_POST['id'])) {
        $id = (int)$_POST['id'];
    }

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM faqs WHERE id = ?');
        $stmt->execute([$id]);
        log_activity('delete_faq', 'faqs', $id);
        flash('success', 'ลบคำถามแล้ว');
        clean_redirect('/admin/faqs.php', []);
    }

    $category = trim((string)($_POST['category'] ?? ''));
    $question = trim((string)($_POST['question'] ?? ''));
    $answer = trim((string)($_POST['answer'] ?? ''));
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = 0;
    if (isset($_POST['is_active'])) {
        $isActive = 1;
    }

    if ($category === '' || $question === '' || $answer === '') {
        flash('error', 'กรุณากรอกข้อมูลคำถามให้ครบ');
        clean_redirect('/admin/faqs.php', []);
    }

    if ($id > 0) {
        $stmt = db()->prepare('UPDATE faqs SET category = ?, question = ?, answer = ?, is_active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$category, $question, $answer, $isActive, $sortOrder, $id]);
        log_activity('update_faq', 'faqs', $id);
    } else {
        $stmt = db()->prepare('INSERT INTO faqs (category, question, answer, is_active, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([$category, $question, $answer, $isActive, $sortOrder]);
        log_activity('create_faq', 'faqs', (int)db()->lastInsertId());
    }

    flash('success', 'บันทึกคำถามแล้ว');
    clean_redirect('/admin/faqs.php', []);
}

$editFaq = null;
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM faqs WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $editFaq = $stmt->fetch();
}

$items = db_fetch_all('SELECT * FROM faqs ORDER BY category, sort_order, id DESC');

$pageTitle = 'จัดการคำถามที่พบบ่อย';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="section-kicker">จัดการคำถามที่พบบ่อย</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950"><i class="fa-solid fa-circle-question mr-2 text-red-600"></i>จัดการคำถามที่พบบ่อย</h1>
            <p class="mt-2 max-w-3xl text-base font-semibold leading-7 text-neutral-600">
                FAQ คือคำถามที่ผู้ดูแลระบบรวบรวมจากข้อสงสัยของลูกค้าและช่างภาพ แล้วจัดหมวดหมู่เองเพื่อแสดงในหน้าเว็บไซต์
            </p>
        </div>
        <a href="/faq.php" target="_blank" class="rounded-full border border-neutral-200 px-5 py-3 text-sm font-black hover:bg-neutral-950 hover:text-white"><i class="fa-solid fa-eye mr-2"></i>ดูหน้าคำถาม</a>
    </div>

    <form method="post" class="stock-card mt-6 grid gap-4 rounded-[1.75rem] p-6 lg:grid-cols-6">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?php if ($editFaq): ?><?= (int)$editFaq['id'] ?><?php endif; ?>">
        <label class="grid gap-2 text-sm font-black text-neutral-700 lg:col-span-2">
            <span><i class="fa-solid fa-layer-group mr-1 text-red-600"></i>หมวดหมู่</span>
            <input name="category" required value="<?php if ($editFaq): ?><?= h($editFaq['category']) ?><?php endif; ?>" class="stock-input rounded-2xl px-4 py-3 font-semibold" placeholder="การจอง">
        </label>
        <label class="grid gap-2 text-sm font-black text-neutral-700 lg:col-span-3">
            <span><i class="fa-solid fa-circle-question mr-1 text-red-600"></i>คำถาม</span>
            <input name="question" required value="<?php if ($editFaq): ?><?= h($editFaq['question']) ?><?php endif; ?>" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        </label>
        <label class="grid gap-2 text-sm font-black text-neutral-700">
            <span><i class="fa-solid fa-arrow-down-1-9 mr-1 text-red-600"></i>ลำดับ</span>
            <input name="sort_order" type="number" value="<?php if ($editFaq): ?><?= (int)$editFaq['sort_order'] ?><?php else: ?>0<?php endif; ?>" class="stock-input rounded-2xl px-4 py-3 font-semibold">
        </label>
        <label class="grid gap-2 text-sm font-black text-neutral-700 lg:col-span-6">
            <span><i class="fa-solid fa-message mr-1 text-red-600"></i>คำตอบ</span>
            <textarea name="answer" rows="4" required class="stock-input rounded-2xl px-4 py-3 font-semibold"><?php if ($editFaq): ?><?= h($editFaq['answer']) ?><?php endif; ?></textarea>
        </label>
        <label class="inline-flex items-center gap-2 rounded-2xl bg-neutral-50 px-4 py-3 text-sm font-black">
            <input type="checkbox" name="is_active" <?php if (!$editFaq || (int)$editFaq['is_active'] === 1): ?>checked<?php endif; ?>>
            <i class="fa-solid fa-toggle-on text-red-600"></i>เปิดแสดงผล
        </label>
        <button class="stock-button rounded-2xl px-5 py-3 font-black lg:col-span-5"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกคำถาม</button>
    </form>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.75rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>หมวดหมู่</th>
                    <th>คำถาม</th>
                    <th>สถานะ</th>
                    <th>ลำดับ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="font-black"><?= h($item['category']) ?></td>
                        <td><?= h($item['question']) ?></td>
                        <td>
                            <?php if ((int)$item['is_active'] === 1): ?>
                                <?= status_badge('visible') ?>
                            <?php else: ?>
                                <?= status_badge('hidden') ?>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$item['sort_order'] ?></td>
                        <td>
                            <div class="flex flex-wrap gap-2">
                                <?= clean_context_button('/admin/faqs.php', ['edit' => (int)$item['id']], '<i class="fa-solid fa-pen mr-1"></i>แก้ไข', 'rounded-full bg-amber-50 px-3 py-1.5 font-black text-amber-700') ?>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                    <button data-confirm="ลบคำถามนี้?" class="btn-danger btn-sm"><i class="fa-solid fa-trash"></i>ลบ</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

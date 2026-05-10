<?php
require_once __DIR__ . '/includes/functions.php';

$context = clean_context_init(['q', 'category', 'page']);
$keyword = trim((string)clean_context_value($context, 'q', ''));
$category = trim((string)clean_context_value($context, 'category', ''));
$page = max(1, (int)clean_context_value($context, 'page', 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$whereSql = 'WHERE is_active = 1';
$params = [];

if ($keyword !== '') {
    $whereSql .= ' AND (question LIKE ? OR answer LIKE ? OR category LIKE ?)';
    $keywordLike = '%' . $keyword . '%';
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $params[] = $keywordLike;
}

if ($category !== '') {
    $whereSql .= ' AND category = ?';
    $params[] = $category;
}

$countStmt = db()->prepare('SELECT COUNT(*) FROM faqs ' . $whereSql);
$countStmt->execute($params);
$totalFaqs = (int)$countStmt->fetchColumn();

$stmt = db()->prepare('SELECT * FROM faqs ' . $whereSql . ' ORDER BY category, sort_order, id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
$stmt->execute($params);
$faqs = $stmt->fetchAll();

$categories = db_fetch_all('SELECT category, COUNT(*) AS total FROM faqs WHERE is_active = 1 GROUP BY category ORDER BY category');
$grouped = [];
foreach ($faqs as $faq) {
    $grouped[$faq['category']][] = $faq;
}

$pageTitle = 'คำถามที่พบบ่อย';
include __DIR__ . '/includes/header.php';
?>

<section class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
    <div class="dashboard-hero overflow-hidden rounded-[2rem] p-6 text-white md:p-8">
        <div class="grid gap-6 lg:grid-cols-[1fr_360px] lg:items-end">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/60">
                    <i class="fa-solid fa-circle-question mr-2"></i>คำถามที่พบบ่อย
                </p>
                <h1 class="mt-2 text-4xl font-black md:text-5xl">คำถามที่พบบ่อย</h1>
                <p class="mt-4 max-w-3xl text-base font-semibold leading-8 text-white/75 md:text-lg">
                    FAQ ในระบบนี้เป็นคำถามที่ผู้ดูแลรวบรวมจากการใช้งานจริง ข้อสงสัยของลูกค้าและช่างภาพ แล้วจัดหมวดหมู่เพื่อให้อ่านง่าย
                </p>
            </div>
            <div class="rounded-[1.75rem] bg-white/12 p-5 backdrop-blur">
                <p class="text-sm font-black text-white/60"><i class="fa-solid fa-folder-tree mr-2"></i>สรุป FAQ</p>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-white/10 p-4 text-center">
                        <div class="text-3xl font-black"><?= number_format($totalFaqs) ?></div>
                        <div class="text-xs font-black text-white/55">คำถามที่พบ</div>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-4 text-center">
                        <div class="text-3xl font-black"><?= number_format(count($categories)) ?></div>
                        <div class="text-xs font-black text-white/55">หมวดหมู่</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="post" action="/faq.php" class="stock-card mt-6 rounded-[1.75rem] p-5">
        <?= csrf_field() ?>
        <input type="hidden" name="__context_nav" value="1">
        <input type="hidden" name="page" value="1">
        <div class="grid gap-4 lg:grid-cols-[1fr_260px_160px]">
            <label class="block">
                <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-magnifying-glass mr-2 text-red-600"></i>ค้นหาคำถาม / คำตอบ</span>
                <input name="q" value="<?= h($keyword) ?>" placeholder="เช่น จอง, รีวิว, ชำระเงิน, ติดต่อช่างภาพ" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
            </label>
            <label class="block">
                <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-folder-open mr-2 text-red-600"></i>หมวดหมู่</span>
                <select name="category" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
                    <option value="">ทุกหมวดหมู่</option>
                    <?php foreach ($categories as $row): ?>
                        <option value="<?= h($row['category']) ?>" <?= $category === $row['category'] ? 'selected' : '' ?>>
                            <?= h($row['category']) ?> (<?= number_format((int)$row['total']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="flex items-end">
                <button class="stock-button w-full rounded-full px-5 py-3 font-black">
                    <i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา
                </button>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-bold text-neutral-500">
                <i class="fa-solid fa-circle-info mr-1 text-red-600"></i>แสดงคำถามที่ผู้ดูแลเปิดเผยแพร่เท่านั้น
            </p>
            <button type="button" class="btn-muted btn-md" onclick="this.closest('form').querySelector('[name=q]').value=''; this.closest('form').querySelector('[name=category]').value=''; this.closest('form').submit();">
                <i class="fa-solid fa-rotate-left mr-2"></i>ล้างตัวกรอง
            </button>
        </div>
    </form>

    <?php if ($keyword !== '' || $category !== ''): ?>
        <div class="mt-5 flex flex-wrap gap-2">
            <?php if ($keyword !== ''): ?>
                <span class="rounded-full bg-red-50 px-4 py-2 text-sm font-black text-red-700">
                    <i class="fa-solid fa-magnifying-glass mr-1"></i><?= h($keyword) ?>
                </span>
            <?php endif; ?>
            <?php if ($category !== ''): ?>
                <span class="rounded-full bg-amber-50 px-4 py-2 text-sm font-black text-amber-700">
                    <i class="fa-solid fa-folder mr-1"></i><?= h($category) ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <?php foreach ($grouped as $groupName => $items): ?>
            <div class="stock-card rounded-[1.75rem] p-6">
                <h2 class="text-2xl font-black">
                    <i class="fa-solid fa-circle-question mr-2 text-red-600"></i><?= h($groupName) ?>
                </h2>
                <p class="mt-2 text-sm font-bold text-neutral-500">หมวดนี้จัดกลุ่มโดยผู้ดูแลระบบ</p>
                <div class="mt-5 grid gap-3">
                    <?php foreach ($items as $faq): ?>
                        <details class="rounded-2xl bg-neutral-50 p-4">
                            <summary class="cursor-pointer text-base font-black text-neutral-950">
                                <i class="fa-solid fa-angle-down mr-2 text-red-600"></i><?= h($faq['question']) ?>
                            </summary>
                            <p class="mt-3 text-base font-semibold leading-8 text-neutral-600"><?= nl2br(h($faq['answer'])) ?></p>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!$grouped): ?>
            <div class="empty-state rounded-[2rem] p-10 text-center lg:col-span-2">
                <i class="fa-solid fa-circle-question text-5xl text-red-600"></i>
                <h2 class="mt-4 text-2xl font-black">ไม่พบคำถามที่ตรงกับตัวกรอง</h2>
                <p class="mt-2 text-neutral-600">ลองเปลี่ยนคำค้นหาหรือเลือกทุกหมวดหมู่</p>
                <?= clean_context_button('/faq.php', ['q' => '', 'category' => '', 'page' => 1], '<i class="fa-solid fa-rotate-left mr-2"></i>ดูคำถามทั้งหมด', 'btn-cta btn-md mt-5') ?>
            </div>
        <?php endif; ?>
    </div>

    <?= paginate_clean($totalFaqs, $page, $perPage, '/faq.php', ['q' => $keyword, 'category' => $category]) ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

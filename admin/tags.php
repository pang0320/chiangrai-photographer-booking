<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$cleanContext = clean_context_init(['edit', 'q']);
$editId = (int)clean_context_value($cleanContext, 'edit', 0);
$keyword = trim((string)clean_context_value($cleanContext, 'q', ''));

ensure_tags_status_column();
ensure_predefined_article_tags();

if (is_post()) {
    verify_csrf();

    $action = (string)($_POST['action'] ?? 'save');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $usage = db_fetch_all('SELECT
            (SELECT COUNT(*) FROM article_tags WHERE tag_id = ?) AS article_total,
            (SELECT COUNT(*) FROM blog_tags WHERE tag_id = ?) AS blog_total,
            (SELECT COUNT(*) FROM portfolio_tags WHERE tag_id = ?) AS portfolio_total', [$id, $id, $id]);
        $totalUsage = 0;
        if ($usage) {
            $totalUsage = (int)$usage[0]['article_total'] + (int)$usage[0]['blog_total'] + (int)$usage[0]['portfolio_total'];
        }

        $stmt = db()->prepare('UPDATE tags SET is_active = 0 WHERE id = ?');
        $stmt->execute([$id]);
        cache_clear_all();
        log_activity('hide_tag', 'tags', $id);
        if ($totalUsage > 0) {
            flash('success', 'ซ่อนแท็กแล้ว ข้อมูลเดิมยังอยู่ในประวัติ ' . number_format($totalUsage) . ' จุด');
        } else {
            flash('success', 'ซ่อนแท็กแล้ว ข้อมูลยังอยู่ในฐานข้อมูล');
        }
        clean_redirect('/admin/tags.php', []);
    }

    if ($action === 'restore') {
        $stmt = db()->prepare('UPDATE tags SET is_active = 1 WHERE id = ?');
        $stmt->execute([$id]);
        cache_clear_all();
        log_activity('restore_tag', 'tags', $id);
        flash('success', 'เปิดใช้งานแท็กอีกครั้งแล้ว');
        clean_redirect('/admin/tags.php', []);
    }

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        flash('error', 'กรุณากรอกชื่อแท็ก');
        clean_redirect('/admin/tags.php', []);
    }

    if (text_length($name) > 100) {
        flash('error', 'ชื่อแท็กต้องไม่เกิน 100 ตัวอักษร');
        clean_redirect('/admin/tags.php', []);
    }

    $duplicate = db_fetch_value('SELECT id FROM tags WHERE name = ? AND id <> ? LIMIT 1', [$name, $id]);
    if ($duplicate) {
        flash('error', 'มีแท็กชื่อนี้อยู่แล้ว');
        clean_redirect('/admin/tags.php', ['edit' => $id]);
    }

    $slug = unique_slug('tags', $name, $id > 0 ? $id : null);

    if ($id > 0) {
        $stmt = db()->prepare('UPDATE tags SET name = ?, slug = ? WHERE id = ?');
        $stmt->execute([$name, $slug, $id]);
        log_activity('update_tag', 'tags', $id);
    } else {
        $stmt = db()->prepare('INSERT INTO tags (name, slug, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$name, $slug]);
        $id = (int)db()->lastInsertId();
        log_activity('create_tag', 'tags', $id);
    }

    cache_clear_all();
    flash('success', 'บันทึกแท็กแล้ว');
    clean_redirect('/admin/tags.php', []);
}

$editTag = null;
if ($editId > 0) {
    $rows = db_fetch_all('SELECT * FROM tags WHERE id = ? LIMIT 1', [$editId]);
    if ($rows) {
        $editTag = $rows[0];
    }
}

$whereSql = '1=1';
$params = [];
if ($keyword !== '') {
    $whereSql = '(t.name LIKE ? OR t.slug LIKE ?)';
    $params[] = '%' . $keyword . '%';
    $params[] = '%' . $keyword . '%';
}

$items = db_fetch_all('SELECT t.*,
                       (SELECT COUNT(*) FROM article_tags atg WHERE atg.tag_id = t.id) AS article_total,
                       (SELECT COUNT(*) FROM blog_tags bt WHERE bt.tag_id = t.id) AS blog_total,
                       (SELECT COUNT(*) FROM portfolio_tags pt WHERE pt.tag_id = t.id) AS portfolio_total
                       FROM tags t
                       WHERE ' . $whereSql . '
                       ORDER BY t.name ASC', $params);

$totalTags = count($items);
$usedTags = 0;
$unusedTags = 0;
$activeTags = 0;
$hiddenTags = 0;
foreach ($items as $item) {
    $totalUsage = (int)$item['article_total'] + (int)$item['blog_total'] + (int)$item['portfolio_total'];
    if ($totalUsage > 0) {
        $usedTags++;
    } else {
        $unusedTags++;
    }
    if ((int)($item['is_active'] ?? 1) === 1) {
        $activeTags++;
    } else {
        $hiddenTags++;
    }
}

$pageTitle = 'จัดการแท็ก';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="dashboard-hero overflow-hidden rounded-[2rem] p-6 text-white md:p-8">
        <div class="grid gap-6 lg:grid-cols-[1fr_420px] lg:items-end">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/60">
                    <i class="fa-solid fa-tags mr-2"></i>ผู้ดูแลระบบ
                </p>
                <h1 class="mt-2 text-3xl font-black md:text-4xl">จัดการแท็กทั้งหมด</h1>
                <p class="mt-3 max-w-3xl text-base font-semibold leading-8 text-white/75">
                    ควบคุมแท็กกลางที่ใช้กับบทความเว็บ บทความช่างภาพ และผลงาน เพื่อให้ข้อมูลเป็นชุดเดียวกันทั้งระบบ
                </p>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-tags text-2xl text-red-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format($totalTags) ?></div>
                    <div class="text-xs font-black text-white/55">ทั้งหมด</div>
                </div>
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-link text-2xl text-emerald-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format($usedTags) ?></div>
                    <div class="text-xs font-black text-white/55">ถูกใช้งาน</div>
                </div>
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-trash-can text-2xl text-amber-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format($hiddenTags) ?></div>
                    <div class="text-xs font-black text-white/55">ซ่อนไว้</div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[420px_1fr]">
        <form method="post" class="stock-card rounded-[1.75rem] p-6">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php if ($editTag): ?><?= (int)$editTag['id'] ?><?php endif; ?>">

            <p class="section-kicker">
                <?php if ($editTag): ?>
                    <i class="fa-solid fa-pen mr-2"></i>แก้ไขแท็ก
                <?php else: ?>
                    <i class="fa-solid fa-plus mr-2"></i>เพิ่มแท็ก
                <?php endif; ?>
            </p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950">
                <?php if ($editTag): ?>แก้ไขชื่อแท็ก<?php else: ?>สร้างแท็กใหม่<?php endif; ?>
            </h2>
            <p class="mt-2 text-sm font-semibold leading-7 text-neutral-600">
                ใช้ชื่อสั้น เข้าใจง่าย เช่น รถ, เชียงราย, งานแต่งงาน, พอร์ตเทรต
            </p>

            <label class="mt-5 grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-tag mr-2 text-red-600"></i>ชื่อแท็ก <?= required_mark() ?></span>
                <input name="name" required maxlength="100" value="<?php if ($editTag): ?><?= h($editTag['name']) ?><?php endif; ?>" class="stock-input rounded-2xl px-4 py-3 font-semibold" placeholder="เช่น รถ, เชียงราย">
            </label>

            <?php if ($editTag): ?>
                <div class="mt-4 rounded-2xl bg-neutral-50 p-4 text-sm font-bold text-neutral-600">
                    <i class="fa-solid fa-link mr-2 text-red-600"></i>Slug ปัจจุบัน:
                    <span class="font-black text-neutral-950"><?= h($editTag['slug']) ?></span>
                </div>
            <?php endif; ?>

            <div class="mt-5 flex flex-wrap gap-3">
                <button class="stock-button rounded-2xl px-5 py-3 font-black">
                    <i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกแท็ก
                </button>
                <?php if ($editTag): ?>
                    <?= clean_context_button('/admin/tags.php', [], '<i class="fa-solid fa-xmark mr-2"></i>ยกเลิกแก้ไข', 'rounded-2xl border border-neutral-200 px-5 py-3 font-black text-neutral-700 hover:bg-neutral-950 hover:text-white') ?>
                <?php endif; ?>
            </div>
        </form>

        <div class="stock-card rounded-[1.75rem] p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="section-kicker"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหาแท็ก</p>
                    <h2 class="mt-1 text-2xl font-black text-neutral-950">รายการแท็กในระบบ</h2>
                </div>
                <?= clean_context_button('/admin/tags.php', [], '<i class="fa-solid fa-rotate-left mr-2"></i>ล้างค้นหา', 'rounded-full border border-neutral-200 px-4 py-2 text-sm font-black text-neutral-700 hover:bg-neutral-950 hover:text-white') ?>
            </div>
            <form method="post" class="mt-5 grid gap-3 md:grid-cols-[1fr_auto]">
                <?= csrf_field() ?>
                <input type="hidden" name="__context_nav" value="1">
                <input name="q" value="<?= h($keyword) ?>" class="stock-input rounded-2xl px-4 py-3 font-semibold" placeholder="ค้นหาชื่อแท็กหรือ slug">
                <button class="stock-button rounded-2xl px-5 py-3 font-black"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหา</button>
            </form>
        </div>
    </div>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.75rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>แท็ก</th>
                    <th>Slug</th>
                    <th>บทความช่างภาพ</th>
                    <th>บทความเว็บ</th>
                    <th>ผลงาน</th>
                    <th>รวมใช้งาน</th>
                    <th>สถานะ</th>
                    <th>วันที่สร้าง</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    $articleUsage = (int)$item['article_total'];
                    $blogUsage = (int)$item['blog_total'];
                    $portfolioUsage = (int)$item['portfolio_total'];
                    $totalUsage = $articleUsage + $blogUsage + $portfolioUsage;
                    $isActiveTag = (int)($item['is_active'] ?? 1) === 1;
                    $hideText = 'ระบบจะซ่อนแท็ก “' . (string)$item['name'] . '” ออกจากหน้าเลือกแท็กและหน้า public แต่ข้อมูลเดิมและความสัมพันธ์เดิมยังเก็บอยู่ในฐานข้อมูล';
                    ?>
                    <tr>
                        <td class="font-black text-neutral-950">
                            <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1.5 text-red-700">
                                <i class="fa-solid fa-tag"></i><?= h($item['name']) ?>
                            </span>
                        </td>
                        <td><code class="rounded-lg bg-neutral-100 px-2 py-1 text-xs font-bold"><?= h($item['slug']) ?></code></td>
                        <td><?= number_format((int)$item['article_total']) ?></td>
                        <td><?= number_format((int)$item['blog_total']) ?></td>
                        <td><?= number_format((int)$item['portfolio_total']) ?></td>
                        <td>
                            <?php if ($totalUsage > 0): ?>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700"><?= number_format($totalUsage) ?> จุด</span>
                            <?php else: ?>
                                <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-black text-neutral-600">ยังไม่ใช้</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isActiveTag): ?>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i>เปิดใช้งาน</span>
                            <?php else: ?>
                                <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-black text-neutral-600"><i class="fa-solid fa-eye-slash mr-1"></i>ซ่อนไว้</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h(format_be_datetime($item['created_at'])) ?></td>
                        <td>
                            <div class="flex flex-wrap gap-2">
                                <?= clean_context_button('/admin/tags.php', ['edit' => (int)$item['id']], '<i class="fa-solid fa-pen mr-1"></i>แก้ไข', 'rounded-full bg-neutral-950 px-3 py-1.5 text-xs font-black text-white hover:bg-red-600') ?>
                                <?php if ($isActiveTag): ?>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                        <button
                                            class="rounded-full bg-amber-50 px-3 py-1.5 text-xs font-black text-amber-700 hover:bg-amber-500 hover:text-white"
                                            data-confirm="ซ่อนแท็กนี้?"
                                            data-confirm-text="<?= h($hideText) ?>"
                                            data-confirm-button="ซ่อนแท็ก"
                                        >
                                            <i class="fa-solid fa-eye-slash mr-1"></i>ซ่อน
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                        <button class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-black text-emerald-700 hover:bg-emerald-600 hover:text-white" data-confirm="เปิดใช้งานแท็กนี้อีกครั้ง?" data-confirm-button="เปิดใช้งาน">
                                            <i class="fa-solid fa-eye mr-1"></i>เปิดใช้
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

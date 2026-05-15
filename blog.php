<?php
require_once __DIR__ . '/includes/functions.php';

$context = clean_context_init(['source', 'q', 'category', 'page']);
$currentUser = current_user();

$source = (string)clean_context_value($context, 'source', 'all');
$keyword = trim((string)clean_context_value($context, 'q', ''));
$category = trim((string)clean_context_value($context, 'category', ''));
$page = max(1, (int)clean_context_value($context, 'page', 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

if (!in_array($source, ['all', 'system', 'photographer'], true)) {
    $source = 'all';
}

$baseSql = 'FROM (
    SELECT
        "system" AS article_source,
        b.id,
        b.title,
        b.slug,
        b.cover_image,
        b.excerpt,
        b.content,
        u.name AS author_name,
        "ผู้ดูแลระบบ" AS author_role,
        b.published_at,
        b.created_at,
        (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ", ")
         FROM blog_tags bt
         JOIN tags t ON t.id = bt.tag_id
         WHERE bt.blog_id = b.id) AS categories
    FROM blogs b
    JOIN users u ON u.id = b.admin_id
    WHERE b.status = "published"
      AND b.deleted_at IS NULL

    UNION ALL

    SELECT
        "photographer" AS article_source,
        a.id,
        a.title,
        a.slug,
        a.cover_image,
        LEFT(a.content, 240) AS excerpt,
        a.content,
        p.display_name AS author_name,
        "ช่างภาพ" AS author_role,
        a.published_at,
        a.created_at,
        (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ", ")
         FROM article_tags agt
         JOIN tags t ON t.id = agt.tag_id
         WHERE agt.article_id = a.id) AS categories
    FROM photographer_articles a
    JOIN photographer_profiles p ON p.id = a.photographer_id
    JOIN users u ON u.id = p.user_id
    WHERE a.status = "published"
      AND a.deleted_at IS NULL
      AND p.deleted_at IS NULL
      AND p.approval_status = "approved"
      AND u.status = "active"
      AND u.deleted_at IS NULL
) article_pool
WHERE 1 = 1';

$whereSql = '';
$params = [];

if ($source !== 'all') {
    $whereSql .= ' AND article_source = ?';
    $params[] = $source;
}

if ($keyword !== '') {
    $whereSql .= ' AND (title LIKE ? OR author_name LIKE ? OR content LIKE ? OR COALESCE(categories, "") LIKE ?)';
    $keywordLike = '%' . $keyword . '%';
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $params[] = $keywordLike;
}

if ($category !== '') {
    $whereSql .= ' AND COALESCE(categories, "") LIKE ?';
    $params[] = '%' . $category . '%';
}

$countStmt = db()->prepare('SELECT COUNT(*) ' . $baseSql . $whereSql);
$countStmt->execute($params);
$totalArticles = (int)$countStmt->fetchColumn();

$articleStmt = db()->prepare('SELECT article_pool.*
                              ' . $baseSql . $whereSql . '
                              ORDER BY COALESCE(published_at, created_at) DESC, id DESC
                              LIMIT ' . $perPage . ' OFFSET ' . $offset);
$articleStmt->execute($params);
$articles = $articleStmt->fetchAll();

$blogSourceCounts = cache_remember('blog_source_counts_v2', 120, function () {
    $system = (int)db_fetch_value('SELECT COUNT(*) FROM blogs WHERE status = "published" AND deleted_at IS NULL');
    $photographer = (int)db_fetch_value('SELECT COUNT(*)
                                         FROM photographer_articles a
                                         JOIN photographer_profiles p ON p.id = a.photographer_id
                                         JOIN users u ON u.id = p.user_id
                                         WHERE a.status = "published"
                                           AND a.deleted_at IS NULL
                                           AND p.deleted_at IS NULL
                                           AND p.approval_status = "approved"
                                           AND u.status = "active"
                                           AND u.deleted_at IS NULL');
    return [
        'system' => $system,
        'photographer' => $photographer,
    ];
});
$systemCount = (int)$blogSourceCounts['system'];
$photographerCount = (int)$blogSourceCounts['photographer'];
$allCount = $systemCount + $photographerCount;

$categories = db_fetch_all_cached('blog_public_categories', 300, 'SELECT DISTINCT t.name
                                                                    FROM tags t
                                                                    WHERE EXISTS (SELECT 1 FROM blog_tags bt JOIN blogs b ON b.id = bt.blog_id WHERE bt.tag_id = t.id AND b.status = "published" AND b.deleted_at IS NULL)
                                                                       OR EXISTS (SELECT 1
                                                                                  FROM article_tags agt
                                                                                  JOIN photographer_articles a ON a.id = agt.article_id
                                                                                  JOIN photographer_profiles p ON p.id = a.photographer_id
                                                                                  JOIN users u ON u.id = p.user_id
                                                                                  WHERE agt.tag_id = t.id
                                                                                    AND a.status = "published"
                                                                                    AND a.deleted_at IS NULL
                                                                                    AND p.deleted_at IS NULL
                                                                                    AND p.approval_status = "approved"
                                                                                    AND u.status = "active"
                                                                                    AND u.deleted_at IS NULL)
                                                                    ORDER BY t.name');

$sourceTabs = [
    ['all', 'ทั้งหมด', $allCount, 'fa-newspaper'],
    ['system', 'จากระบบ', $systemCount, 'fa-user-shield'],
    ['photographer', 'จากช่างภาพ', $photographerCount, 'fa-camera-retro'],
];

$pageTitle = 'บทความ';
include __DIR__ . '/includes/header.php';
?>

<section class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
    <div class="dashboard-hero overflow-hidden rounded-[2rem] p-6 text-white md:p-8">
        <div class="grid gap-6 lg:grid-cols-[1fr_420px] lg:items-end">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/60">
                    <i class="fa-solid fa-newspaper mr-2"></i>บทความ
                </p>
                <h1 class="mt-2 text-4xl font-black md:text-5xl">รวมบทความถ่ายภาพและคำแนะนำการจอง</h1>
                <p class="mt-4 max-w-3xl text-base font-semibold leading-8 text-white/75 md:text-lg">
                    อ่านบทความจากผู้ดูแลระบบและช่างภาพในเชียงรายในหน้าเดียว ค้นหาด้วยชื่อบทความ ผู้เขียน หรือประเภทบทความได้ทันที
                </p>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <?php foreach ($sourceTabs as $tab): ?>
                    <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                        <i class="fa-solid <?= h($tab[3]) ?> text-2xl text-red-200"></i>
                        <div class="mt-2 text-2xl font-black"><?= number_format((int)$tab[2]) ?></div>
                        <div class="text-xs font-black text-white/55"><?= h($tab[1]) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="mt-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap gap-2">
            <?php foreach ($sourceTabs as $tab): ?>
                <?php
                $tabClass = 'rounded-full border px-5 py-3 text-sm font-black transition';
                if ($source === $tab[0]) {
                    $tabClass .= ' border-neutral-950 bg-neutral-950 text-white';
                } else {
                    $tabClass .= ' border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-950 hover:text-white';
                }
                ?>
                <?= clean_context_button('/blog.php', ['source' => $tab[0], 'q' => $keyword, 'category' => $category, 'page' => 1], '<i class="fa-solid ' . h($tab[3]) . ' mr-2"></i>' . h($tab[1]) . ' <span class="ml-1 opacity-70">(' . number_format((int)$tab[2]) . ')</span>', $tabClass) ?>
            <?php endforeach; ?>
        </div>

        <div class="flex flex-wrap gap-3">
            <?php if ($currentUser && $currentUser['role_name'] === 'admin'): ?>
                <a href="/admin/blogs.php" class="rounded-full border border-neutral-200 bg-white px-5 py-3 text-sm font-black text-neutral-700 hover:bg-neutral-950 hover:text-white">
                    <i class="fa-solid fa-pen-to-square mr-2"></i>จัดการบทความระบบ
                </a>
            <?php endif; ?>
            <?= clean_context_button('/blog.php', ['source' => 'all', 'q' => '', 'category' => '', 'page' => 1], '<i class="fa-solid fa-newspaper mr-2"></i>ดูบทความทั้งหมด', 'stock-button rounded-full px-5 py-3 text-sm font-black') ?>
        </div>
    </div>

    <form method="post" action="/blog.php" class="stock-card mt-6 rounded-[1.75rem] p-5">
        <?= csrf_field() ?>
        <input type="hidden" name="__context_nav" value="1">
        <input type="hidden" name="page" value="1">
        <div class="grid gap-4 lg:grid-cols-[1fr_220px_180px]">
            <label class="block">
                <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-magnifying-glass mr-2 text-red-600"></i>ค้นหาด้วยชื่อบทความ / ผู้เขียน / ประเภท</span>
                <input name="q" value="<?= h($keyword) ?>" placeholder="เช่น รับปริญญา, งานแต่งงาน, ชื่อช่างภาพ" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
            </label>
            <label class="block">
                <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-tags mr-2 text-red-600"></i>ประเภทบทความ</span>
                <select name="category" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
                    <option value="">ทุกประเภท</option>
                    <?php foreach ($categories as $row): ?>
                        <option value="<?= h($row['name']) ?>" <?= $category === $row['name'] ? 'selected' : '' ?>>
                            <?= h($row['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-black text-neutral-700"><i class="fa-solid fa-filter mr-2 text-red-600"></i>แหล่งบทความ</span>
                <select name="source" class="stock-input mt-2 w-full rounded-2xl px-4 py-3 font-semibold">
                    <option value="all" <?= $source === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
                    <option value="system" <?= $source === 'system' ? 'selected' : '' ?>>จากระบบ</option>
                    <option value="photographer" <?= $source === 'photographer' ? 'selected' : '' ?>>จากช่างภาพ</option>
                </select>
            </label>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-bold text-neutral-500">
                <i class="fa-solid fa-circle-info mr-1 text-red-600"></i>พบ <?= number_format($totalArticles) ?> บทความ
            </p>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-muted btn-md" onclick="this.closest('form').querySelector('[name=q]').value=''; this.closest('form').querySelector('[name=category]').value=''; this.closest('form').querySelector('[name=source]').value='all'; this.closest('form').submit();">
                    <i class="fa-solid fa-rotate-left mr-2"></i>ล้างตัวกรอง
                </button>
                <button class="stock-button rounded-full px-5 py-3 font-black">
                    <i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหาบทความ
                </button>
            </div>
        </div>
    </form>

    <?php if ($keyword !== '' || $category !== '' || $source !== 'all'): ?>
        <div class="mt-5 flex flex-wrap gap-2">
            <?php if ($source !== 'all'): ?>
                <span class="rounded-full bg-neutral-950 px-4 py-2 text-sm font-black text-white">
                    <i class="fa-solid fa-filter mr-1"></i><?= $source === 'system' ? 'จากระบบ' : 'จากช่างภาพ' ?>
                </span>
            <?php endif; ?>
            <?php if ($keyword !== ''): ?>
                <span class="rounded-full bg-red-50 px-4 py-2 text-sm font-black text-red-700">
                    <i class="fa-solid fa-magnifying-glass mr-1"></i><?= h($keyword) ?>
                </span>
            <?php endif; ?>
            <?php if ($category !== ''): ?>
                <span class="rounded-full bg-amber-50 px-4 py-2 text-sm font-black text-amber-700">
                    <i class="fa-solid fa-tag mr-1"></i><?= h($category) ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($articles as $article): ?>
            <?php
            $isSystemArticle = $article['article_source'] === 'system';
            $articleBadgeClass = 'bg-red-50 text-red-700';
            $articleBadgeIcon = 'fa-user-shield';
            $articleBadgeText = 'จากระบบ';
            $detailPath = '/blog_detail.php';
            if (!$isSystemArticle) {
                $articleBadgeClass = 'bg-amber-50 text-amber-700';
                $articleBadgeIcon = 'fa-camera-retro';
                $articleBadgeText = 'จากช่างภาพ';
                $detailPath = '/article_detail.php';
            }
            $articleExcerpt = trim((string)$article['excerpt']);
            if ($articleExcerpt === '') {
                $articleExcerpt = strip_tags((string)$article['content']);
            }
            $articleDate = $article['published_at'] ?: $article['created_at'];
            $tagList = array_filter(array_map('trim', explode(',', (string)$article['categories'])));
            $visibleTags = array_slice($tagList, 0, 4);
            $hiddenTagCount = max(0, count($tagList) - count($visibleTags));
            ?>
            <article class="stock-card stock-card-hover flex h-full flex-col overflow-hidden rounded-[1.75rem]">
                <img class="h-56 w-full object-cover" loading="lazy" decoding="async" src="<?= h(public_image($article['cover_image'], '/assets/uploads/seed/photo-1492691527719-9d1e07e534b4.jpg')) ?>" alt="">
                <div class="flex flex-1 flex-col p-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-black <?= h($articleBadgeClass) ?>">
                            <i class="fa-solid <?= h($articleBadgeIcon) ?>"></i><?= h($articleBadgeText) ?>
                        </span>
                        <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-black text-neutral-600">
                            <i class="fa-solid fa-user mr-1"></i><?= h($article['author_name']) ?>
                        </span>
                        <?= new_content_badge($articleDate) ?>
                    </div>
                    <h2 class="mt-4 line-clamp-2 text-xl font-black leading-snug text-neutral-950"><?= h($article['title']) ?></h2>
                    <p class="mt-2 text-xs font-black text-neutral-400"><i class="fa-solid fa-calendar-day mr-1 text-red-600"></i><?= h(format_be_datetime($articleDate)) ?></p>
                    <p class="mt-3 line-clamp-3 text-base font-semibold leading-7 text-neutral-600"><?= h(strip_tags($articleExcerpt)) ?></p>
                    <?php if ($tagList): ?>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <?php foreach ($visibleTags as $tagName): ?>
                                <span class="rounded-full bg-neutral-50 px-3 py-1 text-xs font-black text-neutral-500">
                                    <i class="fa-solid fa-tag mr-1 text-red-600"></i><?= h($tagName) ?>
                                </span>
                            <?php endforeach; ?>
                            <?php if ($hiddenTagCount > 0): ?>
                                <span class="rounded-full bg-neutral-950 px-3 py-1 text-xs font-black text-white">+<?= number_format($hiddenTagCount) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="mt-auto pt-5">
                        <?= clean_context_button($detailPath, ['slug' => $article['slug']], '<i class="fa-solid fa-eye mr-2"></i>อ่านบทความ', 'btn-primary btn-md w-full text-center') ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if (!$articles): ?>
            <div class="empty-state rounded-[2rem] p-10 text-center md:col-span-2 xl:col-span-3">
                <i class="fa-solid fa-newspaper text-5xl text-red-600"></i>
                <h2 class="mt-4 text-2xl font-black">ไม่พบบทความ</h2>
                <p class="mt-2 text-neutral-600">ลองเปลี่ยนคำค้นหา แหล่งบทความ หรือประเภทบทความ</p>
                <?= clean_context_button('/blog.php', ['source' => 'all', 'q' => '', 'category' => '', 'page' => 1], '<i class="fa-solid fa-rotate-left mr-2"></i>ล้างตัวกรอง', 'btn-cta btn-md mt-5') ?>
            </div>
        <?php endif; ?>
    </div>

    <?= paginate_clean($totalArticles, $page, $perPage, '/blog.php', ['source' => $source, 'q' => $keyword, 'category' => $category]) ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

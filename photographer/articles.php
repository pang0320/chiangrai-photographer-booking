<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');
ensure_photographer_articles_excerpt_column();

$profile = photographer_profile_by_user((int)current_user()['id']);
if (!$profile) {
    flash('error', 'ไม่พบโปรไฟล์ช่างภาพ');
    redirect('/photographer/onboarding.php');
}
$pid = (int)$profile['id'];
$cleanContext = clean_context_init(['edit', 'sort']);
$editId = (int)clean_context_value($cleanContext, 'edit', 0);
$sort = (string)clean_context_value($cleanContext, 'sort', 'newest');

if (!in_array($sort, ['newest', 'oldest'], true)) {
    $sort = 'newest';
}

if (is_post()) {
    verify_csrf();

    try {
        $action = (string)($_POST['action'] ?? 'save');
        $articleId = (int)($_POST['id'] ?? 0);

        if ($action === 'hide' || $action === 'delete') {
            $stmt = db()->prepare('UPDATE photographer_articles SET status = "hidden", deleted_at = NULL, updated_at = NOW() WHERE id = ? AND photographer_id = ?');
            $stmt->execute([$articleId, $pid]);
            log_activity('manage_articles', 'photographer_articles', $articleId);
            flash('success', 'ซ่อนบทความแล้ว บทความยังอยู่ในหน้าจัดการของคุณ');
            clean_redirect('/photographer/articles.php', ['sort' => $sort]);
        }

        if ($action === 'publish') {
            $stmt = db()->prepare('UPDATE photographer_articles
                                   SET status = "published",
                                       deleted_at = NULL,
                                       published_at = IFNULL(published_at, NOW()),
                                       updated_at = NOW()
                                   WHERE id = ? AND photographer_id = ?');
            $stmt->execute([$articleId, $pid]);
            log_activity('publish_article', 'photographer_articles', $articleId);
            flash('success', 'เผยแพร่บทความแล้ว');
            clean_redirect('/photographer/articles.php', ['sort' => $sort]);
        }

        if ($action === 'draft') {
            $stmt = db()->prepare('UPDATE photographer_articles SET status = "draft", deleted_at = NULL, updated_at = NOW() WHERE id = ? AND photographer_id = ?');
            $stmt->execute([$articleId, $pid]);
            log_activity('draft_article', 'photographer_articles', $articleId);
            flash('success', 'เปลี่ยนเป็นฉบับร่างแล้ว');
            clean_redirect('/photographer/articles.php', ['sort' => $sort]);
        }

        $currentArticle = null;
        if ($articleId > 0) {
            $stmt = db()->prepare('SELECT * FROM photographer_articles WHERE id = ? AND photographer_id = ? LIMIT 1');
            $stmt->execute([$articleId, $pid]);
            $currentArticle = $stmt->fetch();

            if (!$currentArticle) {
                flash('error', 'ไม่พบบทความที่ต้องการแก้ไข');
                clean_redirect('/photographer/articles.php', ['sort' => $sort]);
            }
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $excerpt = trim((string)($_POST['excerpt'] ?? ''));
        $status = (string)($_POST['status'] ?? 'draft');
        $content = trim((string)($_POST['content'] ?? ''));
        $contentFallback = trim((string)($_POST['content_fallback'] ?? ''));

        if ($content === '' && $contentFallback !== '') {
            $fallbackParagraphs = [];
            $fallbackLines = preg_split('/\R+/', $contentFallback);
            if (!is_array($fallbackLines)) {
                $fallbackLines = [$contentFallback];
            }

            foreach ($fallbackLines as $fallbackLine) {
                $fallbackLine = trim((string)$fallbackLine);
                if ($fallbackLine !== '') {
                    $fallbackParagraphs[] = '<p>' . h($fallbackLine) . '</p>';
                }
            }

            $content = implode('', $fallbackParagraphs);
        }

        if ($content === '' && $currentArticle && !empty($currentArticle['content'])) {
            $content = (string)$currentArticle['content'];
        }

        $plainContent = trim(strip_tags($content));
        $tagIds = selected_article_tag_ids_from_post($pid);

        if (!in_array($status, ['draft', 'published', 'hidden'], true)) {
            $status = 'draft';
        }

        if ($title === '' || $plainContent === '') {
            flash('error', 'กรุณากรอกหัวข้อและเนื้อหาบทความ');
            clean_redirect('/photographer/articles.php', ['edit' => $articleId, 'sort' => $sort]);
        }

        $currentCover = '';
        if ($currentArticle && !empty($currentArticle['cover_image'])) {
            $currentCover = (string)$currentArticle['cover_image'];
        }

        $cover = upload_image($_FILES['cover_image'] ?? [], 'articles');
        if (!$cover) {
            $cover = $currentCover;
        }

        if ($articleId > 0) {
            $slug = unique_slug('photographer_articles', $title, $articleId);
            $stmt = db()->prepare('UPDATE photographer_articles
                                   SET title = ?, slug = ?, cover_image = ?, excerpt = ?, content = ?, status = ?,
                                       deleted_at = NULL,
                                       published_at = IF(? = "published", IFNULL(published_at, NOW()), published_at),
                                       updated_at = NOW()
                                   WHERE id = ? AND photographer_id = ?');
            $stmt->execute([$title, $slug, $cover, $excerpt, $content, $status, $status, $articleId, $pid]);
            sync_article_tag_relations('article_tags', 'article_id', $articleId, $tagIds, $pid);
            log_activity('manage_articles', 'photographer_articles', $articleId);
            flash('success', 'แก้ไขบทความแล้ว');
        } else {
            $stmt = db()->prepare('INSERT INTO photographer_articles (photographer_id, title, slug, cover_image, excerpt, content, status, published_at, created_at, updated_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, IF(? = "published", NOW(), NULL), NOW(), NOW())');
            $stmt->execute([$pid, $title, unique_slug('photographer_articles', $title), $cover, $excerpt, $content, $status, $status]);
            $articleId = (int)db()->lastInsertId();
            sync_article_tag_relations('article_tags', 'article_id', $articleId, $tagIds, $pid);
            log_activity('manage_articles', 'photographer_articles', $articleId);
            flash('success', 'บันทึกบทความแล้ว');
        }

        clean_redirect('/photographer/articles.php', ['sort' => $sort]);
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
}

$editArticle = null;
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM photographer_articles WHERE id = ? AND photographer_id = ? LIMIT 1');
    $stmt->execute([$editId, $pid]);
    $editArticle = $stmt->fetch();
}
$editTagIds = $editArticle ? selected_article_tag_ids('article_tags', 'article_id', (int)$editArticle['id']) : [];
$tagSelectorHtml = article_tag_selector_html($editTagIds, 'tag_ids', $pid);
$editStatus = '';
if ($editArticle) {
    $editStatus = (string)$editArticle['status'];
    if (!empty($editArticle['deleted_at'])) {
        $editStatus = 'hidden';
    }
}

$orderSql = 'created_at DESC, id DESC';
if ($sort === 'oldest') {
    $orderSql = 'created_at ASC, id ASC';
}

$stmt = db()->prepare('SELECT a.*,
                       IF(a.deleted_at IS NOT NULL, "hidden", a.status) AS manage_status,
                       (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ", ")
                        FROM article_tags atg
                        JOIN tags t ON t.id = atg.tag_id
                        WHERE atg.article_id = a.id
                          AND t.is_active = 1) AS tags
                       FROM photographer_articles a
                       WHERE a.photographer_id = ?
                       ORDER BY ' . $orderSql);
$stmt->execute([$pid]);
$items = $stmt->fetchAll();

$allowedArticleTagNames = [];
foreach (article_tag_options($pid) as $allowedTagGroup) {
    foreach ($allowedTagGroup as $allowedTag) {
        $allowedArticleTagNames[(string)$allowedTag['name']] = true;
    }
}

$articleStatusCounts = [
    'all' => count($items),
    'published' => 0,
    'draft' => 0,
    'hidden' => 0,
];

foreach ($items as $countItem) {
    $countStatus = (string)$countItem['status'];
    if (!empty($countItem['manage_status'])) {
        $countStatus = (string)$countItem['manage_status'];
    }

    if (isset($articleStatusCounts[$countStatus])) {
        $articleStatusCounts[$countStatus]++;
    }
}

$pageTitle = 'บทความ';
include __DIR__ . '/../includes/header.php';
?>

<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="dashboard-hero overflow-hidden rounded-[2rem] p-6 text-white md:p-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/60"><i class="fa-solid fa-newspaper mr-2"></i>สตูดิโอช่างภาพ</p>
                <h1 class="mt-2 text-3xl font-black md:text-4xl">บทความช่างภาพ</h1>
                <p class="mt-3 max-w-2xl text-base font-semibold leading-8 text-white/75">เขียนคำแนะนำการถ่ายภาพ แนะนำการเตรียมตัว หรือเล่าแนวทางทำงานของคุณให้ลูกค้าอ่านก่อนจอง</p>
            </div>
            <div class="rounded-[1.5rem] bg-white/12 p-4 text-sm font-bold leading-7 text-white/75">
                <i class="fa-solid fa-circle-info mr-2 text-red-200"></i>Editor รองรับตัวหนา ตัวเอียง หัวข้อ รายการ และย่อหน้าแบบคล้าย Word
            </div>
        </div>
    </div>

    <form id="article-form" method="post" enctype="multipart/form-data" class="mt-6 grid gap-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?php if ($editArticle): ?><?= (int)$editArticle['id'] ?><?php endif; ?>">
        <input type="hidden" name="sort" value="<?= h($sort) ?>">
        <input id="article-content" type="hidden" name="content" value="<?php if ($editArticle): ?><?= h($editArticle['content']) ?><?php endif; ?>">

        <div class="stock-card grid gap-4 rounded-[1.75rem] p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="section-kicker"><i class="fa-solid fa-pen-to-square mr-2"></i><?= $editArticle ? 'แก้ไขบทความ' : 'เพิ่มบทความใหม่' ?></p>
                    <h2 class="mt-1 text-2xl font-black text-neutral-950"><?= $editArticle ? h($editArticle['title']) : 'เขียนบทความใหม่' ?></h2>
                </div>
                <?php if ($editArticle): ?>
                    <button type="submit" form="article-reset-form" class="btn-muted btn-md">
                        <i class="fa-solid fa-plus mr-2"></i>เพิ่มบทความใหม่
                    </button>
                <?php endif; ?>
            </div>

            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-heading mr-2 text-red-600"></i>หัวข้อบทความ <?= required_mark() ?></span>
                <input name="title" required value="<?php if ($editArticle): ?><?= h($editArticle['title']) ?><?php endif; ?>" placeholder="หัวข้อบทความ" class="stock-input rounded-2xl px-4 py-3 font-semibold">
            </label>

            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-image mr-2 text-red-600"></i>รูปปกบทความ</span>
                <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" class="stock-input rounded-2xl px-4 py-3 font-semibold">
                <span class="text-xs font-bold leading-6 text-neutral-500"><?= h(UPLOAD_IMAGE_HELP_TEXT) ?> <?php if ($editArticle && !empty($editArticle['cover_image'])): ?>ถ้าไม่เลือกไฟล์ใหม่ ระบบจะใช้รูปเดิม<?php endif; ?></span>
            </label>

            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-align-left mr-2 text-red-600"></i>คำโปรยบทความ</span>
                <textarea name="excerpt" rows="2" maxlength="500" placeholder="สรุปสั้น ๆ ที่จะแสดงบนการ์ดบทความ" class="stock-input rounded-2xl px-4 py-3 font-semibold"><?php if ($editArticle): ?><?= h($editArticle['excerpt'] ?? '') ?><?php endif; ?></textarea>
            </label>

            <div class="article-editor-panel">
                <label class="mb-2 block text-sm font-black text-neutral-700"><i class="fa-solid fa-file-lines mr-2 text-red-600"></i>เนื้อหาบทความ <?= required_mark() ?></label>
                <div class="article-editor-shell overflow-hidden rounded-[1.35rem] border border-neutral-200 bg-white">
                    <div id="article-editor" class="hidden"></div>
                    <textarea
                        id="article-content-fallback"
                        name="content_fallback"
                        rows="10"
                        placeholder="พิมพ์เนื้อหาบทความ"
                        class="min-h-[280px] w-full resize-y border-0 bg-white px-4 py-4 text-base font-semibold leading-8 text-neutral-800 outline-none focus:ring-0"><?php if ($editArticle): ?><?= h(strip_tags((string)$editArticle['content'])) ?><?php endif; ?></textarea>
                </div>
                <p class="mt-2 text-xs font-bold leading-6 text-neutral-500">ถ้า editor แบบ Word โหลดไม่ขึ้น ระบบจะใช้ช่องพิมพ์สำรองนี้เพื่อให้ยังบันทึกบทความได้</p>
            </div>
        </div>

        <div class="article-tags-panel stock-card grid gap-3 rounded-[1.75rem] border-red-100 bg-red-50/45 p-6">
            <div>
                <label class="block text-sm font-black text-neutral-700"><i class="fa-solid fa-tags mr-2 text-red-600"></i>แท็กบทความ</label>
                <p class="mt-1 text-xs font-bold leading-6 text-neutral-500">ประเภทงานดึงจากหมวดหมู่งานในฐานข้อมูล และพื้นที่รับงานดึงจากอำเภอที่โปรไฟล์ของคุณเปิดรับงานไว้เท่านั้น</p>
            </div>
            <?= $tagSelectorHtml ?>
        </div>

        <div class="stock-card grid gap-4 rounded-[1.75rem] p-6 md:grid-cols-[1fr_auto] md:items-end">
            <label class="grid gap-2 text-sm font-black text-neutral-700">
                <span><i class="fa-solid fa-toggle-on mr-2 text-red-600"></i>สถานะบทความ</span>
                <select name="status" class="stock-input rounded-2xl px-4 py-3 font-semibold">
                    <?php foreach (['draft' => 'ฉบับร่าง', 'published' => 'เผยแพร่', 'hidden' => 'ซ่อน'] as $status => $label): ?>
                        <option value="<?= h($status) ?>" <?php if ($editStatus === $status): ?>selected<?php endif; ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="stock-button rounded-full px-5 py-3 font-black"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกบทความ</button>
        </div>
    </form>
    <?php if ($editArticle): ?>
        <form id="article-reset-form" method="post" action="/photographer/articles.php" class="hidden">
            <?= clean_context_inputs(['sort' => $sort]) ?>
        </form>
    <?php endif; ?>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="section-kicker"><i class="fa-solid fa-list mr-2"></i>รายการบทความ</p>
            <h2 class="mt-1 text-2xl font-black text-neutral-950">บทความทั้งหมดของฉัน</h2>
            <p class="mt-2 text-sm font-bold text-neutral-500">
                <i class="fa-solid fa-circle-info mr-1 text-red-600"></i>
                บทความที่ซ่อนจะยังอยู่ในหน้านี้ แต่จะไม่แสดงในหน้าบทความสาธารณะ
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?= clean_context_button('/photographer/articles.php', ['sort' => 'newest'], '<i class="fa-solid fa-arrow-down-wide-short mr-2"></i>ใหม่ไปเก่า', $sort === 'newest' ? 'btn-primary btn-md' : 'btn-muted btn-md') ?>
            <?= clean_context_button('/photographer/articles.php', ['sort' => 'oldest'], '<i class="fa-solid fa-arrow-up-wide-short mr-2"></i>เก่าไปใหม่', $sort === 'oldest' ? 'btn-primary btn-md' : 'btn-muted btn-md') ?>
        </div>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-[1.4rem] border border-neutral-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400"><i class="fa-solid fa-newspaper mr-1 text-red-600"></i>ทั้งหมด</p>
            <p class="mt-2 text-2xl font-black text-neutral-950"><?= number_format($articleStatusCounts['all']) ?></p>
        </div>
        <div class="rounded-[1.4rem] border border-emerald-100 bg-emerald-50 p-4 shadow-sm">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i>เผยแพร่</p>
            <p class="mt-2 text-2xl font-black text-emerald-800"><?= number_format($articleStatusCounts['published']) ?></p>
        </div>
        <div class="rounded-[1.4rem] border border-amber-100 bg-amber-50 p-4 shadow-sm">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-amber-700"><i class="fa-solid fa-pen mr-1"></i>ฉบับร่าง</p>
            <p class="mt-2 text-2xl font-black text-amber-800"><?= number_format($articleStatusCounts['draft']) ?></p>
        </div>
        <div class="rounded-[1.4rem] border border-rose-100 bg-rose-50 p-4 shadow-sm">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-rose-700"><i class="fa-solid fa-eye-slash mr-1"></i>ซ่อน</p>
            <p class="mt-2 text-2xl font-black text-rose-800"><?= number_format($articleStatusCounts['hidden']) ?></p>
        </div>
    </div>

    <div class="mt-5 grid gap-3" data-block-paginate="5">
        <?php foreach ($items as $item): ?>
            <?php
            $itemStatus = (string)$item['status'];
            if (!empty($item['manage_status'])) {
                $itemStatus = (string)$item['manage_status'];
            }
            ?>
            <article class="stock-card flex flex-wrap items-center justify-between gap-4 rounded-[1.35rem] p-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <b class="text-lg text-neutral-950"><?= h($item['title']) ?></b>
                        <?= status_badge($itemStatus) ?>
                        <?= new_content_badge($item['published_at'] ?: $item['created_at']) ?>
                    </div>
                    <p class="mt-2 text-sm font-bold text-neutral-500">
                        <i class="fa-solid fa-calendar-day mr-1 text-red-600"></i>
                        โพสต์: <?= h($item['published_at'] ? format_be_datetime($item['published_at']) : 'ยังไม่เผยแพร่') ?>
                        <span class="mx-2 text-neutral-300">/</span>
                        แก้ไขล่าสุด: <?= h(format_be_datetime($item['updated_at'])) ?>
                    </p>
                    <?php
                    $itemTagNames = [];
                    if (!empty($item['tags'])) {
                        foreach (array_filter(array_map('trim', explode(',', (string)$item['tags']))) as $rawTagName) {
                            if (isset($allowedArticleTagNames[$rawTagName])) {
                                $itemTagNames[] = $rawTagName;
                            }
                        }
                    }
                    ?>
                    <?php if ($itemTagNames): ?>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <?php foreach (array_slice($itemTagNames, 0, 6) as $tagName): ?>
                                <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-black text-red-700"><i class="fa-solid fa-tag mr-1"></i><?= h($tagName) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php
                    $itemExcerpt = trim((string)($item['excerpt'] ?? ''));
                    if ($itemExcerpt === '') {
                        $itemExcerpt = strip_tags((string)$item['content']);
                    }
                    ?>
                    <p class="mt-2 line-clamp-2 text-sm leading-7 text-neutral-600"><?= h($itemExcerpt) ?></p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <?= clean_context_button('/photographer/articles.php', ['edit' => (int)$item['id'], 'sort' => $sort], '<i class="fa-solid fa-pen"></i>แก้ไข', 'btn-warning btn-sm') ?>
                    <?php if ($itemStatus === 'published'): ?>
                        <?= clean_context_button('/article_detail.php', ['slug' => $item['slug']], '<i class="fa-solid fa-eye"></i>ดู', 'btn-muted btn-sm', 'inline', 'target="_blank"') ?>
                    <?php endif; ?>
                    <?php if ($itemStatus !== 'published'): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="publish">
                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                            <input type="hidden" name="sort" value="<?= h($sort) ?>">
                            <button data-confirm="เผยแพร่บทความนี้?" data-confirm-text="บทความจะกลับไปแสดงในหน้าบทความสาธารณะ" data-confirm-button="เผยแพร่" class="btn-success btn-sm">
                                <i class="fa-solid fa-circle-check mr-1"></i>เผยแพร่
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($itemStatus === 'published'): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="draft">
                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                            <input type="hidden" name="sort" value="<?= h($sort) ?>">
                            <button data-confirm="เปลี่ยนบทความเป็นฉบับร่าง?" data-confirm-text="บทความจะไม่แสดงในหน้าสาธารณะชั่วคราว" data-confirm-button="เปลี่ยนเป็นฉบับร่าง" class="btn-muted btn-sm">
                                <i class="fa-solid fa-pen mr-1"></i>ฉบับร่าง
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($itemStatus !== 'hidden'): ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="hide">
                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                        <input type="hidden" name="sort" value="<?= h($sort) ?>">
                        <button data-confirm="ซ่อนบทความนี้?" data-confirm-text="บทความจะหายจากหน้าสาธารณะ แต่ยังแสดงในหน้าจัดการบทความของคุณ" data-confirm-button="ซ่อนบทความ" class="btn-warning btn-sm">
                            <i class="fa-solid fa-eye-slash mr-1"></i>ซ่อน
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <div class="empty-state rounded-[2rem] p-10 text-center">
                <i class="fa-solid fa-newspaper text-5xl text-red-600"></i>
                <h2 class="mt-4 text-2xl font-black">ยังไม่มีบทความ</h2>
                <p class="mt-2 text-neutral-600">เริ่มเขียนบทความแรกเพื่อช่วยให้ลูกค้ารู้จักสไตล์การทำงานของคุณ</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var hiddenInput = document.getElementById('article-content');
    var editorElement = document.getElementById('article-editor');
    var fallbackElement = document.getElementById('article-content-fallback');
    var form = document.getElementById('article-form');
    if (!hiddenInput || !editorElement || !form) return;

    if (!window.Quill) {
        form.addEventListener('submit', function () {
            hiddenInput.value = '';
        });
        return;
    }

    editorElement.classList.remove('hidden');

    if (fallbackElement) {
        fallbackElement.classList.add('hidden');
    }

    var quill = new Quill(editorElement, {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote'],
                ['clean']
            ]
        }
    });

    quill.root.innerHTML = hiddenInput.value || '';

    form.addEventListener('submit', function () {
        hiddenInput.value = quill.root.innerHTML;
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

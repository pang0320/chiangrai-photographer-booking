<?php
require_once __DIR__ . '/includes/functions.php';
$faqs = db_fetch_all('SELECT * FROM faqs WHERE is_active = 1 ORDER BY category, sort_order, id');
$grouped = [];
foreach ($faqs as $faq) {
    $grouped[$faq['category']][] = $faq;
}
$pageTitle = 'คำถามที่พบบ่อย';
include __DIR__ . '/includes/header.php';
?>
<section class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="section-kicker">คำถามที่พบบ่อย</p>
            <h1 class="mt-2 text-4xl font-black">คำถามที่พบบ่อย</h1>
        </div>
        <a href="/contact.php" class="stock-button rounded-full px-5 py-3 font-black"><i class="fa-solid fa-envelope mr-2"></i>ติดต่อเรา</a>
    </div>
    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <?php foreach ($grouped as $category => $items): ?>
            <div class="stock-card rounded-[1.75rem] p-6">
                <h2 class="text-2xl font-black"><i class="fa-solid fa-circle-question mr-2 text-red-600"></i><?= h($category) ?></h2>
                <div class="mt-5 grid gap-3">
                    <?php foreach ($items as $faq): ?>
                        <details class="rounded-2xl bg-neutral-50 p-4">
                            <summary class="cursor-pointer font-black"><i class="fa-solid fa-angle-down mr-2 text-red-600"></i><?= h($faq['question']) ?></summary>
                            <p class="mt-3 leading-7 text-neutral-600"><?= nl2br(h($faq['answer'])) ?></p>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$grouped): ?>
            <div class="empty-state rounded-[2rem] p-10 text-center lg:col-span-2">
                <i class="fa-solid fa-circle-question text-5xl text-red-600"></i>
                <h2 class="mt-4 text-2xl font-black">ยังไม่มีคำถามที่พบบ่อย</h2>
                <p class="mt-2 text-neutral-600">ผู้ดูแลระบบสามารถเพิ่มคำถามที่พบบ่อยได้จากหลังบ้าน</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

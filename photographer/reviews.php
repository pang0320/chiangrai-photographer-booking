<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');

$profile = photographer_profile_by_user((int)current_user()['id']);
$pid = (int)$profile['id'];

$stmt = db()->prepare('SELECT r.*, u.name AS customer_name, b.booking_code
                       FROM reviews r
                       JOIN users u ON u.id = r.customer_id
                       JOIN bookings b ON b.id = r.booking_id
                       WHERE r.photographer_id = ? AND r.deleted_at IS NULL
                       ORDER BY r.created_at DESC');
$stmt->execute([$pid]);
$reviews = $stmt->fetchAll();

$stmt = db()->prepare('SELECT AVG(rating_quality) AS q,
                              AVG(rating_communication) AS c,
                              AVG(rating_punctuality) AS p,
                              AVG(rating_professional) AS pro
                       FROM reviews
                       WHERE photographer_id = ? AND status = "visible" AND deleted_at IS NULL');
$stmt->execute([$pid]);
$avg = $stmt->fetch() ?: [];

$ratingCards = [
    'q' => 'คุณภาพ',
    'c' => 'สื่อสาร',
    'p' => 'ตรงเวลา',
    'pro' => 'มืออาชีพ',
];

$pageTitle = 'รีวิว';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">สตูดิโอช่างภาพ</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">รีวิว</h1>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-5">
        <div class="stock-card rounded-[1.5rem] p-5">
            <p class="text-sm font-bold text-neutral-500">คะแนนเฉลี่ย</p>
            <p class="mt-2 text-3xl font-black"><?= h($profile['average_rating']) ?></p>
        </div>

        <?php foreach ($ratingCards as $key => $label): ?>
            <div class="stock-card rounded-[1.5rem] p-5">
                <p class="text-sm font-bold text-neutral-500"><?= h($label) ?></p>
                <p class="mt-2 text-3xl font-black"><?= number_format((float)($avg[$key] ?? 0), 1) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-6 grid gap-4">
        <?php foreach ($reviews as $review): ?>
            <article class="stock-card rounded-[1.5rem] p-6">
                <div class="flex flex-wrap justify-between gap-3">
                    <b><?= h($review['customer_name']) ?> · <?= h($review['booking_code']) ?></b>
                    <span class="text-red-600"><?= str_repeat('★', (int)$review['rating_overall']) ?></span>
                </div>
                <p class="mt-2 text-neutral-700"><?= nl2br(h($review['comment'])) ?></p>
                <p class="mt-2 text-xs text-neutral-500"><?= h(format_be_datetime($review['created_at'])) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

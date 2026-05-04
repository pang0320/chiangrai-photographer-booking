<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$topCategories = db_fetch_all('SELECT sc.name, COUNT(b.id) AS total
                               FROM service_categories sc
                               LEFT JOIN bookings b ON b.category_id = sc.id AND b.deleted_at IS NULL
                               GROUP BY sc.id
                               ORDER BY total DESC
                               LIMIT 10');
$topDistricts = db_fetch_all('SELECT d.district_name, COUNT(b.id) AS total
                              FROM districts d
                              LEFT JOIN bookings b ON b.district_id = d.id AND b.deleted_at IS NULL
                              GROUP BY d.id
                              ORDER BY total DESC
                              LIMIT 10');
$topPhotographers = db_fetch_all('SELECT display_name, average_rating, total_reviews
                                  FROM photographer_profiles
                                  WHERE deleted_at IS NULL
                                  ORDER BY average_rating DESC, total_reviews DESC
                                  LIMIT 10');
$avgReview = db_fetch_value('SELECT AVG(rating_overall) FROM reviews WHERE status = "visible" AND deleted_at IS NULL');

$pageTitle = 'รายงาน';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Admin</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">รายงาน</h1>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-4">
        <div class="stock-card rounded-[1.5rem] p-5">
            <p class="text-sm font-bold text-neutral-500">Users</p>
            <b class="mt-2 block text-3xl"><?= table_count('users', 'deleted_at IS NULL') ?></b>
        </div>
        <div class="stock-card rounded-[1.5rem] p-5">
            <p class="text-sm font-bold text-neutral-500">Photographers</p>
            <b class="mt-2 block text-3xl"><?= table_count('photographer_profiles', 'deleted_at IS NULL') ?></b>
        </div>
        <div class="stock-card rounded-[1.5rem] p-5">
            <p class="text-sm font-bold text-neutral-500">Bookings</p>
            <b class="mt-2 block text-3xl"><?= table_count('bookings', 'deleted_at IS NULL') ?></b>
        </div>
        <div class="stock-card rounded-[1.5rem] p-5">
            <p class="text-sm font-bold text-neutral-500">Avg Review</p>
            <b class="mt-2 block text-3xl"><?= number_format((float)$avgReview, 1) ?></b>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <?php foreach ([['Top Categories', $topCategories, 'name'], ['Top Districts', $topDistricts, 'district_name'], ['Top Rated', $topPhotographers, 'display_name']] as $box): ?>
            <div class="stock-card rounded-[1.5rem] p-6">
                <h2 class="font-black"><?= h($box[0]) ?></h2>
                <div class="mt-4 grid gap-2">
                    <?php foreach ($box[1] as $row): ?>
                        <div class="flex justify-between rounded-xl bg-neutral-50 px-4 py-3">
                            <span><?= h($row[$box[2]]) ?></span>
                            <b><?= h($row['total'] ?? $row['average_rating']) ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('customer');

$user = current_user();
$reviews = db_fetch_all('SELECT r.*, b.booking_code, b.booking_date, b.time_slot, p.display_name, p.slug AS photographer_slug, sc.name AS category_name, d.district_name
                         FROM reviews r
                         JOIN bookings b ON b.id = r.booking_id
                         JOIN photographer_profiles p ON p.id = r.photographer_id
                         JOIN service_categories sc ON sc.id = b.category_id
                         JOIN districts d ON d.id = b.district_id
                         WHERE r.customer_id = ?
                           AND r.deleted_at IS NULL
                         ORDER BY r.created_at DESC', [(int)$user['id']]);

$reviewCount = count($reviews);
$avgRating = 0;
if ($reviewCount > 0) {
    $ratingSum = 0;
    foreach ($reviews as $review) {
        $ratingSum += (int)$review['rating_overall'];
    }
    $avgRating = $ratingSum / $reviewCount;
}

$pageTitle = 'รีวิวของฉัน';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="dashboard-hero overflow-hidden rounded-[2rem] p-6 text-white md:p-8">
        <div class="grid gap-6 lg:grid-cols-[1fr_320px] lg:items-end">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/60">
                    <i class="fa-solid fa-star mr-2"></i>พื้นที่ลูกค้า
                </p>
                <h1 class="mt-2 text-3xl font-black md:text-4xl">รีวิวของฉัน</h1>
                <p class="mt-3 max-w-2xl text-base font-semibold leading-8 text-white/75">ดูประวัติรีวิวที่คุณเคยให้กับช่างภาพหลังงานเสร็จสิ้น</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-comment text-2xl text-red-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format($reviewCount) ?></div>
                    <div class="text-xs font-black text-white/55">จำนวนรีวิว</div>
                </div>
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-star text-2xl text-yellow-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format($avgRating, 1) ?></div>
                    <div class="text-xs font-black text-white/55">คะแนนเฉลี่ยที่ให้</div>
                </div>
            </div>
        </div>
    </div>

    <div class="stock-card mt-6 rounded-[1.75rem] p-5">
        <?php if ($reviews): ?>
            <div class="grid gap-4" data-block-paginate="5">
                <?php foreach ($reviews as $review): ?>
                    <article class="rounded-[1.5rem] border border-neutral-100 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-red-50 px-3 py-1 text-sm font-black text-red-700">
                                        <i class="fa-solid fa-star mr-1"></i>คะแนน <?= (int)$review['rating_overall'] ?>/5
                                    </span>
                                    <?= status_badge((string)$review['status']) ?>
                                </div>
                                <h2 class="mt-3 text-xl font-black text-neutral-950"><?= h($review['display_name']) ?></h2>
                                <p class="mt-1 text-sm font-bold text-neutral-500">
                                    <i class="fa-solid fa-calendar-check mr-1 text-red-600"></i><?= h($review['booking_code']) ?>
                                    <span class="mx-2 text-neutral-300">/</span>
                                    <?= h($review['category_name']) ?> · <?= h($review['district_name']) ?>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <?= clean_context_button('/customer/booking_detail.php', ['id' => (int)$review['booking_id']], '<i class="fa-solid fa-eye mr-1"></i>รายละเอียดงาน', 'btn-primary btn-sm') ?>
                                <?= clean_context_button('/photographer_detail.php', ['slug' => $review['photographer_slug']], '<i class="fa-solid fa-camera mr-1"></i>โปรไฟล์ช่างภาพ', 'btn-muted btn-sm') ?>
                            </div>
                        </div>

                        <p class="mt-4 rounded-2xl bg-neutral-50 p-4 text-base font-semibold leading-8 text-neutral-700"><?= nl2br(h($review['comment'])) ?></p>

                        <div class="mt-4 grid gap-3 text-sm font-bold text-neutral-600 sm:grid-cols-4">
                            <div class="rounded-2xl bg-neutral-50 p-3"><i class="fa-solid fa-image mr-1 text-red-600"></i>คุณภาพ <?= (int)$review['rating_quality'] ?>/5</div>
                            <div class="rounded-2xl bg-neutral-50 p-3"><i class="fa-solid fa-comments mr-1 text-red-600"></i>สื่อสาร <?= (int)$review['rating_communication'] ?>/5</div>
                            <div class="rounded-2xl bg-neutral-50 p-3"><i class="fa-solid fa-clock mr-1 text-red-600"></i>ตรงเวลา <?= (int)$review['rating_punctuality'] ?>/5</div>
                            <div class="rounded-2xl bg-neutral-50 p-3"><i class="fa-solid fa-briefcase mr-1 text-red-600"></i>มืออาชีพ <?= (int)$review['rating_professional'] ?>/5</div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state rounded-[2rem] p-10 text-center">
                <i class="fa-solid fa-star-half-stroke text-5xl text-red-600"></i>
                <h2 class="mt-4 text-2xl font-black text-neutral-950">ยังไม่มีประวัติรีวิว</h2>
                <p class="mt-2 text-neutral-600">เมื่องานเสร็จสิ้นแล้ว คุณสามารถรีวิวช่างภาพได้จากหน้าประวัติการจอง</p>
                <?= clean_context_button('/customer/bookings.php', ['tab' => 'completed'], '<i class="fa-solid fa-calendar-check mr-2"></i>ดูงานเสร็จสิ้น', 'btn-cta btn-md mt-5') ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

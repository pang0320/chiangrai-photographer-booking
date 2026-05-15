<?php
$cardId = 0;
if (isset($p['id'])) {
    $cardId = (int)$p['id'];
}

$cardName = 'ช่างภาพ';
if (!empty($p['display_name'])) {
    $cardName = (string)$p['display_name'];
}

$cardSlug = '';
if (!empty($p['slug'])) {
    $cardSlug = (string)$p['slug'];
}

$cardDetailParams = ['id' => $cardId];
if ($cardSlug !== '') {
    $cardDetailParams = ['slug' => $cardSlug];
}

$cardImagePath = null;
if (!empty($p['featured_image'])) {
    $cardImagePath = $p['featured_image'];
} elseif (!empty($p['cover_image'])) {
    $cardImagePath = $p['cover_image'];
}

$cardAreaText = '-';
if (!empty($p['areas'])) {
    $cardAreaText = $p['areas'];
} elseif (!empty($p['district_name'])) {
    $cardAreaText = $p['district_name'];
}

$cardServiceText = '';
if (!empty($p['services'])) {
    $cardServiceText = $p['services'];
} elseif (!empty($p['bio'])) {
    $cardServiceText = $p['bio'];
}

if ($cardServiceText === '') {
    $cardServiceText = 'ดูตัวอย่างงานถ่ายภาพ พื้นที่ให้บริการ วันว่าง และช่องทางติดต่อโดยตรงกับช่างภาพ';
}

$cardRating = 0.0;
if (isset($p['average_rating'])) {
    $cardRating = (float)$p['average_rating'];
}

$cardStartingPrice = 0.0;
if (isset($p['starting_price'])) {
    $cardStartingPrice = (float)$p['starting_price'];
}

$cardTotalReviews = 0;
if (isset($p['total_reviews'])) {
    $cardTotalReviews = (int)$p['total_reviews'];
}

$cardIsVerified = true;
if (isset($p['is_verified'])) {
    $cardIsVerified = (int)$p['is_verified'] === 1;
}

$cardIsFeatured = false;
if (isset($p['is_featured'])) {
    $cardIsFeatured = (int)$p['is_featured'] === 1;
}

$cardFormId = 'photographer-card-link-' . $cardId . '-' . substr(md5(uniqid('', true)), 0, 8);
?>
<article class="stock-card stock-card-hover rounded-[1.75rem]" data-clickable-card data-card-form="<?= h($cardFormId) ?>" tabindex="0" role="link" aria-label="ดูโปรไฟล์ <?= h($cardName) ?>">
    <form id="<?= h($cardFormId) ?>" method="post" action="/photographer_detail.php" class="hidden" aria-hidden="true">
        <?= clean_context_inputs($cardDetailParams) ?>
    </form>
    <?= clean_context_button('/photographer_detail.php', $cardDetailParams, '<img src="' . h(public_image($cardImagePath, '/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg')) . '" alt=""><div class="media-overlay p-5"><div>' . ($cardIsVerified ? '<span class="rounded-full bg-white/95 px-3 py-1 text-xs font-black text-neutral-950"><i class="fa-solid fa-circle-check mr-1 text-red-600"></i>ยืนยันแล้ว</span>' : '') . ($cardIsFeatured ? '<span class="ml-1 rounded-full bg-yellow-300 px-3 py-1 text-xs font-black text-neutral-950"><i class="fa-solid fa-award mr-1"></i>แนะนำ</span>' : '') . '<p class="mt-3 text-sm font-semibold text-white/90">ดูตัวอย่างงานและข้อมูลติดต่อ</p></div></div>', 'media-tile block h-64 w-full text-left', 'block') ?>
    <div class="p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-black tracking-tight text-neutral-950"><?= h($cardName) ?></h3>
                <p class="mt-1 text-sm font-medium text-neutral-500"><i class="fa-solid fa-location-dot mr-1 text-red-600"></i><?= h($cardAreaText) ?></p>
            </div>
            <span class="shrink-0 rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-600"><i class="fa-solid fa-star mr-1"></i>คะแนนเฉลี่ย <?= number_format($cardRating, 1) ?></span>
        </div>
        <p class="mt-3 line-clamp-2 text-sm leading-6 text-neutral-600"><?= h($cardServiceText) ?></p>
        <div class="mt-4 flex items-end justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-neutral-400">ราคาเริ่มต้นโดยประมาณ</p>
                <p class="text-xl font-black text-neutral-950"><?= number_format($cardStartingPrice) ?> บาท</p>
                <p class="text-sm font-semibold text-neutral-500">
                    จำนวนรีวิว <?= number_format($cardTotalReviews) ?> รายการ
                    <?php if (isset($p['distance_km'])): ?>
                        · ระยะประมาณ <?= number_format((float)$p['distance_km'], 1) ?> กม. จากอำเภอที่เลือก
                    <?php endif; ?>
                </p>
                <p class="mt-1 text-xs font-bold leading-5 text-neutral-500">ชำระเงินและตกลงราคากับช่างภาพโดยตรง</p>
            </div>
        </div>
        <div class="mt-5 grid auto-rows-fr gap-3 sm:grid-cols-2">
            <?= clean_context_button('/photographer_detail.php', $cardDetailParams, '<i class="fa-solid fa-eye mr-1"></i>ดูโปรไฟล์', 'btn-primary btn-sm min-h-[44px] w-full whitespace-nowrap text-center', 'contents') ?>
            <?= clean_context_button('/compare.php', ['ids' => $cardId], '<i class="fa-solid fa-code-compare mr-1"></i>เปรียบเทียบ', 'btn-muted btn-sm min-h-[44px] w-full whitespace-nowrap text-center', 'contents') ?>
            <?= clean_context_button('/customer/create_booking.php', ['photographer_id' => $cardId], '<i class="fa-solid fa-calendar-check mr-1"></i>จอง', 'btn-cta btn-sm min-h-[44px] w-full whitespace-nowrap text-center sm:col-span-2', 'contents') ?>
        </div>
    </div>
</article>

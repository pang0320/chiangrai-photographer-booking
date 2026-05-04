<article class="stock-card stock-card-hover rounded-[1.75rem]">
    <a href="/photographer_detail.php?id=<?= (int)$p['id'] ?>" class="media-tile block h-64">
        <img src="<?= h(public_image($p['featured_image'] ?? $p['cover_image'] ?? null, 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=900&q=80')) ?>" alt="">
        <div class="media-overlay p-5">
            <div>
                <span class="rounded-full bg-white/95 px-3 py-1 text-xs font-black text-neutral-950"><i class="fa-solid fa-shield-halved mr-1 text-red-600"></i>ยืนยันแล้ว</span>
                <p class="mt-3 text-sm font-semibold text-white/90">ดูผลงานและข้อมูลติดต่อ</p>
            </div>
        </div>
    </a>
    <div class="p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-black tracking-tight text-neutral-950"><?= h($p['display_name']) ?></h3>
                <p class="mt-1 text-sm font-medium text-neutral-500"><i class="fa-solid fa-location-dot mr-1 text-red-600"></i><?= h($p['areas'] ?? $p['district_name'] ?? '-') ?></p>
            </div>
            <span class="shrink-0 rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-600"><?= number_format((float)$p['average_rating'], 1) ?> <i class="fa-solid fa-star"></i></span>
        </div>
        <p class="mt-3 line-clamp-2 text-sm leading-6 text-neutral-600"><?= h($p['services'] ?? $p['bio'] ?? '') ?></p>
        <div class="mt-4 flex items-end justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-neutral-400">เริ่มต้น</p>
                <p class="text-xl font-black text-neutral-950"><?= number_format((float)$p['starting_price']) ?> บาท</p>
                <p class="text-sm font-semibold text-neutral-500">
                    <?= (int)$p['total_reviews'] ?> รีวิว
                    <?php if (isset($p['distance_km'])): ?>
                        · <?= number_format((float)$p['distance_km'], 1) ?> กม.
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="mt-5 grid grid-cols-2 gap-3">
            <a class="rounded-full border border-neutral-200 px-4 py-2.5 text-center text-sm font-black hover:border-neutral-950 hover:bg-neutral-950 hover:text-white" href="/photographer_detail.php?id=<?= (int)$p['id'] ?>">ดูโปรไฟล์</a>
            <a class="stock-button rounded-full px-4 py-2.5 text-center text-sm font-black" href="/customer/create_booking.php?photographer_id=<?= (int)$p['id'] ?>">ส่งคำขอจอง</a>
        </div>
    </div>
</article>

<?php
require_once __DIR__ . '/includes/functions.php';
$stats = [
    'photographers' => db_fetch_value('SELECT COUNT(*) FROM photographer_profiles WHERE approval_status = "approved" AND deleted_at IS NULL'),
    'reviews' => db_fetch_value('SELECT COUNT(*) FROM reviews WHERE status = "visible" AND deleted_at IS NULL'),
    'bookings' => db_fetch_value('SELECT COUNT(*) FROM bookings WHERE deleted_at IS NULL'),
    'districts' => db_fetch_value('SELECT COUNT(*) FROM districts WHERE is_active = 1'),
];
$pageTitle = 'เกี่ยวกับเรา';
include __DIR__ . '/includes/header.php';
?>
<section class="relative overflow-hidden bg-neutral-950 text-white">
    <div class="absolute inset-0">
        <img class="h-full w-full object-cover opacity-35" src="https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=1800&q=85" alt="">
        <div class="absolute inset-0 bg-gradient-to-r from-neutral-950 via-neutral-950/80 to-neutral-950/40"></div>
    </div>
    <div class="relative stock-shell px-4 py-20 sm:px-6 lg:px-8">
        <p class="section-kicker text-red-300">About Platform</p>
        <h1 class="mt-4 max-w-4xl text-4xl font-black sm:text-6xl">แพลตฟอร์มค้นหาและจองช่างภาพท้องถิ่นเชียงราย</h1>
        <p class="mt-5 max-w-2xl text-lg leading-8 text-white/70"><?= h(PAYMENT_DISCLAIMER) ?></p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="/photographers.php" class="rounded-full bg-white px-5 py-3 font-black text-neutral-950 hover:bg-red-600 hover:text-white"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหาช่างภาพ</a>
            <a href="/register.php?role=photographer" class="rounded-full bg-white/12 px-5 py-3 font-black text-white hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-user-plus mr-2"></i>สมัครเป็นช่างภาพ</a>
        </div>
    </div>
</section>

<section class="stock-shell px-4 py-14 sm:px-6 lg:px-8">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ([['fa-camera-retro','ช่างภาพ', $stats['photographers']], ['fa-star','รีวิว', $stats['reviews']], ['fa-calendar-check','คำขอจอง', $stats['bookings']], ['fa-map','อำเภอ', $stats['districts']]] as $card): ?>
            <div class="metric-card rounded-[1.5rem] p-6">
                <i class="fa-solid <?= h($card[0]) ?> text-3xl text-red-600"></i>
                <p class="mt-4 text-3xl font-black"><?= number_format((int)$card[2]) ?></p>
                <p class="font-bold text-neutral-500"><?= h($card[1]) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-10 grid gap-6 lg:grid-cols-3">
        <?php foreach ([['fa-bullseye','เป้าหมายของระบบ','ช่วยให้ลูกค้าค้นหาช่างภาพที่เหมาะกับงานในเชียงรายได้เร็วขึ้น และให้ช่างภาพท้องถิ่นมีพื้นที่แสดงผลงาน'],['fa-shield-halved','น่าเชื่อถือ','ช่างภาพต้องผ่านการอนุมัติจาก Admin ก่อนแสดงในระบบ พร้อมรีวิวจาก booking ที่เสร็จสิ้นจริง'],['fa-handshake','ติดต่อโดยตรง','ลูกค้าและช่างภาพพูดคุยราคา เงื่อนไข และการชำระเงินกันเองภายนอกเว็บไซต์']] as $item): ?>
            <article class="stock-card rounded-[1.75rem] p-7">
                <i class="fa-solid <?= h($item[0]) ?> text-3xl text-red-600"></i>
                <h2 class="mt-4 text-2xl font-black"><?= h($item[1]) ?></h2>
                <p class="mt-3 leading-7 text-neutral-600"><?= h($item[2]) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

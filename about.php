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
        <img class="h-full w-full object-cover opacity-35" src="/assets/uploads/seed/photo-1516035069371-29a1b244cc32.jpg" alt="">
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

    <div class="mt-10 grid gap-6 lg:grid-cols-[1fr_1.25fr] lg:items-stretch">
        <div class="dashboard-hero rounded-[2rem] p-8 text-white">
            <p class="section-kicker text-red-300">Developer</p>
            <h2 class="mt-4 text-3xl font-black">ผู้พัฒนาระบบ</h2>
            <p class="mt-4 text-lg font-bold leading-8 text-white/72">Hello, my name is Creepygame or Game.</p>
            <p class="mt-2 leading-8 text-white/68">Photographer from Chiang Rai, Thailand และเป็นผู้พัฒนาแพลตฟอร์มนี้เพื่อช่วยให้ลูกค้าและช่างภาพท้องถิ่นติดต่อกันได้ง่ายขึ้น</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="tel:0994344335" class="rounded-full bg-white px-5 py-3 font-black text-neutral-950 transition hover:bg-red-600 hover:text-white"><i class="fa-solid fa-phone mr-2"></i>099-4344335</a>
                <button type="button" data-developer-modal-open class="rounded-full bg-white/12 px-5 py-3 font-black text-white transition hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-code mr-2"></i>ข้อมูลผู้พัฒนา</button>
            </div>
        </div>

        <div class="stock-card rounded-[2rem] p-8">
            <div class="flex flex-wrap items-start gap-4">
                <div class="grid h-16 w-16 place-items-center rounded-2xl bg-red-600 text-2xl text-white shadow-lg shadow-red-600/20">
                    <i class="fa-solid fa-camera-retro"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="section-kicker">Creepygame / Game</p>
                    <h3 class="mt-2 text-2xl font-black text-neutral-950">Photographer from Chiang Rai / Thailand</h3>
                    <p class="mt-3 leading-8 text-neutral-600">สนใจติดต่องาน สามารถทัก IB หรือโทรเบอร์ 099-4344335 ได้เลยครับ</p>
                </div>
            </div>
            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl bg-neutral-50 p-4">
                    <p class="text-sm font-black text-neutral-500"><i class="fa-solid fa-user mr-2 text-red-600"></i>ชื่อ</p>
                    <p class="mt-1 font-black text-neutral-950">Creepygame / Game</p>
                </div>
                <div class="rounded-2xl bg-neutral-50 p-4">
                    <p class="text-sm font-black text-neutral-500"><i class="fa-solid fa-location-dot mr-2 text-red-600"></i>พื้นที่</p>
                    <p class="mt-1 font-black text-neutral-950">Chiang Rai, Thailand</p>
                </div>
                <div class="rounded-2xl bg-neutral-50 p-4">
                    <p class="text-sm font-black text-neutral-500"><i class="fa-solid fa-phone mr-2 text-red-600"></i>โทร</p>
                    <a href="tel:0994344335" class="mt-1 block font-black text-red-600">099-4344335</a>
                </div>
            </div>
            <button type="button" data-developer-modal-open class="mt-5 w-full rounded-2xl bg-neutral-950 px-5 py-3 font-black text-white transition hover:bg-red-600">
                <i class="fa-brands fa-github mr-2"></i>ดู GitHub ผู้พัฒนา
            </button>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('photographer');

$user = current_user();
$profile = photographer_profile_by_user((int)$user['id']);
if (!$profile) {
    exit('Profile not found');
}

$pid = (int)$profile['id'];
$portfolioCount = (int)db_fetch_value('SELECT COUNT(*) FROM photographer_portfolios WHERE photographer_id = ? AND deleted_at IS NULL', [$pid]);
$areaCount = (int)db_fetch_value('SELECT COUNT(*) FROM photographer_service_areas WHERE photographer_id = ? AND is_active = 1', [$pid]);
$serviceCount = (int)db_fetch_value('SELECT COUNT(*) FROM photographer_services WHERE photographer_id = ? AND is_active = 1', [$pid]);
$availabilityCount = (int)db_fetch_value('SELECT COUNT(*) FROM photographer_availability WHERE photographer_id = ? AND available_date >= CURDATE() AND status = "available"', [$pid]);
$completionPercent = photographer_completion_percent($pid);

$steps = [
    [
        'title' => 'เพิ่มรูปโปรไฟล์',
        'description' => 'รูปโปรไฟล์ช่วยให้ลูกค้าจำช่างภาพได้ง่ายขึ้น',
        'done' => trim((string)$profile['profile_image']) !== '',
        'url' => '/photographer/profile.php',
        'icon' => 'fa-id-card',
    ],
    [
        'title' => 'เพิ่มรูปปก',
        'description' => 'ใช้ภาพผลงานเด่นเป็น first impression ของโปรไฟล์',
        'done' => trim((string)$profile['cover_image']) !== '',
        'url' => '/photographer/profile.php',
        'icon' => 'fa-image',
    ],
    [
        'title' => 'เพิ่มช่องทางติดต่อ',
        'description' => 'เบอร์โทรหรือ LINE ช่วยให้ลูกค้าติดต่อหลังส่งคำขอจองได้ทันที',
        'done' => trim((string)$profile['phone_public']) !== '' || trim((string)$profile['line_id']) !== '',
        'url' => '/photographer/profile.php',
        'icon' => 'fa-phone',
    ],
    [
        'title' => 'เลือกพื้นที่ให้บริการ',
        'description' => 'เลือกอำเภอที่รับงาน และกำหนดอำเภอหลัก',
        'done' => $areaCount > 0,
        'url' => '/photographer/service_areas.php',
        'icon' => 'fa-location-dot',
    ],
    [
        'title' => 'เลือกประเภทงาน',
        'description' => 'เพิ่มหมวดงานพร้อมราคาเริ่มต้นให้ลูกค้าตัดสินใจง่าย',
        'done' => $serviceCount > 0,
        'url' => '/photographer/services.php',
        'icon' => 'fa-layer-group',
    ],
    [
        'title' => 'อัปโหลด Portfolio อย่างน้อย 5 รูป',
        'description' => 'ตอนนี้มี ' . $portfolioCount . ' รูป',
        'done' => $portfolioCount >= 5,
        'url' => '/photographer/portfolio.php',
        'icon' => 'fa-images',
    ],
    [
        'title' => 'เพิ่มวันว่าง',
        'description' => 'เปิดวันว่างเพื่อให้ลูกค้าส่งคำขอจองได้ถูกต้อง',
        'done' => $availabilityCount > 0,
        'url' => '/photographer/availability.php',
        'icon' => 'fa-calendar-plus',
    ],
];

$pageTitle = 'เริ่มต้นใช้งานช่างภาพ';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="dashboard-hero rounded-[2rem] p-6 text-white sm:p-8">
        <div class="grid gap-6 lg:grid-cols-[1fr_340px] lg:items-center">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/58">Onboarding</p>
                <h1 class="mt-2 text-3xl font-black sm:text-5xl">ตั้งค่าโปรไฟล์ให้พร้อมรับงาน</h1>
                <p class="mt-4 max-w-2xl leading-8 text-white/70">ทำครบตามขั้นตอนนี้ โปรไฟล์จะดูน่าเชื่อถือขึ้นและพร้อมแสดงผลงานแบบมืออาชีพหลัง Admin อนุมัติ</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="/photographer/dashboard.php" class="rounded-full bg-white px-5 py-3 font-black text-neutral-950 hover:bg-red-600 hover:text-white"><i class="fa-solid fa-gauge mr-2"></i>ไป Dashboard</a>
                    <a href="/photographer/profile.php" class="rounded-full bg-white/12 px-5 py-3 font-black text-white hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-user-pen mr-2"></i>แก้ไขโปรไฟล์</a>
                </div>
            </div>
            <div class="stat-pill rounded-[2rem] p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-black uppercase tracking-[0.18em] text-white/50">Completion</p>
                        <p class="mt-2 text-6xl font-black"><?= (int)$completionPercent ?>%</p>
                    </div>
                    <div class="grid h-20 w-20 place-items-center rounded-[1.5rem] bg-white text-3xl text-red-600"><i class="fa-solid fa-gauge-high"></i></div>
                </div>
                <div class="mt-5 h-3 overflow-hidden rounded-full bg-white/18"><div class="h-full rounded-full bg-red-500" style="width: <?= (int)$completionPercent ?>%"></div></div>
                <p class="mt-4 text-sm font-bold text-white/65">ไม่มีระบบชำระเงิน ลูกค้าและช่างภาพตกลงราคากันภายนอกระบบ</p>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($steps as $step): ?>
            <article class="stock-card stock-card-hover rounded-[1.75rem] p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="grid h-14 w-14 place-items-center rounded-2xl bg-neutral-950 text-xl text-white"><i class="fa-solid <?= h($step['icon']) ?>"></i></div>
                    <?php if ($step['done']): ?>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i>เสร็จแล้ว</span>
                    <?php else: ?>
                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-black text-amber-700"><i class="fa-solid fa-hourglass-half mr-1"></i>รอทำ</span>
                    <?php endif; ?>
                </div>
                <h2 class="mt-5 text-xl font-black text-neutral-950"><?= h($step['title']) ?></h2>
                <p class="mt-2 min-h-[48px] text-sm leading-6 text-neutral-600"><?= h($step['description']) ?></p>
                <a href="<?= h($step['url']) ?>" class="mt-5 inline-flex rounded-full bg-red-600 px-5 py-3 text-sm font-black text-white hover:bg-neutral-950">
                    <i class="fa-solid fa-arrow-right mr-2"></i>ไปตั้งค่า
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

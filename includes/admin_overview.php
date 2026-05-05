<?php
$adminPath = '/';
if (isset($_SERVER['REQUEST_URI'])) {
    $adminPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
}
if (!$adminPath) {
    $adminPath = '/';
}

$adminCurrentTitle = $pageTitle ?? 'ผู้ดูแลระบบ';
$adminStats = [
    [
        'label' => 'ผู้ใช้งานทั้งหมด',
        'value' => (int)db_fetch_value('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'),
        'icon' => 'fa-users',
        'tone' => 'text-sky-600',
        'hint' => 'บัญชีในระบบ',
    ],
    [
        'label' => 'ช่างภาพรออนุมัติ',
        'value' => (int)db_fetch_value('SELECT COUNT(*) FROM photographer_profiles WHERE approval_status = "pending" AND deleted_at IS NULL'),
        'icon' => 'fa-user-clock',
        'tone' => 'text-amber-600',
        'hint' => 'ต้องตรวจสอบ',
    ],
    [
        'label' => 'คำขอจองรอดำเนินการ',
        'value' => (int)db_fetch_value('SELECT COUNT(*) FROM bookings WHERE status = "pending" AND deleted_at IS NULL'),
        'icon' => 'fa-calendar-check',
        'tone' => 'text-red-600',
        'hint' => 'สถานะ pending',
    ],
    [
        'label' => 'รายงานปัญหา',
        'value' => (int)db_fetch_value('SELECT COUNT(*) FROM reports WHERE status = "pending"'),
        'icon' => 'fa-shield-halved',
        'tone' => 'text-rose-600',
        'hint' => 'รอตรวจสอบ',
    ],
];

$adminUnreadContacts = (int)db_fetch_value('SELECT COUNT(*) FROM contact_messages WHERE status = "unread"');
$adminHiddenReviews = (int)db_fetch_value('SELECT COUNT(*) FROM reviews WHERE status = "hidden" AND deleted_at IS NULL');
?>

<section class="px-4 pt-6 sm:px-6 lg:px-8">
    <div class="dashboard-hero rounded-[2rem] p-5 text-white sm:p-6">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.22em] text-white/55"><i class="fa-solid fa-gauge-high mr-2 text-red-300"></i>Admin Dashboard</p>
                <h1 class="mt-2 text-2xl font-black sm:text-4xl"><?= h($adminCurrentTitle) ?></h1>
                <p class="mt-2 max-w-2xl text-sm font-bold leading-7 text-white/65">ภาพรวมสั้น ๆ สำหรับเข้าใจสถานะระบบก่อนจัดการข้อมูลในหน้านี้</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="/admin/dashboard.php" class="rounded-full bg-white px-4 py-2.5 text-sm font-black text-neutral-950 transition hover:bg-red-600 hover:text-white"><i class="fa-solid fa-gauge mr-2"></i>แดชบอร์ดหลัก</a>
                <a href="/admin/photographers.php?status=pending" class="rounded-full bg-white/12 px-4 py-2.5 text-sm font-black text-white transition hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-camera-retro mr-2"></i>อนุมัติช่างภาพ</a>
                <a href="/admin/reports_moderation.php" class="rounded-full bg-white/12 px-4 py-2.5 text-sm font-black text-white transition hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-shield-halved mr-2"></i>ตรวจรายงาน</a>
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($adminStats as $stat): ?>
            <a href="/admin/dashboard.php" class="metric-card rounded-[1.5rem] p-5 transition hover:-translate-y-1 hover:shadow-2xl">
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm font-bold text-neutral-500"><?= h($stat['label']) ?></p>
                    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-white shadow-sm"><i class="fa-solid <?= h($stat['icon']) ?> <?= h($stat['tone']) ?>"></i></span>
                </div>
                <p class="mt-3 text-3xl font-black text-neutral-950"><?= number_format((int)$stat['value']) ?></p>
                <p class="mt-1 text-xs font-black text-neutral-400"><?= h($stat['hint']) ?></p>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="stock-card rounded-[1.5rem] p-5">
            <p class="section-kicker">Table View</p>
            <h2 class="mt-2 text-xl font-black text-neutral-950"><i class="fa-solid fa-table-list mr-2 text-red-600"></i>ตารางแสดงทีละ 10 รายการ</h2>
            <p class="mt-2 text-sm font-bold leading-7 text-neutral-500">ทุกตารางในฝั่ง Admin ใช้ DataTables และตั้งค่าเริ่มต้นให้แสดง 10 รายการต่อหน้า</p>
        </div>
        <div class="stock-card rounded-[1.5rem] p-5">
            <p class="section-kicker">Contact</p>
            <h2 class="mt-2 text-xl font-black text-neutral-950"><i class="fa-solid fa-envelope-open-text mr-2 text-red-600"></i>ข้อความใหม่ <?= number_format($adminUnreadContacts) ?></h2>
            <a href="/admin/contact_messages.php" class="mt-3 inline-flex rounded-full bg-red-50 px-4 py-2 text-sm font-black text-red-700 hover:bg-red-600 hover:text-white"><i class="fa-solid fa-eye mr-2"></i>เปิดกล่องข้อความ</a>
        </div>
        <div class="stock-card rounded-[1.5rem] p-5">
            <p class="section-kicker">Moderation</p>
            <h2 class="mt-2 text-xl font-black text-neutral-950"><i class="fa-solid fa-star-half-stroke mr-2 text-red-600"></i>รีวิวที่ซ่อน <?= number_format($adminHiddenReviews) ?></h2>
            <a href="/admin/reviews.php?status=hidden" class="mt-3 inline-flex rounded-full bg-neutral-950 px-4 py-2 text-sm font-black text-white hover:bg-red-600"><i class="fa-solid fa-eye-slash mr-2"></i>ดูรีวิวที่ซ่อน</a>
        </div>
    </div>
</section>

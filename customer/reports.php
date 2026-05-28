<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('customer');

$user = current_user();
$reports = db_fetch_all('SELECT *
                         FROM reports
                         WHERE reporter_id = ?
                         ORDER BY created_at DESC', [(int)$user['id']]);

$statusCounts = [
    'pending' => 0,
    'reviewed' => 0,
    'resolved' => 0,
    'rejected' => 0,
];

foreach ($reports as $report) {
    $status = (string)$report['status'];
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
}

/**
 * สร้างข้อความอธิบายเป้าหมายที่ถูกรายงานเพื่อให้ลูกค้าอ่านเข้าใจง่าย
 *
 * @param array $report ข้อมูลรายงานปัญหา
 * @return string ข้อความอธิบายเป้าหมาย (เช่น "โปรไฟล์ช่างภาพ: ชื่อช่างภาพ")
 */
function customer_report_target_label(array $report): string
{
    $targetType = (string)$report['target_type'];
    $targetId = (int)$report['target_id'];

    if ($targetType === 'photographer') {
        $name = db_fetch_value('SELECT display_name FROM photographer_profiles WHERE id = ? LIMIT 1', [$targetId]);
        if ($name) {
            return 'โปรไฟล์ช่างภาพ: ' . (string)$name;
        }
        return 'โปรไฟล์ช่างภาพ #' . $targetId;
    }

    if ($targetType === 'review') {
        $name = db_fetch_value('SELECT p.display_name
                                FROM reviews r
                                JOIN photographer_profiles p ON p.id = r.photographer_id
                                WHERE r.id = ?
                                LIMIT 1', [$targetId]);
        if ($name) {
            return 'รีวิวของช่างภาพ: ' . (string)$name;
        }
        return 'รีวิว #' . $targetId;
    }

    if ($targetType === 'booking') {
        $code = db_fetch_value('SELECT booking_code FROM bookings WHERE id = ? LIMIT 1', [$targetId]);
        if ($code) {
            return 'รายการจอง: ' . (string)$code;
        }
        return 'รายการจอง #' . $targetId;
    }

    if ($targetType === 'article') {
        $title = db_fetch_value('SELECT title FROM photographer_articles WHERE id = ? LIMIT 1', [$targetId]);
        if ($title) {
            return 'บทความ: ' . (string)$title;
        }
        return 'บทความ #' . $targetId;
    }

    return $targetType . ' #' . $targetId;
}

/**
 * แปลงประเภทรายงานเป็นข้อความภาษาไทยสั้นๆ สำหรับแสดงในป้ายสถานะ (Badge)
 *
 * @param string $type ประเภทรายงาน
 * @return string ข้อความภาษาไทยที่อธิบายประเภท
 */
function customer_report_type_label(string $type): string
{
    $labels = [
        'photographer' => 'ช่างภาพ',
        'review' => 'รีวิว',
        'booking' => 'การจอง',
        'article' => 'บทความ',
    ];

    if (isset($labels[$type])) {
        return $labels[$type];
    }

    return $type;
}

$pageTitle = 'รายงานปัญหาของฉัน';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="dashboard-hero overflow-hidden rounded-[2rem] p-6 text-white md:p-8">
        <div class="grid gap-6 lg:grid-cols-[1fr_420px] lg:items-end">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.22em] text-white/60">
                    <i class="fa-solid fa-shield-halved mr-2"></i>พื้นที่ลูกค้า
                </p>
                <h1 class="mt-2 text-3xl font-black md:text-4xl">รายงานปัญหาของฉัน</h1>
                <p class="mt-3 max-w-2xl text-base font-semibold leading-8 text-white/75">ดูประวัติการรายงานโปรไฟล์ รีวิว การจอง หรือบทความที่คุณส่งให้ผู้ดูแลตรวจสอบ</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-hourglass-half text-2xl text-amber-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format($statusCounts['pending']) ?></div>
                    <div class="text-xs font-black text-white/55">รอตรวจสอบ</div>
                </div>
                <div class="rounded-3xl bg-white/12 p-4 text-center backdrop-blur">
                    <i class="fa-solid fa-circle-check text-2xl text-emerald-200"></i>
                    <div class="mt-2 text-2xl font-black"><?= number_format($statusCounts['resolved']) ?></div>
                    <div class="text-xs font-black text-white/55">แก้ไขแล้ว</div>
                </div>
            </div>
        </div>
    </div>

    <div class="stock-card mt-6 rounded-[1.75rem] p-5">
        <?php if ($reports): ?>
            <div class="grid gap-4" data-block-paginate="5">
                <?php foreach ($reports as $report): ?>
                    <article class="rounded-[1.5rem] border border-neutral-100 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-red-50 px-3 py-1 text-sm font-black text-red-700">
                                        <i class="fa-solid fa-flag mr-1"></i><?= h(customer_report_type_label((string)$report['target_type'])) ?>
                                    </span>
                                    <?= status_badge((string)$report['status']) ?>
                                </div>
                                <h2 class="mt-3 text-xl font-black text-neutral-950"><?= h(customer_report_target_label($report)) ?></h2>
                                <p class="mt-1 text-sm font-bold text-neutral-500">
                                    <i class="fa-solid fa-calendar-day mr-1 text-red-600"></i><?= h(format_be_datetime($report['created_at'])) ?>
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <div class="rounded-2xl bg-neutral-50 p-4">
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">เหตุผลที่รายงาน</p>
                                <p class="mt-2 font-black text-neutral-950"><?= h($report['reason']) ?></p>
                            </div>
                            <div class="rounded-2xl bg-neutral-50 p-4">
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-neutral-400">รายละเอียดเพิ่มเติม</p>
                                <p class="mt-2 font-semibold leading-7 text-neutral-700"><?= nl2br(h($report['detail'])) ?></p>
                            </div>
                        </div>

                        <?php if (!empty($report['admin_note'])): ?>
                            <div class="mt-4 rounded-2xl bg-emerald-50 p-4 font-bold leading-7 text-emerald-700">
                                <i class="fa-solid fa-user-shield mr-2"></i>หมายเหตุจากผู้ดูแล: <?= nl2br(h($report['admin_note'])) ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state rounded-[2rem] p-10 text-center">
                <i class="fa-solid fa-shield-halved text-5xl text-red-600"></i>
                <h2 class="mt-4 text-2xl font-black text-neutral-950">ยังไม่มีประวัติรายงานปัญหา</h2>
                <p class="mt-2 text-neutral-600">เมื่อคุณรายงานโปรไฟล์หรือรีวิว ระบบจะแสดงสถานะการตรวจสอบที่นี่</p>
                <a href="/photographers.php" class="btn-cta btn-md mt-5"><i class="fa-solid fa-magnifying-glass mr-2"></i>ค้นหาช่างภาพ</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

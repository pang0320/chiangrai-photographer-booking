<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');
$cleanContext = clean_context_init(['status']);

function admin_report_type_label(string $type): string
{
    $labels = [
        'photographer' => 'โปรไฟล์ช่างภาพ',
        'review' => 'รีวิว',
        'booking' => 'คำขอจอง',
        'article' => 'บทความ',
    ];

    if (isset($labels[$type])) {
        return $labels[$type];
    }

    return $type;
}

function admin_report_source_label(array $report): string
{
    $targetType = (string)$report['target_type'];
    $reporterRole = (string)($report['reporter_role'] ?? '');

    if ($targetType === 'photographer') {
        return 'หน้าโปรไฟล์ช่างภาพ > ฟอร์มรายงานโปรไฟล์';
    }

    if ($targetType === 'review') {
        if ($reporterRole === 'photographer') {
            return 'แดชบอร์ดช่างภาพ > หน้ารีวิว > ฟอร์มรายงานรีวิว';
        }
        return 'หน้าโปรไฟล์ช่างภาพ > ส่วนรีวิวจากลูกค้า';
    }

    if ($targetType === 'booking') {
        if ($reporterRole === 'photographer') {
            return 'แดชบอร์ดช่างภาพ > รายละเอียดคำขอจอง';
        }
        return 'แดชบอร์ดลูกค้า > รายละเอียดคำขอจอง';
    }

    if ($targetType === 'article') {
        return 'หน้าอ่านบทความ > ฟอร์มรายงานบทความ';
    }

    return 'ไม่ทราบจุดที่รายงาน';
}

function admin_report_content_status_label(string $status): string
{
    $labels = [
        'published' => 'เผยแพร่',
        'draft' => 'ฉบับร่าง',
        'hidden' => 'ซ่อน',
        'visible' => 'แสดง',
    ];

    if (isset($labels[$status])) {
        return $labels[$status];
    }

    return $status;
}

function admin_report_role_text(?string $roleLabel, ?string $roleName): string
{
    $label = trim((string)$roleLabel);
    if ($label !== '') {
        return $label;
    }

    $role = trim((string)$roleName);
    if ($role === 'customer') {
        return 'ลูกค้า';
    }
    if ($role === 'photographer') {
        return 'ช่างภาพ';
    }
    if ($role === 'admin') {
        return 'ผู้ดูแลระบบ';
    }

    return $role !== '' ? $role : 'ไม่ทราบบทบาท';
}

function admin_report_add_recipient(array &$recipients, array $row, string $source): void
{
    $userId = (int)($row['user_id'] ?? 0);
    if ($userId <= 0) {
        return;
    }

    foreach ($recipients as $recipient) {
        if ((int)$recipient['user_id'] === $userId) {
            return;
        }
    }

    $recipients[] = [
        'user_id' => $userId,
        'name' => (string)($row['name'] ?? '-'),
        'email' => (string)($row['email'] ?? '-'),
        'role_name' => (string)($row['role_name'] ?? ''),
        'role_label' => admin_report_role_text($row['role_label'] ?? '', $row['role_name'] ?? ''),
        'source' => $source,
    ];
}

function admin_report_target_recipients(array $report): array
{
    $targetType = (string)$report['target_type'];
    $targetId = (int)$report['target_id'];
    $reporterId = (int)($report['reporter_id'] ?? 0);
    $recipients = [];

    if ($targetType === 'photographer') {
        $rows = db_fetch_all('SELECT u.id AS user_id, u.name, u.email, r.name AS role_name, r.display_name AS role_label
                              FROM photographer_profiles p
                              JOIN users u ON u.id = p.user_id
                              JOIN roles r ON r.id = u.role_id
                              WHERE p.id = ?
                              LIMIT 1', [$targetId]);
        if ($rows) {
            admin_report_add_recipient($recipients, $rows[0], 'เจ้าของโปรไฟล์ช่างภาพที่ถูกรายงาน');
        }
    }

    if ($targetType === 'review') {
        $rows = db_fetch_all('SELECT u.id AS user_id, u.name, u.email, ro.name AS role_name, ro.display_name AS role_label
                              FROM reviews rv
                              JOIN users u ON u.id = rv.customer_id
                              JOIN roles ro ON ro.id = u.role_id
                              WHERE rv.id = ?
                              LIMIT 1', [$targetId]);
        if ($rows) {
            admin_report_add_recipient($recipients, $rows[0], 'เจ้าของรีวิวที่ถูกรายงาน');
        }
    }

    if ($targetType === 'booking') {
        $bookingParties = db_fetch_all('SELECT cu.id AS customer_user_id,
                                               cu.name AS customer_name,
                                               cu.email AS customer_email,
                                               cr.name AS customer_role_name,
                                               cr.display_name AS customer_role_label,
                                               pu.id AS photographer_user_id,
                                               pu.name AS photographer_owner_name,
                                               pu.email AS photographer_owner_email,
                                               pr.name AS photographer_role_name,
                                               pr.display_name AS photographer_role_label
                                        FROM bookings b
                                        JOIN users cu ON cu.id = b.customer_id
                                        JOIN roles cr ON cr.id = cu.role_id
                                        JOIN photographer_profiles p ON p.id = b.photographer_id
                                        JOIN users pu ON pu.id = p.user_id
                                        JOIN roles pr ON pr.id = pu.role_id
                                        WHERE b.id = ?
                                        LIMIT 1', [$targetId]);
        if ($bookingParties) {
            $party = $bookingParties[0];
            if ($reporterId > 0 && $reporterId === (int)$party['customer_user_id']) {
                admin_report_add_recipient($recipients, [
                    'user_id' => $party['photographer_user_id'],
                    'name' => $party['photographer_owner_name'],
                    'email' => $party['photographer_owner_email'],
                    'role_name' => $party['photographer_role_name'],
                    'role_label' => $party['photographer_role_label'],
                ], 'ช่างภาพในคำขอจองที่ถูกรายงานโดยคู่กรณี');
            } elseif ($reporterId > 0 && $reporterId === (int)$party['photographer_user_id']) {
                admin_report_add_recipient($recipients, [
                    'user_id' => $party['customer_user_id'],
                    'name' => $party['customer_name'],
                    'email' => $party['customer_email'],
                    'role_name' => $party['customer_role_name'],
                    'role_label' => $party['customer_role_label'],
                ], 'ลูกค้าในคำขอจองที่ถูกรายงานโดยคู่กรณี');
            } else {
                admin_report_add_recipient($recipients, [
                    'user_id' => $party['customer_user_id'],
                    'name' => $party['customer_name'],
                    'email' => $party['customer_email'],
                    'role_name' => $party['customer_role_name'],
                    'role_label' => $party['customer_role_label'],
                ], 'ลูกค้าในคำขอจองที่เกี่ยวข้อง');
                admin_report_add_recipient($recipients, [
                    'user_id' => $party['photographer_user_id'],
                    'name' => $party['photographer_owner_name'],
                    'email' => $party['photographer_owner_email'],
                    'role_name' => $party['photographer_role_name'],
                    'role_label' => $party['photographer_role_label'],
                ], 'ช่างภาพในคำขอจองที่เกี่ยวข้อง');
            }
        }
    }

    if ($targetType === 'article') {
        $photographerArticle = db_fetch_all('SELECT u.id AS user_id, u.name, u.email, ro.name AS role_name, ro.display_name AS role_label
                                             FROM photographer_articles a
                                             JOIN photographer_profiles p ON p.id = a.photographer_id
                                             JOIN users u ON u.id = p.user_id
                                             JOIN roles ro ON ro.id = u.role_id
                                             WHERE a.id = ?
                                             LIMIT 1', [$targetId]);
        if ($photographerArticle) {
            admin_report_add_recipient($recipients, $photographerArticle[0], 'ผู้เขียนบทความช่างภาพที่ถูกรายงาน');
        }

        $systemBlog = db_fetch_all('SELECT u.id AS user_id, u.name, u.email, ro.name AS role_name, ro.display_name AS role_label
                                    FROM blogs b
                                    JOIN users u ON u.id = b.admin_id
                                    JOIN roles ro ON ro.id = u.role_id
                                    WHERE b.id = ?
                                    LIMIT 1', [$targetId]);
        if ($systemBlog) {
            admin_report_add_recipient($recipients, $systemBlog[0], 'ผู้เขียนบทความระบบที่ถูกรายงาน');
        }
    }

    return $recipients;
}

function admin_report_target_info(array $report): array
{
    $targetType = (string)$report['target_type'];
    $targetId = (int)$report['target_id'];
    $typeLabel = admin_report_type_label($targetType);
    $fallback = [
        'title' => $typeLabel . ' #' . $targetId,
        'meta' => 'ไม่พบข้อมูลเป้าหมายในฐานข้อมูล หรือข้อมูลอาจถูกลบแล้ว',
        'icon' => 'fa-bullseye',
        'tone' => 'bg-neutral-100 text-neutral-700',
    ];

    if ($targetType === 'photographer') {
        $target = db_fetch_all('SELECT p.display_name, p.slug, u.name AS owner_name, u.email AS owner_email, d.district_name
                                FROM photographer_profiles p
                                JOIN users u ON u.id = p.user_id
                                LEFT JOIN districts d ON d.id = p.main_district_id
                                WHERE p.id = ?
                                LIMIT 1', [$targetId]);
        if ($target) {
            return [
                'title' => 'ช่างภาพ: ' . (string)$target[0]['display_name'],
                'meta' => 'เจ้าของบัญชี: ' . (string)$target[0]['owner_name'] . ' · ' . (string)$target[0]['owner_email'] . ' · อำเภอหลัก: ' . (string)($target[0]['district_name'] ?: '-'),
                'icon' => 'fa-camera-retro',
                'tone' => 'bg-red-50 text-red-700',
            ];
        }
    }

    if ($targetType === 'review') {
        $target = db_fetch_all('SELECT r.rating_overall, r.comment, r.status, r.created_at,
                                       cu.name AS customer_name,
                                       p.display_name AS photographer_name,
                                       b.booking_code
                                FROM reviews r
                                JOIN users cu ON cu.id = r.customer_id
                                JOIN photographer_profiles p ON p.id = r.photographer_id
                                LEFT JOIN bookings b ON b.id = r.booking_id
                                WHERE r.id = ?
                                LIMIT 1', [$targetId]);
        if ($target) {
            $comment = trim(strip_tags((string)$target[0]['comment']));
            if ($comment === '') {
                $comment = 'ไม่มีข้อความรีวิว';
            }
            if (text_length($comment) > 90) {
                if (function_exists('mb_substr')) {
                    $comment = mb_substr($comment, 0, 90, 'UTF-8') . '...';
                } else {
                    $comment = substr($comment, 0, 90) . '...';
                }
            }
            return [
                'title' => 'รีวิวของช่างภาพ: ' . (string)$target[0]['photographer_name'],
                'meta' => 'รีวิวโดย: ' . (string)$target[0]['customer_name'] . ' · คะแนน ' . (int)$target[0]['rating_overall'] . '/5 · Booking: ' . (string)($target[0]['booking_code'] ?: '-') . ' · "' . $comment . '"',
                'icon' => 'fa-star-half-stroke',
                'tone' => 'bg-amber-50 text-amber-700',
            ];
        }
    }

    if ($targetType === 'booking') {
        $target = db_fetch_all('SELECT b.booking_code, b.status, b.booking_date,
                                       cu.name AS customer_name,
                                       p.display_name AS photographer_name,
                                       sc.name AS category_name,
                                       d.district_name
                                FROM bookings b
                                JOIN users cu ON cu.id = b.customer_id
                                JOIN photographer_profiles p ON p.id = b.photographer_id
                                LEFT JOIN service_categories sc ON sc.id = b.category_id
                                LEFT JOIN districts d ON d.id = b.district_id
                                WHERE b.id = ?
                                LIMIT 1', [$targetId]);
        if ($target) {
            return [
                'title' => 'คำขอจอง: ' . (string)$target[0]['booking_code'],
                'meta' => 'ลูกค้า: ' . (string)$target[0]['customer_name'] . ' · ช่างภาพ: ' . (string)$target[0]['photographer_name'] . ' · ประเภทงาน: ' . (string)($target[0]['category_name'] ?: '-') . ' · อำเภอ: ' . (string)($target[0]['district_name'] ?: '-') . ' · สถานะ: ' . booking_status_label((string)$target[0]['status']),
                'icon' => 'fa-calendar-check',
                'tone' => 'bg-sky-50 text-sky-700',
            ];
        }
    }

    if ($targetType === 'article') {
        $articleTargets = [];
        $photographerArticle = db_fetch_all('SELECT a.title, a.status, p.display_name
                                             FROM photographer_articles a
                                             JOIN photographer_profiles p ON p.id = a.photographer_id
                                             WHERE a.id = ?
                                             LIMIT 1', [$targetId]);
        if ($photographerArticle) {
            $articleTargets[] = 'บทความช่างภาพ: ' . (string)$photographerArticle[0]['title'] . ' · ผู้เขียน: ' . (string)$photographerArticle[0]['display_name'] . ' · สถานะ: ' . admin_report_content_status_label((string)$photographerArticle[0]['status']);
        }

        $systemBlog = db_fetch_all('SELECT b.title, b.status, u.name AS admin_name
                                    FROM blogs b
                                    JOIN users u ON u.id = b.admin_id
                                    WHERE b.id = ?
                                    LIMIT 1', [$targetId]);
        if ($systemBlog) {
            $articleTargets[] = 'บทความระบบ: ' . (string)$systemBlog[0]['title'] . ' · ผู้เขียน: ' . (string)$systemBlog[0]['admin_name'] . ' · สถานะ: ' . admin_report_content_status_label((string)$systemBlog[0]['status']);
        }

        if ($articleTargets) {
            return [
                'title' => count($articleTargets) > 1 ? 'บทความ #' . $targetId . ' (พบมากกว่า 1 แหล่ง)' : $articleTargets[0],
                'meta' => count($articleTargets) > 1 ? implode(' / ', $articleTargets) : 'รหัสอ้างอิงบทความ #' . $targetId,
                'icon' => 'fa-newspaper',
                'tone' => 'bg-violet-50 text-violet-700',
            ];
        }
    }

    return $fallback;
}

if (is_post()) {
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? 'moderate');
    $status = (string)($_POST['status'] ?? 'reviewed');
    $adminNote = trim((string)($_POST['admin_note'] ?? ''));

    if ($action === 'notify_target') {
        $reportRows = db_fetch_all('SELECT * FROM reports WHERE id = ? LIMIT 1', [$id]);
        if (!$reportRows) {
            flash('error', 'ไม่พบรายงานปัญหาที่ต้องการส่งแจ้งเตือน');
            clean_redirect('/admin/reports_moderation.php', []);
        }

        $report = $reportRows[0];
        $targetInfo = admin_report_target_info($report);
        $recipients = admin_report_target_recipients($report);
        if (!$recipients) {
            flash('error', 'ไม่พบผู้ถูกรายงานที่สามารถส่งแจ้งเตือนได้');
            clean_redirect('/admin/reports_moderation.php', []);
        }

        $notifyNote = trim((string)($_POST['notify_note'] ?? ''));
        $message = 'มีรายงานปัญหาเกี่ยวกับ ' . $targetInfo['title']
            . ' เหตุผล: ' . (string)$report['reason']
            . ' รายละเอียด: ' . trim((string)$report['detail']);
        if ($notifyNote !== '') {
            $message .= ' ข้อความจากผู้ดูแล: ' . $notifyNote;
        }
        $message .= ' ระบบไม่เปิดเผยตัวตนของผู้รายงาน';

        foreach ($recipients as $recipient) {
            notify_user((int)$recipient['user_id'], 'แจ้งเตือนรายงานปัญหา', $message, 'report_notice', $id);
        }

        log_activity('notify_report_target', 'reports', $id, 'sent_to=' . implode(',', array_map(static function ($recipient) {
            return (string)$recipient['user_id'];
        }, $recipients)));
        flash('success', 'ส่งแจ้งเตือนไปยังผู้ถูกรายงานแล้ว โดยไม่เปิดเผยผู้รายงาน');
        clean_redirect('/admin/reports_moderation.php', []);
    }

    $allowed = ['pending', 'reviewed', 'resolved', 'rejected'];
    if (!in_array($status, $allowed, true)) {
        $status = 'reviewed';
    }

    $report = db_fetch_all('SELECT reporter_id, target_type, target_id FROM reports WHERE id = ? LIMIT 1', [$id]);
    $stmt = db()->prepare('UPDATE reports SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $adminNote, $id]);
    if ($report && !empty($report[0]['reporter_id'])) {
        notify_user((int)$report[0]['reporter_id'], 'ผลตรวจรายงานปัญหา', 'รายงาน #' . $id . ' เป็น ' . report_status_label($status), 'report', $id);
    }
    log_activity('moderate_report', 'reports', $id, $adminNote);
    flash('success', 'อัปเดตรายงานปัญหาแล้ว');
    clean_redirect('/admin/reports_moderation.php', []);
}

$statusFilter = (string)clean_context_value($cleanContext, 'status', '');
$whereSql = '1=1';
$params = [];
if (in_array($statusFilter, ['pending', 'reviewed', 'resolved', 'rejected'], true)) {
    $whereSql = 'r.status = ?';
    $params[] = $statusFilter;
}

$items = db_fetch_all('SELECT r.*, u.name AS reporter_name, u.email AS reporter_email, roles.name AS reporter_role, roles.display_name AS reporter_role_label
                       FROM reports r
                       LEFT JOIN users u ON u.id = r.reporter_id
                       LEFT JOIN roles ON roles.id = u.role_id
                       WHERE ' . $whereSql . '
                       ORDER BY r.created_at DESC', $params);

$pageTitle = 'ตรวจสอบรายงานปัญหา';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="section-kicker">ตรวจสอบรายงาน</p>
            <h1 class="mt-1 text-3xl font-black text-neutral-950"><i class="fa-solid fa-shield-halved mr-2 text-red-600"></i>รายงานปัญหา</h1>
            <p class="mt-2 max-w-3xl text-sm font-bold leading-7 text-neutral-500">รายงานมาจากปุ่มรายงานในหน้าโปรไฟล์ช่างภาพและจุดที่แสดงรีวิว ผู้ใช้กรอกเหตุผลและรายละเอียดเอง แล้วผู้ดูแลตรวจสอบต่อที่หน้านี้</p>
        </div>
        <div class="rounded-full bg-red-50 px-5 py-3 text-sm font-black text-red-700"><i class="fa-solid fa-hourglass-half mr-2"></i><?= table_count('reports', 'status = "pending"') ?> รอตรวจสอบ</div>
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
        <?php foreach (['' => 'ทั้งหมด', 'pending' => 'รอตรวจสอบ', 'reviewed' => 'ตรวจแล้ว', 'resolved' => 'แก้ไขแล้ว', 'rejected' => 'ปฏิเสธ'] as $filterValue => $filterLabel): ?>
            <?= clean_context_button('/admin/reports_moderation.php', ['status' => $filterValue], h($filterLabel), 'rounded-full px-4 py-2 text-sm font-black ' . ($statusFilter === $filterValue ? 'bg-neutral-950 text-white' : 'bg-white text-neutral-700')) ?>
        <?php endforeach; ?>
    </div>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.75rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>ผู้รายงาน</th>
                    <th>เป้าหมาย</th>
                    <th>เหตุผล</th>
                    <th>รายละเอียด</th>
                    <th>สถานะ</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                    <?php
                    $targetInfo = admin_report_target_info($item);
                    $targetRecipients = admin_report_target_recipients($item);
                    $sourceLabel = admin_report_source_label($item);
                    $reporterRoleLabel = 'ผู้เยี่ยมชม';
                    if (!empty($item['reporter_id'])) {
                        $reporterRoleLabel = admin_report_role_text($item['reporter_role_label'] ?? '', $item['reporter_role'] ?? '');
                    }
                    ?>
                    <tr>
                        <td class="font-black text-neutral-500"><?= $index + 1 ?></td>
                        <td>
                            <b><?php if ($item['reporter_name']): ?><?= h($item['reporter_name']) ?><?php else: ?>ผู้เยี่ยมชม<?php endif; ?></b>
                            <p class="text-xs text-neutral-500"><?= h($item['reporter_email']) ?></p>
                            <p class="mt-1 inline-flex rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-black text-neutral-600">
                                <i class="fa-solid fa-user-tag mr-1 text-red-600"></i>บทบาทผู้รายงาน: <?= h($reporterRoleLabel) ?>
                            </p>
                        </td>
                        <td class="min-w-[360px]">
                            <div class="rounded-2xl border border-neutral-100 bg-white p-3 shadow-sm">
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-black <?= h($targetInfo['tone']) ?>">
                                    <i class="fa-solid <?= h($targetInfo['icon']) ?> mr-1"></i><?= h(admin_report_type_label((string)$item['target_type'])) ?> #<?= (int)$item['target_id'] ?>
                                </span>
                                <h3 class="mt-2 text-sm font-black text-neutral-950"><?= h($targetInfo['title']) ?></h3>
                                <p class="mt-1 text-xs font-bold leading-5 text-neutral-500"><?= h($targetInfo['meta']) ?></p>
                                <p class="mt-2 rounded-xl bg-red-50 px-3 py-2 text-xs font-black leading-5 text-red-700">
                                    <i class="fa-solid fa-location-crosshairs mr-1"></i>รีพอร์ตจาก: <?= h($sourceLabel) ?>
                                </p>
                                <div class="mt-2 grid gap-2">
                                    <?php if ($targetRecipients): ?>
                                        <?php foreach ($targetRecipients as $recipient): ?>
                                            <div class="rounded-xl bg-sky-50 px-3 py-2 text-xs font-bold leading-5 text-sky-800">
                                                <p class="font-black">
                                                    <i class="fa-solid fa-bullseye mr-1"></i>ผู้ถูกรายงาน: <?= h($recipient['name']) ?>
                                                </p>
                                                <p>
                                                    บทบาท: <?= h($recipient['role_label']) ?> · อีเมล: <?= h($recipient['email']) ?>
                                                </p>
                                                <p class="text-sky-700/80">ที่มา: <?= h($recipient['source']) ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="rounded-xl bg-amber-50 px-3 py-2 text-xs font-black leading-5 text-amber-800">
                                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>ยังระบุผู้ถูกรายงานไม่ได้ อาจเป็นข้อมูลที่ถูกลบไปแล้ว
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="font-black"><?= h($item['reason']) ?></td>
                        <td class="max-w-md"><?= nl2br(h($item['detail'])) ?></td>
                        <td><?= status_badge($item['status'] === 'pending' ? 'pending' : ($item['status'] === 'resolved' ? 'completed' : ($item['status'] === 'rejected' ? 'rejected' : 'visible'))) ?></td>
                        <td>
                            <form method="post" class="grid min-w-[260px] gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="moderate">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <input name="admin_note" value="<?= h($item['admin_note']) ?>" placeholder="บันทึกโดยผู้ดูแล" class="stock-input rounded-xl px-3 py-2 text-sm">
                                <div class="flex flex-wrap gap-2">
                                    <button name="status" value="reviewed" class="btn-warning btn-sm"><i class="fa-solid fa-eye"></i>ตรวจแล้ว</button>
                                    <button name="status" value="resolved" class="btn-success btn-sm"><i class="fa-solid fa-check"></i>แก้ไขแล้ว</button>
                                    <button name="status" value="rejected" class="btn-danger btn-sm"><i class="fa-solid fa-xmark"></i>ปฏิเสธ</button>
                                </div>
                            </form>
                            <form method="post" class="mt-3 grid min-w-[260px] gap-2 rounded-2xl bg-neutral-50 p-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="notify_target">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <textarea name="notify_note" rows="2" placeholder="ข้อความถึงผู้ถูกรายงาน (ไม่บอกชื่อผู้รายงาน)" class="stock-input rounded-xl px-3 py-2 text-sm"></textarea>
                                <button
                                    class="btn-primary btn-sm"
                                    data-confirm="ส่งแจ้งเตือนไปยังผู้ถูกรายงาน?"
                                    data-confirm-text="ระบบจะส่งเหตุผลและรายละเอียดรายงาน แต่จะไม่เปิดเผยชื่อผู้รายงาน"
                                    data-confirm-button="ส่งแจ้งเตือน">
                                    <i class="fa-solid fa-bell"></i>แจ้งผู้ถูกรายงาน
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('customer');
$user = current_user();
$cleanContext = clean_context_init(['booking_id']);
$bookingId = (int)clean_context_value($cleanContext, 'booking_id', ($_POST['booking_id'] ?? 0));
$stmt = db()->prepare('SELECT b.*, p.display_name, p.user_id photographer_user_id FROM bookings b JOIN photographer_profiles p ON p.id=b.photographer_id WHERE b.id=? AND b.customer_id=? AND b.status="completed" AND b.deleted_at IS NULL LIMIT 1');
$stmt->execute([$bookingId, (int)$user['id']]);
$booking = $stmt->fetch();
if (!$booking) exit('Review not allowed');
$exists = db()->prepare('SELECT id FROM reviews WHERE booking_id=? AND deleted_at IS NULL');
$exists->execute([$bookingId]);
if ($exists->fetchColumn()) exit('Already reviewed');

if (is_post()) {
    verify_csrf();
    $ratings = [];
    foreach (['rating_overall','rating_quality','rating_communication','rating_punctuality','rating_professional'] as $field) {
        $ratingValue = (string)($_POST[$field] ?? '');
        if (!in_array($ratingValue, ['1', '2', '3', '4', '5'], true)) {
            flash('error', 'กรุณาให้คะแนนเป็นจำนวนเต็ม 1-5 คะแนน');
            clean_redirect('/customer/review.php', ['booking_id' => $bookingId]);
        }
        $ratings[$field] = (int)$ratingValue;
    }
    db()->beginTransaction();
    $stmt = db()->prepare('INSERT INTO reviews (booking_id, customer_id, photographer_id, rating_overall, rating_quality, rating_communication, rating_punctuality, rating_professional, comment, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "visible", NOW(), NOW())');
    $stmt->execute([$bookingId, (int)$user['id'], (int)$booking['photographer_id'], $ratings['rating_overall'], $ratings['rating_quality'], $ratings['rating_communication'], $ratings['rating_punctuality'], $ratings['rating_professional'], trim((string)$_POST['comment'])]);
    $reviewId = (int)db()->lastInsertId();
    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['name'] as $i => $name) {
            $file = ['name'=>$name,'type'=>$_FILES['images']['type'][$i],'tmp_name'=>$_FILES['images']['tmp_name'][$i],'error'=>$_FILES['images']['error'][$i],'size'=>$_FILES['images']['size'][$i]];
            $path = upload_image($file, 'reviews');
            if ($path) db()->prepare('INSERT INTO review_images (review_id, image_path, created_at) VALUES (?, ?, NOW())')->execute([$reviewId, $path]);
        }
    }
    update_photographer_rating((int)$booking['photographer_id']);
    notify_user((int)$booking['photographer_user_id'], 'มีรีวิวใหม่', $booking['booking_code'], 'review', $reviewId);
    log_activity('create_review', 'reviews', $reviewId);
    db()->commit();
    flash('success', 'บันทึกรีวิวแล้ว');
    redirect('/customer/bookings.php');
}

$pageTitle = 'รีวิวช่างภาพ';
include __DIR__ . '/../includes/header.php';
?>
<section class="mx-auto max-w-2xl px-4 py-10">
    <div class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
        <h1 class="text-2xl font-extrabold">รีวิว <?= h($booking['display_name']) ?></h1>
        <p class="mt-2 rounded-2xl bg-red-50 p-4 text-sm font-bold leading-7 text-red-700">
            <i class="fa-solid fa-circle-info mr-2"></i>ให้คะแนนเป็นจำนวนเต็ม 1-5 คะแนน ส่วนค่าเฉลี่ยในหน้าโปรไฟล์จะแสดงทศนิยมจากรีวิวหลายรายการ
        </p>
        <form method="post" enctype="multipart/form-data" class="mt-6 grid gap-4">
            <?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= $bookingId ?>">
            <?php foreach (['rating_overall'=>'คะแนนรวม', 'rating_quality'=>'คะแนน: คุณภาพงาน', 'rating_communication'=>'คะแนน: การสื่อสาร', 'rating_punctuality'=>'คะแนน: ความตรงเวลา', 'rating_professional'=>'คะแนน: ความเป็นมืออาชีพ'] as $field=>$label): ?>
                <label class="grid gap-2 text-sm font-bold">
                    <?= h($label) ?>
                    <select name="<?= h($field) ?>" class="rounded-2xl border px-4 py-3">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?= $i ?>"><?= $i ?> คะแนน</option>
                        <?php endfor; ?>
                    </select>
                </label>
            <?php endforeach; ?>
            <textarea name="comment" rows="5" class="rounded-2xl border px-4 py-3" placeholder="ความคิดเห็น"></textarea>
            <label class="grid gap-2 text-sm font-bold">
                รูปภาพรีวิว
                <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="rounded-2xl border px-4 py-3">
                <span class="text-xs font-bold leading-6 text-slate-500"><?= h(UPLOAD_IMAGE_HELP_TEXT) ?></span>
            </label>
            <button data-confirm="ยืนยันบันทึกรีวิว?" class="stock-button rounded-2xl px-5 py-3 font-black"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกรีวิว</button>
        </form>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>

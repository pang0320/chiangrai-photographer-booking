<?php require_once __DIR__ . '/includes/functions.php'; $pageTitle = 'เงื่อนไขการใช้งาน'; include __DIR__ . '/includes/header.php'; ?>
<section class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
    <div class="stock-card rounded-[2rem] p-8">
        <p class="section-kicker">เงื่อนไข</p>
        <h1 class="mt-2 text-4xl font-black">เงื่อนไขการใช้งาน</h1>
        <div class="mt-6 grid gap-4 leading-8 text-neutral-700">
            <p><i class="fa-solid fa-circle-info mr-2 text-red-600"></i><?= h(PAYMENT_DISCLAIMER) ?></p>
            <p><i class="fa-solid fa-handshake mr-2 text-red-600"></i>การตกลงราคา เงื่อนไขงาน และการชำระเงินเป็นเรื่องระหว่างลูกค้ากับช่างภาพโดยตรง</p>
            <p><i class="fa-solid fa-star mr-2 text-red-600"></i>การรีวิวทำได้เฉพาะงานที่เสร็จสิ้นแล้ว และต้องเป็นประสบการณ์จริง</p>
            <p><i class="fa-solid fa-ban mr-2 text-red-600"></i>ผู้ดูแลระบบสามารถระงับบัญชีหรือซ่อนข้อมูลที่ไม่เหมาะสมได้</p>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

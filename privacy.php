<?php require_once __DIR__ . '/includes/functions.php'; $pageTitle = 'นโยบายความเป็นส่วนตัว'; include __DIR__ . '/includes/header.php'; ?>
<section class="stock-shell px-4 py-12 sm:px-6 lg:px-8">
    <div class="stock-card rounded-[2rem] p-8">
        <p class="section-kicker">ความเป็นส่วนตัว</p>
        <h1 class="mt-2 text-4xl font-black">นโยบายความเป็นส่วนตัว</h1>
        <div class="mt-6 grid gap-4 leading-8 text-neutral-700">
            <p><i class="fa-solid fa-user-shield mr-2 text-red-600"></i>ระบบเก็บข้อมูลบัญชี คำขอจอง รีวิว ประวัติการค้นหา และข้อความติดต่อเพื่อให้บริการแพลตฟอร์ม</p>
            <p><i class="fa-solid fa-lock mr-2 text-red-600"></i>รหัสผ่านถูกจัดเก็บด้วย password_hash และระบบใช้ PDO Prepared Statement</p>
            <p><i class="fa-solid fa-eye mr-2 text-red-600"></i>ผู้ใช้สามารถดูประวัติการจอง รายการโปรด และแจ้งเตือนของตนเองเท่านั้น</p>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>

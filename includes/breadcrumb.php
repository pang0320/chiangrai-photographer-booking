<?php
$breadcrumbRequestUri = '/';
if (isset($_SERVER['REQUEST_URI'])) {
    $breadcrumbRequestUri = $_SERVER['REQUEST_URI'];
}
$breadcrumbPath = parse_url($breadcrumbRequestUri, PHP_URL_PATH);
if (!$breadcrumbPath) {
    $breadcrumbPath = '/';
}
$breadcrumbLabels = [
    'admin' => 'ผู้ดูแลระบบ',
    'customer' => 'ลูกค้า',
    'photographer' => 'ช่างภาพ',
    'dashboard.php' => 'แดชบอร์ด',
    'users.php' => 'สมาชิก',
    'photographers.php' => 'ช่างภาพ',
    'photographer_detail.php' => 'รายละเอียดช่างภาพ',
    'categories.php' => 'หมวดหมู่',
    'districts.php' => 'อำเภอ',
    'bookings.php' => 'คำขอจอง',
    'booking_detail.php' => 'รายละเอียดการจอง',
    'reviews.php' => 'รีวิว',
    'articles.php' => 'บทความ',
    'reports.php' => 'รายงาน',
    'activity_logs.php' => 'ประวัติการใช้งาน',
    'settings.php' => 'ตั้งค่า',
    'blog.php' => 'บทความ',
    'blog_detail.php' => 'รายละเอียดบทความ',
    'article_detail.php' => 'รายละเอียดบทความ',
    'blogs.php' => 'บทความระบบ',
    'tags.php' => 'แท็ก',
    'profile.php' => 'โปรไฟล์',
    'analytics.php' => 'วิเคราะห์ผลงาน',
    'portfolio.php' => 'ตัวอย่างงานถ่ายภาพ',
    'availability.php' => 'วันว่าง',
    'services.php' => 'ประเภทงาน',
    'service_areas.php' => 'พื้นที่ให้บริการ',
    'notifications.php' => 'แจ้งเตือน',
    'faq.php' => 'คำถามที่พบบ่อย',
    'contact.php' => 'ติดต่อเรา',
    'onboarding.php' => 'เริ่มต้นใช้งาน',
    'compare.php' => 'เปรียบเทียบช่างภาพ',
    'register.php' => 'สมัครสมาชิก',
    'login.php' => 'เข้าสู่ระบบ',
    'forgot_password.php' => 'ลืมรหัสผ่าน',
    'reset_password.php' => 'ตั้งรหัสผ่านใหม่',
    'about.php' => 'เกี่ยวกับเรา',
    'privacy.php' => 'นโยบายความเป็นส่วนตัว',
    'terms.php' => 'ข้อตกลงการใช้งาน',
];
$breadcrumbLinks = [
    '/admin' => '/admin/dashboard.php',
    '/customer' => '/customer/dashboard.php',
    '/photographer' => '/photographer/dashboard.php',
];

if ($breadcrumbPath !== '/' && $breadcrumbPath !== '/index.php'):
    $parts = array_values(array_filter(explode('/', trim($breadcrumbPath, '/'))));
    $runningPath = '';
?>
    <div class="stock-shell px-4 py-4 sm:px-6 lg:px-8">
        <nav class="flex flex-wrap items-center gap-2 text-xs font-black uppercase tracking-[0.12em] text-neutral-500">
            <a href="/index.php" class="hover:text-red-600">หน้าแรก</a>
            <?php foreach ($parts as $part): ?>
                <?php
                $runningPath .= '/' . $part;
                if (isset($breadcrumbLabels[$part])) {
                    $label = $breadcrumbLabels[$part];
                } else {
                    $label = ucwords(str_replace(['_', '.php'], [' ', ''], $part));
                }
                ?>
                <span class="text-neutral-300">/</span>
                <a href="<?= h($breadcrumbLinks[$runningPath] ?? $runningPath) ?>" class="hover:text-red-600"><?= h($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
<?php endif; ?>

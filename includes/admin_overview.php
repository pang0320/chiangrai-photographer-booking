<?php
$adminPath = '/';
if (isset($_SERVER['REQUEST_URI'])) {
    $adminPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
}
if (!$adminPath) {
    $adminPath = '/';
}

$adminPage = basename($adminPath);
$adminCurrentTitle = $pageTitle ?? 'ผู้ดูแลระบบ';

if (!function_exists('admin_overview_count')) {
    /**
     * นับจำนวนข้อมูลจาก SQL query ที่ส่งเข้ามา (ใช้ภายในหน้า Overview แอดมิน)
     * ใช้สำหรับอำนวยความสะดวกในการทำงานเกี่ยวกับ นับจำนวนข้อมูลจาก SQL query ที่ส่งเข้ามา (ใช้ภายในหน้า Overview แอดมิน)
     * @param string $sql คำสั่ง SQL สำหรับส่งไปคิวรี
     * @return int ตัวเลข (Integer)
     */
    function admin_overview_count(string $sql): int
    {
        return (int)db_fetch_value($sql);
    }
}

$overviewDefaults = [
    'description' => 'ภาพรวมสั้น ๆ ของข้อมูลในหน้านี้ ใช้ตรวจสถานะและไปยังงานสำคัญได้เร็วขึ้น',
    'stats' => [
        ['สมาชิกทั้งหมด', 'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL', 'fa-users', 'text-sky-600', 'บัญชีในระบบ', '/admin/users.php'],
        ['ช่างภาพรออนุมัติ', 'SELECT COUNT(*) FROM photographer_profiles WHERE approval_status = "pending" AND deleted_at IS NULL', 'fa-user-clock', 'text-amber-600', 'ต้องตรวจสอบ', '/admin/photographers.php?status=pending'],
        ['คำขอจองรอตอบรับ', 'SELECT COUNT(*) FROM bookings WHERE status = "pending" AND deleted_at IS NULL', 'fa-calendar-check', 'text-red-600', 'รอดำเนินการ', '/admin/bookings.php?status=pending'],
        ['รายงานรอตรวจสอบ', 'SELECT COUNT(*) FROM reports WHERE status = "pending"', 'fa-shield-halved', 'text-rose-600', 'รอตรวจสอบ', '/admin/reports_moderation.php'],
    ],
    'cards' => [
        ['ภาพรวมระบบ', 'งานที่ต้องดูแลวันนี้', 'รวมรายการสำคัญจากผู้ใช้ ช่างภาพ การจอง และรายงานปัญหา', '/admin/dashboard.php', 'เปิดแดชบอร์ด', 'fa-gauge'],
        ['ข้อความ', 'ข้อความใหม่ ' . number_format(admin_overview_count('SELECT COUNT(*) FROM contact_messages WHERE status = "unread"')), 'กล่องข้อความจากหน้าติดต่อที่ยังไม่ได้อ่าน', '/admin/contact_messages.php', 'เปิดกล่องข้อความ', 'fa-envelope-open-text'],
        ['ตรวจเนื้อหา', 'รีวิวที่ซ่อน ' . number_format(admin_overview_count('SELECT COUNT(*) FROM reviews WHERE status = "hidden" AND deleted_at IS NULL')), 'ตรวจสอบรีวิวที่ถูกซ่อนและเนื้อหาที่ควรดูแล', '/admin/reviews.php?status=hidden', 'ดูรีวิวที่ซ่อน', 'fa-star-half-stroke'],
    ],
];

$overviewMap = [
    'users.php' => [
        'description' => 'จัดการบัญชีลูกค้า ช่างภาพ และผู้ดูแล พร้อมตรวจสถานะการใช้งานของแต่ละบัญชี',
        'stats' => [
            ['สมาชิกทั้งหมด', 'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL', 'fa-users', 'text-sky-600', 'บัญชีที่ยังอยู่ในระบบ', '/admin/users.php'],
            ['ลูกค้า', 'SELECT COUNT(*) FROM users WHERE role_id = 1 AND deleted_at IS NULL', 'fa-user', 'text-emerald-600', 'บัญชีลูกค้า', '/admin/users.php?role_id=1'],
            ['ช่างภาพ', 'SELECT COUNT(*) FROM users WHERE role_id = 2 AND deleted_at IS NULL', 'fa-camera-retro', 'text-amber-600', 'บัญชีช่างภาพ', '/admin/users.php?role_id=2'],
            ['ถูกระงับ', 'SELECT COUNT(*) FROM users WHERE status = "suspended" AND deleted_at IS NULL', 'fa-ban', 'text-rose-600', 'บัญชีที่ถูกบล็อก', '/admin/users.php?status=suspended'],
        ],
        'cards' => [
            ['บทบาทสมาชิก', 'แยกบัญชีตามบทบาท', 'ใช้ตรวจว่าผู้ใช้เป็นลูกค้า ช่างภาพ หรือผู้ดูแลระบบ', '/admin/users.php', 'จัดการสมาชิก', 'fa-users-gear'],
            ['สถานะบัญชี', 'บัญชีรอตรวจสอบ ' . number_format(admin_overview_count('SELECT COUNT(*) FROM users WHERE status = "pending" AND deleted_at IS NULL')), 'ติดตามบัญชีที่ยังไม่พร้อมใช้งานหรือรอยืนยัน', '/admin/users.php?status=pending', 'ดูบัญชีรอตรวจสอบ', 'fa-user-clock'],
            ['สิทธิ์เข้าถึง', 'บัญชีผู้ดูแล ' . number_format(admin_overview_count('SELECT COUNT(*) FROM users WHERE role_id = 3 AND deleted_at IS NULL')), 'ตรวจจำนวนผู้มีสิทธิ์ดูแลระบบ', '/admin/users.php?role_id=3', 'ดูผู้ดูแล', 'fa-user-shield'],
        ],
    ],
    'photographers.php' => [
        'description' => 'ตรวจโปรไฟล์ช่างภาพ อนุมัติ ระงับ ยืนยันตัวตน และเลือกช่างภาพแนะนำ',
        'stats' => [
            ['ช่างภาพทั้งหมด', 'SELECT COUNT(*) FROM photographer_profiles WHERE deleted_at IS NULL', 'fa-camera-retro', 'text-sky-600', 'โปรไฟล์ทั้งหมด', '/admin/photographers.php'],
            ['รออนุมัติ', 'SELECT COUNT(*) FROM photographer_profiles WHERE approval_status = "pending" AND deleted_at IS NULL', 'fa-user-clock', 'text-amber-600', 'ต้องตรวจสอบ', '/admin/photographers.php?status=pending'],
            ['อนุมัติแล้ว', 'SELECT COUNT(*) FROM photographer_profiles WHERE approval_status = "approved" AND deleted_at IS NULL', 'fa-circle-check', 'text-emerald-600', 'แสดงในระบบ', '/admin/photographers.php?status=approved'],
            ['ถูกระงับ', 'SELECT COUNT(*) FROM photographer_profiles WHERE approval_status = "suspended" AND deleted_at IS NULL', 'fa-ban', 'text-rose-600', 'ถูกจำกัดการใช้งาน', '/admin/photographers.php?status=suspended'],
        ],
        'cards' => [
            ['ยืนยันตัวตน', 'ยืนยันตัวตนแล้ว ' . number_format(admin_overview_count('SELECT COUNT(*) FROM photographer_profiles WHERE is_verified = 1 AND deleted_at IS NULL')), 'โปรไฟล์ที่มี badge เพิ่มความน่าเชื่อถือ', '/admin/photographers.php', 'ตรวจช่างภาพ', 'fa-circle-check'],
            ['ช่างภาพแนะนำ', 'ช่างภาพแนะนำ ' . number_format(admin_overview_count('SELECT COUNT(*) FROM photographer_profiles WHERE is_featured = 1 AND deleted_at IS NULL')), 'โปรไฟล์ที่ถูกดันให้เด่นในหน้าค้นหา', '/admin/photographers.php', 'จัดการแนะนำ', 'fa-award'],
            ['สถานะรับงาน', 'เปิดรับงาน ' . number_format(admin_overview_count('SELECT COUNT(*) FROM photographer_profiles WHERE is_available = 1 AND deleted_at IS NULL')), 'ช่างภาพที่พร้อมรับคำขอจอง', '/admin/photographers.php?status=approved', 'ดูโปรไฟล์พร้อมใช้งาน', 'fa-calendar-check'],
        ],
    ],
    'bookings.php' => [
        'description' => 'ติดตามคำขอจอง เปลี่ยนสถานะ และตรวจงานที่ยังรอการตอบรับจากช่างภาพหรือลูกค้า',
        'stats' => [
            ['คำขอจองทั้งหมด', 'SELECT COUNT(*) FROM bookings WHERE deleted_at IS NULL', 'fa-calendar-days', 'text-sky-600', 'รายการจองทั้งหมด', '/admin/bookings.php'],
            ['รอดำเนินการ', 'SELECT COUNT(*) FROM bookings WHERE status = "pending" AND deleted_at IS NULL', 'fa-hourglass-half', 'text-amber-600', 'รอตอบรับ', '/admin/bookings.php?status=pending'],
            ['กำลังดำเนินงาน', 'SELECT COUNT(*) FROM bookings WHERE status = "in_progress" AND deleted_at IS NULL', 'fa-person-running', 'text-violet-600', 'อยู่ระหว่างทำงาน', '/admin/bookings.php?status=in_progress'],
            ['ยกเลิก/ปฏิเสธ', 'SELECT COUNT(*) FROM bookings WHERE status IN ("cancelled","rejected") AND deleted_at IS NULL', 'fa-circle-xmark', 'text-rose-600', 'ต้องติดตาม', '/admin/bookings.php?status=cancelled'],
        ],
        'cards' => [
            ['งานวันนี้', 'งานวันนี้ ' . number_format(admin_overview_count('SELECT COUNT(*) FROM bookings WHERE booking_date = CURDATE() AND deleted_at IS NULL')), 'คำขอจองที่ตรงกับวันที่ปัจจุบัน', '/admin/bookings.php', 'เปิดรายการจอง', 'fa-calendar-day'],
            ['เสร็จสิ้นแล้ว', 'เสร็จสิ้น ' . number_format(admin_overview_count('SELECT COUNT(*) FROM bookings WHERE status = "completed" AND deleted_at IS NULL')), 'งานที่ปิดสถานะเรียบร้อยแล้ว', '/admin/bookings.php?status=completed', 'ดูงานเสร็จสิ้น', 'fa-circle-check'],
            ['ตอบรับแล้ว', 'ตอบรับแล้ว ' . number_format(admin_overview_count('SELECT COUNT(*) FROM bookings WHERE status = "accepted" AND deleted_at IS NULL')), 'คำขอที่ช่างภาพตอบรับแล้วแต่ยังไม่จบงาน', '/admin/bookings.php?status=accepted', 'ดูงานตอบรับ', 'fa-handshake'],
        ],
    ],
    'reviews.php' => [
        'description' => 'ดูแลจำนวนรีวิว คะแนนเฉลี่ย และสถานะการแสดงผล เพื่อให้หน้าโปรไฟล์ช่างภาพน่าเชื่อถือ',
        'stats' => [
            ['จำนวนรีวิวทั้งหมด', 'SELECT COUNT(*) FROM reviews WHERE deleted_at IS NULL', 'fa-star', 'text-amber-600', 'รีวิวในระบบ', '/admin/reviews.php'],
            ['แสดงอยู่', 'SELECT COUNT(*) FROM reviews WHERE status = "visible" AND deleted_at IS NULL', 'fa-eye', 'text-emerald-600', 'แสดงอยู่', '/admin/reviews.php?status=visible'],
            ['ถูกซ่อน', 'SELECT COUNT(*) FROM reviews WHERE status = "hidden" AND deleted_at IS NULL', 'fa-eye-slash', 'text-rose-600', 'ซ่อน', '/admin/reviews.php?status=hidden'],
            ['คะแนนเฉลี่ยจากรีวิว', 'SELECT COALESCE(ROUND(AVG(rating_overall), 1), 0) FROM reviews WHERE deleted_at IS NULL', 'fa-chart-simple', 'text-sky-600', 'จากรีวิวทั้งหมด', '/admin/reviews.php'],
        ],
        'cards' => [
            ['ตรวจรีวิว', 'รีวิวที่ต้องตรวจ', 'ซ่อนหรือเปิดรีวิวที่มีผลต่อความน่าเชื่อถือของโปรไฟล์', '/admin/reviews.php?status=hidden', 'ดูรีวิวที่ซ่อน', 'fa-shield-halved'],
            ['คะแนนรีวิว', 'จำนวนรีวิว 5 คะแนน ' . number_format(admin_overview_count('SELECT COUNT(*) FROM reviews WHERE rating_overall = 5 AND deleted_at IS NULL')), 'ติดตามคุณภาพบริการจากคะแนนที่ลูกค้าให้', '/admin/reviews.php', 'ดูคะแนนทั้งหมด', 'fa-ranking-star'],
            ['เนื้อหารีวิว', 'รีวิวพร้อมข้อความ ' . number_format(admin_overview_count('SELECT COUNT(*) FROM reviews WHERE comment <> "" AND deleted_at IS NULL')), 'ตรวจเนื้อหารีวิวที่ลูกค้าเขียนไว้', '/admin/reviews.php', 'ตรวจข้อความรีวิว', 'fa-comment-dots'],
        ],
    ],
    'contact_messages.php' => [
        'description' => 'กล่องข้อความจากหน้าติดต่อ แยกข้อความใหม่ อ่านแล้ว และตอบกลับแล้ว',
        'stats' => [
            ['ข้อความทั้งหมด', 'SELECT COUNT(*) FROM contact_messages', 'fa-inbox', 'text-sky-600', 'รายการทั้งหมด', '/admin/contact_messages.php'],
            ['ยังไม่อ่าน', 'SELECT COUNT(*) FROM contact_messages WHERE status = "unread"', 'fa-circle-exclamation', 'text-rose-600', 'ต้องเปิดอ่าน', '/admin/contact_messages.php'],
            ['อ่านแล้ว', 'SELECT COUNT(*) FROM contact_messages WHERE status = "read"', 'fa-envelope-open', 'text-amber-600', 'อ่านแล้ว', '/admin/contact_messages.php'],
            ['ตอบแล้ว', 'SELECT COUNT(*) FROM contact_messages WHERE status = "replied"', 'fa-reply', 'text-emerald-600', 'ตอบแล้ว', '/admin/contact_messages.php'],
        ],
        'cards' => [
            ['กล่องข้อความ', 'ข้อความใหม่ ' . number_format(admin_overview_count('SELECT COUNT(*) FROM contact_messages WHERE status = "unread"')), 'ควรเปิดอ่านก่อนเพื่อไม่ให้คำถามตกหล่น', '/admin/contact_messages.php', 'เปิดกล่องข้อความ', 'fa-envelope-open-text'],
            ['ติดตามต่อ', 'รอตอบกลับ ' . number_format(admin_overview_count('SELECT COUNT(*) FROM contact_messages WHERE status IN ("unread","read")')), 'ข้อความที่ยังไม่อยู่ในสถานะตอบแล้ว', '/admin/contact_messages.php', 'จัดการข้อความ', 'fa-headset'],
            ['ประวัติข้อความ', 'ตอบแล้ว ' . number_format(admin_overview_count('SELECT COUNT(*) FROM contact_messages WHERE status = "replied"')), 'ใช้ดูประวัติการดูแลผู้ติดต่อ', '/admin/contact_messages.php', 'ดูประวัติ', 'fa-clock-rotate-left'],
        ],
    ],
    'reports_moderation.php' => [
        'description' => 'ตรวจรายงานปัญหาจากผู้ใช้และจัดสถานะให้ชัดเจนว่าตรวจแล้วหรือแก้ไขแล้ว',
        'stats' => [
            ['รายงานทั้งหมด', 'SELECT COUNT(*) FROM reports', 'fa-shield-halved', 'text-sky-600', 'รายงานในระบบ', '/admin/reports_moderation.php'],
            ['รอตรวจสอบ', 'SELECT COUNT(*) FROM reports WHERE status = "pending"', 'fa-hourglass-half', 'text-amber-600', 'รอตรวจสอบ', '/admin/reports_moderation.php?status=pending'],
            ['แก้ไขแล้ว', 'SELECT COUNT(*) FROM reports WHERE status = "resolved"', 'fa-circle-check', 'text-emerald-600', 'แก้ไขแล้ว', '/admin/reports_moderation.php?status=resolved'],
            ['ปฏิเสธรายงาน', 'SELECT COUNT(*) FROM reports WHERE status = "rejected"', 'fa-ban', 'text-rose-600', 'ไม่รับรายงาน', '/admin/reports_moderation.php?status=rejected'],
        ],
        'cards' => [
            ['คิวตรวจสอบ', 'รอตรวจสอบ ' . number_format(admin_overview_count('SELECT COUNT(*) FROM reports WHERE status = "pending"')), 'รายงานที่ควรจัดการก่อน', '/admin/reports_moderation.php?status=pending', 'เปิดคิวตรวจสอบ', 'fa-list-check'],
            ['เป้าหมายรายงาน', 'รายงานรีวิว ' . number_format(admin_overview_count('SELECT COUNT(*) FROM reports WHERE target_type = "review"')), 'เนื้อหาที่ถูกรายงานในส่วนรีวิว', '/admin/reports_moderation.php', 'ดูเป้าหมายรายงาน', 'fa-crosshairs'],
            ['แก้ไขแล้ว', 'แก้ไขแล้ว ' . number_format(admin_overview_count('SELECT COUNT(*) FROM reports WHERE status = "resolved"')), 'รายการที่ปิดงานแล้ว', '/admin/reports_moderation.php?status=resolved', 'ดูรายการแก้ไขแล้ว', 'fa-check-double'],
        ],
    ],
    'categories.php' => [
        'description' => 'จัดประเภทงานถ่ายภาพที่ใช้ในหน้า search และฟอร์มคำขอจอง',
        'stats' => [
            ['ประเภททั้งหมด', 'SELECT COUNT(*) FROM service_categories', 'fa-layer-group', 'text-sky-600', 'หมวดหมู่บริการ', '/admin/categories.php'],
            ['เปิดใช้งาน', 'SELECT COUNT(*) FROM service_categories WHERE is_active = 1', 'fa-circle-check', 'text-emerald-600', 'แสดงให้เลือก', '/admin/categories.php'],
            ['ปิดใช้งาน', 'SELECT COUNT(*) FROM service_categories WHERE is_active = 0', 'fa-eye-slash', 'text-rose-600', 'ซ่อนไว้', '/admin/categories.php'],
            ['มีการจอง', 'SELECT COUNT(DISTINCT category_id) FROM bookings WHERE deleted_at IS NULL', 'fa-calendar-check', 'text-amber-600', 'ถูกใช้งานจริง', '/admin/bookings.php'],
        ],
        'cards' => [
            ['ตัวกรองค้นหา', 'หมวดที่เปิดใช้งาน', 'ใช้เป็นตัวเลือกค้นหาช่างภาพและเลือกประเภทงานตอนจอง', '/admin/categories.php', 'จัดประเภทงาน', 'fa-filter'],
            ['ใช้ในคำขอจอง', 'การจองทั้งหมด ' . number_format(admin_overview_count('SELECT COUNT(*) FROM bookings WHERE deleted_at IS NULL')), 'ตรวจว่าหมวดหมู่มีผลกับคำขอจอง', '/admin/bookings.php', 'ดูการจอง', 'fa-calendar-days'],
            ['การเรียงลำดับ', 'เรียงลำดับหมวดหมู่', 'ควบคุมลำดับแสดงผลของประเภทงานในหน้าเว็บ', '/admin/categories.php', 'จัดลำดับ', 'fa-arrow-down-1-9'],
        ],
    ],
    'districts.php' => [
        'description' => 'จัดข้อมูลอำเภอเชียงรายสำหรับพื้นที่ให้บริการ การค้นหา และตำแหน่งหลักของช่างภาพ',
        'stats' => [
            ['อำเภอทั้งหมด', 'SELECT COUNT(*) FROM districts', 'fa-map-location-dot', 'text-sky-600', 'พื้นที่ในระบบ', '/admin/districts.php'],
            ['มีช่างภาพ', 'SELECT COUNT(DISTINCT main_district_id) FROM photographer_profiles WHERE main_district_id IS NOT NULL AND deleted_at IS NULL', 'fa-camera-retro', 'text-emerald-600', 'อำเภอที่มีโปรไฟล์', '/admin/photographers.php'],
            ['ใช้ในคำขอจอง', 'SELECT COUNT(DISTINCT district_id) FROM bookings WHERE district_id IS NOT NULL AND deleted_at IS NULL', 'fa-calendar-check', 'text-amber-600', 'พื้นที่จองจริง', '/admin/bookings.php'],
            ['ถูกค้นหา', 'SELECT COUNT(DISTINCT district_id) FROM search_logs WHERE district_id IS NOT NULL', 'fa-magnifying-glass-location', 'text-rose-600', 'มีประวัติค้นหา', '/admin/reports.php'],
        ],
        'cards' => [
            ['พื้นที่ให้บริการ', 'พื้นที่ให้บริการ', 'ข้อมูลอำเภอใช้ผูกกับช่างภาพและการค้นหา', '/admin/districts.php', 'จัดการอำเภอ', 'fa-map'],
            ['ช่างภาพ', 'ช่างภาพทั้งหมด ' . number_format(admin_overview_count('SELECT COUNT(*) FROM photographer_profiles WHERE deleted_at IS NULL')), 'ดูการกระจายตัวของช่างภาพตามอำเภอ', '/admin/photographers.php', 'ดูช่างภาพ', 'fa-camera-retro'],
            ['ความต้องการค้นหา', 'คำค้นพื้นที่ ' . number_format(admin_overview_count('SELECT COUNT(*) FROM search_logs WHERE district_id IS NOT NULL')), 'อำเภอที่ผู้ใช้ค้นหาบ่อยช่วยบอกความต้องการ', '/admin/reports.php', 'ดูรายงานค้นหา', 'fa-chart-line'],
        ],
    ],
    'blogs.php' => [
        'description' => 'จัดบทความเว็บจากผู้ดูแล ใช้เผยแพร่ข่าว บทความ SEO และเนื้อหาส่วนกลาง',
        'stats' => [
            ['บทความทั้งหมด', 'SELECT COUNT(*) FROM blogs WHERE deleted_at IS NULL', 'fa-newspaper', 'text-sky-600', 'บทความเว็บ', '/admin/blogs.php'],
            ['เผยแพร่แล้ว', 'SELECT COUNT(*) FROM blogs WHERE status = "published" AND deleted_at IS NULL', 'fa-circle-check', 'text-emerald-600', 'เผยแพร่แล้ว', '/admin/blogs.php'],
            ['ฉบับร่าง', 'SELECT COUNT(*) FROM blogs WHERE status = "draft" AND deleted_at IS NULL', 'fa-pen', 'text-amber-600', 'ฉบับร่าง', '/admin/blogs.php'],
            ['ถูกซ่อน', 'SELECT COUNT(*) FROM blogs WHERE status = "hidden" AND deleted_at IS NULL', 'fa-eye-slash', 'text-rose-600', 'ซ่อน', '/admin/blogs.php'],
        ],
        'cards' => [
            ['การเผยแพร่', 'เผยแพร่แล้ว ' . number_format(admin_overview_count('SELECT COUNT(*) FROM blogs WHERE status = "published" AND deleted_at IS NULL')), 'บทความที่ผู้ใช้เปิดอ่านได้', '/admin/blogs.php', 'จัดการบทความ', 'fa-upload'],
            ['ฉบับร่าง', 'ร่างที่ยังไม่เผยแพร่', 'เก็บงานเขียนที่ยังไม่พร้อมแสดงหน้าเว็บ', '/admin/blogs.php', 'ดูฉบับร่าง', 'fa-file-pen'],
            ['เนื้อหาส่วนกลาง', 'บทความส่วนกลาง', 'ใช้เพิ่มเนื้อหาที่เกี่ยวกับช่างภาพและการจอง', '/admin/blogs.php', 'เขียนบทความ', 'fa-feather-pointed'],
        ],
    ],
    'articles.php' => [
        'description' => 'ดูบทความจากช่างภาพและสถานะการเผยแพร่ของเนื้อหาในระบบ',
        'stats' => [
            ['บทความช่างภาพ', 'SELECT COUNT(*) FROM photographer_articles WHERE deleted_at IS NULL', 'fa-newspaper', 'text-sky-600', 'ทั้งหมด', '/admin/articles.php'],
            ['เผยแพร่แล้ว', 'SELECT COUNT(*) FROM photographer_articles WHERE status = "published" AND deleted_at IS NULL', 'fa-circle-check', 'text-emerald-600', 'เผยแพร่', '/admin/articles.php'],
            ['ฉบับร่าง', 'SELECT COUNT(*) FROM photographer_articles WHERE status = "draft" AND deleted_at IS NULL', 'fa-pen', 'text-amber-600', 'ฉบับร่าง', '/admin/articles.php'],
            ['ถูกซ่อน', 'SELECT COUNT(*) FROM photographer_articles WHERE status = "hidden" AND deleted_at IS NULL', 'fa-eye-slash', 'text-rose-600', 'ถูกซ่อน', '/admin/articles.php'],
        ],
        'cards' => [
            ['เนื้อหาช่างภาพ', 'บทความจากช่างภาพ', 'ตรวจเนื้อหาที่ช่างภาพใช้สื่อสารกับลูกค้า', '/admin/articles.php', 'ดูบทความ', 'fa-camera'],
            ['เผยแพร่แล้ว', 'เผยแพร่แล้ว ' . number_format(admin_overview_count('SELECT COUNT(*) FROM photographer_articles WHERE status = "published" AND deleted_at IS NULL')), 'บทความที่ปรากฏหน้าเว็บ', '/admin/articles.php', 'ดูบทความเผยแพร่', 'fa-eye'],
            ['ตรวจเนื้อหา', 'ซ่อนไว้ ' . number_format(admin_overview_count('SELECT COUNT(*) FROM photographer_articles WHERE status = "hidden" AND deleted_at IS NULL')), 'บทความที่ไม่ควรแสดงต่อผู้ใช้', '/admin/articles.php', 'ตรวจบทความซ่อน', 'fa-shield-halved'],
        ],
    ],
    'faqs.php' => [
        'description' => 'จัดคำถามที่พบบ่อยสำหรับช่วยตอบข้อสงสัยก่อนผู้ใช้ติดต่อแอดมิน',
        'stats' => [
            ['คำถามทั้งหมด', 'SELECT COUNT(*) FROM faqs', 'fa-circle-question', 'text-sky-600', 'รายการคำถาม', '/admin/faqs.php'],
            ['เปิดใช้งาน', 'SELECT COUNT(*) FROM faqs WHERE is_active = 1', 'fa-eye', 'text-emerald-600', 'แสดงหน้าเว็บ', '/admin/faqs.php'],
            ['ปิดใช้งาน', 'SELECT COUNT(*) FROM faqs WHERE is_active = 0', 'fa-eye-slash', 'text-rose-600', 'ซ่อนไว้', '/admin/faqs.php'],
            ['หมวดคำถาม', 'SELECT COUNT(DISTINCT category) FROM faqs', 'fa-folder-tree', 'text-amber-600', 'กลุ่มคำถาม', '/admin/faqs.php'],
        ],
        'cards' => [
            ['ช่วยเหลือผู้ใช้', 'ลดภาระตอบซ้ำ', 'FAQ ช่วยให้ผู้ใช้หาคำตอบได้เองก่อนส่งข้อความ', '/admin/faqs.php', 'จัดการคำถาม', 'fa-headset'],
            ['หมวดคำถาม', 'หมวดคำถาม', 'แยกคำถามตามหัวข้อเพื่อให้อ่านง่าย', '/admin/faqs.php', 'จัดหมวด', 'fa-folder-open'],
            ['การเรียงลำดับ', 'ลำดับคำถาม', 'ควบคุมลำดับแสดงผลในหน้า FAQ', '/admin/faqs.php', 'จัดลำดับ', 'fa-arrow-down-1-9'],
        ],
    ],
    'reports.php' => [
        'description' => 'ภาพรวมเชิงตัวเลขของการใช้งาน การค้นหา การจอง และข้อมูลยอดนิยมในระบบ',
        'stats' => [
            ['การจองทั้งหมด', 'SELECT COUNT(*) FROM bookings WHERE deleted_at IS NULL', 'fa-calendar-check', 'text-sky-600', 'ข้อมูลการจอง', '/admin/reports.php'],
            ['คำค้นทั้งหมด', 'SELECT COUNT(*) FROM search_logs', 'fa-magnifying-glass', 'text-emerald-600', 'พฤติกรรมค้นหา', '/admin/reports.php'],
            ['ช่างภาพทั้งหมด', 'SELECT COUNT(*) FROM photographer_profiles WHERE deleted_at IS NULL', 'fa-camera-retro', 'text-amber-600', 'โปรไฟล์', '/admin/photographers.php'],
            ['จำนวนรีวิวทั้งหมด', 'SELECT COUNT(*) FROM reviews WHERE deleted_at IS NULL', 'fa-star', 'text-rose-600', 'รีวิวในระบบ', '/admin/reviews.php'],
        ],
        'cards' => [
            ['วิเคราะห์ข้อมูล', 'รายงานการใช้งาน', 'ดูแนวโน้มคำขอจอง คำค้น และข้อมูลยอดนิยม', '/admin/reports.php', 'เปิดรายงาน', 'fa-chart-line'],
            ['ความต้องการค้นหา', 'คำค้นทั้งหมด ' . number_format(admin_overview_count('SELECT COUNT(*) FROM search_logs')), 'ข้อมูลช่วยดูว่าลูกค้าหาอะไรบ่อย', '/admin/reports.php', 'ดูคำค้น', 'fa-magnifying-glass'],
            ['แนวโน้มการจอง', 'จองเดือนนี้ ' . number_format(admin_overview_count('SELECT COUNT(*) FROM bookings WHERE DATE_FORMAT(created_at, "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m") AND deleted_at IS NULL')), 'จำนวนคำขอที่เกิดในเดือนปัจจุบัน', '/admin/reports.php', 'ดูแนวโน้ม', 'fa-chart-simple'],
        ],
    ],
    'activity_logs.php' => [
        'description' => 'ตรวจประวัติการใช้งานของระบบ การเปลี่ยนสถานะ และกิจกรรมจากผู้ใช้แต่ละคน',
        'stats' => [
            ['ประวัติทั้งหมด', 'SELECT COUNT(*) FROM activity_logs', 'fa-clock-rotate-left', 'text-sky-600', 'ประวัติทั้งหมด', '/admin/activity_logs.php'],
            ['วันนี้', 'SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()', 'fa-calendar-day', 'text-emerald-600', 'กิจกรรมวันนี้', '/admin/activity_logs.php'],
            ['สมาชิกที่ใช้งาน', 'SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE user_id IS NOT NULL', 'fa-user-check', 'text-amber-600', 'มีประวัติ', '/admin/activity_logs.php'],
            ['IP ที่พบ', 'SELECT COUNT(DISTINCT ip_address) FROM activity_logs WHERE ip_address IS NOT NULL', 'fa-network-wired', 'text-rose-600', 'แหล่งที่มา', '/admin/activity_logs.php'],
        ],
        'cards' => [
            ['ตรวจสอบย้อนหลัง', 'ไทม์ไลน์ล่าสุด', 'ใช้ตรวจว่าใครทำอะไรกับข้อมูลสำคัญ', '/admin/activity_logs.php', 'เปิดประวัติ', 'fa-list-timeline'],
            ['วันนี้', 'กิจกรรมวันนี้ ' . number_format(admin_overview_count('SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()')), 'ดูความเคลื่อนไหวล่าสุดของระบบ', '/admin/activity_logs.php', 'ดูวันนี้', 'fa-calendar-day'],
            ['ข้อมูลที่ถูกแก้ไข', 'ตารางที่ถูกแก้ไข', 'ช่วยไล่ปัญหาเมื่อข้อมูลเปลี่ยนผิดปกติ', '/admin/activity_logs.php', 'ตรวจประวัติ', 'fa-database'],
        ],
    ],
    'settings.php' => [
        'description' => 'ตั้งค่าระบบกลาง เช่น ชื่อเว็บ ช่องทางติดต่อ และข้อความที่ใช้แสดงผล',
        'stats' => [
            ['ค่าทั้งหมด', 'SELECT COUNT(*) FROM settings', 'fa-gears', 'text-sky-600', 'ค่าตั้งค่าระบบ', '/admin/settings.php'],
            ['ข้อความติดต่อ', 'SELECT COUNT(*) FROM contact_messages WHERE status = "unread"', 'fa-envelope', 'text-rose-600', 'ยังไม่อ่าน', '/admin/contact_messages.php'],
            ['สมาชิกทั้งหมด', 'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL', 'fa-users', 'text-emerald-600', 'อิงค่าระบบ', '/admin/users.php'],
            ['ค่าหน้าแรก', 'SELECT COUNT(*) FROM settings WHERE setting_key LIKE "home_%"', 'fa-house', 'text-amber-600', 'หัวข้อหน้าแรก', '/admin/settings.php'],
        ],
        'cards' => [
            ['ตั้งค่าเว็บ', 'ตั้งค่าระบบ', 'ปรับค่าที่ส่งผลกับการแสดงผลหลักของเว็บ', '/admin/settings.php', 'เปิดตั้งค่า', 'fa-sliders'],
            ['ข้อมูลติดต่อ', 'ข้อมูลติดต่อ', 'ตรวจค่าที่ผู้ใช้เห็นในหน้าเว็บและ footer', '/admin/settings.php', 'แก้ข้อมูลติดต่อ', 'fa-address-card'],
            ['ค่าระบบ', 'ค่ากลางของระบบ', 'แก้เฉพาะค่าที่จำเป็นต่อการใช้งานจริง', '/admin/settings.php', 'จัดการค่า', 'fa-gear'],
        ],
    ],
];

$adminOverview = $overviewMap[$adminPage] ?? $overviewDefaults;
$adminStats = $adminOverview['stats'];
$adminCards = $adminOverview['cards'];
$adminDescription = $adminOverview['description'];
?>

<section class="px-4 pt-6 sm:px-6 lg:px-8">
    <div class="dashboard-hero rounded-[2rem] p-5 text-white sm:p-6">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.22em] text-white/55"><i class="fa-solid fa-gauge-high mr-2 text-red-300"></i>Admin</p>
                <h1 class="mt-2 text-2xl font-black sm:text-4xl"><?= h($adminCurrentTitle) ?></h1>
                <p class="mt-2 max-w-2xl text-sm font-bold leading-7 text-white/65"><?= h($adminDescription) ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="/admin/dashboard.php" class="rounded-full bg-white px-4 py-2.5 text-sm font-black text-neutral-950 transition hover:bg-red-600 hover:text-white"><i class="fa-solid fa-gauge mr-2"></i>แดชบอร์ดหลัก</a>
                <?= clean_context_button('/admin/photographers.php', ['status' => 'pending'], '<i class="fa-solid fa-camera-retro mr-2"></i>อนุมัติช่างภาพ', 'rounded-full bg-white/12 px-4 py-2.5 text-sm font-black text-white transition hover:bg-white hover:text-neutral-950') ?>
                <a href="/admin/reports_moderation.php" class="rounded-full bg-white/12 px-4 py-2.5 text-sm font-black text-white transition hover:bg-white hover:text-neutral-950"><i class="fa-solid fa-shield-halved mr-2"></i>ตรวจรายงาน</a>
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($adminStats as $stat): ?>
            <?php
            $statLabel = $stat[0];
            $statValue = admin_overview_count($stat[1]);
            $statIcon = $stat[2];
            $statTone = $stat[3];
            $statHint = $stat[4];
            $statHref = $stat[5] ?? '/admin/dashboard.php';
            ?>
            <?= clean_context_button_from_url($statHref, '<div class="flex items-center justify-between gap-4"><p class="text-sm font-bold text-neutral-500">' . h($statLabel) . '</p><span class="grid h-11 w-11 place-items-center rounded-2xl bg-white shadow-sm"><i class="fa-solid ' . h($statIcon) . ' ' . h($statTone) . '"></i></span></div><p class="mt-3 text-3xl font-black text-neutral-950">' . number_format($statValue) . '</p><p class="mt-1 text-xs font-black text-neutral-400">' . h($statHint) . '</p>', 'metric-card w-full rounded-[1.5rem] p-5 text-left transition hover:-translate-y-1 hover:shadow-2xl', 'contents') ?>
        <?php endforeach; ?>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <?php foreach ($adminCards as $card): ?>
            <div class="stock-card rounded-[1.5rem] p-5">
                <p class="section-kicker"><?= h($card[0]) ?></p>
                <h2 class="mt-2 text-xl font-black text-neutral-950"><i class="fa-solid <?= h($card[5]) ?> mr-2 text-red-600"></i><?= h($card[1]) ?></h2>
                <p class="mt-2 text-sm font-bold leading-7 text-neutral-500"><?= h($card[2]) ?></p>
                <?= clean_context_button_from_url((string)$card[3], '<i class="fa-solid fa-arrow-up-right-from-square mr-2"></i>' . h($card[4]), 'mt-3 inline-flex rounded-full bg-red-50 px-4 py-2 text-sm font-black text-red-700 hover:bg-red-600 hover:text-white') ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

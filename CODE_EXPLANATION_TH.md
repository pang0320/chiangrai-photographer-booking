# คู่มืออธิบายโค้ดระบบ Chiang Rai Photographer Booking

เอกสารนี้ใช้สำหรับอธิบายตอนนำเสนอโปรเจกต์ ว่าแต่ละไฟล์ แต่ละหน้า และแต่ละฟังก์ชันทำหน้าที่อะไร ระบบนี้เป็นเว็บค้นหาและส่งคำขอจองช่างภาพในจังหวัดเชียงราย โดยไม่มีระบบชำระเงินในเว็บไซต์ ลูกค้าและช่างภาพตกลงราคาและชำระเงินกันเองภายนอกระบบ

## 1. ภาพรวมระบบ

ระบบแบ่งผู้ใช้เป็น 3 role เท่านั้น

- `Customer` ลูกค้า: ค้นหาช่างภาพ ส่งคำขอจอง ดูสถานะ รีวิว รายงานปัญหา บันทึกรายการโปรด
- `Photographer` ช่างภาพ: จัดการโปรไฟล์ พื้นที่รับงาน ประเภทงาน ตัวอย่างงาน วันว่าง คำขอจอง บทความ รีวิว
- `Admin` ผู้ดูแลระบบ: อนุมัติช่างภาพ จัดการสมาชิก หมวดหมู่ อำเภอ booking รีวิว บทความ FAQ แบนเนอร์ รายงานปัญหา Activity Log และ settings

แนวคิดหลักของระบบ:

- Public visitor ดูหน้าเว็บ ค้นหา ดูโปรไฟล์ อ่านบทความ/FAQ ได้โดยไม่ต้องสมัคร
- การจอง รีวิว รายการโปรด และรายงานปัญหาต้อง login
- ช่างภาพต้อง `approval_status = approved` และ user ต้อง `status = active` จึงแสดงในหน้าค้นหา
- ไม่มี payment gateway ไม่มี invoice ไม่มีตะกร้าสินค้า ไม่มีระบบการเงิน
- ทุก action สำคัญบันทึกลง `activity_logs`
- ทุก query หลักใช้ PDO prepared statement
- ฟอร์มสำคัญใช้ CSRF token
- อัปโหลดรูปตรวจชนิดไฟล์ ขนาด MIME และเปลี่ยนชื่อไฟล์เป็น random hash

## 2. โครงสร้างโฟลเดอร์

- `config/` เก็บ config ระบบและ database connection
- `includes/` เก็บ component และ helper กลาง เช่น auth, csrf, upload, navbar, sidebar, function
- `assets/` เก็บ CSS, JS, uploads และรูป seed/local
- `admin/` หน้าหลังบ้านของผู้ดูแลระบบ
- `customer/` dashboard และหน้าทำงานของลูกค้า
- `photographer/` dashboard และหน้าทำงานของช่างภาพ
- root files เช่น `index.php`, `photographers.php`, `photographer_detail.php`, `login.php`, `register.php`
- `database.sql` schema และ seed data
- `docker.yml` ใช้รัน PHP, MariaDB, phpMyAdmin

## 3. Database สำคัญ

ไฟล์ `database.sql` สร้างฐานข้อมูล `chiangrai_photographer_booking` และตารางหลักดังนี้

- `roles`: เก็บ role ทั้ง 3 แบบ
- `users`: เก็บบัญชีผู้ใช้ทุก role เช่น ชื่อ อีเมล เบอร์โทร รหัสผ่าน status
- `password_resets`: token สำหรับ reset password
- `login_attempts`: จำกัด login attempt
- `districts`: อำเภอในเชียงราย พร้อม latitude/longitude ใช้ค้นหาใกล้เคียงด้วย Haversine
- `service_categories`: หมวดหมู่งานถ่ายภาพ
- `photographer_profiles`: โปรไฟล์ช่างภาพ เช่น slug, bio, price, contact, approval, rating, response rate
- `photographer_services`: ประเภทงานที่ช่างภาพรับ
- `photographer_service_areas`: พื้นที่อำเภอที่ช่างภาพรับงาน
- `photographer_portfolios`: ตัวอย่างงานถ่ายภาพของช่างภาพ
- `photographer_availability`: วันว่าง/ช่วงเวลาที่ช่างภาพเปิดรับงาน
- `bookings`: คำขอจองจากลูกค้า
- `booking_status_logs`: ประวัติการเปลี่ยนสถานะ booking
- `reviews`: รีวิวจากลูกค้าหลังงาน completed
- `review_images`: รูปประกอบรีวิว
- `photographer_articles`: บทความที่ช่างภาพเขียน
- `blogs`: บทความกลางจาก admin
- `notifications`: แจ้งเตือนของผู้ใช้
- `activity_logs`: ประวัติ action สำคัญในระบบ
- `banners`: แบนเนอร์หน้าแรก
- `settings`: ค่าตั้งค่าระบบ
- `favorite_photographers`: ช่างภาพที่ลูกค้าบันทึกไว้
- `faqs`: คำถามที่พบบ่อย
- `contact_messages`: ข้อความจากหน้า contact
- `reports`: รายงานปัญหาช่างภาพ/รีวิว/booking/article
- `search_logs`: ประวัติการค้นหา
- `recently_viewed_photographers`: ประวัติช่างภาพที่ลูกค้าเคยดู
- `tags`, `portfolio_tags`, `article_tags`, `blog_tags`: tag สำหรับเนื้อหาและ portfolio

## 4. Config

### `config/app.php`

ไฟล์ตั้งค่าระบบกลาง

- ตั้ง timezone เป็น `Asia/Bangkok`
- กำหนด `APP_NAME`, `APP_URL`, `UPLOAD_PATH`, `UPLOAD_URL`
- กำหนด `MAX_UPLOAD_SIZE = 5MB`
- กำหนดข้อความช่วย upload `UPLOAD_IMAGE_HELP_TEXT`
- กำหนด disclaimer ว่าเว็บไม่รับชำระเงิน
- ตั้ง session cookie ให้ปลอดภัย: `httponly`, `samesite=Lax`, `secure` เมื่อเป็น HTTPS
- รองรับ `ENFORCE_HTTPS=1` เพื่อบังคับ redirect ไป HTTPS
- เมื่อเป็น HTTPS จะส่ง header `Strict-Transport-Security`

### `config/database.php`

ไฟล์เชื่อมต่อฐานข้อมูล

- อ่าน env `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`
- fallback เป็นค่าที่ใช้กับ local/docker
- ฟังก์ชัน `db()` คืนค่า PDO connection แบบ singleton
- ตั้ง PDO ให้ throw exception, fetch เป็น associative array และปิด emulate prepares เพื่อให้ prepared statement ทำงานจริง

## 5. Includes และฟังก์ชันกลาง

### `includes/functions.php`

เป็นไฟล์ helper หลักของระบบ หน้าส่วนใหญ่ include ไฟล์นี้

#### Security และ output

- `h($value)`: แปลงข้อมูลเป็น HTML-safe ด้วย `htmlspecialchars()` ป้องกัน XSS ตอนแสดงผล
- `redirect($path)`: redirect ไป path ที่กำหนดแล้ว `exit`
- `client_ip()`: คืน IP ผู้ใช้แบบตัดความยาวไม่เกิน 64 ตัวอักษร
- `is_post()`: ตรวจว่า request เป็น POST หรือไม่

#### Clean context / URL ไม่โชว์ query

ระบบนี้มีแนวคิดไม่ส่งค่า query ให้เห็นบน URL โดยใช้ session เก็บ context

- `clean_context_path($path)`: คืน path ล้วนจาก URL เช่น `/photographer_detail.php`
- `clean_context_set($path, $params)`: เก็บ params ของหน้านั้นลง session
- `clean_context_get($path)`: ดึง params ที่เคยเก็บไว้จาก session
- `clean_context_init($allowedKeys, $path)`: รับค่า GET/POST เฉพาะ key ที่อนุญาต แล้ว redirect กลับ path สะอาด
- `clean_context_value($context, $key, $default)`: อ่านค่าจาก context พร้อม default
- `clean_redirect($path, $params)`: ตั้ง context แล้ว redirect ไป path
- `clean_context_inputs($params)`: สร้าง hidden inputs พร้อม CSRF สำหรับ form navigation
- `clean_context_button($path, $params, $content, $buttonClass, $formClass, $formAttrs)`: สร้าง form+button ที่ส่ง context แบบ POST
- `clean_context_button_from_url($url, $content, $buttonClass, $formClass, $formAttrs)`: แปลง URL ที่มี query เป็น clean context button

#### Auth และ role

- `auth_session_expired()`: ตรวจ session timeout
- `clear_auth_session($restart)`: ล้าง session auth และสร้าง session ใหม่ถ้าต้องการ
- `current_user()`: อ่าน user ปัจจุบันจาก session แล้ว query role ล่าสุดจาก DB
- `role_id($role)`: คืน id ของ role ตามชื่อ เช่น customer/admin/photographer
- `requireLogin()`: บังคับ login ถ้าไม่ login redirect ไป `login.php`
- `requireRole($roles)`: บังคับ role ของหน้า เช่น admin เท่านั้น
- `dashboard_path($role)`: คืน dashboard ตาม role
- `user_workspace_path($user)`: คืนหน้าทำงานหลัก ถ้าช่างภาพ profile ไม่ครบอาจไป onboarding
- `user_workspace_label($user)`: label ปุ่มเมนูของฉัน
- `user_workspace_icon($user)`: icon ของปุ่มเมนูของฉัน

#### Settings และ query helper

- `setting($key, $default)`: อ่านค่าจาก `settings`
- `set_setting($key, $value)`: insert/update settings
- `db_fetch_all($sql, $params)`: query แล้วคืนหลายแถว
- `db_fetch_value($sql, $params)`: query แล้วคืนค่าเดียว
- `table_count($table, $where)`: นับข้อมูลในตาราง

#### Slug และ log

- `slugify($text)`: แปลงข้อความเป็น slug สำหรับ URL
- `unique_slug($table, $base, $ignoreId)`: สร้าง slug ไม่ซ้ำในตาราง
- `log_activity($action, $table, $recordId, $description)`: บันทึก activity ลง `activity_logs`
- `notify_user($userId, $title, $message, $type, $relatedId)`: สร้าง notification
- `unread_notifications_count($userId)`: นับแจ้งเตือนที่ยังไม่อ่าน
- `recent_notifications($userId, $limit)`: ดึงแจ้งเตือนล่าสุด

#### Login attempt

- `is_login_blocked($email)`: ตรวจว่าอีเมลโดน block จากการ login ผิดหลายครั้งหรือไม่
- `record_login_attempt($email, $success)`: บันทึก login attempt
- `clear_failed_login_attempts($email)`: ล้าง attempt หลัง login สำเร็จ

#### Photographer helper

- `photographer_profile_by_user($userId)`: ดึง profile ช่างภาพจาก user id
- `photographer_id_for_user($userId)`: คืน photographer profile id ของ user
- `public_image($path, $fallback)`: แปลง path รูปให้แสดงผลได้ ถ้าไม่มีใช้ fallback
- `normalize_local_image_fallback($fallback)`: normalize รูป fallback ใน project
- `favorite_count($photographerId)`: นับจำนวนคนบันทึกช่างภาพ
- `is_favorite_photographer($customerId, $photographerId)`: ตรวจว่าลูกค้าบันทึกช่างภาพไว้ไหม
- `toggle_favorite_photographer($customerId, $photographerId)`: เพิ่ม/ลบรายการโปรด
- `record_recently_viewed($userId, $photographerId)`: บันทึกช่างภาพที่เคยดู
- `photographer_completion_percent($photographerId)`: คำนวณความสมบูรณ์ของโปรไฟล์ช่างภาพ

#### วันที่ พ.ศ. และปฏิทิน

- `time_slot_label($slot)`: แปลง slot เช่น `morning` เป็น `เช้า`
- `parse_be_date_to_iso($value)`: รับวันที่ พ.ศ. เช่น `05/05/2569` แล้วแปลงเป็น `YYYY-MM-DD`
- `format_be_date($value)`: แสดงวันที่เป็น พ.ศ.
- `format_be_datetime($value)`: แสดงวันเวลาเป็น พ.ศ.
- `current_be_year()`: ปี พ.ศ. ปัจจุบัน
- `be_date_input_value($value)`: เตรียม value สำหรับ input วันที่ พ.ศ.
- `be_date_input($name, $value, $classes, $required, $placeholder)`: สร้าง input วันที่แบบ พ.ศ.
- `calendar_date_input($name, $value, $dateStatuses, $required, $label)`: สร้างปฏิทิน custom พร้อมสถานะว่าง/ไม่ว่าง/ถูกจอง/รอตอบรับ

#### Booking / Review

- `booking_status_label($status)`: แปลง status booking เป็นภาษาไทย
- `role_display_name($role)`: แปลง role เป็นภาษาไทย
- `status_badge($status)`: สร้าง badge สถานะพร้อมสีและ icon
- `generate_booking_code()`: สร้างรหัส booking เช่น `CRB...`
- `add_booking_status_log($bookingId, $oldStatus, $newStatus, $changedBy, $note)`: บันทึกประวัติสถานะ booking
- `can_book_slot($photographerId, $date, $slot, $excludeBookingId)`: ตรวจว่าวัน/ช่วงเวลานั้นจองได้ไหม กัน booking ซ้ำ
- `update_photographer_rating($photographerId)`: คำนวณคะแนนเฉลี่ยและจำนวนรีวิวใหม่
- `update_photographer_response_stats($photographerId)`: คำนวณ response rate และเวลาตอบกลับเฉลี่ย

#### Pagination / Search / Report

- `paginate($total, $page, $perPage, $baseUrl)`: pagination แบบ URL ปกติ
- `paginate_clean($total, $page, $perPage, $path, $baseParams)`: pagination แบบ clean context
- `record_search_log($keyword, $districtId, $categoryId)`: เก็บประวัติการค้นหา
- `report_status_label($status)`: แปลงสถานะ report เป็นภาษาไทย

### `includes/security.php`

ไฟล์ตรวจ request และ input ที่น่าสงสัย

- `detectSuspiciousInput($input)`: ตรวจ pattern เช่น XSS script, event handler, SQL union select, path traversal, shell command, PHP wrapper แล้วคืน score/matches
- `scanRequestForThreats()`: scan `GET`, `POST`, `COOKIE` ทั้ง request ถ้าพบ threat จะ log
- `logSecurityEvent($eventType, $context)`: บันทึก security event ลง `activity_logs`
- `scanSecuritySource($sourceName, $value, $path, &$threats)`: recursive scan array input
- `isSensitiveSecurityField($fieldName)`: ข้าม field ลับ เช่น password, token, csrf ไม่เอาไป log preview

### `includes/upload.php`

ไฟล์จัดการ upload รูป

- `upload_image($file, $folder)`: รับไฟล์จาก `$_FILES` แล้วตรวจ upload อย่างละเอียด
  - อนุญาตเฉพาะ folder: avatars, covers, portfolios, reviews, articles, banners
  - อนุญาตเฉพาะ jpg/jpeg/png/webp
  - block นามสกุลเสี่ยง เช่น php, phtml, phar, exe, js, html, svg
  - จำกัด 5MB
  - ตรวจ `is_uploaded_file`
  - ตรวจ MIME ด้วย `finfo_file`
  - ตรวจ binary รูปด้วย `getimagesize`
  - เปลี่ยนชื่อไฟล์เป็น `random_bytes`
  - คืน path เช่น `portfolios/xxxxx.webp`
- `upload_security_reject($reason, $context)`: log เหตุผล upload fail ลง security/activity log

### `includes/csrf.php`

- `csrf_token()`: สร้าง token เก็บใน session
- `csrf_field()`: คืน hidden input สำหรับ form
- `verify_csrf()`: ตรวจ token ใน POST ถ้าไม่ถูกต้องหยุด request

### `includes/flash.php`

- `flash($type, $message)`: เก็บข้อความแจ้งเตือนลง session
- `flashes()`: อ่านและล้าง flash messages เพื่อแสดงผลด้วย SweetAlert/alert

### `includes/header.php`

โหลด HTML head, CSS, Font Awesome, Tailwind, SweetAlert2, DataTables ตามที่หน้าใช้งาน และ include navbar

### `includes/navbar.php`

แถบบนของทุกหน้า

- แสดงโลโก้
- เมนูหลัก: หน้าแรก, ค้นหาช่างภาพ, บทความ, FAQ, ติดต่อเว็บไซต์
- ถ้า login จะแสดงปุ่มเมนูของฉัน, แจ้งเตือน, โปรไฟล์, logout
- กดโปรไฟล์จะกลับ dashboard หลักตาม role

### `includes/sidebar.php`

sidebar สำหรับ dashboard แต่ละ role

- admin: dashboard, สมาชิก, ช่างภาพ, หมวดหมู่, อำเภอ, คำขอจอง, รีวิว, บทความ, FAQ, settings ฯลฯ
- photographer: dashboard, profile, service areas, services, portfolio, availability, bookings, articles, reviews
- customer: dashboard, ค้นหาช่างภาพ, booking, favorites, recently viewed, reviews, reports, profile

### `includes/breadcrumb.php`

สร้าง breadcrumb ภาษาไทยตามหน้าปัจจุบัน ช่วยให้ผู้ใช้รู้ว่าตอนนี้อยู่หน้าไหน

### `includes/footer.php`

footer รวม link สำคัญ หมวดหมู่ยอดนิยม อำเภอยอดนิยม disclaimer และข้อมูลติดต่อ

### `includes/photographer_card.php`

component การ์ดช่างภาพ ใช้ซ้ำในหน้าแรกและหน้าค้นหา

- เตรียมข้อมูลชื่อ รูป พื้นที่ บริการ rating ราคา รีวิว
- ทั้งการ์ดคลิกเพื่อเข้าโปรไฟล์ได้
- ปุ่ม `ดูโปรไฟล์`, `เปรียบเทียบ`, `จอง`
- แสดงราคาเป็น “ราคาเริ่มต้นโดยประมาณ ... บาท”

### `includes/admin_overview.php`

component dashboard สรุปรวมสำหรับหน้า admin ต่าง ๆ ช่วยให้ทุกหน้า admin มีภาพรวมและ shortcut เข้าใจง่าย

### `includes/auth.php`

ไฟล์ bridge สำหรับ include ระบบ auth เดิม

- ภายใน require `includes/functions.php`
- ใช้กรณีไฟล์บางหน้าต้องการ include auth โดยตรง
- ฟังก์ชัน auth จริงอยู่ใน `includes/functions.php` เช่น `current_user`, `requireLogin`, `requireRole`

## 5.1 Assets

### `assets/css/app.css`

ไฟล์ style หลักของระบบ

- ตั้ง font เป็น Inter + Noto Sans Thai
- กำหนดสีหลัก เช่น red/ink/muted/gold/teal
- สร้าง class component เช่น `stock-card`, `stock-button`, `btn-primary`, `btn-cta`, `btn-muted`
- กำหนด layout dashboard/sidebar/navbar/card
- กำหนด style ของ custom calendar พ.ศ.
- ป้องกันปุ่มตัดคำด้วย `white-space: nowrap`
- ทำ responsive สำหรับ mobile/tablet/desktop

### `assets/js/app.js`

ไฟล์ JavaScript หลักของระบบ

- จับปุ่มที่มี `data-confirm` แล้วเปิด SweetAlert2 ก่อน submit
- เปิด/ปิด modal ข้อมูลผู้พัฒนา
- init DataTables ภาษาไทยและ page length 5
- init block pagination ให้บล็อกต่าง ๆ แสดงทีละ 5 รายการโดยไม่ reload ทั้งหน้า
- init clickable card ให้กดการ์ดช่างภาพได้ทั้งใบ แต่ไม่ชนกับปุ่มย่อย
- init custom calendar date input
- แปลงวันที่ พ.ศ. เป็น ISO ก่อน submit form
- ฟังก์ชัน JS สำคัญ:
  - `initClickableCards()`: ทำให้ card ที่มี `data-clickable-card` คลิกได้
  - `initCalendarDateInputs()`: render ปฏิทินรายเดือนและเลือกวันที่
  - `initBlockPagination()`: แบ่งรายการใน block เป็นหน้า ๆ ฝั่ง client
  - `syncAllBeDateInputs()`: sync input วันที่ พ.ศ. ไป hidden ISO date
  - `parseBeDateToIso(value)`: แปลงวันที่ พ.ศ./ค.ศ. หลายรูปแบบเป็น `YYYY-MM-DD`
  - `formatIsoToBeDate(value)`: แปลง ISO เป็น `dd/mm/พ.ศ.`
  - `isValidDate(year, month, day)`: ตรวจวันที่ถูกต้อง
  - `buildIsoDate(year, month, day)`: สร้าง string ISO date

## 6. Public Pages

### `index.php`

หน้าแรกของเว็บ

- ดึงอำเภอ หมวดหมู่ ช่างภาพแนะนำ ช่างภาพคะแนนสูง อำเภอยอดนิยม portfolio ล่าสุด บทความ FAQ รีวิว และ stat
- Hero section มี search box: อำเภอ, ประเภทงาน, วันที่ว่าง
- ใช้ `be_date_input()` เพื่อเลือกวันที่ พ.ศ.
- แสดงคำอธิบายว่าเว็บไม่มีระบบรับชำระเงิน
- แสดงหมวดหมู่ ช่างภาพแนะนำ วิธีใช้งาน อำเภอยอดนิยม ผลงานล่าสุด รีวิว CTA ช่างภาพ บทความ FAQ และ footer

### `photographers.php`

หน้าค้นหาช่างภาพ

- รับ filter ด้วย `clean_context_init`
- filter ได้จากอำเภอ ประเภทงาน วันที่ว่าง คะแนนขั้นต่ำ ราคาเริ่มต้น ชื่อช่างภาพ sort และ page
- ถ้า user เป็น customer และ login แล้วสามารถเข้าได้ปกติ ถ้า guest ก็ดูข้อมูลได้
- ถ้าไม่พบผลลัพธ์ในอำเภอที่เลือก จะค้นหาช่างภาพใกล้เคียงด้วย Haversine จาก latitude/longitude
- Nearby recommendation ยังล็อกตามประเภทงานที่เลือก
- บันทึก search log ด้วย `record_search_log`
- ใช้ `includes/photographer_card.php` แสดงผล

### `photographer_detail.php`

หน้าโปรไฟล์ช่างภาพ

- รับ `id` หรือ `slug` ผ่าน clean context
- ดึงข้อมูล profile, พื้นที่, services, portfolio, availability, articles, reviews, similar photographers
- เพิ่ม `profile_views` ทุกครั้งที่เปิดหน้า
- ถ้า login จะบันทึก recently viewed
- ลูกค้ากด favorite ได้
- ลูกค้ารายงานช่างภาพหรือรีวิวได้
- แสดง cover/profile, ราคาเริ่มต้น, พื้นที่รับงาน, ประเภทงาน, ปฏิทินวันว่าง, ตัวอย่างงานถ่ายภาพ, รีวิว, บทความ, ติดต่อช่างภาพ
- การ์ดวันว่างกดแล้วไป `customer/create_booking.php` พร้อมเลือกวันที่และช่วงเวลาให้อัตโนมัติ

### `register.php`

หน้าสมัครสมาชิก

- เลือก role customer หรือ photographer
- ตรวจชื่อ อีเมล เบอร์ รหัสผ่าน และ confirm password
- ใช้ `password_hash()`
- ถ้าลูกค้า: user status active
- ถ้าช่างภาพ: user status pending และ photographer approval_status pending
- สร้าง profile ช่างภาพเบื้องต้น เช่น display name, district, contact
- ใช้ transaction เพื่อให้ user/profile/service area ไม่หลุดครึ่งทาง

### `login.php`

หน้าเข้าสู่ระบบ

- ตรวจ CSRF
- ตรวจ login attempt ด้วย `is_login_blocked`
- query user จาก email
- ตรวจรหัสผ่านด้วย `password_verify`
- login สำเร็จทำ `session_regenerate_id(true)`
- อัปเดต `last_login_at`
- log activity
- redirect ตาม role/dashboard

### `logout.php`

ออกจากระบบ

- log activity
- ล้าง session ด้วย `clear_auth_session`
- redirect ไป login

### `forgot_password.php`

ขอ reset password

- รับ email
- ถ้ามี user จะสร้าง token เก็บใน `password_resets`
- มี link สำหรับ development ให้ทดสอบได้
- ไม่เปิดเผยว่า email มีในระบบหรือไม่มากเกินไป

### `reset_password.php`

ตั้งรหัสผ่านใหม่

- รับ token ผ่าน clean context
- ตรวจ token และ `expires_at`
- ตั้ง password ใหม่ด้วย `password_hash`
- ลบ token หลังใช้

### `blog.php`

หน้ารวมบทความ

- รวมบทความจาก admin (`blogs`) และบทความจากช่างภาพ (`photographer_articles`)
- filter แหล่งบทความ: ทั้งหมด/จากระบบ/จากช่างภาพ
- ค้นหาด้วยชื่อ ผู้เขียน ประเภท
- แบ่งหน้า pagination
- ใช้คำว่า “บทความ” ให้เหมือนกันทั้งระบบ

### `blog_detail.php`

รายละเอียดบทความจากระบบ

- รับ slug ผ่าน clean context
- แสดง cover, title, excerpt, content, published date

### `article_detail.php`

รายละเอียดบทความจากช่างภาพ

- รับ slug ผ่าน clean context
- แสดงข้อมูลบทความและผู้เขียน
- เชื่อมกลับไปโปรไฟล์ช่างภาพได้

### `faq.php`

หน้า FAQ

- ดึง `faqs` ที่ active
- ค้นหาคำถาม/คำตอบ
- filter หมวดหมู่
- แสดงแบบ accordion
- อธิบายว่า FAQ รวบรวมและจัดหมวดโดย admin

### `contact.php`

หน้าติดต่อเว็บไซต์

- แสดงข้อมูลผู้พัฒนาและผู้ช่วยพัฒนา
- มี modal link GitHub
- ฟอร์มชื่อ อีเมล เบอร์ หัวข้อ ข้อความ
- บันทึกลง `contact_messages`
- log activity

### `about.php`

หน้าเกี่ยวกับระบบ

- อธิบายเป้าหมายของแพลตฟอร์ม
- จุดเด่น ระบบช่วยช่างภาพท้องถิ่นอย่างไร
- CTA สมัครเป็นช่างภาพ

### `compare.php`

หน้าเปรียบเทียบช่างภาพ

- รับ ids ผ่าน clean context
- ดึงช่างภาพ 2-3 คนมาเทียบ
- แสดงรูป ชื่อ คะแนน รีวิว ราคา พื้นที่ ประเภทงาน จำนวน portfolio และช่องทางติดต่อ

### `notifications.php`

หน้ารวมแจ้งเตือน

- redirect ไป `customer/notifications.php` ถ้าเป็น customer
- ถ้า role อื่นใช้หน้ากลางเพื่อดู/mark all read

### `terms.php`, `privacy.php`

หน้าเงื่อนไขการใช้งานและนโยบายความเป็นส่วนตัว

- ระบุว่าเว็บเป็นตัวกลาง
- การตกลงราคาและชำระเงินอยู่นอกระบบ
- อธิบายข้อมูลที่เก็บ เช่น booking, review, activity log

### `sitemap.php`

HTML sitemap

- รวม link เมนูหลัก หมวดหมู่ อำเภอ บทความ ช่างภาพแนะนำ
- ช่วยทั้ง UX และ SEO

## 7. Customer Pages

### `customer/dashboard.php`

dashboard ลูกค้า

- ต้อง login เป็น customer
- แสดง welcome banner
- stat คำขอจอง pending/accepted/completed ฯลฯ
- ปุ่ม stat เป็น link ไป booking filter
- รายการจองล่าสุดเน้นงานที่ยังไม่เสร็จ
- แสดง recommended photographers, quick actions, review reminder, recently viewed

### `customer/bookings.php`

ประวัติคำขอจองของลูกค้า

- แยก tab `กำลังดำเนินการ` และ `เสร็จสิ้นแล้ว`
- แสดง booking code, ช่างภาพ, ประเภทงาน, วันที่, ช่วงเวลา, อำเภอ, สถานะ
- ถ้า completed และยังไม่รีวิว จะแสดงปุ่มรีวิว

### `customer/booking_detail.php`

รายละเอียด booking ของลูกค้า

- ตรวจว่า booking เป็นของ customer คนนั้น
- แสดงข้อมูลช่างภาพ ช่องทางติดต่อ รายละเอียดงาน สถานะ
- แสดง timeline จาก `booking_status_logs`
- ลูกค้ากดยกเลิกได้ถ้ายังไม่ completed/cancelled

### `customer/create_booking.php`

สร้างคำขอจอง

- ต้อง login customer
- รับ `photographer_id`, `booking_date`, `time_slot` ผ่าน clean context
- ถ้ามาจากปฏิทินวันว่าง ระบบ preselect วันที่และช่วงเวลาให้
- ตรวจช่างภาพ approved/available
- ตรวจวันที่ว่างจาก `photographer_availability`
- ตรวจ booking ซ้ำด้วย `can_book_slot`
- insert `bookings`
- insert `booking_status_logs`
- สร้าง notification ให้ช่างภาพ
- log activity

### `customer/review.php`

รีวิวช่างภาพ

- review ได้เฉพาะ booking ของตัวเอง
- booking ต้อง `completed`
- 1 booking review ได้ 1 ครั้ง
- rating เป็นจำนวนเต็ม 1-5
- upload review images optional
- หลังบันทึก update rating ของช่างภาพด้วย `update_photographer_rating`

### `customer/profile.php`

แก้ไขโปรไฟล์ลูกค้า

- แก้ชื่อ อีเมล เบอร์ รูปโปรไฟล์ และ password
- upload avatar ผ่าน `upload_image`
- ตรวจอีเมลซ้ำ

### `customer/favorites.php`

รายการโปรด

- แสดงช่างภาพที่ customer กดบันทึกไว้
- ลบรายการโปรดได้

### `customer/recently_viewed.php`

ช่างภาพที่เคยดู

- อ่านจาก `recently_viewed_photographers`
- เก็บเมื่อเปิด `photographer_detail.php` ขณะ login

### `customer/reviews.php`

รีวิวของฉัน

- แสดงรีวิวที่ customer เคยเขียน
- ใช้อธิบายย้อนหลังตอนนำเสนอว่า review ผูกกับ booking completed

### `customer/reports.php`

รายงานปัญหาของฉัน

- แสดง report ที่ customer เคยส่ง
- ฟังก์ชันในไฟล์:
  - `customer_report_target_label($report)`: แสดงเป้าหมาย report เช่น ช่างภาพ/รีวิว
  - `customer_report_type_label($type)`: แปลง target type เป็นภาษาไทย

### `customer/notifications.php`

แจ้งเตือนลูกค้า

- แสดงแจ้งเตือนทั้งหมด/ยังไม่อ่าน
- mark all as read ได้

### `customer/photographers.php`

ทางลัดไปหน้าค้นหาช่างภาพ หรือ wrapper สำหรับฝั่ง customer

## 8. Photographer Pages

### `photographer/dashboard.php`

dashboard ช่างภาพ

- แสดงกราฟคำขอจอง 12 เดือน
- แยกสถานะ booking เช่น กำลังดำเนินการ เสร็จสิ้น ยกเลิก/ปฏิเสธ
- แสดง profile completion, stats, upcoming/pending bookings, calendar preview, latest reviews, portfolio performance, quick actions

### `photographer/profile.php`

จัดการโปรไฟล์ช่างภาพ

- แก้ display name, bio, experience, starting price, contact, social links, is_available
- upload profile image และ cover image
- แสดงหัวข้อชัดว่า upload อะไร
- log activity

### `photographer/service_areas.php`

จัดการพื้นที่รับงาน

- เลือกอำเภอได้หลายอำเภอ
- มีเลือกทั้งหมด/รับงานทุกอำเภอ
- กำหนด main district / primary area
- ใช้ transaction update service areas

### `photographer/services.php`

จัดการประเภทงาน

- เพิ่มประเภทงานที่รับจาก `service_categories`
- ใส่รายละเอียดบริการและราคาเริ่มต้น
- เปิด/ปิดประเภทงานได้
- ลบ/แก้ไข service ได้
- แนะนำความยาวรายละเอียด 300-500 ตัวอักษร

### `photographer/portfolio.php`

จัดการตัวอย่างงานถ่ายภาพ

- upload รูป portfolio
- ตั้ง title, description, featured, sort order
- ลบแบบ soft delete และ unlink ไฟล์จริงถ้าเป็นไฟล์ local ไม่ใช่ seed/shared
- ตรวจ upload ผ่าน `upload_image`

### `photographer/availability.php`

จัดการวันว่าง

- เพิ่มวันว่างตามวันที่ พ.ศ. และช่วงเวลา
- status: available, unavailable, booked
- ป้องกันวันที่+ช่วงเวลาซ้ำ
- ลบรายการวันว่างได้

### `photographer/bookings.php`

จัดการคำขอจอง

- filter/tab กำลังดำเนินการและเสร็จสิ้นแล้ว
- ช่างภาพตอบรับ ปฏิเสธ พร้อมเหตุผล confirmed completed ได้ตาม business flow
- ทุกการเปลี่ยน status บันทึก `booking_status_logs`
- update response stats
- สร้าง notification ให้ลูกค้า

### `photographer/booking_detail.php`

รายละเอียด booking ฝั่งช่างภาพ

- ตรวจว่า booking เป็นของช่างภาพคนนั้น
- แสดงข้อมูลลูกค้า รายละเอียดงาน วันที่ อำเภอ ช่องทางติดต่อ
- แสดง timeline status logs ชัดเจน

### `photographer/articles.php`

จัดการบทความของช่างภาพ

- เพิ่ม/แก้ไข/ลบ soft delete
- ใช้ editor สำหรับ content
- มี title, slug, cover image, content, status, published_at
- sort ใหม่/เก่า

### `photographer/reviews.php`

รีวิวฝั่งช่างภาพ

- แสดงคะแนนเฉลี่ยและ rating breakdown เป็นกราฟ
- แสดงรีวิว รายละเอียด รูปรีวิว
- ช่างภาพรายงานรีวิวไม่เหมาะสมได้

### `photographer/onboarding.php`

หน้าเริ่มต้นหลังสมัครช่างภาพ

- แนะนำ step ให้ทำโปรไฟล์ครบ
- เพิ่มรูปโปรไฟล์ ช่องทางติดต่อ พื้นที่ ประเภทงาน portfolio วันว่าง
- ใช้ profile completion percent เป็นตัวนำ

## 9. Admin Pages

### `admin/dashboard.php`

dashboard ผู้ดูแลระบบ

- แสดง stat สมาชิก ช่างภาพ booking review article report
- chart booking รายเดือน
- chart ช่างภาพตามอำเภอ
- pending photographer approval
- recent bookings, recent reviews, recent activity logs
- ฟังก์ชัน `dashboard_activity_action_label($action)` แปลง action log เป็นภาษาไทย

### `admin/activity_logs.php`

หน้า Activity Log แบบ dashboard

- filter ผู้ใช้ action ตาราง วันที่ คำค้น
- แสดงกราฟ/summary activity
- อธิบายว่าเก็บจาก `activity_logs` ผ่าน `log_activity`
- ฟังก์ชัน:
  - `activity_action_label($action)`: แปลง action เป็นภาษาไทย
  - `activity_table_label($table)`: แปลง table name เป็นชื่อไทย
  - `activity_icon($action)`: เลือก icon ตามประเภท action
  - `activity_color_class($action)`: เลือกสี card ตาม action

### `admin/users.php`

จัดการสมาชิก

- filter role/status/search
- activate, suspend, soft delete
- แสดงลำดับแทน id จริง
- log activity ทุก action

### `admin/photographers.php`

จัดการช่างภาพ

- filter pending/approved/rejected/suspended
- approve, reject พร้อมเหตุผล, suspend, activate
- verify/unverify badge
- feature/unfeature ช่างภาพแนะนำ
- แจ้งเตือนช่างภาพเมื่ออนุมัติ/ระงับ

### `admin/categories.php`

จัดการหมวดหมู่งาน

- เพิ่ม/แก้ไข/ลบ
- เปิด/ปิด active
- icon Font Awesome, sort order
- UI แสดง `เปิดใช้งาน/ปิดใช้งาน` แทน 0/1

### `admin/districts.php`

จัดการอำเภอ

- เพิ่ม/แก้ไข latitude/longitude
- เปิด/ปิดใช้งาน
- พิกัดใช้กับ Haversine recommendation

### `admin/bookings.php`

จัดการ booking

- filter status, photographer, customer, date, tab
- ดูรายละเอียดและประวัติ status
- เปลี่ยนสถานะได้ผ่าน modal confirm
- บันทึก `booking_status_logs`, activity log และ notification

### `admin/reviews.php`

จัดการรีวิว

- filter rating/status/search
- hide/show/delete soft delete
- admin ซ่อนรีวิวไม่เหมาะสมได้
- log activity

### `admin/articles.php`

จัดการบทความช่างภาพ

- publish/hide/delete
- filter q/status
- ใช้ตรวจบทความที่ช่างภาพเขียน

### `admin/blogs.php`

จัดการบทความระบบ

- เพิ่ม/แก้ไข/delete/status
- upload cover
- tag บทความด้วย `save_blog_tags($blogId, $tagText)`
- ฟังก์ชัน `save_blog_tags`: แยกข้อความ tag, สร้าง tag ถ้ายังไม่มี, ผูกกับ `blog_tags`

### `admin/banners.php`

จัดการแบนเนอร์

- เพิ่มรูป แก้ title/subtitle/button url
- เปิด/ปิด sort order
- ใช้จัดภาพโปรโมตหน้าแรก

### `admin/faqs.php`

จัดการ FAQ

- เพิ่ม/แก้ไข/ลบ
- เปิด/ปิดแสดงผล
- category และ sort order

### `admin/contact_messages.php`

ดูข้อความติดต่อเว็บไซต์

- filter q/status/date
- mark unread/read/replied
- ฟังก์ชัน:
  - `contact_message_status_label($status)`: แปลง status เป็นไทย
  - `contact_message_status_badge($status)`: สร้าง badge สถานะ

### `admin/reports_moderation.php`

จัดการรายงานปัญหา

- filter status
- ตรวจ report จาก users
- เปลี่ยนสถานะ pending/reviewed/resolved/rejected
- เก็บ admin note

### `admin/reports.php`

หน้ารายงานเชิงสรุป

- สรุป users, photographers, bookings, top categories, top districts, top rated photographers, average review

### `admin/settings.php`

ตั้งค่าระบบ

- site name, logo, footer text, admin contact
- allow photographer registration
- nearby radius km

## 10. Flow สำคัญสำหรับนำเสนอ

### สมัครช่างภาพ

1. เปิด `register.php`
2. เลือก role ช่างภาพ
3. ระบบสร้าง `users.status = pending`
4. สร้าง `photographer_profiles.approval_status = pending`
5. admin เปิด `admin/photographers.php`
6. กด approve
7. ระบบเปลี่ยนเป็น approved และสร้าง notification

### ลูกค้าค้นหาและจอง

1. ลูกค้า/guest เปิด `photographers.php`
2. เลือกอำเภอ ประเภทงาน วันที่ คะแนน ราคา หรือชื่อ
3. ระบบ query ช่างภาพ approved/active
4. ถ้าไม่พบในอำเภอ ระบบคำนวณใกล้เคียงด้วย Haversine
5. ลูกค้าเปิด `photographer_detail.php`
6. กดวันว่าง หรือกดจอง
7. เข้าหน้า `customer/create_booking.php`
8. ระบบตรวจ availability และ booking ซ้ำ
9. insert booking status pending
10. log status และ notify ช่างภาพ

### ช่างภาพตอบรับ/ปฏิเสธ

1. ช่างภาพเปิด `photographer/bookings.php`
2. เลือก booking
3. กด accepted/rejected/confirmed/completed
4. ถ้า rejected ต้องมีเหตุผล
5. ระบบ update booking
6. insert `booking_status_logs`
7. update response stats
8. notify ลูกค้า

### รีวิว

1. booking ต้อง `completed`
2. ลูกค้าเปิด `customer/review.php`
3. ระบบตรวจว่า booking เป็นของลูกค้าและยังไม่เคยรีวิว
4. insert `reviews`
5. optional upload `review_images`
6. update average rating และ total reviews
7. notify ช่างภาพ

### รายงานปัญหา

1. ลูกค้าหรือช่างภาพกดรายงานช่างภาพ/รีวิว
2. ระบบ insert `reports`
3. admin เปิด `admin/reports_moderation.php`
4. ตรวจและเปลี่ยนสถานะ report

### Activity Log

1. ทุก action สำคัญเรียก `log_activity`
2. เก็บ user_id, action, table_name, record_id, ip, user_agent, description, created_at
3. admin เปิด `admin/activity_logs.php`
4. ใช้ดูย้อนหลังว่าใครทำอะไรในระบบ

## 11. Security ที่ควรพูดตอนนำเสนอ

- Password ใช้ `password_hash()` และ `password_verify()`
- SQL ใช้ PDO prepared statement
- Output ใช้ `h()` หรือ `htmlspecialchars()`
- Form สำคัญมี CSRF token
- Upload ตรวจ extension, MIME, getimagesize, size 5MB และ random filename
- Login attempt จำกัดด้วย `login_attempts`
- Session ตั้ง `httponly`, `samesite`, `secure` เมื่อ HTTPS
- Role-based access ใช้ `requireRole`
- Booking ownership ตรวจทุกหน้า detail
- Review ทำได้เฉพาะ booking completed และ 1 booking ต่อ 1 review
- Direct access ไฟล์สำคัญถูกคุมด้วยโครงสร้าง include/role
- Activity/security events บันทึกลง log

## 12. จุดที่จารย์อาจถามและคำตอบสั้น

- ทำไมไม่มีระบบชำระเงิน: เพราะ requirement กำหนดให้เว็บเป็นตัวกลางค้นหา/ติดต่อเท่านั้น ลดความซับซ้อนเรื่องกฎหมายและการเงิน
- ทำไมต้องอนุมัติช่างภาพ: เพื่อคุมคุณภาพและป้องกันโปรไฟล์ปลอมก่อนแสดง public
- ถ้าเลือกอำเภอแล้วไม่พบทำอย่างไร: ใช้ Haversine คำนวณระยะประมาณจากพิกัดอำเภอ และแนะนำช่างภาพใกล้เคียงตามประเภทงานที่เลือก
- ทำไมวันที่เป็น พ.ศ.: ผู้ใช้ไทยอ่านง่ายกว่า จึงแปลง input/output ด้วย helper วันที่
- ป้องกันจองซ้ำอย่างไร: `can_book_slot()` ตรวจ booking เดิมใน photographer/date/time_slot ก่อน insert
- ลูกค้ารีวิวมั่วได้ไหม: ไม่ได้ ต้องเป็น booking ของลูกค้าเองและ status completed
- รูป upload ปลอดภัยอย่างไร: ตรวจ extension, MIME, binary image, ขนาด, folder whitelist และเปลี่ยนชื่อไฟล์ใหม่
- URL ไม่โชว์ id/slug อย่างไร: ใช้ clean context เก็บ params ใน session แล้ว redirect ไป path สะอาด

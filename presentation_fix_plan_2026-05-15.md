# Presentation Fix Plan 15-05-2026

เอกสารนี้ใช้สรุปแผนแก้ไขและสถานะงานก่อนพรีเซนต์วันที่ 15-05-2026 โดยอ้างอิงจาก checklist feedback ของอาจารย์

## สรุปสถานะรวม

- [x] ทำแผนแก้ไขเป็นไฟล์ `presentation_fix_plan_2026-05-15.md`
- [x] ไล่ทวนโค้ดหน้าหลัก หน้าโปรไฟล์ ลูกค้า ช่างภาพ บทความ notification และ flow การจอง
- [x] แก้โค้ดตาม checklist หลักแล้ว
- [x] ใช้ภาษาไทยให้สม่ำเสมอมากขึ้น
- [x] เพิ่ม automation ให้วันว่างเปลี่ยนตามสถานะการจอง
- [x] ทดสอบ syntax ด้วย PHP 7.4 ผ่าน Docker
- [x] ทดสอบ runtime หน้า public หลัก ไม่มี fatal/parse/PDO error

## 1. หน้าหลัก / เลือกช่างภาพ

### 1.1 ทำปุ่มให้มันอยู่เท่า ๆ กัน ตรงช่องเลือกช่างภาพ

- [x] ทำแล้ว

วิธีแก้:
- ปรับปุ่มในการ์ดช่างภาพให้มีความสูงขั้นต่ำเท่ากัน
- ใช้ `min-h-[44px]`, `w-full`, `auto-rows-fr`
- ลดปุ่มจองจาก `btn-md` เป็น `btn-sm` เพื่อให้ขนาดบาลานซ์กับปุ่มอื่น

ไฟล์ที่เกี่ยวข้อง:
- `includes/photographer_card.php`

### 1.2 ถ้าช่างภาพคะแนนเท่ากัน ใครควรขึ้นก่อน จะดึงขึ้นมายังไง

- [x] ทำแล้ว

หลักการจัดอันดับเมื่อคะแนนเท่ากัน:
1. คะแนนเฉลี่ยสูงสุด
2. จำนวนรีวิวมากกว่า
3. จำนวนงานที่เสร็จสิ้นมากกว่า
4. อัตราการตอบกลับสูงกว่า
5. โปรไฟล์ยืนยันแล้วขึ้นก่อน
6. จำนวนการดูโปรไฟล์มากกว่า
7. id น้อยกว่าขึ้นก่อน เพื่อให้ผลลัพธ์นิ่ง

SQL order ที่ใช้:

```sql
average_rating DESC,
total_reviews DESC,
completed_bookings DESC,
response_rate DESC,
is_verified DESC,
profile_views DESC,
id ASC
```

วิธีแก้:
- เพิ่ม helper กลาง `ranking_order_sql()`
- ใช้ helper นี้กับหน้าแรก หน้าค้นหาช่างภาพ และช่างภาพใกล้เคียง

ไฟล์ที่เกี่ยวข้อง:
- `includes/functions.php`
- `index.php`
- `photographers.php`
- `photographer_detail.php`

### 1.3 ปฏิทินวันว่าง ไม่จำเป็นต้องบอกวันว่าง จะทำให้สารสนเทศฟุ่มเฟือย

- [x] ทำแล้ว

วิธีแก้:
- หน้า public profile ไม่แสดงวัน/slot ที่ไม่พร้อมจองแล้ว
- แสดงเฉพาะ slot ที่ `available` และไม่มี booking ที่ชนอยู่
- ตัดการแสดง status ที่ไม่จำเป็นออกจาก card วันว่าง
- เหลือเฉพาะวันที่ เวลา และปุ่ม `เลือกวันนี้และจอง`

ไฟล์ที่เกี่ยวข้อง:
- `photographer_detail.php`
- `customer/create_booking.php`
- `includes/functions.php`
- `assets/js/app.js`

### 1.4 ถ้ากดจองแบบที่ไม่ได้ล็อคอิน จะให้เด้งไปหน้าที่ขอจองเลย

- [x] ทำแล้ว

วิธีแก้:
- เพิ่มระบบจำหน้าเดิมก่อน redirect login ด้วย `$_SESSION['intended_url']`
- ถ้าผู้ใช้ยังไม่ login แล้วกดจอง ระบบพาไป `login.php`
- หลัง login สำเร็จ ระบบ redirect กลับไป URL เดิม เช่น `customer/create_booking.php?photographer_id=...&booking_date=...&time_slot=...`

ไฟล์ที่เกี่ยวข้อง:
- `includes/functions.php`
- `login.php`
- `customer/create_booking.php`

### 1.5 ถ้ากดวันที่ขอจองในหน้าโปรไฟล์ ต้อง fix ให้ตรงกับที่กด ไม่ให้เปลี่ยนค่า

- [x] ทำแล้ว

วิธีแก้:
- ถ้ามาจากหน้าโปรไฟล์พร้อม `booking_date` และ `time_slot`
- ระบบตรวจว่า slot นั้นยังจองได้จริงด้วย `can_book_slot()`
- ถ้าจองได้ จะ lock วันที่และช่วงเวลาเป็น hidden input
- แสดงกล่องสีเขียวพร้อม icon lock เพื่อบอกว่าล็อกจากหน้าโปรไฟล์แล้ว

ไฟล์ที่เกี่ยวข้อง:
- `customer/create_booking.php`
- `includes/functions.php`

### 1.6 ในหน้าโปรไฟล์ช่างภาพ ใส่ปุ่มส่งคำขอจองมาทำไม

- [x] ทำแล้ว

วิธีแก้:
- ตัดปุ่มส่งคำขอจองแบบกว้าง ๆ ออกจากหน้าโปรไฟล์ช่างภาพ
- เปลี่ยนปุ่มด้านบนเป็น `เลือกวันว่างเพื่อจอง`
- การจองจริงให้เริ่มจาก slot ที่ว่างเท่านั้น
- ในส่วนช่องทางติดต่อ ไม่ใส่ปุ่มส่งคำขอจองซ้ำแล้ว

ไฟล์ที่เกี่ยวข้อง:
- `photographer_detail.php`

## 2. หน้าโปรไฟล์ลูกค้า / หน้าโปรไฟล์ช่างภาพ

### 2.1 รหัสผ่านใหม่ (ไม่บังคับ) อาจารย์งงว่าใส่ ไม่บังคับมาทำไม

- [x] ทำแล้ว

วิธีแก้:
- เปลี่ยน wording จาก `รหัสผ่านใหม่ (ไม่บังคับ)`
- เป็น section ชัดเจนชื่อ `เปลี่ยนรหัสผ่าน`
- ใส่ข้อความอธิบายว่า `เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน`

ไฟล์ที่เกี่ยวข้อง:
- `customer/profile.php`

### 2.2 ติด * แดง เพื่อบอกว่าช่องไหนสำคัญ

- [x] ทำแล้ว

วิธีแก้:
- เพิ่ม helper `required_mark()` สำหรับแสดง `*` สีแดง
- ใส่ในช่องสำคัญ เช่น ชื่อผู้ใช้ อีเมล ชื่อช่างภาพ ราคาเริ่มต้น
- เพิ่ม validation ฝั่ง server ให้ตรงกับช่อง required

ไฟล์ที่เกี่ยวข้อง:
- `includes/functions.php`
- `customer/profile.php`
- `photographer/profile.php`

### 2.3 ถ้ามีโพสต์มาใหม่ ควรให้เห็นว่ามันขึ้นว่าใหม่ด้วย ระยะให้การแสดงเท่าไหร่

- [x] ทำแล้ว

กติกา:
- แสดง badge `ใหม่` เป็นเวลา 7 วัน
- ใช้วันที่ `published_at`
- ถ้าไม่มี `published_at` ใช้ `created_at`

วิธีแก้:
- เพิ่ม helper `is_new_content($date, $days = 7)`
- เพิ่ม helper `new_content_badge($date, $days = 7)`
- ใช้กับบทความบนหน้า blog, article detail, blog detail, รายการบทความช่างภาพ และบทความในหน้าโปรไฟล์ช่างภาพ

ไฟล์ที่เกี่ยวข้อง:
- `includes/functions.php`
- `blog.php`
- `blog_detail.php`
- `article_detail.php`
- `photographer/articles.php`
- `photographer_detail.php`

## 3. ฝั่งช่างภาพ

### 3.1 ทำให้ระบบมัน auto ด้วย เช่น วันว่าง

- [x] ทำแล้ว

วิธีแก้:
- เพิ่ม helper `sync_availability_after_booking_status($bookingId)`
- เมื่อคำขอจองเปลี่ยนเป็น `accepted` หรือ `confirmed` ระบบเปลี่ยน slot เป็น `booked`
- เมื่อคำขอจองเป็น `rejected` หรือ `cancelled` ระบบคืน slot เป็น `available` ถ้าไม่มี booking อื่นชน

ไฟล์ที่เกี่ยวข้อง:
- `includes/functions.php`
- `photographer/bookings.php`
- `customer/booking_detail.php`

### 3.2 หน้าบอร์ดของช่างภาพ ขอให้สถานะขึ้นตรงเมนู แบบไฮไลท์สีหรือมีเลขขึ้น

- [x] ทำแล้ว

วิธีแก้:
- เพิ่ม badge จำนวนใน sidebar ของช่างภาพ
- เมนู `คำขอจอง` แสดงจำนวนคำขอใหม่หรือรายการที่กำลังดำเนินการ
- เมนู `แจ้งเตือน` แสดงจำนวน notification ที่ยังไม่อ่าน

ไฟล์ที่เกี่ยวข้อง:
- `includes/sidebar.php`
- `assets/css/app.css`

### 3.3 Notification ทำให้กดไปยังหน้าได้ด้วย

- [x] ทำแล้ว

วิธีแก้:
- เพิ่ม helper `notification_target_url($notification, $user)`
- notification type `booking` จะพาไปหน้ารายละเอียด booking ตาม role
  - ลูกค้าไป `customer/booking_detail.php`
  - ช่างภาพไป `photographer/booking_detail.php`
  - admin ไป `admin/bookings.php`
- รายการ notification เปลี่ยนจาก card ธรรมดาเป็น link/card ที่กดได้

ไฟล์ที่เกี่ยวข้อง:
- `includes/functions.php`
- `notifications.php`
- `customer/notifications.php`

### 3.4 สถานะ ทำไมไม่ใช้คำเดียวกัน

- [x] ทำแล้ว

วิธีแก้:
- ปรับ label หลักใน `booking_status_label()`
- ใช้คำไทยชุดเดียวกัน เช่น
  - `pending` = รอการตอบรับ
  - `accepted` = ตอบรับแล้ว
  - `confirmed` = ยืนยันงาน
  - `completed` = เสร็จสิ้น
  - `rejected` = ปฏิเสธ
  - `cancelled` = ยกเลิกโดยลูกค้า

ไฟล์ที่เกี่ยวข้อง:
- `includes/functions.php`
- `photographer/bookings.php`
- `customer/bookings.php`
- `photographer/dashboard.php`
- `customer/booking_detail.php`
- `photographer/booking_detail.php`

### 3.5 ไทยบ้าง อังกฤษบ้าง ไปแก้ให้ใช้ภาษาใดภาษาหนึ่ง

- [x] ทำแล้ว

วิธีแก้:
- ปรับคำอังกฤษที่หลุดใน dashboard/หน้าค้นหา เช่น
  - `Calendar Preview` เป็น `ปฏิทินวันว่าง`
  - `Status Timeline` เป็น `ประวัติสถานะ`
  - `System` เป็น `ระบบ`
  - `completed` เป็น `งานเสร็จสิ้น`
  - `Nearby recommendation` เป็น `ช่างภาพใกล้เคียง`

ไฟล์ที่เกี่ยวข้อง:
- `photographer/dashboard.php`
- `customer/booking_detail.php`
- `photographer/booking_detail.php`
- `photographers.php`

### 3.6 ปฏิเสธก็ปฏิเสธ ยกเลิกก็คือยกเลิก หลังจากคุยกับลูกค้าแล้วลูกค้ายกเลิก ใส่เพิ่มมาด้วย

- [x] ทำแล้ว

วิธีแก้:
- เพิ่มตัวเลือก `cancelled` ในฝั่งช่างภาพ
- ใช้คำว่า `ยกเลิกโดยลูกค้า`
- ช่องเหตุผลรองรับทั้งปฏิเสธและยกเลิก
- `rejected` ยังใช้ความหมายปฏิเสธจากช่างภาพ
- `cancelled` ใช้กรณีลูกค้ายกเลิกหลังคุยกันแล้ว

ไฟล์ที่เกี่ยวข้อง:
- `photographer/bookings.php`
- `includes/functions.php`

### 3.7 วันว่าง ป้อนย้อนหลังไม่ได้ โดยระบบ ไปแก้ให้เลือกย้อนไม่ได้

- [x] ทำแล้ว

วิธีแก้:
- ฝั่ง server ตรวจ `available_date < date('Y-m-d')` แล้วไม่ให้บันทึก
- ฝั่ง JavaScript ปิดปุ่มวันย้อนหลังใน custom calendar
- `can_book_slot()` ไม่รับวันที่ย้อนหลัง

ไฟล์ที่เกี่ยวข้อง:
- `photographer/availability.php`
- `includes/functions.php`
- `assets/js/app.js`
- `assets/css/app.css`

### 3.8 ถ้าถูกจองแล้ว ควรให้ระบบเปลี่ยนให้เลย ไม่ต้องให้ช่างภาพมาเปลี่ยนเอง

- [x] ทำแล้ว

วิธีแก้:
- เมื่อ booking เป็น `accepted` หรือ `confirmed`
- ระบบ upsert slot ใน `photographer_availability` เป็น `booked`
- ช่างภาพไม่ต้องเลือกสถานะ `booked` เอง

ไฟล์ที่เกี่ยวข้อง:
- `includes/functions.php`
- `photographer/bookings.php`

### 3.9 ในหน้าช่างภาพ ตรงช่องปฏิทินวันว่าง อันที่ถูกจองแล้ว เอาให้หายไป

- [x] ทำแล้ว

วิธีแก้:
- หน้า `photographer/availability.php` ไม่แสดงรายการที่ `status = booked`
- dashboard ช่างภาพแสดงเฉพาะ slot ที่ `available`
- หน้า public profile แสดงเฉพาะ slot ที่จองได้จริง

ไฟล์ที่เกี่ยวข้อง:
- `photographer/availability.php`
- `photographer/dashboard.php`
- `photographer_detail.php`

## 4. หน้าบทความ

### 4.1 ถ้าแท็กเยอะ ๆ จะเป็นยังไง

- [x] ทำแล้ว

วิธีแก้:
- แสดง tag สูงสุด 4 ตัว
- ถ้ามีมากกว่า 4 ตัว แสดง `+N`
- ทำให้ card ไม่ยาวเกินหรือดัน layout พัง

ไฟล์ที่เกี่ยวข้อง:
- `blog.php`
- `blog_detail.php`
- `article_detail.php`
- `admin/blogs.php`

### 4.2 ใส่วันที่มาในการ์ดด้วย

- [x] ทำแล้ว

วิธีแก้:
- เพิ่มวันที่เผยแพร่/วันที่สร้างบนการ์ดบทความ
- ใช้ `format_be_datetime()`

ไฟล์ที่เกี่ยวข้อง:
- `blog.php`
- `photographer_detail.php`

### 4.3 ใส่ให้รีพอร์ตมาด้วย เผื่อมันไม่ดูดี/ผิด

- [x] ทำแล้ว

วิธีแก้:
- เพิ่มฟอร์มรายงานบทความในหน้ารายละเอียดบทความ
- บันทึกลงตาราง `reports`
- ใช้ `target_type = article`
- ตรวจ required, maxlength และ CSRF
- ถ้ายังไม่ login ระบบพาไป login ก่อน

ไฟล์ที่เกี่ยวข้อง:
- `blog_detail.php`
- `article_detail.php`
- `includes/functions.php`

### 4.4 ลองเทสที่ป้อนข้อมูลเยอะ ๆ แล้วมาดูหน้าการ์ดว่ามันจะตัดไหม

- [x] ทำแล้ว

วิธีแก้:
- ใช้ `line-clamp-3` กับเนื้อหาใน card
- จำกัดจำนวน tag ที่แสดง
- ปุ่มอ่านบทความอยู่ด้านล่าง card ด้วย `mt-auto`
- ทดสอบ runtime หน้า blog แล้วไม่มี fatal error

ไฟล์ที่เกี่ยวข้อง:
- `blog.php`
- `assets/css/app.css`

## 5. Helper / Function ที่เพิ่ม

ไฟล์: `includes/functions.php`

- [x] `redirect_with_intended($path)`
  - จำ URL เดิมก่อนเด้งไป login

- [x] `required_mark()`
  - แสดง `*` สีแดงสำหรับช่องจำเป็น

- [x] `text_length($value)`
  - ตรวจความยาวแบบรองรับ `mb_strlen` ถ้ามี

- [x] `is_new_content($date, $days = 7)`
  - ตรวจว่า content ใหม่ในช่วงกี่วัน

- [x] `new_content_badge($date, $days = 7)`
  - สร้าง badge `ใหม่`

- [x] `ranking_order_sql($alias = 'p')`
  - SQL สำหรับจัดอันดับช่างภาพกรณีคะแนนเท่ากัน

- [x] `notification_target_url($notification, $user)`
  - สร้าง URL ปลายทางของ notification ตาม role

- [x] `sync_availability_after_booking_status($bookingId)`
  - sync วันว่างตามสถานะ booking

## 6. Checklist ทดสอบที่ทำแล้ว

- [x] รัน `php -l` ทุกไฟล์ PHP ที่แก้ ผ่าน Docker PHP 7.4
- [x] รัน `git diff --check` ผ่าน
- [x] เปิดหน้า `/` ได้ HTTP 200
- [x] เปิดหน้า `/photographers.php` ได้ HTTP 200
- [x] เปิดหน้า `/blog.php` ได้ HTTP 200
- [x] เปิดหน้า `/photographer_detail.php?id=1` ได้ HTTP 200
- [x] เปิดหน้า `/article_detail.php?slug=prepare-prewedding-chiangrai` ได้ HTTP 200
- [x] เปิดหน้า `/blog_detail.php?slug=choose-right-photographer` ได้ HTTP 200
- [x] ทดสอบ flow ยังไม่ login แล้วกดจอง ระบบเด้งไป login
- [x] หลัง login สำเร็จ ระบบกลับไปหน้า `customer/create_booking.php`
- [x] วันที่และช่วงเวลาที่เลือกจากหน้าโปรไฟล์ถูก lock
- [x] ทดสอบ sync booking status: `accepted` ทำให้วันว่างเป็น `booked`
- [x] ทดสอบ sync booking status: `cancelled` คืนวันว่างเป็น `available`

## 7. หมายเหตุ

- เครื่อง local ไม่มีคำสั่ง `php` ใน PATH จึงใช้ Docker image PHP 7.4 ของโปรเจกต์ในการ lint
- ไม่แก้ schema ฐานข้อมูล เพราะ status และตาราง `reports` รองรับอยู่แล้วใน `database.sql`
- มีโปรเจกต์ Docker อื่นใช้ port `3307` อยู่ จึงทดสอบ runtime ด้วย container database ชั่วคราวแบบไม่ bind port แล้วลบทิ้งหลังทดสอบ

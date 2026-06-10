# สรุปการจัดการตารางฐานข้อมูลสำหรับนำเสนอ

## password_resets

ตารางนี้ใช้งานจริง ไม่ควรลบ

ใช้ใน:
- `forgot_password.php`
- `reset_password.php`

ข้อมูลที่เก็บ:
- email
- user_id
- token
- expires_at
- requested_ip
- requested_user_agent
- used_at
- used_ip
- used_user_agent
- invalidated_at

เหตุผลที่ต้องมี:
- ใช้ยืนยันว่าเจ้าของบัญชีเข้าถึงอีเมลได้จริง
- token ใช้ได้ครั้งเดียว
- token เก่าถูก invalidated เมื่อขอลิงก์ใหม่
- เก็บ IP และ user agent เพื่อ audit ความปลอดภัย

## login_attempts

ตารางนี้ใช้งานจริง ไม่ควรลบ

ใช้ใน:
- `login.php`
- `includes/functions.php`

ข้อมูลที่เก็บ:
- email ที่พยายาม login
- user_id ถ้าพบผู้ใช้
- ip_address
- success
- failure_reason
- user_agent
- attempted_at
- cleared_at

เหตุผลที่ต้องมี:
- เก็บประวัติการพยายามเข้าสู่ระบบ
- จำกัดการลองรหัสผ่านผิดซ้ำ
- ใช้ตรวจสอบเหตุการณ์ เช่น wrong_password, user_not_found, user_suspended, blocked_too_many_attempts

## banners

ตารางนี้ไม่ใช้แล้ว และถูกถอดออกจาก schema

เหตุผล:
- ระบบไม่ได้ใช้หน้า `admin/banners.php` แล้ว
- ข้อมูลชื่อเว็บ, hero, footer และข้อความหน้าแรกย้ายไปอยู่ใน `settings`
- ไฟล์ migration มี `DROP TABLE IF EXISTS banners;`

## blogs และ photographer_articles

ระบบยังเก็บเป็น 2 ตารางจริงเพื่อให้ workflow แยกกันชัด:
- `blogs` = บทความจากผู้ดูแลระบบ
- `photographer_articles` = บทความจากช่างภาพ

แต่มี view กลางชื่อ `articles` สำหรับอ่านรวมเป็นตารางเดียว

View `articles` มี field สำคัญ:
- uid
- source_id
- source_table
- role_id
- author_user_id
- photographer_id
- title
- slug
- cover_image
- excerpt
- content
- status
- published_at
- created_at
- updated_at
- deleted_at

การแยก role:
- `role_id = 2` คือบทความของช่างภาพ
- `role_id = 3` คือบทความของผู้ดูแลระบบ

เหตุผลที่ยังไม่ drop 2 ตารางจริง:
- หน้า admin และ photographer มี workflow การเขียนบทความคนละสิทธิ์
- การใช้ view รวมช่วยลดความซ้ำตอนอ่านข้อมูล โดยไม่ทำให้ระบบเขียนข้อมูลพัง

## tags, article_tags, blog_tags, portfolio_tags

ตาราง `tags` เป็น master data ของแท็ก

Pivot table ยังแยกตามชนิดข้อมูล:
- `article_tags`
- `blog_tags`
- `portfolio_tags`

แต่มี view กลางชื่อ `tag_usage` สำหรับอ่านรวมเป็นชุดเดียว

View `tag_usage` มี field:
- target_type
- target_id
- tag_id

เหตุผล:
- ลดความซ้ำตอนทำรายงานหรือค้นหาการใช้งานแท็ก
- ยังรักษา foreign key ของแต่ละชนิดข้อมูลไว้ได้
- ปลอดภัยกว่าการรวม pivot ทุกอย่างลงตารางเดียวโดยไม่มี foreign key ตรงชนิดข้อมูล

## ไฟล์ migration ที่เกี่ยวข้อง

ใช้ไฟล์:
- `migrations/2026_06_10_database_usage_cleanup.sql`

คำสั่งรัน:

```bash
docker compose -f docker.yml exec -T db mariadb -uroot -proot chiangrai_photographer_booking < migrations/2026_06_10_database_usage_cleanup.sql
```


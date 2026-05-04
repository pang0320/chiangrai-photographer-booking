# Chiang Rai Photographer Booking Platform

เว็บแอปค้นหาและจองช่างภาพในจังหวัดเชียงรายด้วย PHP 7.4, MySQL/MariaDB, PDO, Tailwind CSS, JavaScript, SweetAlert2 และ Font Awesome

## Requirements

- PHP 7.4
- MySQL 5.7+ หรือ MariaDB 10.3+
- Web server เช่น Apache/Nginx หรือ PHP built-in server

## Installation

1. สร้างฐานข้อมูลจากไฟล์ `database.sql`

```bash
mysql -u root -p < database.sql
```

2. แก้ค่าฐานข้อมูลใน `config/database.php`

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'chiangrai_photographer_booking');
define('DB_USER', 'root');
define('DB_PASS', '');
```

3. ถ้าต้องการ fix URL เอง ให้ตั้ง environment variable `APP_URL`

```bash
export APP_URL=http://localhost:8000
```

4. ตั้ง permission ให้โฟลเดอร์ upload เขียนไฟล์ได้

```bash
chmod -R 755 assets/uploads
```

5. เปิดเว็บ

```bash
php -S localhost:8000
```

จากนั้นเข้า `http://localhost:8000`

## Demo Accounts

รหัสผ่านทุกบัญชีตัวอย่างคือ `password`

- Admin: `admin@example.com`
- Customer: `customer@example.com`
- Photographer: `northstudio@example.com`

## Main Features

- Public marketplace homepage
- Search photographers by district, category, available date, rating, name, and starting price
- Nearby photographer recommendation using latitude/longitude and Haversine Formula
- Customer register/login/profile/booking/review
- Photographer profile/service areas/services/portfolio/availability/bookings/articles/reviews
- Admin dashboard/users/photographers/categories/districts/bookings/reviews/articles/banners/reports/activity logs/settings
- No payment system
- Disclaimer shown on photographer profile and booking form
- CSRF protection, password_hash/password_verify, PDO prepared statements, XSS escaping, upload validation, role checks, activity logs

## Notes

- ช่างภาพสมัครใหม่จะมีสถานะ `pending` และต้องให้ Admin อนุมัติก่อนจึงจะแสดงในหน้าค้นหา
- ลูกค้าจองได้เฉพาะวันและช่วงเวลาที่ช่างภาพเปิดว่างใน `photographer_availability`
- ลูกค้ารีวิวได้เฉพาะ booking ที่ `completed` และ 1 booking รีวิวได้ 1 ครั้ง
- เว็บไซต์เป็นเพียงตัวกลางในการค้นหาและติดต่อช่างภาพเท่านั้น ไม่ได้เป็นตัวกลางรับชำระเงิน

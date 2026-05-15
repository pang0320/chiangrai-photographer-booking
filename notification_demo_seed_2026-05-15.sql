-- เติมแจ้งเตือนตัวอย่างสำหรับฐานข้อมูลเดิมที่มีข้อมูล seed อยู่แล้ว
-- รันไฟล์นี้เฉพาะตอนต้องการทดสอบหน้า notifications.php

INSERT INTO notifications (user_id, title, message, type, related_id, is_read, created_at)
SELECT 1, 'มีรายงานใหม่รอตรวจสอบ', 'ลูกค้าส่งรายงานเนื้อหาที่ต้องตรวจสอบในระบบ', 'report', 1, 0, NOW()
WHERE EXISTS (SELECT 1 FROM users WHERE id = 1)
  AND EXISTS (SELECT 1 FROM reports WHERE id = 1)
  AND NOT EXISTS (
    SELECT 1 FROM notifications
    WHERE user_id = 1
      AND title = 'มีรายงานใหม่รอตรวจสอบ'
      AND type = 'report'
      AND related_id = 1
  );

INSERT INTO notifications (user_id, title, message, type, related_id, is_read, created_at)
SELECT 2, 'สถานะคำขอจองเปลี่ยนแปลง', 'CRB260501AAAAAA เป็น เสร็จสิ้น', 'booking', 1, 0, NOW()
WHERE EXISTS (SELECT 1 FROM users WHERE id = 2)
  AND EXISTS (SELECT 1 FROM bookings WHERE id = 1)
  AND NOT EXISTS (
    SELECT 1 FROM notifications
    WHERE user_id = 2
      AND type = 'booking'
      AND related_id = 1
      AND title = 'สถานะคำขอจองเปลี่ยนแปลง'
  );

INSERT INTO notifications (user_id, title, message, type, related_id, is_read, created_at)
SELECT 3, 'มีรีวิวใหม่', 'CRB260501AAAAAA ได้รับรีวิวใหม่จากลูกค้า', 'review', 1, 0, NOW()
WHERE EXISTS (SELECT 1 FROM users WHERE id = 3)
  AND EXISTS (SELECT 1 FROM reviews WHERE id = 1)
  AND NOT EXISTS (
    SELECT 1 FROM notifications
    WHERE user_id = 3
      AND type = 'review'
      AND related_id = 1
      AND title = 'มีรีวิวใหม่'
  );

INSERT INTO notifications (user_id, title, message, type, related_id, is_read, created_at)
SELECT 10, 'คำขอจองกำลังรอตอบรับ', 'CRB260502000020 รอช่างภาพตอบรับ', 'booking', 20, 0, NOW()
WHERE EXISTS (SELECT 1 FROM users WHERE id = 10)
  AND EXISTS (SELECT 1 FROM bookings WHERE id = 20)
  AND NOT EXISTS (
    SELECT 1 FROM notifications
    WHERE user_id = 10
      AND type = 'booking'
      AND related_id = 20
      AND title = 'คำขอจองกำลังรอตอบรับ'
  );

INSERT INTO notifications (user_id, title, message, type, related_id, is_read, created_at)
SELECT 19, 'มีคำขอจองใหม่', 'CRB260502000020 รอการตอบรับจากช่างภาพ', 'booking', 20, 0, NOW()
WHERE EXISTS (SELECT 1 FROM users WHERE id = 19)
  AND EXISTS (SELECT 1 FROM bookings WHERE id = 20)
  AND NOT EXISTS (
    SELECT 1 FROM notifications
    WHERE user_id = 19
      AND type = 'booking'
      AND related_id = 20
      AND title = 'มีคำขอจองใหม่'
  );

-- Chiang Rai Photographer Booking - presentation update SQL
-- รวม patch ก่อนพรีเซนต์ไว้ไฟล์เดียวเพื่อลดความสับสน
-- รันหลัง import database.sql แล้ว

-- =========================================================
-- 1) Soft delete/status support
-- =========================================================

SET @tag_is_active_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tags'
    AND COLUMN_NAME = 'is_active'
);

SET @tag_is_active_sql := IF(
  @tag_is_active_exists = 0,
  'ALTER TABLE tags ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER slug',
  'SELECT 1'
);
PREPARE tag_is_active_stmt FROM @tag_is_active_sql;
EXECUTE tag_is_active_stmt;
DEALLOCATE PREPARE tag_is_active_stmt;

SET @tag_active_index_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tags'
    AND INDEX_NAME = 'idx_tags_active'
);

SET @tag_active_index_sql := IF(
  @tag_active_index_exists = 0,
  'ALTER TABLE tags ADD INDEX idx_tags_active (is_active, name)',
  'SELECT 1'
);
PREPARE tag_active_index_stmt FROM @tag_active_index_sql;
EXECUTE tag_active_index_stmt;
DEALLOCATE PREPARE tag_active_index_stmt;


-- =========================================================
-- 2) Performance indexes
-- =========================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS add_index_if_missing $$
CREATE PROCEDURE add_index_if_missing(
    IN in_table_name VARCHAR(64),
    IN in_index_name VARCHAR(64),
    IN in_index_sql TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = in_table_name
          AND index_name = in_index_name
        LIMIT 1
    ) THEN
        SET @ddl = in_index_sql;
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

CALL add_index_if_missing(
    'photographer_profiles',
    'idx_perf_photographer_public_rank',
    'ALTER TABLE photographer_profiles ADD INDEX idx_perf_photographer_public_rank (approval_status, is_available, deleted_at, average_rating, total_reviews, response_rate, is_verified, profile_views, id)'
);

CALL add_index_if_missing(
    'photographer_profiles',
    'idx_perf_photographer_featured_rank',
    'ALTER TABLE photographer_profiles ADD INDEX idx_perf_photographer_featured_rank (approval_status, is_available, deleted_at, is_featured, featured_until, average_rating, total_reviews, id)'
);

CALL add_index_if_missing(
    'photographer_services',
    'idx_perf_services_category_active',
    'ALTER TABLE photographer_services ADD INDEX idx_perf_services_category_active (category_id, is_active, photographer_id)'
);

CALL add_index_if_missing(
    'photographer_service_areas',
    'idx_perf_areas_district_active',
    'ALTER TABLE photographer_service_areas ADD INDEX idx_perf_areas_district_active (district_id, is_active, photographer_id)'
);

CALL add_index_if_missing(
    'photographer_availability',
    'idx_perf_availability_public',
    'ALTER TABLE photographer_availability ADD INDEX idx_perf_availability_public (photographer_id, available_date, status, time_slot)'
);

CALL add_index_if_missing(
    'bookings',
    'idx_perf_bookings_rank_completed',
    'ALTER TABLE bookings ADD INDEX idx_perf_bookings_rank_completed (status, deleted_at, photographer_id)'
);

CALL add_index_if_missing(
    'bookings',
    'idx_perf_bookings_slot_conflict',
    'ALTER TABLE bookings ADD INDEX idx_perf_bookings_slot_conflict (photographer_id, booking_date, status, deleted_at, time_slot)'
);

CALL add_index_if_missing(
    'reviews',
    'idx_perf_reviews_visible_recent',
    'ALTER TABLE reviews ADD INDEX idx_perf_reviews_visible_recent (status, deleted_at, created_at, photographer_id)'
);

CALL add_index_if_missing(
    'photographer_portfolios',
    'idx_perf_portfolio_featured_image',
    'ALTER TABLE photographer_portfolios ADD INDEX idx_perf_portfolio_featured_image (photographer_id, deleted_at, is_featured, sort_order, id)'
);

CALL add_index_if_missing(
    'blogs',
    'idx_perf_blogs_public_recent',
    'ALTER TABLE blogs ADD INDEX idx_perf_blogs_public_recent (status, deleted_at, published_at, created_at, id)'
);

CALL add_index_if_missing(
    'photographer_articles',
    'idx_perf_articles_public_recent',
    'ALTER TABLE photographer_articles ADD INDEX idx_perf_articles_public_recent (status, deleted_at, published_at, created_at, photographer_id, id)'
);

CALL add_index_if_missing(
    'notifications',
    'idx_perf_notifications_user_recent',
    'ALTER TABLE notifications ADD INDEX idx_perf_notifications_user_recent (user_id, is_read, created_at)'
);

CALL add_index_if_missing(
    'activity_logs',
    'idx_perf_activity_action_created',
    'ALTER TABLE activity_logs ADD INDEX idx_perf_activity_action_created (action, created_at)'
);

DROP PROCEDURE IF EXISTS add_index_if_missing;


-- =========================================================
-- 3) Demo notifications for testing notifications.php
-- =========================================================

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

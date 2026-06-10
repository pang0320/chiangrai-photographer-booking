USE chiangrai_photographer_booking;

-- 1) password_resets ใช้งานจริงกับ forgot_password.php / reset_password.php
-- เก็บ token, user, IP, user agent, เวลาใช้จริง และ invalidated_at สำหรับทำให้ token เก่าใช้ไม่ได้
ALTER TABLE password_resets
  ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL AFTER email,
  ADD COLUMN IF NOT EXISTS requested_ip VARCHAR(64) NULL AFTER expires_at,
  ADD COLUMN IF NOT EXISTS requested_user_agent VARCHAR(255) NULL AFTER requested_ip,
  ADD COLUMN IF NOT EXISTS used_at DATETIME NULL AFTER requested_user_agent,
  ADD COLUMN IF NOT EXISTS used_ip VARCHAR(64) NULL AFTER used_at,
  ADD COLUMN IF NOT EXISTS used_user_agent VARCHAR(255) NULL AFTER used_ip,
  ADD COLUMN IF NOT EXISTS invalidated_at DATETIME NULL AFTER used_user_agent;

CREATE INDEX IF NOT EXISTS idx_password_resets_user ON password_resets (user_id);
CREATE INDEX IF NOT EXISTS idx_password_resets_email ON password_resets (email);
CREATE INDEX IF NOT EXISTS idx_password_resets_used ON password_resets (used_at);
CREATE INDEX IF NOT EXISTS idx_password_resets_active ON password_resets (email, used_at, invalidated_at, expires_at);

-- 2) login_attempts ใช้งานจริงกับ login.php
-- เก็บทุกครั้งที่พยายาม login ทั้งสำเร็จและไม่สำเร็จ เพื่อจำกัดการลองรหัสผ่านผิดซ้ำ
ALTER TABLE login_attempts
  ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL AFTER email,
  ADD COLUMN IF NOT EXISTS failure_reason VARCHAR(120) NULL AFTER success,
  ADD COLUMN IF NOT EXISTS cleared_at DATETIME NULL AFTER attempted_at;

CREATE INDEX IF NOT EXISTS idx_login_attempts_user ON login_attempts (user_id);
CREATE INDEX IF NOT EXISTS idx_login_attempts_email_ip_time ON login_attempts (email, ip_address, attempted_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_block ON login_attempts (email, ip_address, success, cleared_at, attempted_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_attempted_at ON login_attempts (attempted_at);

-- 3) banners ไม่ได้ใช้ในระบบปัจจุบันแล้ว
-- ชื่อเว็บไซต์ / hero / footer ย้ายไป settings แทน จึงลบตาราง banners ได้
DROP TABLE IF EXISTS banners;

-- 4) blogs และ photographer_articles ยังเป็นตารางเขียนข้อมูลจริงของแต่ละ role
-- แต่รวมการอ่านข้อมูลด้วย VIEW articles โดยมี role_id แยกว่าใครเป็นผู้เขียน
-- role_id = 2 คือช่างภาพ, role_id = 3 คือผู้ดูแลระบบ
DROP VIEW IF EXISTS articles;

CREATE VIEW articles AS
SELECT
  CONCAT('photographer:', pa.id) AS uid,
  pa.id AS source_id,
  'photographer_articles' AS source_table,
  2 AS role_id,
  pp.user_id AS author_user_id,
  pa.photographer_id,
  pa.title,
  pa.slug,
  pa.cover_image,
  pa.excerpt,
  pa.content,
  pa.status,
  pa.published_at,
  pa.created_at,
  pa.updated_at,
  pa.deleted_at
FROM photographer_articles pa
JOIN photographer_profiles pp ON pp.id = pa.photographer_id
UNION ALL
SELECT
  CONCAT('admin:', b.id) AS uid,
  b.id AS source_id,
  'blogs' AS source_table,
  3 AS role_id,
  b.admin_id AS author_user_id,
  NULL AS photographer_id,
  b.title,
  b.slug,
  b.cover_image,
  b.excerpt,
  b.content,
  b.status,
  b.published_at,
  b.created_at,
  b.updated_at,
  b.deleted_at
FROM blogs b;

-- 5) article_tags / blog_tags / portfolio_tags ยังเป็น pivot จริงตามชนิดข้อมูล
-- แต่รวมการอ่านด้วย VIEW tag_usage เพื่อให้รายงานเห็นเป็นชุดเดียว ไม่ต้อง query 3 ตาราง
DROP VIEW IF EXISTS tag_usage;

CREATE VIEW tag_usage AS
SELECT 'photographer_article' AS target_type, article_id AS target_id, tag_id FROM article_tags
UNION ALL
SELECT 'blog' AS target_type, blog_id AS target_id, tag_id FROM blog_tags
UNION ALL
SELECT 'portfolio' AS target_type, portfolio_id AS target_id, tag_id FROM portfolio_tags;


USE chiangrai_photographer_booking;

ALTER TABLE password_resets
  ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL AFTER email,
  ADD COLUMN IF NOT EXISTS requested_ip VARCHAR(64) NULL AFTER expires_at,
  ADD COLUMN IF NOT EXISTS requested_user_agent VARCHAR(255) NULL AFTER requested_ip,
  ADD COLUMN IF NOT EXISTS used_at DATETIME NULL AFTER requested_user_agent,
  ADD COLUMN IF NOT EXISTS used_ip VARCHAR(64) NULL AFTER used_at,
  ADD COLUMN IF NOT EXISTS used_user_agent VARCHAR(255) NULL AFTER used_ip,
  ADD COLUMN IF NOT EXISTS invalidated_at DATETIME NULL AFTER used_user_agent;

CREATE INDEX IF NOT EXISTS idx_password_resets_user ON password_resets (user_id);
CREATE INDEX IF NOT EXISTS idx_password_resets_used ON password_resets (used_at);
CREATE INDEX IF NOT EXISTS idx_password_resets_active ON password_resets (email, used_at, invalidated_at, expires_at);

ALTER TABLE login_attempts
  ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL AFTER email,
  ADD COLUMN IF NOT EXISTS failure_reason VARCHAR(120) NULL AFTER success,
  ADD COLUMN IF NOT EXISTS cleared_at DATETIME NULL AFTER attempted_at;

CREATE INDEX IF NOT EXISTS idx_login_attempts_user ON login_attempts (user_id);
CREATE INDEX IF NOT EXISTS idx_login_attempts_block ON login_attempts (email, ip_address, success, cleared_at, attempted_at);

ALTER TABLE photographer_articles
  ADD COLUMN IF NOT EXISTS excerpt TEXT NULL AFTER cover_image;

DROP TABLE IF EXISTS banners;

DROP VIEW IF EXISTS tag_usage;
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

CREATE VIEW tag_usage AS
SELECT 'photographer_article' AS target_type, article_id AS target_id, tag_id FROM article_tags
UNION ALL
SELECT 'blog' AS target_type, blog_id AS target_id, tag_id FROM blog_tags
UNION ALL
SELECT 'portfolio' AS target_type, portfolio_id AS target_id, tag_id FROM portfolio_tags;

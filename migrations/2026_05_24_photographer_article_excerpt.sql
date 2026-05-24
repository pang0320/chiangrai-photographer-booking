USE chiangrai_photographer_booking;

ALTER TABLE photographer_articles
  ADD COLUMN IF NOT EXISTS excerpt TEXT NULL AFTER cover_image;

UPDATE photographer_articles
SET excerpt = LEFT(TRIM(REPLACE(REPLACE(REPLACE(content, '<p>', ''), '</p>', ''), '<br>', '')), 240)
WHERE (excerpt IS NULL OR excerpt = '')
  AND content IS NOT NULL;

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

CREATE DATABASE IF NOT EXISTS chiangrai_photographer_booking
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE chiangrai_photographer_booking;

SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS tag_usage;
DROP VIEW IF EXISTS articles;

DROP TABLE IF EXISTS review_images;
DROP TABLE IF EXISTS blog_tags;
DROP TABLE IF EXISTS article_tags;
DROP TABLE IF EXISTS portfolio_tags;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS recently_viewed_photographers;
DROP TABLE IF EXISTS search_logs;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS blogs;
DROP TABLE IF EXISTS faqs;
DROP TABLE IF EXISTS favorite_photographers;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS booking_status_logs;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS photographer_availability;
DROP TABLE IF EXISTS photographer_portfolios;
DROP TABLE IF EXISTS photographer_service_areas;
DROP TABLE IF EXISTS photographer_services;
DROP TABLE IF EXISTS photographer_articles;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS photographer_profiles;
DROP TABLE IF EXISTS service_categories;
DROP TABLE IF EXISTS districts;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  display_name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(50) NULL,
  password VARCHAR(255) NOT NULL,
  avatar VARCHAR(255) NULL,
  status ENUM('active','pending','suspended') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  remember_token VARCHAR(100) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uk_users_email (email),
  KEY idx_users_role (role_id),
  KEY idx_users_status (status),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  user_id INT UNSIGNED NULL,
  token VARCHAR(128) NOT NULL,
  expires_at DATETIME NOT NULL,
  requested_ip VARCHAR(64) NULL,
  requested_user_agent VARCHAR(255) NULL,
  used_at DATETIME NULL,
  used_ip VARCHAR(64) NULL,
  used_user_agent VARCHAR(255) NULL,
  invalidated_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_password_resets_user (user_id),
  KEY idx_password_resets_email (email),
  KEY idx_password_resets_used (used_at),
  KEY idx_password_resets_active (email, used_at, invalidated_at, expires_at),
  UNIQUE KEY uk_password_resets_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  user_id INT UNSIGNED NULL,
  ip_address VARCHAR(64) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  failure_reason VARCHAR(120) NULL,
  user_agent VARCHAR(255) NULL,
  attempted_at DATETIME NOT NULL,
  cleared_at DATETIME NULL,
  KEY idx_login_attempts_user (user_id),
  KEY idx_login_attempts_email_ip_time (email, ip_address, attempted_at),
  KEY idx_login_attempts_block (email, ip_address, success, cleared_at, attempted_at),
  KEY idx_login_attempts_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE districts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  province_name VARCHAR(100) NOT NULL DEFAULT 'เชียงราย',
  district_name VARCHAR(100) NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_district_name (province_name, district_name),
  KEY idx_district_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  icon VARCHAR(80) NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uk_service_categories_slug (slug),
  KEY idx_service_categories_active (is_active, sort_order),
  KEY idx_service_categories_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE photographer_profiles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  display_name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  bio TEXT NULL,
  experience_years INT UNSIGNED NOT NULL DEFAULT 0,
  starting_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  profile_image VARCHAR(255) NULL,
  cover_image VARCHAR(255) NULL,
  phone_public VARCHAR(50) NULL,
  line_id VARCHAR(100) NULL,
  facebook_url VARCHAR(255) NULL,
  instagram_url VARCHAR(255) NULL,
  website_url VARCHAR(255) NULL,
  main_district_id INT UNSIGNED NULL,
  approval_status ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  rejection_reason TEXT NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  profile_views INT UNSIGNED NOT NULL DEFAULT 0,
  average_rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  total_reviews INT UNSIGNED NOT NULL DEFAULT 0,
  response_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  average_response_hours DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  verified_at DATETIME NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  featured_until DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uk_photographer_user (user_id),
  UNIQUE KEY uk_photographer_slug (slug),
  KEY idx_photographer_approval (approval_status, is_available),
  KEY idx_photographer_price (starting_price),
  KEY idx_photographer_rating (average_rating, total_reviews),
  KEY idx_photographer_district (main_district_id),
  KEY idx_photographer_featured (is_featured, featured_until),
  KEY idx_photographer_verified (is_verified),
  CONSTRAINT fk_photographer_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_photographer_main_district FOREIGN KEY (main_district_id) REFERENCES districts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE photographer_services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  photographer_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  description TEXT NULL,
  starting_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_photographer_service (photographer_id, category_id),
  KEY idx_photographer_services_active (is_active),
  CONSTRAINT fk_photographer_services_profile FOREIGN KEY (photographer_id) REFERENCES photographer_profiles(id),
  CONSTRAINT fk_photographer_services_category FOREIGN KEY (category_id) REFERENCES service_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE photographer_service_areas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  photographer_id INT UNSIGNED NOT NULL,
  district_id INT UNSIGNED NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_photographer_area (photographer_id, district_id),
  KEY idx_service_areas_district (district_id, is_active),
  CONSTRAINT fk_service_areas_profile FOREIGN KEY (photographer_id) REFERENCES photographer_profiles(id),
  CONSTRAINT fk_service_areas_district FOREIGN KEY (district_id) REFERENCES districts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE photographer_portfolios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  photographer_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  image_path VARCHAR(255) NOT NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_portfolios_photographer (photographer_id, is_featured, sort_order),
  CONSTRAINT fk_portfolios_profile FOREIGN KEY (photographer_id) REFERENCES photographer_profiles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE photographer_availability (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  photographer_id INT UNSIGNED NOT NULL,
  available_date DATE NOT NULL,
  time_slot ENUM('morning','afternoon','evening','full_day') NOT NULL,
  status ENUM('available','unavailable','booked') NOT NULL DEFAULT 'available',
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_availability_slot (photographer_id, available_date, time_slot),
  KEY idx_availability_date (available_date, status),
  CONSTRAINT fk_availability_profile FOREIGN KEY (photographer_id) REFERENCES photographer_profiles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_code VARCHAR(40) NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  photographer_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  district_id INT UNSIGNED NOT NULL,
  booking_date DATE NOT NULL,
  time_slot ENUM('morning','afternoon','evening','full_day') NOT NULL,
  contact_name VARCHAR(160) NOT NULL,
  contact_phone VARCHAR(50) NOT NULL,
  contact_channel VARCHAR(120) NOT NULL,
  job_detail TEXT NOT NULL,
  note TEXT NULL,
  status ENUM('pending','accepted','rejected','cancelled','confirmed','completed') NOT NULL DEFAULT 'pending',
  rejection_reason TEXT NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uk_booking_code (booking_code),
  KEY idx_bookings_customer (customer_id, status),
  KEY idx_bookings_photographer (photographer_id, status),
  KEY idx_bookings_date_slot (photographer_id, booking_date, time_slot, status),
  KEY idx_bookings_category (category_id),
  KEY idx_bookings_district (district_id),
  CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_id) REFERENCES users(id),
  CONSTRAINT fk_bookings_photographer FOREIGN KEY (photographer_id) REFERENCES photographer_profiles(id),
  CONSTRAINT fk_bookings_category FOREIGN KEY (category_id) REFERENCES service_categories(id),
  CONSTRAINT fk_bookings_district FOREIGN KEY (district_id) REFERENCES districts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_status_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  old_status VARCHAR(40) NULL,
  new_status VARCHAR(40) NOT NULL,
  changed_by INT UNSIGNED NULL,
  note TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_booking_logs_booking (booking_id),
  KEY idx_booking_logs_user (changed_by),
  CONSTRAINT fk_booking_logs_booking FOREIGN KEY (booking_id) REFERENCES bookings(id),
  CONSTRAINT fk_booking_logs_user FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  photographer_id INT UNSIGNED NOT NULL,
  rating_overall TINYINT UNSIGNED NOT NULL,
  rating_quality TINYINT UNSIGNED NOT NULL,
  rating_communication TINYINT UNSIGNED NOT NULL,
  rating_punctuality TINYINT UNSIGNED NOT NULL,
  rating_professional TINYINT UNSIGNED NOT NULL,
  comment TEXT NULL,
  status ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uk_reviews_booking (booking_id),
  KEY idx_reviews_photographer (photographer_id, status),
  KEY idx_reviews_customer (customer_id),
  CONSTRAINT fk_reviews_booking FOREIGN KEY (booking_id) REFERENCES bookings(id),
  CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id) REFERENCES users(id),
  CONSTRAINT fk_reviews_photographer FOREIGN KEY (photographer_id) REFERENCES photographer_profiles(id),
  CONSTRAINT chk_reviews_rating CHECK (rating_overall BETWEEN 1 AND 5 AND rating_quality BETWEEN 1 AND 5 AND rating_communication BETWEEN 1 AND 5 AND rating_punctuality BETWEEN 1 AND 5 AND rating_professional BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE review_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  review_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_review_images_review (review_id),
  CONSTRAINT fk_review_images_review FOREIGN KEY (review_id) REFERENCES reviews(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE photographer_articles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  photographer_id INT UNSIGNED NOT NULL,
  title VARCHAR(220) NOT NULL,
  slug VARCHAR(240) NOT NULL,
  cover_image VARCHAR(255) NULL,
  excerpt TEXT NULL,
  content MEDIUMTEXT NOT NULL,
  status ENUM('draft','published','hidden') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uk_articles_slug (slug),
  KEY idx_articles_photographer (photographer_id, status),
  CONSTRAINT fk_articles_profile FOREIGN KEY (photographer_id) REFERENCES photographer_profiles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  type VARCHAR(60) NOT NULL DEFAULT 'info',
  related_id INT UNSIGNED NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_user (user_id, is_read),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  table_name VARCHAR(120) NULL,
  record_id INT UNSIGNED NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  description TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_user (user_id),
  KEY idx_activity_table (table_name, record_id),
  KEY idx_activity_created (created_at),
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL,
  setting_value TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favorite_photographers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  photographer_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_favorite_customer_photographer (customer_id, photographer_id),
  KEY idx_favorite_photographer (photographer_id),
  CONSTRAINT fk_favorite_customer FOREIGN KEY (customer_id) REFERENCES users(id),
  CONSTRAINT fk_favorite_photographer FOREIGN KEY (photographer_id) REFERENCES photographer_profiles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE faqs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(100) NOT NULL,
  question VARCHAR(255) NOT NULL,
  answer TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_faq_active (is_active, category, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blogs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NOT NULL,
  title VARCHAR(220) NOT NULL,
  slug VARCHAR(240) NOT NULL,
  cover_image VARCHAR(255) NULL,
  excerpt TEXT NULL,
  content MEDIUMTEXT NOT NULL,
  status ENUM('draft','published','hidden') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uk_blogs_slug (slug),
  KEY idx_blogs_admin (admin_id),
  KEY idx_blogs_status (status, published_at),
  CONSTRAINT fk_blogs_admin FOREIGN KEY (admin_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(50) NULL,
  subject VARCHAR(220) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('unread','read','replied') NOT NULL DEFAULT 'unread',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_contact_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reporter_id INT UNSIGNED NULL,
  target_type ENUM('photographer','review','booking','article') NOT NULL,
  target_id INT UNSIGNED NOT NULL,
  reason VARCHAR(180) NOT NULL,
  detail TEXT NULL,
  status ENUM('pending','reviewed','resolved','rejected') NOT NULL DEFAULT 'pending',
  admin_note TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_reports_target (target_type, target_id),
  KEY idx_reports_status (status, created_at),
  KEY idx_reports_reporter (reporter_id),
  CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE search_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  keyword VARCHAR(190) NULL,
  district_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  search_date DATE NOT NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_search_user (user_id),
  KEY idx_search_district (district_id),
  KEY idx_search_category (category_id),
  KEY idx_search_keyword (keyword),
  CONSTRAINT fk_search_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_search_district FOREIGN KEY (district_id) REFERENCES districts(id),
  CONSTRAINT fk_search_category FOREIGN KEY (category_id) REFERENCES service_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recently_viewed_photographers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  photographer_id INT UNSIGNED NOT NULL,
  viewed_at DATETIME NOT NULL,
  UNIQUE KEY uk_recent_user_photographer (user_id, photographer_id),
  KEY idx_recent_user (user_id, viewed_at),
  CONSTRAINT fk_recent_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_recent_photographer FOREIGN KEY (photographer_id) REFERENCES photographer_profiles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tags_slug (slug),
  KEY idx_tags_active (is_active, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portfolio_tags (
  portfolio_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (portfolio_id, tag_id),
  CONSTRAINT fk_portfolio_tags_portfolio FOREIGN KEY (portfolio_id) REFERENCES photographer_portfolios(id),
  CONSTRAINT fk_portfolio_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE article_tags (
  article_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (article_id, tag_id),
  CONSTRAINT fk_article_tags_article FOREIGN KEY (article_id) REFERENCES photographer_articles(id),
  CONSTRAINT fk_article_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_tags (
  blog_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (blog_id, tag_id),
  CONSTRAINT fk_blog_tags_blog FOREIGN KEY (blog_id) REFERENCES blogs(id),
  CONSTRAINT fk_blog_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

INSERT INTO roles (id, name, display_name) VALUES
(1, 'customer', 'ลูกค้า'),
(2, 'photographer', 'ช่างภาพ'),
(3, 'admin', 'ผู้ดูแลระบบ');

INSERT INTO districts (id, district_name, latitude, longitude) VALUES
(1, 'เมืองเชียงราย', 19.9105, 99.8406),
(2, 'เวียงชัย', 19.8832, 99.9334),
(3, 'เชียงของ', 20.2619, 100.4049),
(4, 'เทิง', 19.6846, 100.1947),
(5, 'พาน', 19.5539, 99.7408),
(6, 'ป่าแดด', 19.5043, 99.9922),
(7, 'แม่จัน', 20.1467, 99.8522),
(8, 'เชียงแสน', 20.2741, 100.0841),
(9, 'แม่สาย', 20.4335, 99.8762),
(10, 'แม่สรวย', 19.6561, 99.5437),
(11, 'เวียงป่าเป้า', 19.3421, 99.5066),
(12, 'พญาเม็งราย', 19.8491, 100.1532),
(13, 'เวียงแก่น', 20.1135, 100.5088),
(14, 'ขุนตาล', 19.8338, 100.2695),
(15, 'แม่ฟ้าหลวง', 20.1659, 99.6237),
(16, 'แม่ลาว', 19.7908, 99.6992),
(17, 'เวียงเชียงรุ้ง', 20.0129, 100.0561),
(18, 'ดอยหลวง', 20.1161, 100.0972);

INSERT INTO service_categories (id, name, slug, icon, description, is_active, sort_order) VALUES
(1, 'งานแต่งงาน', 'wedding', 'fa-ring', 'ถ่ายภาพงานแต่ง พรีเวดดิ้ง และพิธีหมั้น', 1, 1),
(2, 'รับปริญญา', 'graduation', 'fa-graduation-cap', 'ถ่ายภาพรับปริญญาและครอบครัว', 1, 2),
(3, 'ครอบครัว', 'family', 'fa-people-roof', 'ถ่ายภาพครอบครัว เด็ก และคู่รัก', 1, 3),
(4, 'สินค้าและร้านค้า', 'product', 'fa-box-open', 'ถ่ายภาพสินค้า อาหาร ร้านกาแฟ และโรงแรม', 1, 4),
(5, 'อีเวนต์', 'event', 'fa-calendar-check', 'ถ่ายภาพงานอีเวนต์ ประชุม และกิจกรรมองค์กร', 1, 5),
(6, 'โปรไฟล์ส่วนตัว', 'portrait', 'fa-user-tie', 'ถ่ายภาพโปรไฟล์ บุคคล และคอนเทนต์โซเชียล', 1, 6);

INSERT INTO users (id, role_id, name, email, phone, password, avatar, status, email_verified_at) VALUES
(1, 3, 'Admin Chiang Rai', 'admin@example.com', '0890000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'active', NOW()),
(2, 1, 'ลูกค้าทดสอบ', 'customer@example.com', '0811111111', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'active', NOW()),
(3, 2, 'เหนือฟ้า สตูดิโอ', 'northstudio@example.com', '0822222222', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'active', NOW()),
(4, 2, 'ริมโขง โฟโต้', 'mekongphoto@example.com', '0833333333', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'active', NOW()),
(5, 2, 'ดอยหลวง โปรดักชัน', 'doiluang@example.com', '0844444444', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'pending', NOW());

INSERT INTO photographer_profiles
(id, user_id, display_name, slug, bio, experience_years, starting_price, profile_image, cover_image, phone_public, line_id, facebook_url, instagram_url, website_url, main_district_id, approval_status, is_available, profile_views, average_rating, total_reviews)
VALUES
(1, 3, 'เหนือฟ้า สตูดิโอ', 'north-sky-studio', 'ช่างภาพงานแต่งและพอร์ตเทรตในเชียงราย เน้นแสงธรรมชาติ โทนอุ่น และโมเมนต์จริง', 8, 3500, NULL, NULL, '0822222222', 'northstudio', 'https://facebook.com/northstudio', 'https://instagram.com/northstudio', '', 1, 'approved', 1, 124, 5.00, 1),
(2, 4, 'ริมโขง โฟโต้', 'mekong-photo', 'บริการถ่ายภาพอีเวนต์ ครอบครัว และท่องเที่ยวโซนเชียงของ เชียงแสน แม่สาย', 6, 2800, NULL, NULL, '0833333333', 'mekongphoto', 'https://facebook.com/mekongphoto', 'https://instagram.com/mekongphoto', '', 3, 'approved', 1, 88, 0.00, 0),
(3, 5, 'ดอยหลวง โปรดักชัน', 'doi-luang-production', 'ทีมถ่ายภาพและวิดีโอสำหรับสินค้าและอีเวนต์ รอการอนุมัติจากผู้ดูแลระบบ', 4, 3000, NULL, NULL, '0844444444', 'doiluang', '', '', '', 18, 'pending', 1, 0, 0.00, 0);

INSERT INTO photographer_services (photographer_id, category_id, description, starting_price, is_active) VALUES
(1, 1, 'แพ็กเกจงานแต่งครึ่งวันและเต็มวัน พร้อมไฟล์แต่งสี', 6500, 1),
(1, 2, 'ถ่ายรับปริญญาในเมืองเชียงรายและพื้นที่ใกล้เคียง', 3500, 1),
(1, 6, 'ถ่ายโปรไฟล์บุคคลสำหรับธุรกิจและโซเชียล', 2500, 1),
(2, 3, 'ถ่ายครอบครัวและคู่รักริมโขง', 2800, 1),
(2, 5, 'ถ่ายอีเวนต์และกิจกรรมองค์กร', 4500, 1),
(2, 4, 'ถ่ายร้านอาหาร คาเฟ่ และสินค้า', 3200, 1);

INSERT INTO photographer_service_areas (photographer_id, district_id, is_primary, is_active) VALUES
(1, 1, 1, 1),(1, 2, 0, 1),(1, 5, 0, 1),(1, 7, 0, 1),(1, 16, 0, 1),
(2, 3, 1, 1),(2, 8, 0, 1),(2, 9, 0, 1),(2, 13, 0, 1),(2, 14, 0, 1),
(3, 18, 1, 1),(3, 17, 0, 1);

INSERT INTO photographer_portfolios (photographer_id, title, description, image_path, is_featured, sort_order) VALUES
(1, 'Wedding in Chiang Rai', 'งานแต่งโทนอุ่นในสวน', 'portfolios/sample-wedding-1.jpg', 1, 1),
(1, 'Portrait Natural Light', 'พอร์ตเทรตแสงธรรมชาติ', 'portfolios/sample-portrait-1.jpg', 1, 2),
(2, 'Mekong Family Session', 'ภาพครอบครัวริมโขง', 'portfolios/sample-family-1.jpg', 1, 1),
(2, 'Cafe Product Shoot', 'ภาพสินค้าและคาเฟ่', 'portfolios/sample-product-1.jpg', 1, 2);

INSERT INTO photographer_availability (photographer_id, available_date, time_slot, status, note) VALUES
(1, '2026-06-01', 'morning', 'available', ''),
(1, '2026-06-01', 'afternoon', 'available', ''),
(1, '2026-06-02', 'full_day', 'available', ''),
(2, '2026-06-01', 'full_day', 'available', ''),
(2, '2026-06-03', 'morning', 'available', ''),
(2, '2026-06-03', 'afternoon', 'available', '');

INSERT INTO bookings (id, booking_code, customer_id, photographer_id, category_id, district_id, booking_date, time_slot, contact_name, contact_phone, contact_channel, job_detail, note, status, completed_at) VALUES
(1, 'CRB260501AAAAAA', 2, 1, 6, 1, '2026-05-01', 'morning', 'ลูกค้าทดสอบ', '0811111111', 'LINE: customerline', 'ถ่ายโปรไฟล์ส่วนตัวในเมืองเชียงราย', 'ขอโทนอุ่น', 'completed', '2026-05-01 16:00:00');

INSERT INTO booking_status_logs (booking_id, old_status, new_status, changed_by, note) VALUES
(1, NULL, 'pending', 2, 'สร้างคำขอจอง'),
(1, 'pending', 'accepted', 3, 'ช่างภาพตอบรับ'),
(1, 'accepted', 'confirmed', 3, 'ยืนยันรายละเอียด'),
(1, 'confirmed', 'completed', 3, 'งานเสร็จสิ้น');

INSERT INTO reviews (id, booking_id, customer_id, photographer_id, rating_overall, rating_quality, rating_communication, rating_punctuality, rating_professional, comment, status) VALUES
(1, 1, 2, 1, 5, 5, 5, 5, 5, 'ช่างภาพสื่อสารดี ภาพสวย ส่งงานเร็ว ประทับใจมาก', 'visible');

INSERT INTO photographer_articles (photographer_id, title, slug, cover_image, content, status, published_at) VALUES
(1, 'เตรียมตัวถ่ายพรีเวดดิ้งในเชียงรายอย่างไรให้ภาพออกมาดี', 'prepare-prewedding-chiangrai', NULL, 'เลือกช่วงเวลาเช้าหรือเย็น เตรียมชุดให้เข้ากับสถานที่ และคุย mood board กับช่างภาพก่อนวันถ่าย', 'published', NOW()),
(2, 'ไอเดียถ่ายภาพครอบครัวริมแม่น้ำโขง', 'family-photo-mekong', NULL, 'ภาพครอบครัวที่ดีเริ่มจากบรรยากาศสบาย ๆ ให้เด็กและผู้ใหญ่ได้เป็นตัวเอง', 'published', NOW());

INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'Chiang Rai Photographer Booking'),
('home_page_title', 'ค้นหาช่างภาพเชียงราย | จองช่างภาพมืออาชีพ งานแต่ง รับปริญญา โปรไฟล์'),
('home_hero_title', 'ค้นหาช่างภาพมืออาชีพในจังหวัดเชียงราย'),
('home_hero_subtitle', 'เลือกดูตัวอย่างงานถ่ายภาพจริง ตรวจวันว่าง ส่งคำขอจอง และติดต่อช่างภาพโดยตรง ไม่มีระบบรับชำระเงินในเว็บไซต์'),
('home_hero_button_text', 'ค้นหาช่างภาพ'),
('home_hero_button_url', '/photographers.php'),
('footer_text', 'ค้นหาช่างภาพเชียงราย ดูตัวอย่างงาน ตรวจวันว่าง และติดต่อช่างภาพได้โดยตรง'),
('payment_disclaimer', 'เว็บไซต์เป็นเพียงตัวกลางในการค้นหาและติดต่อช่างภาพเท่านั้น ไม่ได้เป็นตัวกลางรับชำระเงิน'),
('admin_email', 'admin@example.com'),
('admin_phone', '0890000000'),
('allow_photographer_registration', '1'),
('nearby_radius_km', '30'),
('enforce_https', '0'),
('login_attempt_limit', '5');

INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, user_agent, description) VALUES
(1, 'seed_database', 'settings', 1, '127.0.0.1', 'Seeder', 'Initial database seed');

INSERT INTO users (id, role_id, name, email, phone, password, avatar, status, email_verified_at) VALUES
(6, 1, 'ภัทรา ใจดี', 'pattra@example.com', '0811111106', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', 'seed/photo-1494790108377-be9c29b29330.jpg', 'active', NOW()),
(7, 1, 'ณัฐพล เชียงราย', 'nattapon@example.com', '0811111107', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', 'seed/photo-1500648767791-00dcc994a43e.jpg', 'active', NOW()),
(8, 1, 'ชลธิชา เมืองเหนือ', 'chonthicha@example.com', '0811111108', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', 'seed/photo-1534528741775-53994a69daeb.jpg', 'active', NOW()),
(9, 1, 'กิตติพงษ์ แม่สาย', 'kittipong@example.com', '0811111109', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', 'seed/photo-1506794778202-cad84cf45f1d.jpg', 'active', NOW()),
(10, 1, 'อรทัย พาน', 'ornthai@example.com', '0811111110', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', 'seed/photo-1517841905240-472988babdf9.jpg', 'active', NOW()),
(11, 1, 'ธนวัฒน์ เทิง', 'thanawat@example.com', '0811111112', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', 'seed/photo-1519345182560-3f2917c472ef.jpg', 'active', NOW()),
(12, 1, 'มินตรา แม่จัน', 'mintra@example.com', '0811111113', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', 'seed/photo-1524504388940-b1c1722653e1.jpg', 'active', NOW()),
(13, 2, 'ล้านนา เลนส์', 'lannalens@example.com', '0822222213', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'active', NOW()),
(14, 2, 'ภูชี้ฟ้า โมเมนต์', 'phuchifamoment@example.com', '0822222214', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'active', NOW()),
(15, 2, 'แม่สาย วิชวล', 'maesaivisual@example.com', '0822222215', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'active', NOW()),
(16, 2, 'พาน โปรไฟล์', 'phanprofile@example.com', '0822222216', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'active', NOW()),
(17, 2, 'แม่สรวย ครีเอทีฟ', 'maesuai@example.com', '0822222217', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'active', NOW()),
(18, 2, 'เชียงแสน สตูดิโอ', 'chiangsaen@example.com', '0822222218', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'active', NOW()),
(19, 2, 'เทิง อีเวนต์ โฟโต้', 'thoengevent@example.com', '0822222219', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'active', NOW()),
(20, 2, 'แม่ฟ้าหลวง เวดดิ้ง', 'maefahluangwedding@example.com', '0822222220', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'active', NOW()),
(21, 2, 'เวียงแก่น แกลเลอรี', 'wiangkaengallery@example.com', '0822222221', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'active', NOW()),
(22, 2, 'แม่ลาว พิคเจอร์', 'maelaopicture@example.com', '0822222222', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL, 'active', NOW());

INSERT INTO photographer_profiles
(id, user_id, display_name, slug, bio, experience_years, starting_price, profile_image, cover_image, phone_public, line_id, facebook_url, instagram_url, website_url, main_district_id, approval_status, is_available, profile_views, average_rating, total_reviews)
VALUES
(4, 13, 'ล้านนา เลนส์', 'lanna-lens', 'ช่างภาพพอร์ตเทรตและไลฟ์สไตล์ในเมืองเชียงราย เน้นภาพสะอาด โทนพรีเมียม และจัดท่าทางเป็นธรรมชาติ', 7, 2200, 'seed/photo-1500648767791-00dcc994a43e.jpg', 'seed/photo-1516035069371-29a1b244cc32.jpg', '0822222213', 'lannalens', 'https://facebook.com/lannalens', 'https://instagram.com/lannalens', '', 1, 'approved', 1, 342, 0, 0),
(5, 14, 'ภูชี้ฟ้า โมเมนต์', 'phu-chi-fa-moment', 'ถ่ายคู่รัก พรีเวดดิ้ง และภาพท่องเที่ยวบนภูเขา แสงเช้า หมอก และวิวธรรมชาติ', 9, 4200, 'seed/photo-1519085360753-af0119f7cbe7.jpg', 'seed/photo-1500530855697-b586d89ba3ee.jpg', '0822222214', 'phuchifamoment', 'https://facebook.com/phuchifamoment', 'https://instagram.com/phuchifamoment', '', 4, 'approved', 1, 287, 0, 0),
(6, 15, 'แม่สาย วิชวล', 'mae-sai-visual', 'ทีมถ่ายภาพอีเวนต์ ร้านค้า และคอนเทนต์ธุรกิจในแม่สาย เชียงแสน และโซนเหนือ', 6, 3000, 'seed/photo-1507003211169-0a1dd7228f2d.jpg', 'seed/photo-1517457373958-b7bdd4587205.jpg', '0822222215', 'maesaivisual', 'https://facebook.com/maesaivisual', 'https://instagram.com/maesaivisual', '', 9, 'approved', 1, 198, 0, 0),
(7, 16, 'พาน โปรไฟล์', 'phan-profile', 'ถ่ายรับปริญญา โปรไฟล์ธุรกิจ และภาพครอบครัว โทนอบอุ่น สื่อสารง่าย', 5, 1800, 'seed/photo-1531891437562-4301cf35b7e4.jpg', 'seed/photo-1520854221256-17451cc331bf.jpg', '0822222216', 'phanprofile', 'https://facebook.com/phanprofile', 'https://instagram.com/phanprofile', '', 5, 'approved', 1, 166, 0, 0),
(8, 17, 'แม่สรวย ครีเอทีฟ', 'mae-suai-creative', 'ภาพสินค้า คาเฟ่ โรงแรม และรีสอร์ทในโซนแม่สรวย เวียงป่าเป้า เน้นภาพขายได้จริง', 8, 3600, 'seed/photo-1530268729831-4b0b9e170218.jpg', 'seed/photo-1441986300917-64674bd600d8.jpg', '0822222217', 'maesuai', 'https://facebook.com/maesuai', 'https://instagram.com/maesuai', '', 10, 'approved', 1, 230, 0, 0),
(9, 18, 'เชียงแสน สตูดิโอ', 'chiang-saen-studio', 'ถ่ายครอบครัว งานแต่งเล็ก ๆ และภาพท่องเที่ยวริมโขงในเชียงแสน', 10, 3900, 'seed/photo-1527980965255-d3b416303d12.jpg', 'seed/photo-1470770841072-f978cf4d019e.jpg', '0822222218', 'chiangsaenstudio', 'https://facebook.com/chiangsaenstudio', 'https://instagram.com/chiangsaenstudio', '', 8, 'approved', 1, 310, 0, 0),
(10, 19, 'เทิง อีเวนต์ โฟโต้', 'thoeng-event-photo', 'ถ่ายงานประชุม กีฬา งานโรงเรียน และกิจกรรมองค์กร เก็บจังหวะไว ส่งงานเป็นระบบ', 6, 4500, 'seed/photo-1560250097-0b93528c311a.jpg', 'seed/photo-1505373877841-8d25f7d46678.jpg', '0822222219', 'thoengevent', 'https://facebook.com/thoengevent', 'https://instagram.com/thoengevent', '', 4, 'approved', 1, 141, 0, 0),
(11, 20, 'แม่ฟ้าหลวง เวดดิ้ง', 'mae-fah-luang-wedding', 'ทีมงานแต่งและพรีเวดดิ้งบนดอย โทนภาพ cinematic พร้อมช่วยวางแผนโลเคชัน', 11, 6500, 'seed/photo-1519345182560-3f2917c472ef.jpg', 'seed/photo-1523438885200-e635ba2c371e.jpg', '0822222220', 'mflwedding', 'https://facebook.com/mflwedding', 'https://instagram.com/mflwedding', '', 15, 'approved', 1, 412, 0, 0),
(12, 21, 'เวียงแก่น แกลเลอรี', 'wiang-kaen-gallery', 'ถ่ายภาพท่องเที่ยว คู่รัก และครอบครัวในเวียงแก่น เชียงของ และริมแม่น้ำโขง', 4, 2600, 'seed/photo-1506794778202-cad84cf45f1d.jpg', 'seed/photo-1500534314209-a25ddb2bd429.jpg', '0822222221', 'wiangkaengallery', 'https://facebook.com/wiangkaengallery', 'https://instagram.com/wiangkaengallery', '', 13, 'approved', 1, 116, 0, 0),
(13, 22, 'แม่ลาว พิคเจอร์', 'mae-lao-picture', 'ถ่ายโปรไฟล์ รับปริญญา และคอนเทนต์โซเชียลในแม่ลาว เมืองเชียงราย และพาน', 5, 2100, 'seed/photo-1517841905240-472988babdf9.jpg', 'seed/photo-1520975682031-a769b5b874c3.jpg', '0822222222', 'maelaopicture', 'https://facebook.com/maelaopicture', 'https://instagram.com/maelaopicture', '', 16, 'approved', 1, 177, 0, 0);

INSERT INTO photographer_services (photographer_id, category_id, description, starting_price, is_active) VALUES
(4, 2, 'รับปริญญาและโปรไฟล์ในเมืองเชียงราย', 2200, 1),(4, 6, 'โปรไฟล์ธุรกิจ ภาพผู้บริหาร และ personal branding', 2600, 1),
(5, 1, 'พรีเวดดิ้งภูเขาและงานแต่ง intimate', 7200, 1),(5, 3, 'ถ่ายคู่รักและครอบครัวกลางธรรมชาติ', 4200, 1),
(6, 4, 'ถ่ายสินค้า ร้านค้า คาเฟ่ และคอนเทนต์แบรนด์', 3400, 1),(6, 5, 'ถ่ายงานอีเวนต์และกิจกรรมในแม่สาย', 4800, 1),
(7, 2, 'รับปริญญาและรูปครอบครัวในอำเภอพาน', 1800, 1),(7, 6, 'ภาพโปรไฟล์สมัครงานและโซเชียล', 1900, 1),
(8, 4, 'ภาพสินค้า อาหาร โรงแรม และรีสอร์ท', 4200, 1),(8, 5, 'อีเวนต์องค์กรและกิจกรรมรีสอร์ท', 5000, 1),
(9, 1, 'งานแต่งเล็กริมโขงและพิธีครอบครัว', 5900, 1),(9, 3, 'ภาพครอบครัวและทริปเชียงแสน', 3900, 1),
(10, 5, 'งานประชุม งานกีฬา และกิจกรรมองค์กร', 4500, 1),(10, 4, 'ภาพโปรโมทกิจกรรมและสื่อประชาสัมพันธ์', 3800, 1),
(11, 1, 'งานแต่งและพรีเวดดิ้งบนดอยแบบเต็มวัน', 9800, 1),(11, 6, 'โปรไฟล์คู่รักและแฟชั่น outdoor', 4200, 1),
(12, 3, 'คู่รัก ครอบครัว และท่องเที่ยวเวียงแก่น', 2600, 1),(12, 5, 'อีเวนต์เล็กและงานชุมชนริมโขง', 3600, 1),
(13, 2, 'รับปริญญาและภาพครอบครัวโซนแม่ลาว', 2100, 1),(13, 6, 'โปรไฟล์ส่วนตัวและคอนเทนต์ออนไลน์', 2300, 1);

INSERT INTO photographer_service_areas (photographer_id, district_id, is_primary, is_active) VALUES
(4, 1, 1, 1),(4, 2, 0, 1),(4, 16, 0, 1),
(5, 4, 1, 1),(5, 12, 0, 1),(5, 14, 0, 1),
(6, 9, 1, 1),(6, 8, 0, 1),(6, 15, 0, 1),
(7, 5, 1, 1),(7, 6, 0, 1),(7, 16, 0, 1),
(8, 10, 1, 1),(8, 11, 0, 1),(8, 16, 0, 1),
(9, 8, 1, 1),(9, 3, 0, 1),(9, 9, 0, 1),
(10, 4, 1, 1),(10, 12, 0, 1),(10, 14, 0, 1),
(11, 15, 1, 1),(11, 7, 0, 1),(11, 9, 0, 1),
(12, 13, 1, 1),(12, 3, 0, 1),(12, 14, 0, 1),
(13, 16, 1, 1),(13, 1, 0, 1),(13, 5, 0, 1);

INSERT INTO photographer_portfolios (photographer_id, title, description, image_path, is_featured, sort_order) VALUES
(4, 'City Portrait Set', 'พอร์ตเทรตเมืองเชียงราย', 'seed/photo-1492447166138-50c3889fccb1.jpg', 1, 1),
(4, 'Graduation Morning', 'รับปริญญาแสงเช้า', 'seed/photo-1523050854058-8df90110c9f1.jpg', 0, 2),
(4, 'Business Profile', 'ภาพผู้บริหาร', 'seed/photo-1560250097-0b93528c311a.jpg', 0, 3),
(4, 'Warm Studio', 'โทนอบอุ่นในสตูดิโอ', 'seed/photo-1512316609839-ce289d3eba0a.jpg', 0, 4),
(5, 'Mountain Prewedding', 'พรีเวดดิ้งภูเขา', 'seed/photo-1519741497674-611481863552.jpg', 1, 1),
(5, 'Golden Fog', 'หมอกเช้าและแสงทอง', 'seed/photo-1500530855697-b586d89ba3ee.jpg', 0, 2),
(5, 'Couple Journey', 'ภาพคู่รักระหว่างเดินทาง', 'seed/photo-1520854221256-17451cc331bf.jpg', 0, 3),
(5, 'Outdoor Vows', 'พิธีเล็กกลางแจ้ง', 'seed/photo-1523438885200-e635ba2c371e.jpg', 0, 4),
(6, 'Brand Event', 'อีเวนต์ธุรกิจ', 'seed/photo-1517457373958-b7bdd4587205.jpg', 1, 1),
(6, 'Cafe Campaign', 'ภาพคาเฟ่และเมนู', 'seed/photo-1445116572660-236099ec97a0.jpg', 0, 2),
(6, 'Product Lightbox', 'ภาพสินค้าแสงนุ่ม', 'seed/photo-1523275335684-37898b6baf30.jpg', 0, 3),
(6, 'Team Activity', 'กิจกรรมองค์กร', 'seed/photo-1505373877841-8d25f7d46678.jpg', 0, 4),
(7, 'Graduation Smile', 'รับปริญญาโทนสดใส', 'seed/photo-1523050854058-8df90110c9f1.jpg', 1, 1),
(7, 'Family Field', 'ครอบครัวกลางทุ่ง', 'seed/photo-1511895426328-dc8714191300.jpg', 0, 2),
(7, 'Profile Daylight', 'โปรไฟล์แสงธรรมชาติ', 'seed/photo-1531891437562-4301cf35b7e4.jpg', 0, 3),
(7, 'Casual Portrait', 'พอร์ตเทรตเรียบง่าย', 'seed/photo-1544005313-94ddf0286df2.jpg', 0, 4),
(8, 'Resort Dining', 'ภาพอาหารรีสอร์ท', 'seed/photo-1414235077428-338989a2e8c0.jpg', 1, 1),
(8, 'Hotel Room', 'ภาพห้องพัก', 'seed/photo-1505693416388-ac5ce068fe85.jpg', 0, 2),
(8, 'Coffee Texture', 'ภาพเครื่องดื่ม', 'seed/photo-1495474472287-4d71bcdd2085.jpg', 0, 3),
(8, 'Lifestyle Product', 'สินค้าไลฟ์สไตล์', 'seed/photo-1511556820780-d912e42b4980.jpg', 0, 4),
(9, 'Mekong Wedding', 'งานแต่งริมโขง', 'seed/photo-1511285560929-80b456fea0bc.jpg', 1, 1),
(9, 'Family River', 'ครอบครัวริมแม่น้ำ', 'seed/photo-1511895426328-dc8714191300.jpg', 0, 2),
(9, 'Travel Couple', 'คู่รักท่องเที่ยว', 'seed/photo-1500534314209-a25ddb2bd429.jpg', 0, 3),
(9, 'Golden Triangle', 'ภาพทริปเชียงแสน', 'seed/photo-1470770841072-f978cf4d019e.jpg', 0, 4),
(10, 'Conference Hall', 'งานประชุม', 'seed/photo-1505373877841-8d25f7d46678.jpg', 1, 1),
(10, 'Stage Moment', 'บนเวที', 'seed/photo-1501612780327-45045538702b.jpg', 0, 2),
(10, 'School Activity', 'กิจกรรมโรงเรียน', 'seed/photo-1523580846011-d3a5bc25702b.jpg', 0, 3),
(10, 'Corporate Team', 'ภาพทีมองค์กร', 'seed/photo-1551836022-d5d88e9218df.jpg', 0, 4),
(11, 'Doi Wedding', 'งานแต่งบนดอย', 'seed/photo-1523438885200-e635ba2c371e.jpg', 1, 1),
(11, 'Bride Portrait', 'พอร์ตเทรตเจ้าสาว', 'seed/photo-1519741497674-611481863552.jpg', 0, 2),
(11, 'Cinematic Couple', 'คู่รัก cinematic', 'seed/photo-1520854221256-17451cc331bf.jpg', 0, 3),
(11, 'Outdoor Fashion', 'แฟชั่น outdoor', 'seed/photo-1487412720507-e7ab37603c6f.jpg', 0, 4),
(12, 'River Family', 'ครอบครัวเวียงแก่น', 'seed/photo-1511895426328-dc8714191300.jpg', 1, 1),
(12, 'Local Event', 'งานชุมชน', 'seed/photo-1501281668745-f7f57925c3b4.jpg', 0, 2),
(12, 'Travel Portrait', 'พอร์ตเทรตท่องเที่ยว', 'seed/photo-1524504388940-b1c1722653e1.jpg', 0, 3),
(12, 'Mekong Lifestyle', 'ไลฟ์สไตล์ริมโขง', 'seed/photo-1500534314209-a25ddb2bd429.jpg', 0, 4),
(13, 'Mae Lao Profile', 'โปรไฟล์แม่ลาว', 'seed/photo-1512316609839-ce289d3eba0a.jpg', 1, 1),
(13, 'Graduation Family', 'รับปริญญาครอบครัว', 'seed/photo-1523050854058-8df90110c9f1.jpg', 0, 2),
(13, 'Social Content', 'คอนเทนต์โซเชียล', 'seed/photo-1544005313-94ddf0286df2.jpg', 0, 3),
(13, 'Warm Portrait', 'พอร์ตเทรตอบอุ่น', 'seed/photo-1534528741775-53994a69daeb.jpg', 0, 4);

INSERT INTO photographer_availability (photographer_id, available_date, time_slot, status, note) VALUES
(4, '2026-06-04', 'morning', 'available', ''),(4, '2026-06-04', 'afternoon', 'available', ''),(4, '2026-06-08', 'full_day', 'available', ''),
(5, '2026-06-05', 'full_day', 'available', ''),(5, '2026-06-12', 'morning', 'available', ''),
(6, '2026-06-06', 'morning', 'available', ''),(6, '2026-06-06', 'afternoon', 'available', ''),
(7, '2026-06-07', 'full_day', 'available', ''),(7, '2026-06-13', 'morning', 'available', ''),
(8, '2026-06-08', 'afternoon', 'available', ''),(8, '2026-06-15', 'full_day', 'available', ''),
(9, '2026-06-09', 'morning', 'available', ''),(9, '2026-06-16', 'full_day', 'available', ''),
(10, '2026-06-10', 'full_day', 'available', ''),(10, '2026-06-17', 'afternoon', 'available', ''),
(11, '2026-06-11', 'full_day', 'available', ''),(11, '2026-06-18', 'morning', 'available', ''),
(12, '2026-06-12', 'afternoon', 'available', ''),(12, '2026-06-19', 'full_day', 'available', ''),
(13, '2026-06-13', 'morning', 'available', ''),(13, '2026-06-20', 'afternoon', 'available', '');

INSERT INTO bookings (id, booking_code, customer_id, photographer_id, category_id, district_id, booking_date, time_slot, contact_name, contact_phone, contact_channel, job_detail, note, status, completed_at) VALUES
(2, 'CRB260402000002', 6, 4, 6, 1, '2026-04-02', 'morning', 'ภัทรา ใจดี', '0811111106', 'LINE: pattra', 'ถ่ายโปรไฟล์ธุรกิจในเมืองเชียงราย', '', 'completed', '2026-04-02 14:00:00'),
(3, 'CRB260403000003', 7, 5, 1, 4, '2026-04-03', 'full_day', 'ณัฐพล เชียงราย', '0811111107', 'Facebook', 'ถ่ายพรีเวดดิ้งบนภูเขา', 'อยากได้หมอกเช้า', 'completed', '2026-04-03 18:00:00'),
(4, 'CRB260404000004', 8, 6, 4, 9, '2026-04-04', 'afternoon', 'ชลธิชา เมืองเหนือ', '0811111108', 'LINE: chon', 'ถ่ายภาพสินค้าและร้านค้า', '', 'completed', '2026-04-04 16:00:00'),
(5, 'CRB260405000005', 9, 7, 2, 5, '2026-04-05', 'morning', 'กิตติพงษ์ แม่สาย', '0811111109', 'โทรศัพท์', 'ถ่ายรับปริญญาพร้อมครอบครัว', '', 'completed', '2026-04-05 13:00:00'),
(6, 'CRB260406000006', 10, 8, 4, 10, '2026-04-06', 'full_day', 'อรทัย พาน', '0811111110', 'LINE: ornthai', 'ถ่ายภาพรีสอร์ทและอาหาร', '', 'completed', '2026-04-06 17:00:00'),
(7, 'CRB260407000007', 11, 9, 3, 8, '2026-04-07', 'afternoon', 'ธนวัฒน์ เทิง', '0811111112', 'Facebook', 'ถ่ายครอบครัวริมโขง', '', 'completed', '2026-04-07 16:30:00'),
(8, 'CRB260408000008', 12, 10, 5, 4, '2026-04-08', 'full_day', 'มินตรา แม่จัน', '0811111113', 'LINE: mintra', 'ถ่ายงานสัมมนาบริษัท', '', 'completed', '2026-04-08 18:00:00'),
(9, 'CRB260409000009', 6, 11, 1, 15, '2026-04-09', 'full_day', 'ภัทรา ใจดี', '0811111106', 'LINE: pattra', 'ถ่ายงานแต่งบนดอย', '', 'completed', '2026-04-09 20:00:00'),
(10, 'CRB260410000010', 7, 12, 3, 13, '2026-04-10', 'morning', 'ณัฐพล เชียงราย', '0811111107', 'โทรศัพท์', 'ถ่ายภาพทริปครอบครัว', '', 'completed', '2026-04-10 13:00:00'),
(11, 'CRB260411000011', 8, 13, 6, 16, '2026-04-11', 'afternoon', 'ชลธิชา เมืองเหนือ', '0811111108', 'Instagram', 'ถ่ายโปรไฟล์โซเชียล', '', 'completed', '2026-04-11 16:00:00'),
(12, 'CRB260412000012', 9, 1, 1, 1, '2026-04-12', 'full_day', 'กิตติพงษ์ แม่สาย', '0811111109', 'LINE: kitti', 'ถ่ายงานแต่งในสวน', '', 'completed', '2026-04-12 19:00:00'),
(13, 'CRB260413000013', 10, 2, 5, 3, '2026-04-13', 'morning', 'อรทัย พาน', '0811111110', 'Facebook', 'ถ่ายอีเวนต์ริมโขง', '', 'completed', '2026-04-13 14:00:00'),
(14, 'CRB260414000014', 11, 4, 2, 1, '2026-04-14', 'afternoon', 'ธนวัฒน์ เทิง', '0811111112', 'LINE: thanawat', 'ถ่ายรับปริญญาในเมือง', '', 'completed', '2026-04-14 17:00:00'),
(15, 'CRB260415000015', 12, 5, 3, 4, '2026-04-15', 'full_day', 'มินตรา แม่จัน', '0811111113', 'โทรศัพท์', 'ถ่ายคู่รักบนภูเขา', '', 'completed', '2026-04-15 18:00:00'),
(16, 'CRB260416000016', 6, 6, 4, 9, '2026-04-16', 'morning', 'ภัทรา ใจดี', '0811111106', 'LINE: pattra', 'ถ่ายสินค้าเปิดตัวใหม่', '', 'completed', '2026-04-16 12:00:00'),
(17, 'CRB260417000017', 7, 7, 6, 5, '2026-04-17', 'afternoon', 'ณัฐพล เชียงราย', '0811111107', 'Facebook', 'ถ่ายภาพโปรไฟล์ทีม', '', 'completed', '2026-04-17 16:00:00'),
(18, 'CRB260418000018', 8, 8, 4, 10, '2026-04-18', 'full_day', 'ชลธิชา เมืองเหนือ', '0811111108', 'LINE: chon', 'ถ่ายภาพโรงแรม', '', 'completed', '2026-04-18 18:00:00'),
(19, 'CRB260501000019', 9, 9, 3, 8, '2026-05-01', 'morning', 'กิตติพงษ์ แม่สาย', '0811111109', 'โทรศัพท์', 'ถ่ายภาพครอบครัว', '', 'accepted', NULL),
(20, 'CRB260502000020', 10, 10, 5, 4, '2026-05-02', 'full_day', 'อรทัย พาน', '0811111110', 'LINE: ornthai', 'ถ่ายงานเปิดตัวสินค้า', '', 'pending', NULL),
(21, 'CRB260503000021', 11, 11, 1, 15, '2026-05-03', 'full_day', 'ธนวัฒน์ เทิง', '0811111112', 'Facebook', 'สอบถามแพ็กเกจพรีเวดดิ้ง', '', 'confirmed', NULL),
(22, 'CRB260419000022', 9, 12, 5, 13, '2026-04-19', 'afternoon', 'กิตติพงษ์ แม่สาย', '0811111109', 'LINE: kitti', 'ถ่ายงานชุมชนริมโขง', '', 'completed', '2026-04-19 17:00:00'),
(23, 'CRB260420000023', 10, 13, 2, 16, '2026-04-20', 'morning', 'อรทัย พาน', '0811111110', 'Facebook', 'ถ่ายรับปริญญาครอบครัว', '', 'completed', '2026-04-20 13:00:00');

INSERT INTO reviews (id, booking_id, customer_id, photographer_id, rating_overall, rating_quality, rating_communication, rating_punctuality, rating_professional, comment, status) VALUES
(2, 2, 6, 4, 5, 5, 5, 5, 5, 'ภาพโปรไฟล์ดูมืออาชีพมาก ช่วยแนะนำท่าทางดีมาก', 'visible'),
(3, 3, 7, 5, 5, 5, 5, 4, 5, 'แสงเช้าสวยมาก ทีมงานตรงเวลาและช่วยเลือกมุมได้ดี', 'visible'),
(4, 4, 8, 6, 4, 5, 4, 4, 4, 'รูปสินค้าใช้งานขายออนไลน์ได้ดี สีตรงและส่งไฟล์ครบ', 'visible'),
(5, 5, 9, 7, 5, 5, 5, 5, 5, 'บรรยากาศสบาย ครอบครัวชอบรูปมาก', 'visible'),
(6, 6, 10, 8, 5, 5, 4, 5, 5, 'ภาพอาหารและห้องพักดูแพงขึ้นมาก', 'visible'),
(7, 7, 11, 9, 4, 4, 5, 4, 4, 'ถ่ายเด็กเก่งและใจเย็น ได้ภาพธรรมชาติ', 'visible'),
(8, 8, 12, 10, 5, 5, 5, 5, 5, 'งานอีเวนต์เก็บครบทุกช่วงสำคัญ ส่งงานเป็นระบบ', 'visible'),
(9, 9, 6, 11, 5, 5, 5, 5, 5, 'ภาพงานแต่งสวยเหมือนหนัง ประทับใจมาก', 'visible'),
(10, 10, 7, 12, 4, 4, 4, 5, 4, 'โลเคชันดี ภาพครอบครัวออกมาอบอุ่น', 'visible'),
(11, 11, 8, 13, 5, 5, 5, 5, 5, 'โปรไฟล์โซเชียลสวยและได้ไฟล์เร็ว', 'visible'),
(12, 12, 9, 1, 5, 5, 5, 5, 5, 'งานแต่งโทนอบอุ่น เก็บโมเมนต์ดีมาก', 'visible'),
(13, 13, 10, 2, 4, 4, 5, 4, 4, 'ถ่ายอีเวนต์ครบและคุยง่าย', 'visible'),
(14, 14, 11, 4, 5, 5, 5, 5, 5, 'รูปออกมาดูแพงกว่าที่คิด ชอบมาก', 'visible'),
(15, 15, 12, 5, 5, 5, 4, 5, 5, 'ภูเขาและแสงสวย ทีมงานช่วยดูแลดี', 'visible'),
(16, 16, 6, 6, 4, 4, 4, 5, 4, 'รูปสินค้าชัดเจน เหมาะกับลงเว็บ', 'visible'),
(17, 17, 7, 7, 5, 5, 5, 4, 5, 'ภาพทีมดูเป็นธรรมชาติ ไม่แข็ง', 'visible'),
(18, 18, 8, 8, 5, 5, 5, 5, 5, 'ภาพโรงแรมดูน่าเข้าพักขึ้นมาก', 'visible'),
(19, 22, 9, 12, 4, 4, 5, 4, 4, 'ถ่ายงานชุมชนได้ครบและบรรยากาศดี', 'visible'),
(20, 23, 10, 13, 5, 5, 5, 5, 5, 'รับปริญญาครอบครัวออกมาน่ารักมาก', 'visible');

INSERT INTO photographer_articles (photographer_id, title, slug, cover_image, content, status, published_at) VALUES
(4, 'เลือกชุดถ่ายโปรไฟล์ธุรกิจอย่างไรให้ดูน่าเชื่อถือ', 'business-profile-outfit', 'seed/photo-1560250097-0b93528c311a.jpg', 'เลือกสีเรียบ เนื้อผ้าดี และเตรียมชุดสำรองอย่างน้อยหนึ่งชุดเพื่อให้ภาพมีตัวเลือกหลากหลาย', 'published', NOW()),
(5, 'ช่วงเวลาที่เหมาะกับการถ่ายพรีเวดดิ้งบนภูเขา', 'mountain-prewedding-time', 'seed/photo-1500530855697-b586d89ba3ee.jpg', 'แสงเช้าและก่อนพระอาทิตย์ตกช่วยให้ผิวดูนุ่มและเห็นมิติของภูเขาชัดเจน', 'published', NOW()),
(6, 'ถ่ายภาพสินค้าให้ขายดีต้องเตรียมอะไรบ้าง', 'product-photo-brief', 'seed/photo-1523275335684-37898b6baf30.jpg', 'เตรียมสินค้าให้สะอาด ระบุ mood ของแบรนด์ และทำ shot list ก่อนวันถ่าย', 'published', NOW()),
(7, 'ท่าโพสรับปริญญาที่ดูธรรมชาติ', 'natural-graduation-pose', 'seed/photo-1523050854058-8df90110c9f1.jpg', 'เริ่มจากเดิน หัวเราะ และคุยกับครอบครัว ช่วยให้ภาพไม่แข็งและดูเป็นตัวเอง', 'published', NOW()),
(8, 'ภาพโรงแรมและรีสอร์ทควรมีมุมไหนบ้าง', 'hotel-photo-shot-list', 'seed/photo-1505693416388-ac5ce068fe85.jpg', 'ควรมีภาพห้องพัก รายละเอียดสิ่งอำนวยความสะดวก อาหาร บรรยากาศ และ lifestyle scene', 'published', NOW()),
(9, 'ไอเดียถ่ายภาพครอบครัวริมแม่น้ำโขง', 'mekong-family-ideas', 'seed/photo-1511895426328-dc8714191300.jpg', 'เลือกกิจกรรมง่าย ๆ เช่น เดินเล่น จับมือ หรือเล่นกับเด็ก เพื่อให้ภาพมีเรื่องราว', 'published', NOW()),
(10, 'เช็กลิสต์ก่อนถ่ายงานอีเวนต์องค์กร', 'event-photo-checklist', 'seed/photo-1505373877841-8d25f7d46678.jpg', 'ส่งกำหนดการ รายชื่อบุคคลสำคัญ และจุดที่ต้องเก็บภาพให้ช่างภาพก่อนวันงาน', 'published', NOW()),
(11, 'เตรียม mood board งานแต่งให้ช่างภาพเข้าใจเร็ว', 'wedding-moodboard-guide', 'seed/photo-1523438885200-e635ba2c371e.jpg', 'รวมโทนสี ตัวอย่างภาพ และสิ่งที่ไม่ชอบไว้ในไฟล์เดียว ช่วยลดการสื่อสารผิดพลาด', 'published', NOW());

UPDATE photographer_profiles p
SET average_rating = (
    SELECT COALESCE(ROUND(AVG(r.rating_overall), 2), 0)
    FROM reviews r
    WHERE r.photographer_id = p.id AND r.status = 'visible' AND r.deleted_at IS NULL
),
total_reviews = (
    SELECT COUNT(*)
    FROM reviews r
    WHERE r.photographer_id = p.id AND r.status = 'visible' AND r.deleted_at IS NULL
);

INSERT INTO booking_status_logs (booking_id, old_status, new_status, changed_by, note)
SELECT id, NULL, status, customer_id, 'Seed booking status'
FROM bookings
WHERE id BETWEEN 2 AND 23;

UPDATE photographer_profiles
SET response_rate = 96.50,
    average_response_hours = 2.20,
    is_verified = 1,
    verified_at = NOW()
WHERE approval_status = 'approved';

UPDATE photographer_profiles
SET is_featured = 1,
    featured_until = DATE_ADD(NOW(), INTERVAL 45 DAY)
WHERE id IN (1, 4, 5, 8, 11);

INSERT INTO favorite_photographers (customer_id, photographer_id) VALUES
(2, 1), (2, 4), (6, 4), (6, 11), (7, 5), (8, 6), (9, 9), (10, 8), (11, 10), (12, 13);

INSERT INTO notifications (user_id, title, message, type, related_id, is_read, created_at) VALUES
(1, 'มีรายงานใหม่รอตรวจสอบ', 'ลูกค้าส่งรายงานเนื้อหาที่ต้องตรวจสอบในระบบ', 'report', 1, 0, NOW()),
(2, 'สถานะคำขอจองเปลี่ยนแปลง', 'CRB260501AAAAAA เป็น เสร็จสิ้น', 'booking', 1, 0, NOW()),
(3, 'มีรีวิวใหม่', 'CRB260501AAAAAA ได้รับรีวิวใหม่จากลูกค้า', 'review', 1, 0, NOW()),
(10, 'คำขอจองกำลังรอตอบรับ', 'CRB260502000020 รอช่างภาพตอบรับ', 'booking', 20, 0, NOW()),
(19, 'มีคำขอจองใหม่', 'CRB260502000020 รอการตอบรับจากช่างภาพ', 'booking', 20, 0, NOW());

INSERT INTO faqs (category, question, answer, is_active, sort_order) VALUES
('การจอง', 'ต้องเข้าสู่ระบบก่อนส่งคำขอจองไหม', 'ต้องเข้าสู่ระบบก่อน เพื่อให้ระบบบันทึกประวัติคำขอจองและแจ้งเตือนได้ถูกต้อง', 1, 1),
('การติดต่อ', 'เว็บไซต์รับชำระเงินแทนช่างภาพไหม', 'ไม่รับชำระเงิน เว็บไซต์เป็นเพียงตัวกลางค้นหา ส่งคำขอจอง และติดต่อช่างภาพเท่านั้น', 1, 2),
('รีวิว', 'รีวิวช่างภาพได้เมื่อไหร่', 'รีวิวได้เมื่อ booking มีสถานะ completed และ 1 booking รีวิวได้ 1 ครั้ง', 1, 3),
('ช่างภาพ', 'ช่างภาพต้องได้รับอนุมัติก่อนแสดงผลไหม', 'ใช่ ช่างภาพต้องได้รับการอนุมัติจาก Admin ก่อนจึงจะแสดงในหน้าค้นหา', 1, 4),
('การค้นหา', 'ถ้าไม่พบช่างภาพในอำเภอที่เลือกทำอย่างไร', 'ระบบจะแนะนำช่างภาพใกล้เคียงโดยคำนวณจาก latitude/longitude ของอำเภอ', 1, 5);

INSERT INTO blogs (admin_id, title, slug, cover_image, excerpt, content, status, published_at) VALUES
(1, 'วิธีเลือกช่างภาพให้เหมาะกับงาน', 'choose-right-photographer', 'seed/photo-1492691527719-9d1e07e534b4.jpg', 'เช็กลิสต์สั้น ๆ ก่อนเลือกช่างภาพให้ตรงกับสไตล์และงบประมาณ', 'เริ่มจากดูตัวอย่างงานถ่ายภาพให้ตรงกับงาน ตรวจพื้นที่ให้บริการ อ่านรีวิว และคุยรายละเอียดวัน เวลา จำนวนคน และไฟล์ที่ต้องการก่อนส่งคำขอจอง', 'published', NOW()),
(1, 'เตรียมตัวก่อนถ่ายรับปริญญา', 'graduation-photo-preparation', 'seed/photo-1523050854058-8df90110c9f1.jpg', 'เตรียมชุด เวลา และแผนถ่ายภาพให้วันถ่ายราบรื่น', 'เตรียมชุดสำรอง นัดหมายเวลาชัดเจน เลือกโลเคชันหลักและสำรอง พร้อมแจ้งจำนวนสมาชิกครอบครัวให้ช่างภาพทราบล่วงหน้า', 'published', NOW()),
(1, 'ควรถามอะไรช่างภาพก่อนจอง', 'questions-before-booking-photographer', 'seed/photo-1516035069371-29a1b244cc32.jpg', 'คำถามสำคัญที่ช่วยลดความเข้าใจผิดก่อนวันถ่าย', 'สอบถามระยะเวลาถ่าย จำนวนรูปที่ส่ง ระยะเวลาส่งงาน วิธีส่งไฟล์ ค่าเดินทาง และช่องทางติดต่อหลักให้ครบก่อนตกลงรายละเอียด', 'published', NOW()),
(1, 'เช็กลิสต์ก่อนวันถ่ายภาพ', 'photo-day-checklist', 'seed/photo-1520854221256-17451cc331bf.jpg', 'รวมสิ่งที่ควรเตรียมก่อนถึงวันถ่ายจริง', 'เตรียม reference ชุด พร็อพ แผนเดินทาง และเผื่อเวลาแต่งหน้าเดินทางอย่างน้อย 30-60 นาที เพื่อให้วันถ่ายไม่เร่งเกินไป', 'published', NOW());

INSERT INTO tags (id, name, slug) VALUES
(1, 'งานแต่งงาน', 'wedding-th'),
(2, 'รับปริญญา', 'graduation-th'),
(3, 'ครอบครัว', 'family-th'),
(4, 'สินค้าและร้านค้า', 'product-th'),
(5, 'อีเวนต์', 'event-th'),
(6, 'โปรไฟล์ส่วนตัว', 'portrait-th'),
(7, 'เมืองเชียงราย', 'mueang-chiang-rai'),
(8, 'เวียงชัย', 'wiang-chai'),
(9, 'เชียงของ', 'chiang-khong'),
(10, 'เทิง', 'thoeng'),
(11, 'พาน', 'phan'),
(12, 'ป่าแดด', 'pa-daed'),
(13, 'แม่จัน', 'mae-chan'),
(14, 'เชียงแสน', 'chiang-saen'),
(15, 'แม่สาย', 'mae-sai'),
(16, 'แม่สรวย', 'mae-suai'),
(17, 'เวียงป่าเป้า', 'wiang-pa-pao'),
(18, 'พญาเม็งราย', 'phaya-mengrai'),
(19, 'เวียงแก่น', 'wiang-kaen'),
(20, 'ขุนตาล', 'khun-tan'),
(21, 'แม่ฟ้าหลวง', 'mae-fah-luang'),
(22, 'แม่ลาว', 'mae-lao'),
(23, 'เวียงเชียงรุ้ง', 'wiang-chiang-rung'),
(24, 'ดอยหลวง', 'doi-luang');

INSERT INTO blog_tags (blog_id, tag_id) VALUES
(1, 7), (1, 6), (2, 2), (2, 7), (3, 7), (4, 6);

INSERT INTO article_tags (article_id, tag_id) VALUES
(1, 1), (2, 3), (3, 6), (4, 1), (5, 4), (6, 2);

INSERT INTO portfolio_tags (portfolio_id, tag_id)
SELECT id, 7 FROM photographer_portfolios WHERE id <= 12;

INSERT INTO contact_messages (name, email, phone, subject, message, status) VALUES
('ลูกค้าทดลอง', 'demo-contact@example.com', '0800000000', 'สอบถามการใช้งาน', 'ต้องการทราบวิธีติดต่อช่างภาพหลังส่งคำขอจอง', 'unread');

INSERT INTO reports (reporter_id, target_type, target_id, reason, detail, status) VALUES
(2, 'photographer', 1, 'ข้อมูลติดต่อไม่ชัดเจน', 'อยากให้ตรวจสอบ LINE ID อีกครั้ง', 'pending'),
(6, 'review', 1, 'รีวิวไม่เหมาะสม', 'ขอให้ Admin ตรวจสอบข้อความรีวิว', 'reviewed');

INSERT INTO search_logs (user_id, keyword, district_id, category_id, search_date, ip_address) VALUES
(2, 'งานแต่ง', 1, 1, CURDATE(), '127.0.0.1'),
(6, 'โปรไฟล์', 16, 6, CURDATE(), '127.0.0.1'),
(NULL, 'รับปริญญา', 5, 2, CURDATE(), '127.0.0.1');

INSERT INTO recently_viewed_photographers (user_id, photographer_id, viewed_at) VALUES
(2, 1, NOW()), (2, 4, NOW()), (6, 11, NOW()), (7, 5, NOW());

-- Demo password for every seeded account is: password
UPDATE users
SET password = '$2y$10$5/.NSKFxxQ71K2q0.6njbuvE88GP9guaysk3L/cc/.cxQU4O.KrUa'
WHERE id BETWEEN 1 AND 22;

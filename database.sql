CREATE DATABASE IF NOT EXISTS chiangrai_photographer_booking
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE chiangrai_photographer_booking;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS review_images;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS booking_status_logs;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS photographer_availability;
DROP TABLE IF EXISTS photographer_portfolios;
DROP TABLE IF EXISTS photographer_service_areas;
DROP TABLE IF EXISTS photographer_services;
DROP TABLE IF EXISTS photographer_articles;
DROP TABLE IF EXISTS banners;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS activity_logs;
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
  token VARCHAR(128) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_password_resets_email (email),
  UNIQUE KEY uk_password_resets_token (token)
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
  UNIQUE KEY uk_service_categories_slug (slug),
  KEY idx_service_categories_active (is_active, sort_order)
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
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uk_photographer_user (user_id),
  UNIQUE KEY uk_photographer_slug (slug),
  KEY idx_photographer_approval (approval_status, is_available),
  KEY idx_photographer_price (starting_price),
  KEY idx_photographer_rating (average_rating, total_reviews),
  KEY idx_photographer_district (main_district_id),
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

CREATE TABLE banners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  subtitle VARCHAR(255) NULL,
  image_path VARCHAR(255) NULL,
  button_text VARCHAR(100) NULL,
  button_url VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_banners_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL,
  setting_value TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

INSERT INTO banners (title, subtitle, image_path, button_text, button_url, is_active, sort_order) VALUES
('ค้นหาช่างภาพมืออาชีพในจังหวัดเชียงราย', 'ดูโปรไฟล์ ตรวจวันว่าง และส่งคำขอจองได้ทันที', NULL, 'ค้นหาช่างภาพ', '/photographers.php', 1, 1);

INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'Chiang Rai Photographer Booking'),
('footer_text', 'เว็บไซต์เป็นตัวกลางค้นหาและติดต่อช่างภาพในจังหวัดเชียงราย'),
('admin_email', 'admin@example.com'),
('admin_phone', '0890000000'),
('allow_photographer_registration', '1'),
('nearby_radius_km', '30');

INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, user_agent, description) VALUES
(1, 'seed_database', 'settings', 1, '127.0.0.1', 'Seeder', 'Initial database seed');


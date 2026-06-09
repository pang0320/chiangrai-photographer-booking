USE chiangrai_photographer_booking;

ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS start_date DATE NULL AFTER district_id,
  ADD COLUMN IF NOT EXISTS end_date DATE NULL AFTER start_date,
  ADD COLUMN IF NOT EXISTS start_time TIME NULL AFTER end_date,
  ADD COLUMN IF NOT EXISTS end_time TIME NULL AFTER start_time;

UPDATE bookings
SET start_date = COALESCE(start_date, booking_date),
    end_date = COALESCE(end_date, booking_date),
    start_time = COALESCE(start_time, CASE time_slot WHEN 'morning' THEN '09:00:00' WHEN 'afternoon' THEN '13:00:00' WHEN 'evening' THEN '17:00:00' ELSE '09:00:00' END),
    end_time = COALESCE(end_time, CASE time_slot WHEN 'morning' THEN '12:00:00' WHEN 'afternoon' THEN '17:00:00' WHEN 'evening' THEN '20:00:00' ELSE '17:00:00' END)
WHERE start_date IS NULL OR end_date IS NULL OR start_time IS NULL OR end_time IS NULL;

UPDATE bookings SET status = 'accepted' WHERE status = 'confirmed';

ALTER TABLE bookings
  MODIFY status ENUM('pending','accepted','rejected','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending';

CREATE INDEX IF NOT EXISTS idx_bookings_range ON bookings (photographer_id, start_date, end_date, start_time, end_time, status);

ALTER TABLE photographer_availability
  ADD COLUMN IF NOT EXISTS start_date DATE NULL AFTER photographer_id,
  ADD COLUMN IF NOT EXISTS end_date DATE NULL AFTER start_date,
  ADD COLUMN IF NOT EXISTS start_time TIME NULL AFTER end_date,
  ADD COLUMN IF NOT EXISTS end_time TIME NULL AFTER start_time;

UPDATE photographer_availability
SET start_date = COALESCE(start_date, available_date),
    end_date = COALESCE(end_date, available_date),
    start_time = COALESCE(start_time, CASE time_slot WHEN 'morning' THEN '09:00:00' WHEN 'afternoon' THEN '13:00:00' WHEN 'evening' THEN '17:00:00' ELSE '09:00:00' END),
    end_time = COALESCE(end_time, CASE time_slot WHEN 'morning' THEN '12:00:00' WHEN 'afternoon' THEN '17:00:00' WHEN 'evening' THEN '20:00:00' ELSE '17:00:00' END)
WHERE start_date IS NULL OR end_date IS NULL OR start_time IS NULL OR end_time IS NULL;

ALTER TABLE photographer_availability DROP INDEX IF EXISTS uk_availability_slot;
CREATE INDEX IF NOT EXISTS idx_availability_range ON photographer_availability (photographer_id, start_date, end_date, start_time, end_time, status);

CREATE TABLE IF NOT EXISTS specialty_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  photographer_id INT UNSIGNED NOT NULL,
  specialty_name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_specialty_requests_photographer (photographer_id, status),
  KEY idx_specialty_requests_status (status, created_at),
  CONSTRAINT fk_specialty_requests_photographer FOREIGN KEY (photographer_id) REFERENCES photographer_profiles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS prime_fit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE prime_fit;
CREATE TABLE IF NOT EXISTS users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role ENUM('Admin','Gérant','Réceptionniste','Coach','Comptable') NOT NULL, is_active BOOLEAN NOT NULL DEFAULT TRUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS members (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(30), email VARCHAR(190), card_number VARCHAR(50) UNIQUE, governorate VARCHAR(80), birth_date DATE, avatar_color VARCHAR(20) DEFAULT 'gold', status ENUM('active','inactive','blacklisted') NOT NULL DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_member_name(last_name,first_name)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS plans (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, duration_days INT UNSIGNED NOT NULL, price DECIMAL(10,3) NOT NULL, is_active BOOLEAN NOT NULL DEFAULT TRUE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS subscriptions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, member_id INT UNSIGNED NOT NULL, plan_id INT UNSIGNED NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, status ENUM('active','frozen','cancelled') NOT NULL DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(member_id) REFERENCES members(id), FOREIGN KEY(plan_id) REFERENCES plans(id), INDEX idx_subscription_dates(status,end_date), INDEX idx_subscription_member(member_id)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS payments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, member_id INT UNSIGNED NOT NULL, subscription_id INT UNSIGNED NULL, amount DECIMAL(10,3) NOT NULL, method ENUM('espèces','chèque','virement','D17','Flouci','carte bancaire') NOT NULL, paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(member_id) REFERENCES members(id), FOREIGN KEY(subscription_id) REFERENCES subscriptions(id), INDEX idx_payment_date(paid_at)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS attendance (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, member_id INT UNSIGNED NOT NULL, checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, result ENUM('accepted','refused') NOT NULL, FOREIGN KEY(member_id) REFERENCES members(id), INDEX idx_attendance_date(checked_in_at)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS activity_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NULL, action VARCHAR(255) NOT NULL, ip_address VARCHAR(45), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL, INDEX idx_activity_date(created_at)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  room_id INT UNSIGNED NOT NULL,
  coach_id INT UNSIGNED NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  capacity INT UNSIGNED NOT NULL,
  status ENUM('scheduled','cancelled') NOT NULL DEFAULT 'scheduled',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES rooms(id),
  FOREIGN KEY (coach_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_courses_start (starts_at),
  INDEX idx_courses_room (room_id, starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS course_bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  member_id INT UNSIGNED NOT NULL,
  status ENUM('confirmed','waiting','cancelled') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id),
  FOREIGN KEY (member_id) REFERENCES members(id),
  UNIQUE KEY uq_course_member (course_id, member_id),
  INDEX idx_booking_queue (course_id, status, created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

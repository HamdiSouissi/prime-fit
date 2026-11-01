CREATE DATABASE IF NOT EXISTS prime_fit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE prime_fit;
CREATE TABLE IF NOT EXISTS users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role ENUM('Admin','Gérant','Réceptionniste','Coach','Comptable') NOT NULL, is_active BOOLEAN NOT NULL DEFAULT TRUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
-- Après sauvegarde, sélectionner la base de l'application avant import.
-- Table indépendante des comptes et des sessions ; les clés sont des empreintes.
CREATE TABLE IF NOT EXISTS auth_rate_limits (
  bucket_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  window_started BIGINT UNSIGNED NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  INDEX idx_auth_limit_window (window_started)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS members (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(30), email VARCHAR(190), card_number VARCHAR(50) UNIQUE, qr_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE, governorate VARCHAR(80), birth_date DATE, avatar_color VARCHAR(20) DEFAULT 'gold', status ENUM('active','inactive','blacklisted') NOT NULL DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_member_name(last_name,first_name)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS plans (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, duration_days INT UNSIGNED NOT NULL, price DECIMAL(10,3) NOT NULL, is_active BOOLEAN NOT NULL DEFAULT TRUE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS subscriptions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, member_id INT UNSIGNED NOT NULL, plan_id INT UNSIGNED NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, agreed_price DECIMAL(10,3) NULL, status ENUM('active','frozen','cancelled') NOT NULL DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(member_id) REFERENCES members(id), FOREIGN KEY(plan_id) REFERENCES plans(id), INDEX idx_subscription_dates(status,end_date), INDEX idx_subscription_member(member_id)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS payments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, member_id INT UNSIGNED NOT NULL, subscription_id INT UNSIGNED NULL, amount DECIMAL(10,3) NOT NULL, method ENUM('espèces','chèque','virement','D17','Flouci','carte bancaire') NOT NULL, paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(member_id) REFERENCES members(id), FOREIGN KEY(subscription_id) REFERENCES subscriptions(id), INDEX idx_payment_date(paid_at)) ENGINE=InnoDB;
-- Sélectionner la base utilisée et sauvegarder avant installation.
CREATE TABLE IF NOT EXISTS payment_refunds (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,3) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  request_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  created_by INT UNSIGNED NULL,
  refunded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (payment_id) REFERENCES payments(id),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_refunds_payment (payment_id),
  INDEX idx_refunds_date (refunded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
CREATE TABLE IF NOT EXISTS coach_profiles (
  user_id INT UNSIGNED PRIMARY KEY,
  phone VARCHAR(30) NULL,
  specialties VARCHAR(255) NOT NULL DEFAULT '',
  commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS private_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coach_id INT UNSIGNED NOT NULL,
  member_id INT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  price DECIMAL(10,3) NOT NULL,
  commission_rate DECIMAL(5,2) NOT NULL,
  status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (coach_id) REFERENCES users(id),
  FOREIGN KEY (member_id) REFERENCES members(id),
  INDEX idx_private_coach_start (coach_id, starts_at),
  INDEX idx_private_member_start (member_id, starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS fitness_measurements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NOT NULL,
  measured_on DATE NOT NULL,
  weight_kg DECIMAL(5,2) NOT NULL,
  height_cm DECIMAL(5,2) NOT NULL,
  waist_cm DECIMAL(5,2) DEFAULT NULL,
  notes VARCHAR(500) NOT NULL DEFAULT '',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(id),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_fitness_member_date (member_id, measured_on, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS fitness_programs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  objective VARCHAR(500) NOT NULL,
  content TEXT NOT NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(id),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_program_member_status (member_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS products (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 sku VARCHAR(50) NOT NULL UNIQUE,
 price DECIMAL(10,3) NOT NULL,
 stock INT UNSIGNED NOT NULL DEFAULT 0,
 low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 5,
 is_active BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_product_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS shop_sales (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_id INT UNSIGNED NOT NULL,
 quantity INT UNSIGNED NOT NULL,
 unit_price DECIMAL(10,3) NOT NULL,
 total DECIMAL(13,3) NOT NULL,
 method ENUM('espèces','chèque','virement','D17','Flouci','carte bancaire') NOT NULL,
 sold_by INT UNSIGNED NULL,
 sold_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (product_id) REFERENCES products(id),
 FOREIGN KEY (sold_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_shop_sales_date (sold_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS stock_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_id INT UNSIGNED NOT NULL,
 quantity_change INT NOT NULL,
 reason ENUM('initial','restock','sale','adjustment') NOT NULL,
 note VARCHAR(255) NULL,
 sale_id BIGINT UNSIGNED NULL,
 created_by INT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (product_id) REFERENCES products(id),
 FOREIGN KEY (sale_id) REFERENCES shop_sales(id),
 FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_stock_product_date (product_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS equipment (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  reference_code VARCHAR(50) NOT NULL UNIQUE,
  location VARCHAR(120) NOT NULL DEFAULT '',
  purchased_on DATE NULL,
  status ENUM('operational','out_of_service','maintenance') NOT NULL DEFAULT 'operational',
  next_maintenance DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_equipment_status (status),
  INDEX idx_equipment_maintenance (next_maintenance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS equipment_interventions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  equipment_id INT UNSIGNED NOT NULL,
  intervention_date DATE NOT NULL,
  type ENUM('maintenance','breakdown','repair') NOT NULL,
  description VARCHAR(1000) NOT NULL,
  cost DECIMAL(10,3) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (equipment_id) REFERENCES equipment(id),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_interventions_equipment_date (equipment_id,intervention_date,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS expenses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_date DATE NOT NULL,
  category ENUM('loyer','salaires','électricité','eau','maintenance','fournitures','marketing','autre') NOT NULL,
  description VARCHAR(500) NOT NULL,
  amount DECIMAL(10,3) NOT NULL,
  method ENUM('espèces','chèque','virement','D17','Flouci','carte bancaire') NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_expenses_date (expense_date),
  INDEX idx_expenses_category_date (category,expense_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS promotions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(40) NOT NULL UNIQUE,
 label VARCHAR(120) NOT NULL,
 discount_percent DECIMAL(5,2) NOT NULL,
 starts_on DATE NOT NULL,
 ends_on DATE NOT NULL,
 is_active BOOLEAN NOT NULL DEFAULT TRUE,
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_promotion_dates (is_active, starts_on, ends_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Sélectionner la base utilisée, sauvegarder puis importer sur une installation existante.
CREATE TABLE IF NOT EXISTS subscription_freezes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 subscription_id INT UNSIGNED NOT NULL,
 frozen_on DATE NOT NULL,
 resumed_on DATE NULL,
 credited_days INT UNSIGNED NULL,
 state ENUM('open','resumed','cancelled') NOT NULL DEFAULT 'open',
 created_by INT UNSIGNED NULL,
 closed_by INT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
 FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_freezes_subscription (subscription_id,state,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Sélectionner la base utilisée dans phpMyAdmin ou via mysql ... nom_de_base < ce_fichier.
-- Sauvegarder avant toute migration. Cet import ne remplace pas les paramètres existants.
CREATE TABLE IF NOT EXISTS gym_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  gym_name VARCHAR(120) NULL,
  phone VARCHAR(30) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  tax_id VARCHAR(80) NOT NULL DEFAULT '',
  opening_hours VARCHAR(1000) NOT NULL DEFAULT '',
  vat_enabled BOOLEAN NOT NULL DEFAULT FALSE,
  vat_rate DECIMAL(5,2) NOT NULL DEFAULT 19.00,
  fiscal_stamp DECIMAL(10,3) NOT NULL DEFAULT 1.000,
  updated_by INT UNSIGNED NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO gym_settings (id) VALUES (1) ON DUPLICATE KEY UPDATE id=id;

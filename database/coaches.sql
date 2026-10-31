-- Migration pour une base existante, après schema.sql et courses.sql.
USE prime_fit;
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

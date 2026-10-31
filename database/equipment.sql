-- Migration pour les installations existantes, à importer après schema.sql.
USE prime_fit;
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

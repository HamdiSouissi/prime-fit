-- Sélectionner la base utilisée dans phpMyAdmin ou via mysql ... nom_de_base < ce_fichier.
-- Sauvegarder avant toute migration. Cet import ne remplace pas les paramètres existants.
CREATE TABLE IF NOT EXISTS gym_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
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

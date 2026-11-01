-- Sélectionner la base utilisée et sauvegarder avant import.
CREATE TABLE IF NOT EXISTS marketing_consent_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NOT NULL,
  decision ENUM('accepted','revoked','invalidated') NOT NULL,
  phone_value VARCHAR(30) NOT NULL DEFAULT '',
  evidence VARCHAR(250) NOT NULL DEFAULT '',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(id),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_marketing_consent_member (member_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

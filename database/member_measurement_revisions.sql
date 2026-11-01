-- Après sauvegarde, sélectionner la base de l'application.
-- Les valeurs précédentes et nouvelles sont conservées pour la traçabilité.
CREATE TABLE IF NOT EXISTS member_measurement_revisions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  measurement_id INT UNSIGNED NOT NULL,
  member_id INT UNSIGNED NOT NULL,
  previous_date DATE NOT NULL,
  previous_weight DECIMAL(5,2) NOT NULL,
  previous_height DECIMAL(5,2) NOT NULL,
  previous_waist DECIMAL(5,2) NULL,
  new_date DATE NOT NULL,
  new_weight DECIMAL(5,2) NOT NULL,
  new_height DECIMAL(5,2) NOT NULL,
  new_waist DECIMAL(5,2) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (measurement_id) REFERENCES fitness_measurements(id),
  FOREIGN KEY (member_id) REFERENCES members(id),
  INDEX idx_measurement_revision (measurement_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

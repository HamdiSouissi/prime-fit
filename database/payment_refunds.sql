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

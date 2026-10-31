-- Migration pour une installation existante, après schema.sql.
USE prime_fit;
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

-- Migration pour les installations existantes, après schema.sql.
USE prime_fit;
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

-- Migration pour une base existante, après schema.sql.
USE prime_fit;
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

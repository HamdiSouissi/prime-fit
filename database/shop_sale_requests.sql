-- Après sauvegarde, sélectionner la base contenant shop_sales.
-- Les anciennes ventes ne sont pas modifiées.
CREATE TABLE IF NOT EXISTS shop_sale_requests (
 request_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 sale_id BIGINT UNSIGNED NULL UNIQUE,
 FOREIGN KEY (sale_id) REFERENCES shop_sales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

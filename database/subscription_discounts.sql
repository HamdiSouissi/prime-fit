-- Importer après création des tables subscriptions et promotions ; sélectionner la base.
CREATE TABLE IF NOT EXISTS subscription_discounts (
 subscription_id INT UNSIGNED PRIMARY KEY,
 promotion_id INT UNSIGNED NULL,
 code VARCHAR(40) NOT NULL,
 label VARCHAR(120) NOT NULL,
 base_price DECIMAL(10,3) NOT NULL,
 discount_percent DECIMAL(5,2) NOT NULL,
 discount_amount DECIMAL(10,3) NOT NULL,
 FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
 FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

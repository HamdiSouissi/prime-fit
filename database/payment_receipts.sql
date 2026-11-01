-- Sélectionner la base utilisée et sauvegarder avant import. Reçus immuables des nouveaux paiements.
CREATE TABLE IF NOT EXISTS payment_receipts (
 payment_id INT UNSIGNED PRIMARY KEY,
 pdf LONGBLOB NOT NULL,
 recipient_email VARCHAR(190) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (payment_id) REFERENCES payments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

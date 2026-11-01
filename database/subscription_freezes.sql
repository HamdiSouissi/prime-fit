-- Sélectionner la base utilisée, sauvegarder puis importer sur une installation existante.
CREATE TABLE IF NOT EXISTS subscription_freezes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 subscription_id INT UNSIGNED NOT NULL,
 frozen_on DATE NOT NULL,
 resumed_on DATE NULL,
 credited_days INT UNSIGNED NULL,
 state ENUM('open','resumed','cancelled') NOT NULL DEFAULT 'open',
 created_by INT UNSIGNED NULL,
 closed_by INT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
 FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_freezes_subscription (subscription_id,state,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

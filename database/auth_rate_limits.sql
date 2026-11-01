-- Après sauvegarde, sélectionner la base de l'application avant import.
-- Table indépendante des comptes et des sessions ; les clés sont des empreintes.
CREATE TABLE IF NOT EXISTS auth_rate_limits (
  bucket_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  window_started BIGINT UNSIGNED NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  INDEX idx_auth_limit_window (window_started)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

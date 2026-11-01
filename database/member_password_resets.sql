-- Après sauvegarde, sélectionner la base de l'application.
-- Une seule demande active par membre ; aucun jeton en clair n'est stocké.
CREATE TABLE IF NOT EXISTS member_password_resets (
  member_id INT UNSIGNED NOT NULL PRIMARY KEY,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  account_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  email VARCHAR(190) NOT NULL,
  expires_at DATETIME NOT NULL,
  UNIQUE KEY uq_member_reset_token (token_hash),
  FOREIGN KEY (member_id) REFERENCES member_portal_accounts(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Base existante : sélectionner la base de l'application et effectuer une sauvegarde.
-- Comptes membres séparés des comptes du personnel. Les invitations stockent une
-- empreinte, jamais le lien secret lui-même ; NULL signifie qu'aucun lien n'est actif.
CREATE TABLE IF NOT EXISTS member_portal_accounts (
  member_id INT UNSIGNED NOT NULL PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NULL,
  invitation_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  invitation_expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_portal_email (email),
  UNIQUE KEY uq_portal_invitation (invitation_hash),
  FOREIGN KEY (member_id) REFERENCES members(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

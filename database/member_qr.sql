-- Sélectionner la base utilisée par l’application dans phpMyAdmin avant import.
-- À importer une seule fois sur une base existante, après sauvegarde.
-- Ne pas importer si schema.sql a déjà créé cette colonne.
ALTER TABLE members ADD COLUMN qr_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  ADD UNIQUE KEY uq_members_qr_token (qr_token);
-- Les membres existants reçoivent leur QR depuis leur page « Carte QR ».

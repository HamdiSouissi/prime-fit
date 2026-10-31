-- Après sauvegarde, sélectionner la base existante. À importer une seule fois.
-- N'exécutez pas cette migration si la colonne note existe déjà (schema.sql à jour).
ALTER TABLE stock_movements
  MODIFY COLUMN reason ENUM('initial','restock','sale','adjustment') NOT NULL,
  ADD COLUMN note VARCHAR(255) NULL AFTER reason;

-- Sauvegarder d'abord. Sélectionner la base utilisée. À importer une seule fois.
-- Les anciens prix restent NULL : il faut les vérifier individuellement depuis les contrats.
ALTER TABLE subscriptions ADD COLUMN agreed_price DECIMAL(10,3) NULL AFTER end_date;

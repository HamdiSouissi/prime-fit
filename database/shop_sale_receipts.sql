-- Après sauvegarde, sélectionner la base utilisée. À importer une seule fois.
-- Ne pas exécuter si ces colonnes existent déjà (schema.sql récent).
-- Les anciennes ventes restent NULL : leur nom et référence d'origine sont inconnus.
ALTER TABLE shop_sales
  ADD COLUMN product_name_snapshot VARCHAR(120) NULL AFTER product_id,
  ADD COLUMN product_sku_snapshot VARCHAR(50) NULL AFTER product_name_snapshot;

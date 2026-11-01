-- Réparation ciblée d'un import UTF-8 interprété en CP437/CP850.
-- AVANT : sauvegarder et arrêter les écritures sur payments. Sélectionner la bonne base.
-- MySQL/MariaDB : ALTER TABLE effectue un commit implicite et n'est pas annulable.
-- Le script s'arrête sans changer la structure si la définition ne correspond pas
-- exactement à celle attendue ; ne pas forcer l'exécution en cas de refus.
SET NAMES utf8mb4;

SET @actual_type=NULL, @nullable=NULL, @default_value=NULL, @charset=NULL, @collation_name=NULL, @extra=NULL, @comment=NULL;
SET @legacy_type = 'enum(''esp├¿ces'',''ch├¿que'',''virement'',''D17'',''Flouci'',''carte bancaire'')';
SET @expanded_type = 'enum(''esp├¿ces'',''ch├¿que'',''virement'',''D17'',''Flouci'',''carte bancaire'',''espèces'',''chèque'')';
SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME, EXTRA, COLUMN_COMMENT
  INTO @actual_type, @nullable, @default_value, @charset, @collation_name, @extra, @comment
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payments' AND COLUMN_NAME='method';

-- N'accepte que la définition d'origine constatée ou l'étape intermédiaire de
-- cette migration : ainsi une reprise après interruption ne détruit rien.
SET @compatible = COALESCE(
  (BINARY @actual_type = BINARY @legacy_type OR BINARY @actual_type = BINARY @expanded_type)
  AND @nullable='NO' AND @default_value IS NULL
  AND @charset='utf8mb4' AND @collation_name='utf8mb4_unicode_ci'
  AND @extra='' AND @comment='', 0);
SET @needs_alter = @compatible AND BINARY @actual_type = BINARY @legacy_type;
SET @repair_ddl = IF(@needs_alter,
  'ALTER TABLE payments MODIFY COLUMN method ENUM(''esp├¿ces'',''ch├¿que'',''virement'',''D17'',''Flouci'',''carte bancaire'',''espèces'',''chèque'') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL',
  'SELECT ''Aucune modification de structure : déjà étendue ou structure incompatible'' AS information');
PREPARE repair_payment_methods FROM @repair_ddl;
EXECUTE repair_payment_methods;
DEALLOCATE PREPARE repair_payment_methods;

-- Ne modifier que les deux libellés identifiés ; ID, montants, dates et liens
-- restent inchangés. Les anciennes valeurs restent autorisées par l'ENUM.
UPDATE payments SET method='espèces'
 WHERE @compatible=1 AND BINARY method=BINARY 'esp├¿ces';
SET @cash_corrected=ROW_COUNT();
UPDATE payments SET method='chèque'
 WHERE @compatible=1 AND BINARY method=BINARY 'ch├¿que';
SET @cheques_corrected=ROW_COUNT();
SELECT IF(@compatible=1,'Réparation effectuée ou déjà effectuée','REFUS : structure différente, aucune réparation effectuée') AS resultat,
       @cash_corrected AS libelles_especes_corriges, @cheques_corrected AS libelles_cheque_corriges;

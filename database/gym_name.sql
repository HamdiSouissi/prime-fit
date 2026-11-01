-- À importer une seule fois, après sauvegarde, dans la base de l'application.
-- Si gym_settings n'existe pas encore, utiliser gym_settings.sql à jour à la place.
ALTER TABLE gym_settings ADD COLUMN gym_name VARCHAR(120) NULL AFTER id;

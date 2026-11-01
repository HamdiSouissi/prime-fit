-- Sauvegarder puis sélectionner la base de l'application avant import.
-- Une seule fois : numéro de contact personnel du compte, distinct du profil professionnel d'un coach.
ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL;

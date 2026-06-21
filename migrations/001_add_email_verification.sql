-- Migration: Ajout des colonnes pour vérification email
-- Exécutez ce script une seule fois

-- Vérifie et ajoute les colonnes si elles n'existent pas
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS email_verifi BOOLEAN DEFAULT 0 COMMENT 'Email confirmé par l\'utilisateur';
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS token_verification VARCHAR(64) UNIQUE NULL COMMENT 'Token pour vérifier l\'email';

-- Crée un index sur token_verification pour les recherches rapides
ALTER TABLE utilisateurs ADD INDEX IF NOT EXISTS idx_token_verification (token_verification);

-- Si vous voulez marquer tous les utilisateurs existants comme vérifiés
-- UNCOMMENT LA LIGNE SUIVANTE:
-- UPDATE utilisateurs SET email_verifi = 1 WHERE email_verifi = 0;

-- Ajoute aussi une colonne pour la date de création si absent
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Optionnel: ajout colonne pour réinitialisation mot de passe
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS token_reset_mdp VARCHAR(64) UNIQUE NULL;
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS reset_mdp_expires TIMESTAMP NULL;
ALTER TABLE utilisateurs ADD INDEX IF NOT EXISTS idx_token_reset (token_reset_mdp);

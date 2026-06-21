-- Migration: Ajout support paiements Stripe
-- Exécutez ce script une seule fois

-- Table paiements
CREATE TABLE IF NOT EXISTS paiements (
  id INT PRIMARY KEY AUTO_INCREMENT,
  commande_id INT NOT NULL UNIQUE,
  stripe_session_id VARCHAR(255) UNIQUE,
  stripe_payment_id VARCHAR(255) UNIQUE,
  stripe_refund_id VARCHAR(255),
  montant DECIMAL(10,2) NOT NULL,
  devise VARCHAR(3) DEFAULT 'xof',
  statut ENUM('en_attente', 'confirmé', 'échoué', 'remboursé') DEFAULT 'en_attente',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
  INDEX idx_commande_id (commande_id),
  INDEX idx_stripe_session_id (stripe_session_id),
  INDEX idx_stripe_payment_id (stripe_payment_id),
  INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajoute colonnes à la table commandes si absentes
ALTER TABLE commandes ADD COLUMN IF NOT EXISTS stripe_payment_id VARCHAR(255);
ALTER TABLE commandes ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE commandes MODIFY COLUMN statut ENUM('en_attente', 'payée', 'paiement_échoué', 'traitée', 'livraison_en_cours', 'livrée', 'annulée') DEFAULT 'en_attente';

-- Index pour performance
ALTER TABLE commandes ADD INDEX IF NOT EXISTS idx_stripe_payment_id (stripe_payment_id);
ALTER TABLE commandes ADD INDEX IF NOT EXISTS idx_status (statut);

-- Table webhooks (pour audit)
CREATE TABLE IF NOT EXISTS stripe_webhooks (
  id INT PRIMARY KEY AUTO_INCREMENT,
  event_id VARCHAR(255) UNIQUE NOT NULL,
  event_type VARCHAR(50) NOT NULL,
  data JSON,
  processed BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event_type (event_type),
  INDEX idx_processed (processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

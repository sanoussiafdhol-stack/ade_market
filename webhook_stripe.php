<?php
/**
 * Webhook Stripe
 * Ce fichier traite les événements envoyés par Stripe
 * URL: https://votre-domaine.com/webhook_stripe.php
 */

require_once 'config/database.php';
require_once 'config/logger.php';
require_once 'config/paiement.php';

// Initialise le gestionnaire de paiement
try {
    Paiement::init($pdo);
} catch (Exception $e) {
    Logger::erreur('Erreur initialisation Paiement', ['message' => $e->getMessage()]);
    http_response_code(500);
    die('Erreur configuration');
}

// Traite le webhook
Paiement::traiterWebhook();
?>

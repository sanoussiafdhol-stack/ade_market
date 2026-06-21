<?php
/**
 * Classe de gestion des paiements Stripe
 * Gère les transactions, les webhooks et les confirmations
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/load_env.php';
require_once __DIR__ . '/logger.php';

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\StripeClient;

class Paiement {
    
    private static $stripe_key;
    private static $stripe_webhook_secret;
    private static $pdo;

    public static function init($pdo) {
        self::$pdo = $pdo;
        self::$stripe_key = defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : '';
        self::$stripe_webhook_secret = defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '';
        
        if (!self::$stripe_key) {
            throw new Exception('STRIPE_SECRET_KEY non configurée');
        }
        
        Stripe::setApiKey(self::$stripe_key);
    }

    /**
     * Crée une session de paiement Stripe
     */
    public static function creerSessionPaiement($commande_id, $utilisateur_email, $montant_cents, $panier) {
        try {
            Logger::action('Création session paiement', ['commande_id' => $commande_id]);

            $items = [];
            foreach ($panier as $produit_id => $item) {
                $items[] = [
                    'price_data' => [
                        'currency' => 'xof', // Franc CFA
                        'unit_amount' => (int)($item['prix'] * 100), // en centimes
                        'product_data' => [
                            'name' => $item['nom'],
                        ],
                    ],
                    'quantity' => $item['quantite'],
                ];
            }

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $items,
                'mode' => 'payment',
                'success_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/client/confirmation_paiement.php?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/client/panier.php?erreur=paiement_annule',
                'customer_email' => $utilisateur_email,
                'metadata' => [
                    'commande_id' => $commande_id,
                ],
            ]);

            // Stocke la session en BD
            $stmt = self::$pdo->prepare("
                INSERT INTO paiements 
                (commande_id, stripe_session_id, montant, devise, statut) 
                VALUES (?, ?, ?, 'xof', 'en_attente')
            ");
            $stmt->execute([$commande_id, $session->id, $montant_cents / 100]);

            Logger::action('Session paiement créée', [
                'commande_id' => $commande_id,
                'session_id' => $session->id
            ]);

            return $session;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::erreur('Erreur API Stripe', ['message' => $e->getMessage()]);
            throw new Exception('Erreur lors de la création de la session de paiement');
        }
    }

    /**
     * Récupère les détails d'une session de paiement
     */
    public static function obtenirSession($session_id) {
        try {
            $session = Session::retrieve($session_id);
            
            if ($session->payment_status === 'paid') {
                return [
                    'statut' => 'payé',
                    'commande_id' => $session->metadata->commande_id,
                    'session' => $session
                ];
            } else {
                return [
                    'statut' => 'en_attente',
                    'commande_id' => $session->metadata->commande_id,
                    'session' => $session
                ];
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Logger::erreur('Erreur récupération session', ['message' => $e->getMessage()]);
            throw new Exception('Erreur lors de la récupération de la session');
        }
    }

    /**
     * Confirme le paiement après réception du webhook
     */
    public static function confirmerPaiement($commande_id, $stripe_payment_intent_id) {
        try {
            $stmt = self::$pdo->prepare("
                UPDATE commandes 
                SET statut = 'payée', stripe_payment_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$stripe_payment_intent_id, $commande_id]);

            $stmt = self::$pdo->prepare("
                UPDATE paiements 
                SET statut = 'confirmé', stripe_payment_id = ?, updated_at = NOW()
                WHERE commande_id = ?
            ");
            $stmt->execute([$stripe_payment_intent_id, $commande_id]);

            Logger::action('Paiement confirmé', ['commande_id' => $commande_id]);

            return true;
        } catch (PDOException $e) {
            Logger::erreur('Erreur confirmation paiement', ['message' => $e->getMessage()]);
            throw new Exception('Erreur lors de la confirmation du paiement');
        }
    }

    /**
     * Traite les webhooks Stripe
     */
    public static function traiterWebhook() {
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        if (!$sig_header) {
            http_response_code(400);
            die('Erreur: signature manquante');
        }

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                self::$stripe_webhook_secret
            );

            Logger::action('Webhook Stripe reçu', ['type' => $event->type]);

            switch ($event->type) {
                case 'checkout.session.completed':
                    self::traiterSessionCompletee($event->data->object);
                    break;
                case 'payment_intent.succeeded':
                    self::traiterPaiementReussi($event->data->object);
                    break;
                case 'payment_intent.payment_failed':
                    self::traiterPaiementEchoue($event->data->object);
                    break;
            }

            http_response_code(200);
        } catch (\UnexpectedValueException $e) {
            Logger::erreur('Webhook Stripe invalide', ['message' => $e->getMessage()]);
            http_response_code(403);
            die('Signature invalide');
        } catch (Exception $e) {
            Logger::erreur('Erreur traitement webhook', ['message' => $e->getMessage()]);
            http_response_code(500);
            die('Erreur serveur');
        }
    }

    /**
     * Traite le événement checkout.session.completed
     */
    private static function traiterSessionCompletee($session) {
        $commande_id = $session->metadata->commande_id;
        
        try {
            $stmt = self::$pdo->prepare("
                UPDATE paiements 
                SET statut = 'confirmé', stripe_payment_id = ?, updated_at = NOW()
                WHERE commande_id = ?
            ");
            $stmt->execute([$session->payment_intent, $commande_id]);

            $stmt = self::$pdo->prepare("
                UPDATE commandes 
                SET statut = 'payée', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$commande_id]);

            Logger::action('Session checkout complétée', ['commande_id' => $commande_id]);
        } catch (PDOException $e) {
            Logger::erreur('Erreur traitement session complétée', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Traite le événement payment_intent.succeeded
     */
    private static function traiterPaiementReussi($payment_intent) {
        // Récupère la commande associée
        $stmt = self::$pdo->prepare("
            SELECT commande_id FROM paiements 
            WHERE stripe_payment_id = ?
        ");
        $stmt->execute([$payment_intent->id]);
        $result = $stmt->fetch();

        if ($result) {
            $commande_id = $result['commande_id'];
            
            $stmt = self::$pdo->prepare("
                UPDATE commandes 
                SET statut = 'payée', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$commande_id]);

            Logger::action('Paiement réussi', ['commande_id' => $commande_id]);
        }
    }

    /**
     * Traite le événement payment_intent.payment_failed
     */
    private static function traiterPaiementEchoue($payment_intent) {
        // Récupère la commande associée
        $stmt = self::$pdo->prepare("
            SELECT commande_id FROM paiements 
            WHERE stripe_payment_id = ?
        ");
        $stmt->execute([$payment_intent->id]);
        $result = $stmt->fetch();

        if ($result) {
            $commande_id = $result['commande_id'];
            
            $stmt = self::$pdo->prepare("
                UPDATE commandes 
                SET statut = 'paiement_échoué', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$commande_id]);

            $stmt = self::$pdo->prepare("
                UPDATE paiements 
                SET statut = 'échoué', updated_at = NOW()
                WHERE commande_id = ?
            ");
            $stmt->execute([$commande_id]);

            Logger::action('Paiement échoué', [
                'commande_id' => $commande_id,
                'raison' => $payment_intent->last_payment_error->message ?? 'Inconnu'
            ]);
        }
    }

    /**
     * Récupère les détails du paiement
     */
    public static function obtenirPaiement($commande_id) {
        try {
            $stmt = self::$pdo->prepare("
                SELECT * FROM paiements WHERE commande_id = ?
            ");
            $stmt->execute([$commande_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::erreur('Erreur récupération paiement', ['message' => $e->getMessage()]);
            throw new Exception('Erreur lors de la récupération du paiement');
        }
    }

    /**
     * Remboursement d'un paiement
     */
    public static function rembourser($commande_id) {
        try {
            $paiement = self::obtenirPaiement($commande_id);
            
            if (!$paiement || !$paiement['stripe_payment_id']) {
                throw new Exception('Paiement non trouvé');
            }

            // Crée un remboursement Stripe
            $refund = \Stripe\Refund::create([
                'payment_intent' => $paiement['stripe_payment_id'],
            ]);

            // Met à jour la BD
            $stmt = self::$pdo->prepare("
                UPDATE paiements 
                SET statut = 'remboursé', stripe_refund_id = ?, updated_at = NOW()
                WHERE commande_id = ?
            ");
            $stmt->execute([$refund->id, $commande_id]);

            Logger::action('Paiement remboursé', ['commande_id' => $commande_id]);

            return true;
        } catch (Exception $e) {
            Logger::erreur('Erreur remboursement', ['message' => $e->getMessage()]);
            throw $e;
        }
    }
}
?>

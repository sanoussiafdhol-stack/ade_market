<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/validation.php';
require_once '../config/logger.php';
require_once '../config/paiement.php';

redirigerSiNonConnecte();

// Récupère le session_id de Stripe
$session_id = $_GET['session_id'] ?? '';
$erreur = '';
$commande_id = null;
$paiement = null;

if (!$session_id) {
    $erreur = 'Session de paiement manquante.';
} else {
    try {
        Paiement::init($pdo);
        $session_data = Paiement::obtenirSession($session_id);
        $commande_id = $session_data['commande_id'];

        if ($session_data['statut'] === 'payé') {
            // Récupère les détails du paiement
            $paiement = Paiement::obtenirPaiement($commande_id);
            
            Logger::action('Confirmation paiement affichée', ['commande_id' => $commande_id]);
        } else {
            $erreur = 'Le paiement n\'a pas été complété.';
            Logger::avertissement('Tentative accès confirmation paiement non-complété', ['session_id' => $session_id]);
        }
    } catch (Exception $e) {
        $erreur = 'Erreur lors de la vérification du paiement.';
        Logger::erreur('Erreur confirmation paiement', ['message' => $e->getMessage()]);
    }
}

// Récupère les détails de la commande
$commande = null;
if ($commande_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, u.nom, u.email
            FROM commandes c
            JOIN utilisateurs u ON c.utilisateur_id = u.id
            WHERE c.id = ? AND c.utilisateur_id = ?
        ");
        $stmt->execute([$commande_id, $_SESSION['utilisateur_id']]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $erreur = 'Erreur lors de la récupération de la commande.';
        Logger::erreur('Erreur récupération commande', ['message' => $e->getMessage()]);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de Paiement - ADE MARKET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green: #00c853;
            --green-dark: #00953d;
            --dark: #0a0f1e;
            --red: #ef4444;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: linear-gradient(135deg, var(--dark) 0%, #1a1f2e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .container {
            max-width: 500px;
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 3rem 2rem;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
        }

        .icon.succes {
            background: rgba(0, 200, 83, 0.2);
            color: var(--green);
        }

        .icon.erreur {
            background: rgba(239, 68, 68, 0.2);
            color: var(--red);
        }

        .titre {
            font-family: 'Syne', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            color: white;
            text-align: center;
            margin-bottom: 1rem;
        }

        .succes .titre {
            color: var(--green);
        }

        .erreur .titre {
            color: var(--red);
        }

        .message {
            color: rgba(255, 255, 255, 0.7);
            text-align: center;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .details {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .detail-label {
            color: rgba(255, 255, 255, 0.5);
        }

        .detail-value {
            color: white;
            font-weight: 600;
        }

        .montant {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--green);
            text-align: center;
            margin: 1.5rem 0;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: white;
            box-shadow: 0 6px 20px rgba(0, 200, 83, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 200, 83, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }

        .reference {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.3);
            text-align: center;
            margin-top: 1.5rem;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container <?= $erreur ? 'erreur' : 'succes' ?>">
        <div class="icon <?= $erreur ? 'erreur' : 'succes' ?>">
            <?php if ($erreur): ?>
                <i data-lucide="x-circle"></i>
            <?php else: ?>
                <i data-lucide="check-circle"></i>
            <?php endif; ?>
        </div>

        <div class="titre">
            <?php if ($erreur): ?>
                Erreur de Paiement
            <?php else: ?>
                Paiement Réussi! 🎉
            <?php endif; ?>
        </div>

        <div class="message">
            <?php if ($erreur): ?>
                <?= echapper($erreur) ?>
            <?php else: ?>
                Votre paiement a été traité avec succès.<br>
                Vous recevrez un email de confirmation immédiatement.
            <?php endif; ?>
        </div>

        <?php if ($commande && !$erreur): ?>
            <div class="details">
                <div class="detail-row">
                    <span class="detail-label">Numéro de commande</span>
                    <span class="detail-value">#<?= echapper($commande['id']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email</span>
                    <span class="detail-value"><?= echapper($commande['email']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Livraison à</span>
                    <span class="detail-value"><?= echapper(substr($commande['adresse_livraison'], 0, 30)) . '...' ?></span>
                </div>
            </div>

            <div class="montant">
                <?= number_format($commande['total'], 0, ',', ' ') ?> FCFA
            </div>

            <?php if ($paiement): ?>
                <div class="reference">
                    ID Paiement: <?= echapper($paiement['stripe_payment_id']) ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="btn-group">
            <?php if ($erreur): ?>
                <a href="panier.php" class="btn btn-primary">
                    <i data-lucide="shopping-cart" style="width:18px;height:18px;"></i>
                    Retour au panier
                </a>
                <a href="accueil.php" class="btn btn-secondary">
                    <i data-lucide="home" style="width:18px;height:18px;"></i>
                    Accueil
                </a>
            <?php else: ?>
                <a href="mes_commandes.php" class="btn btn-primary">
                    <i data-lucide="package" style="width:18px;height:18px;"></i>
                    Mes commandes
                </a>
                <a href="accueil.php" class="btn btn-secondary">
                    <i data-lucide="home" style="width:18px;height:18px;"></i>
                    Continuer shopping
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script>lucide.createIcons()</script>
</body>
</html>

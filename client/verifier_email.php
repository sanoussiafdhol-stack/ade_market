<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/logger.php';
require_once '../config/error_handler.php';

$token = $_GET['token'] ?? '';
$message = '';
$type_message = '';

if (!$token) {
    $type_message = 'erreur';
    $message = 'Token de vérification manquant.';
} else {
    try {
        // Cherche l'utilisateur avec ce token
        $stmt = $pdo->prepare("SELECT id, email, email_verifi FROM utilisateurs WHERE token_verification = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $type_message = 'erreur';
            $message = 'Token invalide ou expiré.';
            Logger::avertissement('Tentative de vérification avec token invalide', ['token' => substr($token, 0, 10) . '...']);
        } elseif ($user['email_verifi']) {
            $type_message = 'info';
            $message = 'Votre email est déjà vérifié. Vous pouvez vous connecter.';
        } else {
            // Marque l'email comme vérifié
            $stmt = $pdo->prepare("UPDATE utilisateurs SET email_verifi = 1, token_verification = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);

            $type_message = 'succes';
            $message = 'Email vérifié avec succès ! Vous pouvez maintenant vous connecter.';
            Logger::action('Email vérifié', ['user_id' => $user['id'], 'email' => $user['email']]);
        }
    } catch (PDOException $e) {
        Logger::erreur('Erreur vérification email', ['message' => $e->getMessage()]);
        $type_message = 'erreur';
        $message = 'Erreur serveur. Veuillez réessayer plus tard.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification Email - ADE MARKET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green: #00c853;
            --green-dark: #00953d;
            --dark: #0a0f1e;
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
            max-width: 450px;
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
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
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        .icon.succes {
            background: rgba(0, 200, 83, 0.2);
            color: #00c853;
        }

        .icon.erreur {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .icon.info {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .titre {
            font-family: 'Syne', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 1rem;
        }

        .succes .titre {
            color: #00c853;
        }

        .erreur .titre {
            color: #ef4444;
        }

        .info .titre {
            color: #3b82f6;
        }

        .message {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .buttons {
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
    </style>
</head>
<body>
    <div class="container <?= $type_message ?>">
        <div class="icon <?= $type_message ?>">
            <?php if ($type_message === 'succes'): ?>
                <i data-lucide="check-circle" style="width:50px;height:50px;"></i>
            <?php elseif ($type_message === 'erreur'): ?>
                <i data-lucide="x-circle" style="width:50px;height:50px;"></i>
            <?php else: ?>
                <i data-lucide="info" style="width:50px;height:50px;"></i>
            <?php endif; ?>
        </div>

        <div class="titre">
            <?php if ($type_message === 'succes'): ?>
                Email vérifié ! 🎉
            <?php elseif ($type_message === 'erreur'): ?>
                Erreur de vérification
            <?php else: ?>
                Information
            <?php endif; ?>
        </div>

        <div class="message">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div class="buttons">
            <?php if ($type_message === 'succes'): ?>
                <a href="connexion.php" class="btn btn-primary">
                    <i data-lucide="log-in" style="width:18px;height:18px;"></i>
                    Se connecter
                </a>
            <?php else: ?>
                <a href="inscription.php" class="btn btn-primary">
                    <i data-lucide="user-plus" style="width:18px;height:18px;"></i>
                    Réessayer
                </a>
            <?php endif; ?>
            <a href="accueil.php" class="btn btn-secondary">
                <i data-lucide="home" style="width:18px;height:18px;"></i>
                Accueil
            </a>
        </div>
    </div>

    <script>lucide.createIcons()</script>
</body>
</html>

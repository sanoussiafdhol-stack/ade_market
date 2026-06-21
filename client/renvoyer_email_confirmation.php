<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/validation.php';
require_once '../config/logger.php';
require_once '../config/email.php';

$message = '';
$type_message = '';
$email_saisi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation CSRF
        if (!isset($_POST['csrf_token']) || !verifierTokenCSRF($_POST['csrf_token'])) {
            Logger::avertissement('Tentative de renvoi email avec token CSRF invalide', ['ip' => $_SERVER['REMOTE_ADDR']]);
            $type_message = 'erreur';
            $message = 'Requête invalide.';
        } else {
            // Validation email
            Validateur::reset();
            $email = Validateur::email($_POST['email'] ?? '');
            
            if (!$email) {
                $type_message = 'erreur';
                $message = Validateur::getErreurs()['email'] ?? 'Email invalide.';
                $email_saisi = trim($_POST['email'] ?? '');
            } else {
                $email_saisi = $email;
                
                try {
                    // Cherche l'utilisateur
                    $stmt = $pdo->prepare("SELECT id, nom, email, email_verifi, token_verification FROM utilisateurs WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$user) {
                        // Ne révèle pas si l'email existe (sécurité)
                        $type_message = 'succes';
                        $message = 'Si ce compte existe et n\'est pas vérifié, un email a été envoyé.';
                        Logger::info('Tentative renvoi email pour compte non-existant', ['email' => $email]);
                    } elseif ($user['email_verifi']) {
                        $type_message = 'info';
                        $message = 'Cet email est déjà vérifié. Vous pouvez vous connecter.';
                    } else {
                        // Génère un nouveau token
                        $token_verification = bin2hex(random_bytes(32));
                        
                        $stmt = $pdo->prepare("UPDATE utilisateurs SET token_verification = ? WHERE id = ?");
                        $stmt->execute([$token_verification, $user['id']]);

                        // Envoie l'email
                        $lien_verification = "https://" . $_SERVER['HTTP_HOST'] . "/client/verifier_email.php?token=" . $token_verification;
                        $message_email = "Bonjour " . $user['nom'] . ",\n\n";
                        $message_email .= "Cliquez sur le lien ci-dessous pour confirmer votre email :\n";
                        $message_email .= $lien_verification . "\n\n";
                        $message_email .= "Ce lien expire dans 24 heures.\n\n";
                        $message_email .= "Cordialement,\nL'équipe ADE MARKET";
                        
                        if (envoyerEmail($email, "Confirmez votre email - ADE MARKET", $message_email)) {
                            $type_message = 'succes';
                            $message = 'Email de confirmation renvoyé. Vérifiez votre boîte mail.';
                            Logger::action('Email de confirmation renvoyé', ['user_id' => $user['id'], 'email' => $email]);
                            $email_saisi = '';
                        } else {
                            $type_message = 'avertissement';
                            $message = 'Email non pu être envoyé. Contactez le support.';
                            Logger::erreur('Échec renvoi email de confirmation', ['user_id' => $user['id'], 'email' => $email]);
                        }
                    }
                } catch (PDOException $e) {
                    Logger::erreur('Erreur BD renvoi email', ['message' => $e->getMessage()]);
                    $type_message = 'erreur';
                    $message = 'Erreur serveur. Veuillez réessayer plus tard.';
                }
            }
        }
    } catch (Exception $e) {
        Logger::erreur('Exception renvoi email', ['message' => $e->getMessage()]);
        $type_message = 'erreur';
        $message = 'Une erreur est survenue.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renvoyer Email de Confirmation - ADE MARKET</title>
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
            max-width: 420px;
            width: 100%;
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 2.5rem 2rem;
        }

        .logo-icon {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 30px rgba(0,200,83,0.3);
        }

        .titre {
            font-family: 'Syne', sans-serif;
            font-size: 1.3rem; font-weight: 800;
            color: white; text-align: center; margin-bottom: 0.5rem;
        }

        .sous-titre { 
            font-size: 0.85rem; 
            color: rgba(255,255,255,0.35); 
            text-align: center; 
            margin-bottom: 1.5rem; 
        }

        .message {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
        }

        .message.succes {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .message.erreur {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .message.info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .message.avertissement {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .message i {
            flex-shrink: 0;
            margin-top: 2px;
        }

        .form-group { margin-bottom: 1.25rem; }

        label {
            display: block;
            font-size: 0.83rem; font-weight: 600;
            color: rgba(255,255,255,0.6);
            margin-bottom: 0.5rem;
        }

        input {
            width: 100%;
            padding: 0.8rem 1rem;
            background: rgba(255,255,255,0.05);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            font-size: 0.9rem;
            color: white;
            outline: none;
            transition: all 0.2s;
        }

        input::placeholder { color: rgba(255,255,255,0.2); }
        input:focus {
            border-color: var(--green);
            background: rgba(0,200,83,0.05);
            box-shadow: 0 0 0 3px rgba(0,200,83,0.1);
        }

        .btn {
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 6px 20px rgba(0,200,83,0.25);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,200,83,0.35);
        }

        .link { 
            color: rgba(255,255,255,0.35); 
            text-decoration: none; 
            font-size: 0.83rem;
            text-align: center;
            margin-top: 1.5rem;
            display: block;
            transition: color 0.2s;
        }
        .link:hover { color: rgba(255,255,255,0.7); }
    </style>
</head>
<body>
    <div class="container">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="logo-icon">
                <i data-lucide="mail" style="width:28px;height:28px;color:white"></i>
            </div>
            <div class="titre">Confirmer votre email</div>
            <div class="sous-titre">Renvoyez l'email de confirmation</div>
        </div>

        <?php if ($message): ?>
            <div class="message <?= $type_message ?>">
                <?php if ($type_message === 'succes'): ?>
                    <i data-lucide="check-circle" style="width:18px;height:18px;"></i>
                <?php elseif ($type_message === 'erreur'): ?>
                    <i data-lucide="alert-circle" style="width:18px;height:18px;"></i>
                <?php elseif ($type_message === 'avertissement'): ?>
                    <i data-lucide="alert-triangle" style="width:18px;height:18px;"></i>
                <?php else: ?>
                    <i data-lucide="info" style="width:18px;height:18px;"></i>
                <?php endif; ?>
                <span><?= echapper($message) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
            
            <div class="form-group">
                <label>Adresse email</label>
                <input type="email" name="email" placeholder="votre@email.com" value="<?= echapper($email_saisi) ?>" required>
            </div>

            <button type="submit" class="btn">
                <i data-lucide="send" style="width:18px;height:18px"></i>
                Renvoyer l'email
            </button>
        </form>

        <a href="connexion.php" class="link">
            ← Retour à la connexion
        </a>
    </div>

    <script>lucide.createIcons()</script>
</body>
</html>

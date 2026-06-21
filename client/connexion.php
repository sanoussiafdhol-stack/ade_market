<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/validation.php';
require_once '../config/logger.php';
require_once '../config/error_handler.php';

if (estConnecte()) {
    header("Location: index.php");
    exit();
}

$erreurs = [];
$email_saisi = '';

// Vérification du blocage par tentatives
$tentatives = $_SESSION['tentatives_connexion'] ?? 0;
$bloque_jusqua = $_SESSION['bloque_connexion'] ?? 0;
$compte_bloque = ($tentatives >= 5 && time() < $bloque_jusqua);

if ($compte_bloque) {
    $minutes_restantes = ceil(($bloque_jusqua - time()) / 60);
    $erreurs['compte'] = "Trop de tentatives. Réessayez dans $minutes_restantes min.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$compte_bloque) {
    try {
        // Validation CSRF
        if (!isset($_POST['csrf_token']) || !verifierTokenCSRF($_POST['csrf_token'])) {
            Logger::avertissement('Tentative de connexion avec token CSRF invalide', ['ip' => $_SERVER['REMOTE_ADDR']]);
            $erreurs['csrf'] = "Requête invalide. Veuillez réessayer.";
        } else {
            // Validation email
            Validateur::reset();
            $email_valide = Validateur::email($_POST['email'] ?? '');
            
            if (!$email_valide) {
                $erreurs = array_merge($erreurs, Validateur::getErreurs());
            } else {
                $email_saisi = $email_valide;
                
                // Validation mot de passe
                Validateur::reset();
                if (!Validateur::motDePasse($_POST['mot_de_passe'] ?? '')) {
                    $erreurs = array_merge($erreurs, Validateur::getErreurs());
                } else {
                    $mot_de_passe = $_POST['mot_de_passe'];
                    
                    // Recherche utilisateur
                    try {
                        $stmt = $pdo->prepare("SELECT id, email, mot_de_passe, nom, role, email_verifi FROM utilisateurs WHERE email = ?");
                        $stmt->execute([$email_valide]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($user && password_verify($mot_de_passe, $user['mot_de_passe'])) {
                            // Vérification email
                            if (!$user['email_verifi']) {
                                Logger::avertissement('Tentative de connexion avec email non vérifié', ['email' => $email_valide]);
                                $erreurs['email'] = "Veuillez vérifier votre email avant de vous connecter.";
                            } else {
                                // Connexion réussie
                                unset($_SESSION['tentatives_connexion'], $_SESSION['bloque_connexion']);
                                regenererSession();
                                
                                $_SESSION['utilisateur_id'] = (int)$user['id'];
                                $_SESSION['email'] = $user['email'];
                                $_SESSION['nom'] = $user['nom'];
                                $_SESSION['role'] = $user['role'];
                                
                                Logger::action('Connexion réussie', ['email' => $email_valide, 'role' => $user['role']]);

                                if ($user['role'] === 'admin') {
                                    header("Location: ../admin/dashboard.php");
                                } else {
                                    header("Location: index.php");
                                }
                                exit();
                            }
                        } else {
                            // Identifiants incorrects
                            $_SESSION['tentatives_connexion'] = ($_SESSION['tentatives_connexion'] ?? 0) + 1;
                            if ($_SESSION['tentatives_connexion'] >= 5) {
                                $_SESSION['bloque_connexion'] = time() + 300; // 5 minutes
                                Logger::avertissement('Compte bloqué après 5 tentatives échouées', ['email' => $email_valide]);
                                $erreurs['compte'] = "Trop de tentatives. Réessayez dans 5 minutes.";
                            } else {
                                Logger::avertissement('Tentative de connexion échouée', ['email' => $email_valide, 'tentative' => $_SESSION['tentatives_connexion']]);
                                $erreurs['identifiants'] = "Email ou mot de passe incorrect.";
                            }
                        }
                    } catch (PDOException $e) {
                        Logger::erreur('Erreur base de données connexion', ['message' => $e->getMessage()]);
                        $erreurs['serveur'] = "Erreur serveur. Veuillez réessayer plus tard.";
                    }
                }
            }
        }
    } catch (Exception $e) {
        Logger::erreur('Exception connexion', ['message' => $e->getMessage()]);
        $erreurs['serveur'] = "Une erreur est survenue.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - ADE MARKET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green: #00c853;
            --green-dark: #00953d;
            --green-light: #e8fff2;
            --dark: #0a0f1e;
            --gray: #6b7280;
            --border: #e5e7eb;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 30% 40%, rgba(0,200,83,0.1), transparent),
                        radial-gradient(ellipse 60% 50% at 80% 70%, rgba(0,149,61,0.07), transparent);
        }

        .grid-bg {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 50px 50px;
        }

        .card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 2.5rem 2rem;
            backdrop-filter: blur(20px);
        }

        /* LOGO */
        .logo-wrap { text-align: center; margin-bottom: 2.5rem; }

        .logo-icon {
            width: 60px; height: 60px;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 30px rgba(0,200,83,0.3);
        }

        .logo-text {
            font-family: 'Syne', sans-serif;
            font-size: 1.75rem; font-weight: 800;
            background: linear-gradient(135deg, var(--green), #69f0ae);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-sub { font-size: 0.8rem; color: rgba(255,255,255,0.3); margin-top: 0.25rem; }

        .page-title {
            font-family: 'Syne', sans-serif;
            font-size: 1.3rem; font-weight: 800;
            color: white; margin-bottom: 0.3rem;
        }

        .page-subtitle { font-size: 0.85rem; color: rgba(255,255,255,0.35); margin-bottom: 2rem; }

        /* ERREUR */
        .erreur {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.2);
            color: #fca5a5;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 0.5rem;
        }

        /* FORM */
        .form-group { margin-bottom: 1.25rem; }

        label {
            display: block;
            font-size: 0.83rem; font-weight: 600;
            color: rgba(255,255,255,0.6);
            margin-bottom: 0.5rem;
        }

        .input-wrap { position: relative; }

        .input-icon {
            position: absolute; left: 1rem; top: 50%; transform: translateY(-50%);
            color: rgba(255,255,255,0.25); pointer-events: none;
        }

        input[type=email], input[type=password] {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 2.75rem;
            background: rgba(255,255,255,0.05);
            border: 1.5px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            font-size: 0.9rem;
            color: white;
            outline: none;
            transition: all 0.2s;
            font-family: 'DM Sans', sans-serif;
        }

        input::placeholder { color: rgba(255,255,255,0.2); }

        input:focus {
            border-color: var(--green);
            background: rgba(0,200,83,0.05);
            box-shadow: 0 0 0 3px rgba(0,200,83,0.1);
        }

        .submit-btn {
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: white; border: none; border-radius: 12px;
            font-size: 0.95rem; font-weight: 700;
            cursor: pointer; transition: all 0.2s;
            font-family: 'DM Sans', sans-serif;
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            margin-top: 0.5rem;
            box-shadow: 0 6px 20px rgba(0,200,83,0.25);
        }

        .submit-btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,200,83,0.35); }

        .submit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* LINKS */
        .links {
            display: flex; align-items: center; justify-content: space-between;
            margin-top: 1.5rem; font-size: 0.83rem;
        }

        .link { color: rgba(255,255,255,0.35); text-decoration: none; transition: color 0.2s; }
        .link:hover { color: rgba(255,255,255,0.7); }

        .link-green {
            color: var(--green); text-decoration: none; font-weight: 600;
            display: flex; align-items: center; gap: 0.3rem;
            transition: all 0.2s;
        }

        .link-green:hover { color: #69f0ae; }

        /* DIVIDER */
        .divider {
            display: flex; align-items: center; gap: 1rem;
            margin: 1.5rem 0;
        }

        .divider::before, .divider::after {
            content: ''; flex: 1;
            border-top: 1px solid rgba(255,255,255,0.07);
        }

        .divider span { font-size: 0.75rem; color: rgba(255,255,255,0.2); }

        /* BACK LINK */
        .back-link {
            display: flex; align-items: center; justify-content: center; gap: 0.4rem;
            color: rgba(255,255,255,0.2); text-decoration: none;
            font-size: 0.8rem; margin-top: 1.5rem;
            transition: color 0.2s;
        }

        .back-link:hover { color: rgba(255,255,255,0.5); }
    </style>
</head>
<body>
    <div class="grid-bg"></div>

    <div class="card">
        <div class="logo-wrap">
            <div class="logo-icon">
                <i data-lucide="shopping-bag" style="width:28px;height:28px;color:white"></i>
            </div>
            <div class="logo-text">ADE MARKET</div>
            <div class="logo-sub">Porto-Novo, Bénin</div>
        </div>

        <div class="page-title">Bon retour ! 👋</div>
        <div class="page-subtitle">Connectez-vous pour continuer vos achats</div>

        <?php if (!empty($erreurs)): ?>
            <?php foreach ($erreurs as $champ => $message): ?>
                <div class="erreur">
                    <i data-lucide="alert-circle" style="width:16px;height:16px;flex-shrink:0"></i>
                    <?= echapper($message) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">

            <div class="form-group">
                <label>Adresse email</label>
                <div class="input-wrap">
                    <i data-lucide="mail" class="input-icon" style="width:16px;height:16px"></i>
                    <input type="email" name="email" placeholder="votre@email.com" value="<?= echapper($email_saisi) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Mot de passe</label>
                <div class="input-wrap">
                    <i data-lucide="lock" class="input-icon" style="width:16px;height:16px"></i>
                    <input type="password" name="mot_de_passe" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="submit-btn" <?= $compte_bloque ? 'disabled' : '' ?>>
                <i data-lucide="log-in" style="width:18px;height:18px"></i>
                Se connecter
            </button>
        </form>

        <div class="links">
            <a href="mot_de_passe_oublie.php" class="link">Mot de passe oublié ?</a>
            <a href="renvoyer_email_confirmation.php" class="link">Email non confirmé ?</a>
            <a href="inscription.php" class="link-green">
                S'inscrire <i data-lucide="arrow-right" style="width:14px;height:14px"></i>
            </a>
        </div>

        <a href="accueil.php" class="back-link">
            <i data-lucide="arrow-left" style="width:14px;height:14px"></i>
            Retour à l'accueil
        </a>
    </div>

    <script>lucide.createIcons()</script>
</body>
</html>
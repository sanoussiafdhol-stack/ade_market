<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/validation.php';
require_once '../config/logger.php';
require_once '../config/error_handler.php';
require_once '../config/email.php';

if (estConnecte()) {
    header("Location: index.php");
    exit();
}

$erreurs = [];
$succes = "";
$donnees = [
    'nom' => '',
    'email' => '',
    'telephone' => '',
    'adresse' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation CSRF
        if (!isset($_POST['csrf_token']) || !verifierTokenCSRF($_POST['csrf_token'])) {
            Logger::avertissement('Tentative d\'inscription avec token CSRF invalide', ['ip' => $_SERVER['REMOTE_ADDR']]);
            $erreurs['csrf'] = "Requête invalide. Veuillez réessayer.";
        } else {
            // Réinitialise les erreurs
            Validateur::reset();
            
            // Validation des champs
            $nom = Validateur::nom($_POST['nom'] ?? '', $min = 2, $max = 100);
            if (!$nom) {
                $erreurs = array_merge($erreurs, Validateur::getErreurs());
            } else {
                $donnees['nom'] = $nom;
            }

            Validateur::reset();
            $email = Validateur::email($_POST['email'] ?? '');
            if (!$email) {
                $erreurs = array_merge($erreurs, Validateur::getErreurs());
            } else {
                $donnees['email'] = $email;
            }

            Validateur::reset();
            $telephone = Validateur::chaine($_POST['telephone'] ?? '', $min = 8, $max = 20, 'telephone');
            if (!$telephone) {
                $erreurs = array_merge($erreurs, Validateur::getErreurs());
            } else {
                $donnees['telephone'] = $telephone;
            }

            Validateur::reset();
            $adresse = Validateur::chaine($_POST['adresse'] ?? '', $min = 5, $max = 255, 'adresse');
            if (!$adresse) {
                $erreurs = array_merge($erreurs, Validateur::getErreurs());
            } else {
                $donnees['adresse'] = $adresse;
            }

            // Validation mot de passe
            Validateur::reset();
            if (!Validateur::motDePasse($_POST['mot_de_passe'] ?? '', $min = 8, 'mot_de_passe')) {
                $erreurs = array_merge($erreurs, Validateur::getErreurs());
            } else {
                $mdp = $_POST['mot_de_passe'];
                
                // Vérification confirmation mot de passe
                if ($_POST['confirmer_mot_de_passe'] !== $mdp) {
                    $erreurs['confirmer_mot_de_passe'] = "Les mots de passe ne correspondent pas.";
                }
            }

            // Accepte conditions
            if (empty($_POST['conditions'])) {
                $erreurs['conditions'] = "Vous devez accepter les conditions d'utilisation.";
            }

            // Si pas d'erreurs, continue
            if (empty($erreurs)) {
                try {
                    // Vérifie si email existe déjà
                    $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
                    $stmt->execute([$email]);
                    
                    if ($stmt->fetch()) {
                        $erreurs['email'] = "Cet email est déjà utilisé. Veuillez en utiliser un autre ou <a href='connexion.php'>vous connecter</a>.";
                        Logger::avertissement('Tentative d\'inscription avec email existant', ['email' => $email]);
                    } else {
                        // Hash du mot de passe
                        $mdp_hash = password_hash($mdp, PASSWORD_DEFAULT);
                        
                        // Génère token de confirmation email
                        $token_verification = bin2hex(random_bytes(32));
                        
                        // Insère l'utilisateur
                        $stmt = $pdo->prepare("
                            INSERT INTO utilisateurs 
                            (nom, email, mot_de_passe, telephone, adresse, token_verification, email_verifi, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
                        ");
                        $stmt->execute([$nom, $email, $mdp_hash, $telephone, $adresse, $token_verification]);
                        $user_id = $pdo->lastInsertId();
                        
                        // Prépare l'email de confirmation
                        $lien_verification = "https://" . $_SERVER['HTTP_HOST'] . "/client/verifier_email.php?token=" . $token_verification;
                        $message = "Bienvenue sur ADE MARKET!\n\n";
                        $message .= "Cliquez sur le lien ci-dessous pour confirmer votre email :\n";
                        $message .= $lien_verification . "\n\n";
                        $message .= "Ce lien expire dans 24 heures.\n\n";
                        $message .= "Cordialement,\nL'équipe ADE MARKET";
                        
                        // Envoie l'email
                        if (envoyerEmail($email, "Confirmez votre email - ADE MARKET", $message)) {
                            Logger::action('Nouveau compte créé (en attente de confirmation)', ['email' => $email, 'user_id' => $user_id]);
                            $succes = "Compte créé avec succès ! Veuillez confirmer votre email pour vous connecter.";
                            $donnees = ['nom' => '', 'email' => '', 'telephone' => '', 'adresse' => ''];
                        } else {
                            // L'email n'a pas pu être envoyé, mais le compte est créé
                            Logger::erreur('Échec envoi email confirmation', ['email' => $email, 'user_id' => $user_id]);
                            $succes = "Compte créé ! Un email de confirmation a dû être envoyé (si erreur, <a href='mot_de_passe_oublie.php'>réinitialiser</a>).";
                            $donnees = ['nom' => '', 'email' => '', 'telephone' => '', 'adresse' => ''];
                        }
                    }
                } catch (PDOException $e) {
                    Logger::erreur('Erreur BD inscription', ['message' => $e->getMessage()]);
                    $erreurs['serveur'] = "Erreur serveur. Veuillez réessayer plus tard.";
                }
            }
        }
    } catch (Exception $e) {
        Logger::erreur('Exception inscription', ['message' => $e->getMessage()]);
        $erreurs['serveur'] = "Une erreur est survenue.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - ADE MARKET</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="auth-body flex min-h-screen items-center justify-center px-6">

<div class="w-full max-w-md rounded-[20px] border border-white/40 bg-white/85 px-8 py-10 shadow-xl backdrop-blur-md">
    <div class="mb-8 text-center">
        <h1 class="gradient-text mb-1 text-4xl font-extrabold">ADE MARKET</h1>
        <p class="text-sm text-[#64748b]">Créer un compte</p>
    </div>

    <?php if ($erreurs): ?>
        <div class="mb-5 rounded-xl border border-[rgba(220,38,38,0.2)] bg-[#fee2e2] px-4 py-3">
            <?php foreach ($erreurs as $champ => $message): ?>
                <div class="text-sm font-medium text-[#dc2626] mb-2">
                    <i data-lucide="alert-circle" style="width:16px;height:16px;display:inline;margin-right:0.5rem;vertical-align:-2px"></i>
                    <?= echapper($message) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($succes): ?>
        <div class="mb-5 rounded-xl border border-[rgba(16,185,129,0.2)] bg-[#d1fae5] px-4 py-3 text-center text-sm font-medium text-[#065f46]"><?= $succes ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
        
        <div class="mb-5">
            <label class="mb-2 block text-sm font-medium text-[#1e293b]">Nom complet <span class="text-red-500">*</span></label>
            <input type="text" name="nom" placeholder="Ex: Jean Dupont" value="<?= echapper($donnees['nom']) ?>" required class="w-full rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15 <?= isset($erreurs['nom']) ? 'border-red-500 focus:border-red-500' : '' ?>">
            <?php if (isset($erreurs['nom'])): ?><p class="text-xs text-red-500 mt-1"><?= echapper($erreurs['nom']) ?></p><?php endif; ?>
        </div>

        <div class="mb-5">
            <label class="mb-2 block text-sm font-medium text-[#1e293b]">Email <span class="text-red-500">*</span></label>
            <input type="email" name="email" placeholder="votre@email.com" value="<?= echapper($donnees['email']) ?>" required class="w-full rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15 <?= isset($erreurs['email']) ? 'border-red-500 focus:border-red-500' : '' ?>">
            <?php if (isset($erreurs['email'])): ?><p class="text-xs text-red-500 mt-1"><?= echapper($erreurs['email']) ?></p><?php endif; ?>
        </div>

        <div class="mb-5">
            <label class="mb-2 block text-sm font-medium text-[#1e293b]">Téléphone <span class="text-gray-400">(optionnel)</span></label>
            <input type="text" name="telephone" placeholder="Ex: +229 97000000" value="<?= echapper($donnees['telephone']) ?>" class="w-full rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15 <?= isset($erreurs['telephone']) ? 'border-red-500 focus:border-red-500' : '' ?>">
            <?php if (isset($erreurs['telephone'])): ?><p class="text-xs text-red-500 mt-1"><?= echapper($erreurs['telephone']) ?></p><?php endif; ?>
        </div>

        <div class="mb-5">
            <label class="mb-2 block text-sm font-medium text-[#1e293b]">Adresse de livraison <span class="text-red-500">*</span></label>
            <textarea name="adresse" placeholder="Ex: Quartier Plateau, Porto-Novo" class="min-h-[90px] w-full rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15 <?= isset($erreurs['adresse']) ? 'border-red-500 focus:border-red-500' : '' ?>"><?= echapper($donnees['adresse']) ?></textarea>
            <?php if (isset($erreurs['adresse'])): ?><p class="text-xs text-red-500 mt-1"><?= echapper($erreurs['adresse']) ?></p><?php endif; ?>
        </div>

        <div class="mb-5">
            <label class="mb-2 block text-sm font-medium text-[#1e293b]">Mot de passe <span class="text-red-500">*</span></label>
            <p class="text-xs text-gray-500 mb-2">Minimum 8 caractères, avec majuscule et chiffre recommandés</p>
            <input type="password" name="mot_de_passe" placeholder="••••••••" required class="w-full rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15 <?= isset($erreurs['mot_de_passe']) ? 'border-red-500 focus:border-red-500' : '' ?>">
            <?php if (isset($erreurs['mot_de_passe'])): ?><p class="text-xs text-red-500 mt-1"><?= echapper($erreurs['mot_de_passe']) ?></p><?php endif; ?>
        </div>

        <div class="mb-5">
            <label class="mb-2 block text-sm font-medium text-[#1e293b]">Confirmer le mot de passe <span class="text-red-500">*</span></label>
            <input type="password" name="confirmer_mot_de_passe" placeholder="••••••••" required class="w-full rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15 <?= isset($erreurs['confirmer_mot_de_passe']) ? 'border-red-500 focus:border-red-500' : '' ?>">
            <?php if (isset($erreurs['confirmer_mot_de_passe'])): ?><p class="text-xs text-red-500 mt-1"><?= echapper($erreurs['confirmer_mot_de_passe']) ?></p><?php endif; ?>
        </div>

        <div class="mb-6">
            <label class="flex items-start gap-2 cursor-pointer">
                <input type="checkbox" name="conditions" value="1" class="mt-1" <?= isset($_POST['conditions']) ? 'checked' : '' ?>>
                <span class="text-sm text-gray-600">J'accepte les <a href="#" class="text-[#10b981] hover:underline">conditions d'utilisation</a> et la <a href="#" class="text-[#10b981] hover:underline">politique de confidentialité</a> <span class="text-red-500">*</span></span>
            </label>
            <?php if (isset($erreurs['conditions'])): ?><p class="text-xs text-red-500 mt-1"><?= echapper($erreurs['conditions']) ?></p><?php endif; ?>
        </div>

        <button type="submit" class="btn-gradient inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl px-5 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-xl"><i data-lucide="user-plus" class="h-5 w-5"></i> Créer mon compte</button>
    </form>

    <div class="mt-6 text-center text-sm text-[#64748b]">
        Déjà un compte ? <a href="connexion.php" class="inline-flex items-center gap-1 font-semibold text-[#10b981] transition-all hover:text-[#047857]">Se connecter <i data-lucide="log-in" class="h-4 w-4"></i></a>
    </div>
</div>

<script>lucide.createIcons()</script>
</body>
</html>

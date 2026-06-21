<?php
require_once '../config/database.php';
require_once '../config/session.php';

$message = "";
$erreur = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifierTokenCSRF($_POST['csrf_token'])) {
        $erreur = "Erreur de validation CSRF.";
    } else {
        $email = trim($_POST['email']);
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $pdo->prepare("UPDATE utilisateurs SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            $stmt->execute([$token, $expires, $user['id']]);

            $protocole = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $lien = "$protocole://{$_SERVER['HTTP_HOST']}/ade_market/client/reinitialiser_mot_de_passe.php?token=" . urlencode($token);

            require_once '../config/email.php';
            $sujet = "Réinitialisation de mot de passe - ADE MARKET";
            $msg = "Bonjour,\n\n";
            $msg .= "Vous avez demandé la réinitialisation de votre mot de passe.\n\n";
            $msg .= "Cliquez sur le lien ci-dessous pour choisir un nouveau mot de passe :\n";
            $msg .= "$lien\n\n";
            $msg .= "Ce lien expire dans 1 heure.\n\n";
            $msg .= "Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.\n\n";
            $msg .= "ADE MARKET - Porto-Novo, Bénin\ncontact@ademarket.bj";
            envoyerEmail($email, $sujet, $msg);
        }

        $message = "Si un compte existe avec cet email, un lien de réinitialisation vous a été envoyé.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - ADE MARKET</title>
    <meta name="description" content="Réinitialisez votre mot de passe ADE MARKET en recevant un lien par email.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="auth-body flex min-h-screen items-center justify-center px-6">

<div class="w-full max-w-md rounded-[20px] border border-white/40 bg-white/85 px-8 py-10 shadow-xl backdrop-blur-md">
    <div class="mb-8 text-center">
        <h1 class="gradient-text mb-1 text-4xl font-extrabold">ADE MARKET</h1>
        <p class="text-sm text-[#64748b]">Réinitialisation du mot de passe</p>
    </div>

    <?php if ($message): ?>
        <div class="mb-5 rounded-xl border border-[rgba(16,185,129,0.2)] bg-[#d1fae5] px-4 py-3 text-sm font-medium text-[#065f46]"><?= $message ?></div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="mb-5 rounded-xl border border-[rgba(220,38,38,0.2)] bg-[#fee2e2] px-4 py-3 text-center text-sm font-medium text-[#dc2626]"><?= $erreur ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
        <div class="mb-5">
            <label class="mb-2 block text-sm font-medium text-[#1e293b]">Adresse Email</label>
            <input type="email" name="email" placeholder="votre@email.com" required class="w-full rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
        </div>
        <button type="submit" class="btn-gradient inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl px-5 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-xl"><i data-lucide="send" class="h-4 w-4"></i> Envoyer le lien</button>
    </form>

    <div class="mt-6 text-center text-sm">
        <a href="connexion.php" class="inline-flex items-center gap-1 text-[#64748b] transition-all hover:text-[#10b981]"><i data-lucide="arrow-left" class="h-4 w-4"></i> Retour à la connexion</a>
    </div>
</div>

<script>lucide.createIcons()</script>
</body>
</html>

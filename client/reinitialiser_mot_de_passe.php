<?php
require_once '../config/database.php';
require_once '../config/session.php';

$message = "";
$erreur = "";
$token_valide = false;

$token = $_GET['token'] ?? '';

if ($token) {
    $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $token_valide = true;
        $user_id = $user['id'];
    } else {
        $erreur = "Lien invalide ou expiré.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valide) {
    if (!isset($_POST['csrf_token']) || !verifierTokenCSRF($_POST['csrf_token'])) {
        $erreur = "Erreur de validation CSRF.";
    } else {
        $mdp = $_POST['mot_de_passe'];
        $mdp_confirm = $_POST['mot_de_passe_confirm'];

        if (strlen($mdp) < 6) {
            $erreur = "Le mot de passe doit contenir au moins 6 caractères.";
        } elseif ($mdp !== $mdp_confirm) {
            $erreur = "Les mots de passe ne correspondent pas.";
        } else {
            $hash = password_hash($mdp, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
            $stmt->execute([$hash, $user_id]);
            $message = "Mot de passe réinitialisé ! <a href='connexion.php' class='font-bold text-[#065f46] underline'>Se connecter</a>";
            $token_valide = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser mon mot de passe - ADE MARKET</title>
    <meta name="description" content="Créez un nouveau mot de passe pour votre compte ADE MARKET.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="auth-body flex min-h-screen items-center justify-center px-6">

<div class="w-full max-w-md rounded-[20px] border border-white/40 bg-white/85 px-8 py-10 shadow-xl backdrop-blur-md">
    <div class="mb-8 text-center">
        <h1 class="gradient-text mb-1 text-4xl font-extrabold">ADE MARKET</h1>
        <p class="text-sm text-[#64748b]">Nouveau mot de passe</p>
    </div>

    <?php if ($message): ?>
        <div class="mb-5 rounded-xl border border-[rgba(16,185,129,0.2)] bg-[#d1fae5] px-4 py-3 text-sm font-medium text-[#065f46]"><?= $message ?></div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="mb-5 rounded-xl border border-[rgba(220,38,38,0.2)] bg-[#fee2e2] px-4 py-3 text-center text-sm font-medium text-[#dc2626]"><?= $erreur ?></div>
    <?php endif; ?>

    <?php if ($token_valide): ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
        <div class="mb-5">
            <label class="mb-2 block text-sm font-medium text-[#1e293b]">Nouveau mot de passe</label>
            <input type="password" name="mot_de_passe" placeholder="Au moins 6 caractères" required minlength="6" class="w-full rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
        </div>
        <div class="mb-5">
            <label class="mb-2 block text-sm font-medium text-[#1e293b]">Confirmer le mot de passe</label>
            <input type="password" name="mot_de_passe_confirm" placeholder="Confirmer" required minlength="6" class="w-full rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
        </div>
        <button type="submit" class="btn-gradient inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl px-5 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-xl"><i data-lucide="rotate-ccw" class="h-4 w-4"></i> Réinitialiser</button>
    </form>
    <?php endif; ?>
</div>

<script>lucide.createIcons()</script>
</body>
</html>

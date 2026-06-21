<?php
require_once '../config/database.php';
require_once '../config/session.php';

redirigerSiNonConnecte();

$message = "";
$erreur = "";

$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['utilisateur_id']]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifierTokenCSRF($_POST['csrf_token'])) {
        $erreur = "Erreur de validation CSRF.";
    } else {
        $nom = trim($_POST['nom']);
        $email = trim($_POST['email']);
        $telephone = trim($_POST['telephone']);
        $adresse = trim($_POST['adresse']);

        if (empty($nom) || empty($email)) {
            $erreur = "Le nom et l'email sont obligatoires.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreur = "Email invalide.";
        } elseif (!empty($telephone) && strlen($telephone) < 8) {
            $erreur = "Le téléphone doit contenir au moins 8 caractères.";
        } else {
            if ($email !== $user['email']) {
                $check = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
                $check->execute([$email, $_SESSION['utilisateur_id']]);
                if ($check->fetch()) {
                    $erreur = "Cet email est déjà utilisé.";
                }
            }

            if (empty($erreur)) {
                $stmt = $pdo->prepare("UPDATE utilisateurs SET nom = ?, email = ?, telephone = ?, adresse = ? WHERE id = ?");
                $stmt->execute([$nom, $email, $telephone, $adresse, $_SESSION['utilisateur_id']]);
                $_SESSION['nom'] = $nom;
                $user['nom'] = $nom;
                $user['email'] = $email;
                $user['telephone'] = $telephone;
                $user['adresse'] = $adresse;
                $message = "Profil mis à jour.";
            }
        }
    }
}

$panier_count = 0;
if (isset($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $item) {
        $panier_count += $item['quantite'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Compte - ADE MARKET</title>
    <meta name="description" content="Gérez vos informations personnelles et votre mot de passe sur votre compte ADE MARKET.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>

<header class="sticky top-0 z-50 flex items-center justify-between bg-[#0f172a]/95 px-8 py-5 text-white shadow-sm backdrop-blur">
    <a href="accueil.php" class="no-underline">
        <h1 class="gradient-text text-2xl font-extrabold">ADE MARKET</h1>
    </a>
    <div class="flex items-center gap-4">
        <a href="accueil.php" class="inline-flex items-center gap-2 rounded-full border border-white/30 px-4 py-2 text-sm font-medium text-white transition-all hover:bg-white hover:text-[#0f172a]"><i data-lucide="home" class="h-4 w-4"></i> Accueil</a>
        <a href="mes_commandes.php" class="inline-flex items-center gap-2 rounded-full border border-white/30 px-4 py-2 text-sm font-medium text-white transition-all hover:bg-white hover:text-[#0f172a]"><i data-lucide="clipboard-list" class="h-4 w-4"></i> Mes commandes</a>
        <a href="panier.php" class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-[#10b981] to-[#059669] px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:-translate-y-0.5 hover:shadow-xl"><i data-lucide="shopping-cart" class="h-4 w-4"></i> Panier (<?= $panier_count ?>)</a>
        <a href="deconnexion.php" class="inline-flex items-center gap-2 rounded-full border border-white/30 px-4 py-2 text-sm font-medium text-white transition-all hover:bg-white hover:text-[#0f172a]"><i data-lucide="log-out" class="h-4 w-4"></i> Déconnexion</a>
    </div>
</header>

<div class="mx-auto my-12 max-w-2xl px-6">
    <div class="mb-8 flex items-center gap-3">
        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-[#10b981]/10"><i data-lucide="user" class="h-7 w-7 text-[#10b981]"></i></div>
        <div>
            <h2 class="text-2xl font-bold text-[#0f172a]">Mon compte</h2>
            <p class="text-sm text-[#64748b]">Gérez vos informations personnelles</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 rounded-xl border border-[rgba(16,185,129,0.2)] bg-[#d1fae5] px-5 py-3 text-sm font-medium text-[#065f46]" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show=false, 4000)"><i data-lucide="check-circle" class="mr-1.5 inline h-4 w-4"></i> <?= $message ?></div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="mb-6 rounded-xl border border-[rgba(220,38,38,0.15)] bg-[#fee2e2] px-5 py-3 text-sm font-medium text-[#dc2626]"><i data-lucide="alert-circle" class="mr-1.5 inline h-4 w-4"></i> <?= $erreur ?></div>
    <?php endif; ?>

    <div class="rounded-xl border border-[#e2e8f0] bg-white p-8 shadow-sm">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">

            <div class="mb-5">
                <label class="mb-2 block text-sm font-medium text-[#1e293b]">Nom complet</label>
                <input type="text" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
            </div>

            <div class="mb-5">
                <label class="mb-2 block text-sm font-medium text-[#1e293b]">Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
            </div>

            <div class="mb-5">
                <label class="mb-2 block text-sm font-medium text-[#1e293b]">Téléphone</label>
                <input type="text" name="telephone" value="<?= htmlspecialchars($user['telephone'] ?? '') ?>" placeholder="Ex: +229 97000000" class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
            </div>

            <div class="mb-5">
                <label class="mb-2 block text-sm font-medium text-[#1e293b]">Adresse</label>
                <textarea name="adresse" placeholder="Ex: Quartier Plateau, Face BOA, Porto-Novo" rows="3" class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15"><?= htmlspecialchars($user['adresse'] ?? '') ?></textarea>
            </div>

            <div class="flex items-center gap-3 border-t border-[#e2e8f0] pt-6">
                <button type="submit" class="btn-gradient inline-flex cursor-pointer items-center gap-2 rounded-xl px-6 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-xl"><i data-lucide="save" class="h-4 w-4"></i> Enregistrer</button>
                <a href="index.php" class="inline-flex items-center gap-2 rounded-xl border border-[#e2e8f0] px-6 py-3 text-sm font-medium text-[#64748b] transition-all hover:border-[#fca5a5] hover:text-[#ef4444]"><i data-lucide="x" class="h-4 w-4"></i> Annuler</a>
            </div>
        </form>
    </div>
</div>

<script>lucide.createIcons()</script>
</body>
</html>

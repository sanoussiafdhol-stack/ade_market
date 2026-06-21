<?php
require_once '../config/database.php';
require_once '../config/session.php';

redirigerSiNonConnecte();

$commande_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT c.*, u.nom, u.telephone FROM commandes c JOIN utilisateurs u ON c.utilisateur_id = u.id WHERE c.id = ? AND c.utilisateur_id = ?");
$stmt->execute([$commande_id, $_SESSION['utilisateur_id']]);
$commande = $stmt->fetch();

if (!$commande) {
    header("Location: index.php");
    exit();
}

$stmt = $pdo->prepare("SELECT cp.*, p.nom FROM commande_produits cp JOIN produits p ON cp.produit_id = p.id WHERE cp.commande_id = ?");
$stmt->execute([$commande_id]);
$produits = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande Confirmée - ADE MARKET</title>
    <meta name="description" content="Votre commande ADE MARKET a été confirmée. Merci de votre confiance !">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>

<header class="sticky top-0 z-50 flex items-center justify-between bg-[#0f172a]/95 px-8 py-5 text-white shadow-sm backdrop-blur">
    <a href="accueil.php" class="no-underline">
        <h1 class="gradient-text text-2xl font-extrabold">ADE MARKET</h1>
    </a>
    <div class="flex items-center gap-3">
        <a href="compte.php" class="inline-flex items-center gap-1.5 rounded-full border border-white/20 px-3.5 py-2 text-sm font-medium text-white/80 transition-all hover:bg-white hover:text-[#0f172a]"><i data-lucide="user" class="h-3.5 w-3.5"></i></a>
        <a href="mes_commandes.php" class="inline-flex items-center gap-1.5 rounded-full border border-white/20 px-3.5 py-2 text-sm font-medium text-white/80 transition-all hover:bg-white hover:text-[#0f172a]"><i data-lucide="clipboard-list" class="h-3.5 w-3.5"></i></a>
        <a href="index.php" class="inline-flex items-center gap-2 rounded-full border border-white/30 px-5 py-2.5 text-sm font-medium text-white transition-all hover:bg-white hover:text-[#0f172a]"><i data-lucide="shopping-bag" class="h-4 w-4"></i> Catalogue</a>
    </div>
</header>

<div class="mx-auto my-16 max-w-xl px-6">
    <div class="rounded-xl border border-[#e2e8f0] bg-white p-8 text-center shadow-sm">
        <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-[#d1fae5]"><i data-lucide="party-popper" class="h-10 w-10 text-[#10b981]"></i></div>
        <h2 class="mb-2 text-3xl font-extrabold text-[#10b981]">Commande Validée !</h2>
        <p class="mb-4 text-base text-[#64748b]">Merci <strong class="text-[#0f172a]"><?= htmlspecialchars($commande['nom']) ?></strong>, votre commande est en cours de traitement.</p>
        <div class="mx-auto mb-6 inline-block rounded-full border border-[#e2e8f0] bg-[#f8fafc] px-6 py-2 text-base font-bold text-[#10b981]">Commande #<?= $commande_id ?></div>

        <div class="border-t border-[#e2e8f0] pt-6 text-left">
            <h3 class="mb-4 flex items-center gap-2 text-lg font-bold text-[#0f172a]"><i data-lucide="package" class="h-5 w-5"></i> Détails</h3>
            <?php foreach ($produits as $p): ?>
                <div class="flex items-center justify-between border-b border-[#f8fafc] py-3 text-sm">
                    <span class="font-medium text-[#1e293b]"><?= htmlspecialchars($p['nom']) ?> <span class="text-[#64748b]">x<?= $p['quantite'] ?></span></span>
                    <span class="font-semibold text-[#0f172a]"><?= number_format($p['prix_unitaire'] * $p['quantite'], 0, ',', ' ') ?> FCFA</span>
                </div>
            <?php endforeach; ?>
            <?php if ($commande['remise'] > 0): ?>
                <div class="flex items-center justify-between border-b border-[#f8fafc] py-2 text-sm font-medium text-[#10b981]">
                    <span><i data-lucide="tag" class="mr-1 inline h-3.5 w-3.5"></i> Remise</span>
                    <span>-<?= number_format($commande['remise'], 0, ',', ' ') ?> FCFA</span>
                </div>
            <?php endif; ?>
            <div class="flex items-center justify-between py-4 text-xl font-extrabold text-[#10b981]">
                <span>Total</span>
                <span><?= number_format($commande['total'], 0, ',', ' ') ?> FCFA</span>
            </div>
        </div>

        <div class="mt-6 rounded-lg border-l-4 border-[#10b981] bg-[#f0fdf4] px-5 py-4 text-left text-sm leading-relaxed text-[#065f46]">
            <i data-lucide="truck" class="mr-1 inline h-4 w-4"></i> <strong>Livraison en cours de préparation</strong><br>
            Adresse : <?= htmlspecialchars($commande['adresse_livraison']) ?><br>
            Téléphone : <?= htmlspecialchars($commande['telephone']) ?><br>
            Délai estimé : <strong>1 à 3 heures</strong>
        </div>

        <a href="index.php" class="btn-gradient mt-8 inline-flex items-center gap-2 rounded-xl px-10 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-xl"><i data-lucide="shopping-bag" class="h-4 w-4"></i> Continuer mes achats</a>
    </div>
</div>

<script>lucide.createIcons()</script>
</body>
</html>

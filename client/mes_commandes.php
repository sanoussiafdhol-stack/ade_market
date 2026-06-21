<?php
require_once '../config/database.php';
require_once '../config/session.php';

redirigerSiNonConnecte();

$commandes = $pdo->prepare("
    SELECT c.*, COUNT(cp.id) as nb_produits
    FROM commandes c
    JOIN commande_produits cp ON cp.commande_id = c.id
    WHERE c.utilisateur_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$commandes->execute([$_SESSION['utilisateur_id']]);
$commandes = $commandes->fetchAll();

$panier_count = 0;
if (isset($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $item) {
        $panier_count += $item['quantite'];
    }
}

$statuts_labels = [
    'en_attente' => 'En attente',
    'confirmée' => 'Confirmée',
    'en_livraison' => 'En livraison',
    'livrée' => 'Livrée',
    'annulée' => 'Annulée'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes commandes - ADE MARKET</title>
    <meta name="description" content="Suivez l'historique et le statut de vos commandes ADE MARKET en temps réel.">
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
        <a href="compte.php" class="inline-flex items-center gap-2 rounded-full border border-white/30 px-4 py-2 text-sm font-medium text-white transition-all hover:bg-white hover:text-[#0f172a]"><i data-lucide="user" class="h-4 w-4"></i> Mon compte</a>
        <a href="panier.php" class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-[#10b981] to-[#059669] px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:-translate-y-0.5 hover:shadow-xl"><i data-lucide="shopping-cart" class="h-4 w-4"></i> Panier (<?= $panier_count ?>)</a>
        <a href="deconnexion.php" class="inline-flex items-center gap-2 rounded-full border border-white/30 px-4 py-2 text-sm font-medium text-white transition-all hover:bg-white hover:text-[#0f172a]"><i data-lucide="log-out" class="h-4 w-4"></i> Déconnexion</a>
    </div>
</header>

<div class="mx-auto my-12 max-w-4xl px-6">
    <div class="mb-8 flex items-center gap-3">
        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-[#3b82f6]/10"><i data-lucide="clipboard-list" class="h-7 w-7 text-[#3b82f6]"></i></div>
        <div>
            <h2 class="text-2xl font-bold text-[#0f172a]">Mes commandes</h2>
            <p class="text-sm text-[#64748b]">Historique de vos achats</p>
        </div>
    </div>

    <?php if (empty($commandes)): ?>
        <div class="rounded-xl border border-[#e2e8f0] bg-white px-8 py-20 text-center shadow-sm">
            <i data-lucide="shopping-bag" class="mx-auto mb-4 h-16 w-16 text-[#e2e8f0]"></i>
            <p class="mb-1 text-lg font-medium text-[#64748b]">Aucune commande pour le moment</p>
            <p class="mb-6 text-sm text-[#94a3b8]">Explorez notre catalogue et passez votre première commande</p>
            <a href="index.php" class="btn-gradient inline-flex items-center gap-2 rounded-xl px-8 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-xl"><i data-lucide="shopping-bag" class="h-4 w-4"></i> Découvrir les produits</a>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($commandes as $c): ?>
                <div class="rounded-xl border border-[#e2e8f0] bg-white shadow-sm transition-all hover:shadow-md">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-[#e2e8f0] px-6 py-4">
                        <div class="flex items-center gap-4">
                            <span class="text-lg font-extrabold text-[#10b981]">#<?= $c['id'] ?></span>
                            <span class="rounded-full bg-[#f1f5f9] px-3 py-1 text-xs font-medium text-[#64748b]"><?= $c['nb_produits'] ?> article(s)</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="badge-<?= $c['statut'] ?> inline-block rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider"><?= $statuts_labels[$c['statut']] ?></span>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                        <div class="space-y-1 text-sm text-[#64748b]">
                            <div class="flex items-center gap-4">
                                <span><i data-lucide="calendar" class="mr-1 inline h-3.5 w-3.5"></i> <?= date('d/m/Y', strtotime($c['created_at'])) ?></span>
                                <span><i data-lucide="clock" class="mr-1 inline h-3.5 w-3.5"></i> <?= date('H:i', strtotime($c['created_at'])) ?></span>
                            </div>
                            <div class="mt-1 flex items-center gap-1">
                                <?php if ($c['moyen_paiement'] === 'mobile_money'): ?>
                                    <i data-lucide="smartphone" class="h-3.5 w-3.5 text-[#10b981]"></i> Mobile Money
                                <?php else: ?>
                                    <i data-lucide="banknote" class="h-3.5 w-3.5 text-[#10b981]"></i> Paiement à la livraison
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-xl font-extrabold text-[#0f172a]"><?= number_format($c['total'], 0, ',', ' ') ?> FCFA</span>
                            <a href="confirmation.php?id=<?= $c['id'] ?>" class="inline-flex items-center gap-1 rounded-lg bg-[#f1f5f9] px-3 py-1.5 text-xs font-semibold text-[#0f172a] transition-all hover:bg-[#10b981] hover:text-white"><i data-lucide="eye" class="h-3.5 w-3.5"></i> Détail</a>
                            <a href="facture.php?id=<?= $c['id'] ?>" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-[#e2e8f0] px-3 py-1.5 text-xs font-semibold text-[#64748b] transition-all hover:border-[#10b981] hover:text-[#10b981]"><i data-lucide="file-text" class="h-3.5 w-3.5"></i> Facture</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>lucide.createIcons()</script>
</body>
</html>

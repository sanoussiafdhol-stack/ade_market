<?php
require_once '../config/database.php';
require_once '../config/session.php';

redirigerSiNonAdmin();

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
    if (!isset($_POST['csrf_token']) || !verifierTokenCSRF($_POST['csrf_token'])) {
        $message = "Erreur de validation CSRF.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ? AND role = 'client'");
        $stmt->execute([(int)$_POST['id']]);
        $message = "Client supprimé.";
    }
}

$clients = $pdo->query("
    SELECT u.*, COUNT(c.id) as nb_commandes, SUM(c.total) as total_depense
    FROM utilisateurs u
    LEFT JOIN commandes c ON u.id = c.utilisateur_id
    WHERE u.role = 'client'
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients - ADE MARKET Admin</title>
    <meta name="description" content="Gérez les clients inscrits sur ADE MARKET : historique et statistiques.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>

<div class="flex min-h-screen">
    <aside class="sidebar flex w-64 flex-shrink-0 flex-col bg-[#0f172a] py-8 text-[#94a3b8] shadow-xl">
        <div class="mb-6 border-b border-[#1e293b] px-8 pb-8">
            <h2 class="gradient-text text-2xl font-extrabold">ADE MARKET</h2>
            <p class="text-xs text-[#64748b]">Administration</p>
        </div>
        <nav class="flex flex-col">
            <a href="dashboard.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="bar-chart-3" class="h-4 w-4"></i> Dashboard</a>
            <a href="produits.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="package" class="h-4 w-4"></i> Produits</a>
            <a href="commandes.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="shopping-cart" class="h-4 w-4"></i> Commandes</a>
            <a href="livraisons.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="truck" class="h-4 w-4"></i> Livraisons</a>
            <a href="clients.php" class="active flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="users" class="h-4 w-4"></i> Clients</a>
            <a href="promotions.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="tag" class="h-4 w-4"></i> Promotions</a>
            <a href="../client/index.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="globe" class="h-4 w-4"></i> Voir le site</a>
            <a href="../client/deconnexion.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="log-out" class="h-4 w-4"></i> Déconnexion</a>
        </nav>
    </aside>

    <main class="flex-1 overflow-y-auto bg-[#f8fafc] p-10">
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <h1 class="flex items-center gap-3 text-2xl font-bold text-[#0f172a]"><i data-lucide="users" class="h-6 w-6"></i> Gestion des clients</h1>
            <span class="rounded-full bg-[#10b981]/10 px-4 py-1.5 text-sm font-bold text-[#10b981]"><?= count($clients) ?> clients</span>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 rounded-xl border border-[rgba(16,185,129,0.2)] bg-[#d1fae5] px-5 py-3 text-sm font-medium text-[#065f46]"><?= $message ?></div>
        <?php endif; ?>

        <div class="overflow-hidden rounded-xl border border-[#e2e8f0] bg-white pb-1 shadow-sm">
            <div class="flex items-center justify-between border-b border-[#e2e8f0] px-6 py-5">
                <h3 class="text-base font-bold text-[#0f172a]">Tous les clients</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-[#e2e8f0] bg-[#f8fafc] text-xs font-semibold uppercase tracking-wider text-[#1e293b]">
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Téléphone</th>
                            <th class="px-4 py-3">Adresse</th>
                            <th class="px-4 py-3">Commandes</th>
                            <th class="px-4 py-3">Total dépensé</th>
                            <th class="px-4 py-3">Inscrit le</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $c): ?>
                        <tr class="border-b border-[#e2e8f0] text-sm transition-colors hover:bg-[#10b981]/[0.02]">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-r from-[#10b981] to-[#059669] text-xs font-bold text-white"><?= strtoupper(substr($c['nom'], 0, 2)) ?></div>
                                    <span class="font-semibold text-[#0f172a]"><?= htmlspecialchars($c['nom']) ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-3"><?= htmlspecialchars($c['email']) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($c['telephone'] ?? '-') ?></td>
                            <td class="px-4 py-3 text-xs text-[#64748b]"><?= htmlspecialchars(substr($c['adresse'] ?? '-', 0, 30)) ?></td>
                            <td class="px-4 py-3"><?= $c['nb_commandes'] > 0 ? $c['nb_commandes'] : '<span class="text-[#64748b]">0</span>' ?></td>
                            <td class="px-4 py-3 font-semibold"><?= $c['total_depense'] ? number_format($c['total_depense'], 0, ',', ' ') . ' FCFA' : '<span class="text-[#64748b]">-</span>' ?></td>
                            <td class="px-4 py-3 text-xs text-[#64748b]"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce client ?')">
                                    <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="inline-flex cursor-pointer items-center gap-1 rounded-lg bg-[#fee2e2] px-3 py-1.5 text-xs font-semibold text-[#ef4444] transition-all hover:bg-[#ef4444] hover:text-white"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Supprimer</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>lucide.createIcons()</script>
</body>
</html>

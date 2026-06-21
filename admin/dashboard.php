<?php
require_once '../config/database.php';
require_once '../config/session.php';

redirigerSiNonAdmin();

// Stats générales
$total_produits = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
$total_commandes = $pdo->query("SELECT COUNT(*) FROM commandes")->fetchColumn();
$total_clients = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'client'")->fetchColumn();
$total_revenus = $pdo->query("SELECT SUM(total) FROM commandes WHERE statut != 'annulée'")->fetchColumn() ?? 0;

// Stats du jour
$ajd = date('Y-m-d');
$commandes_ajd = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(total),0) FROM commandes WHERE DATE(created_at) = ? AND statut != 'annulée'");
$commandes_ajd->execute([$ajd]);
list($nb_commandes_ajd, $ca_ajd) = $commandes_ajd->fetch(PDO::FETCH_NUM);

// Stats par statut
$statuts_counts = [];
$stmt = $pdo->query("SELECT statut, COUNT(*) as nb FROM commandes GROUP BY statut");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $statuts_counts[$r['statut']] = (int)$r['nb'];
}

// Stock faible
$stock_faible = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock <= 5 AND stock > 0")->fetchColumn();
$rupture = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock <= 0")->fetchColumn();

// Dernières commandes
$commandes = $pdo->query("SELECT c.*, u.nom FROM commandes c JOIN utilisateurs u ON c.utilisateur_id = u.id ORDER BY c.created_at DESC LIMIT 8")->fetchAll();

// Top produits
$top_produits = $pdo->query("
    SELECT p.nom, SUM(cp.quantite) as total_vendus, SUM(cp.quantite * cp.prix_unitaire) as total_ca
    FROM commande_produits cp
    JOIN produits p ON cp.produit_id = p.id
    JOIN commandes c ON cp.commande_id = c.id
    WHERE c.statut != 'annulée'
    GROUP BY cp.produit_id
    ORDER BY total_vendus DESC
    LIMIT 5
")->fetchAll();

// Chiffre d'affaires par mois (6 derniers mois)
$ca_mensuel = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as mois, COALESCE(SUM(total), 0) as ca
    FROM commandes
    WHERE statut != 'annulée' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY mois
    ORDER BY mois ASC
")->fetchAll();

$mois_labels = [];
$mois_data = [];
foreach ($ca_mensuel as $m) {
    $mois_labels[] = $m['mois'];
    $mois_data[] = (float)$m['ca'];
}
// Remplir les mois manquants avec 0
if (empty($mois_labels)) {
    for ($i = 5; $i >= 0; $i--) {
        $mois_labels[] = date('Y-m', strtotime("-$i months"));
        $mois_data[] = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ADE MARKET Admin</title>
    <meta name="description" content="Dashboard d'administration ADE MARKET : gérez vos ventes, produits et commandes.">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
</head>
<body>

<div class="flex min-h-screen">
    <aside class="sidebar flex w-64 flex-shrink-0 flex-col bg-[#0f172a] py-8 text-[#94a3b8] shadow-xl">
        <div class="mb-6 border-b border-[#1e293b] px-8 pb-8">
            <h2 class="gradient-text text-2xl font-extrabold">ADE MARKET</h2>
            <p class="text-xs text-[#64748b]">Administration</p>
        </div>
        <nav class="flex flex-col">
            <a href="dashboard.php" class="active flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="bar-chart-3" class="h-4 w-4"></i> Dashboard</a>
            <a href="produits.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="package" class="h-4 w-4"></i> Produits</a>
            <a href="commandes.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="shopping-cart" class="h-4 w-4"></i> Commandes</a>
            <a href="livraisons.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="truck" class="h-4 w-4"></i> Livraisons</a>
            <a href="clients.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="users" class="h-4 w-4"></i> Clients</a>
            <a href="../client/index.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="globe" class="h-4 w-4"></i> Voir le site</a>
            <a href="../client/deconnexion.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="log-out" class="h-4 w-4"></i> Déconnexion</a>
        </nav>
    </aside>

    <main class="flex-1 overflow-y-auto bg-[#f8fafc] p-10">
        <div class="mb-8 flex items-center justify-between">
            <h1 class="flex items-center gap-3 text-2xl font-bold text-[#0f172a]"><i data-lucide="layout-dashboard" class="h-6 w-6"></i> Tableau de bord</h1>
            <span class="text-sm text-[#64748b]"><i data-lucide="calendar" class="mr-1 inline h-4 w-4"></i> <?= date('d/m/Y') ?></span>
        </div>

        <!-- Cartes KPI principales -->
        <div class="mb-8 grid grid-cols-2 gap-5 md:grid-cols-4">
            <div class="rounded-xl border border-[#e2e8f0] bg-white p-6 shadow-sm transition-all hover:-translate-y-1 hover:shadow-lg">
                <div class="mb-1 flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#64748b]">Revenus totaux</span>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[rgba(245,158,11,0.1)]"><i data-lucide="wallet" class="h-4 w-4 text-[#f59e0b]"></i></div>
                </div>
                <div class="text-2xl font-extrabold text-[#0f172a]"><?= number_format($total_revenus, 0, ',', ' ') ?> FCFA</div>
                <div class="mt-1 text-xs text-[#10b981]"><i data-lucide="trending-up" class="mr-0.5 inline h-3 w-3"></i> CA aujourd'hui : <?= number_format($ca_ajd, 0, ',', ' ') ?> FCFA</div>
            </div>
            <div class="rounded-xl border border-[#e2e8f0] bg-white p-6 shadow-sm transition-all hover:-translate-y-1 hover:shadow-lg">
                <div class="mb-1 flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#64748b]">Commandes</span>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[rgba(59,130,246,0.1)]"><i data-lucide="shopping-cart" class="h-4 w-4 text-[#3b82f6]"></i></div>
                </div>
                <div class="text-2xl font-extrabold text-[#0f172a]"><?= $total_commandes ?></div>
                <div class="mt-1 text-xs text-[#64748b]"><?= $nb_commandes_ajd ?> aujourd'hui</div>
            </div>
            <div class="rounded-xl border border-[#e2e8f0] bg-white p-6 shadow-sm transition-all hover:-translate-y-1 hover:shadow-lg">
                <div class="mb-1 flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#64748b]">Clients</span>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[rgba(16,185,129,0.1)]"><i data-lucide="users" class="h-4 w-4 text-[#10b981]"></i></div>
                </div>
                <div class="text-2xl font-extrabold text-[#0f172a]"><?= $total_clients ?></div>
            </div>
            <div class="rounded-xl border border-[#e2e8f0] bg-white p-6 shadow-sm transition-all hover:-translate-y-1 hover:shadow-lg">
                <div class="mb-1 flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#64748b]">Produits</span>
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[rgba(244,63,94,0.1)]"><i data-lucide="package" class="h-4 w-4 text-[#10b981]"></i></div>
                </div>
                <div class="text-2xl font-extrabold text-[#0f172a]"><?= $total_produits ?></div>
                <div class="mt-1 flex gap-3 text-xs">
                    <?php if ($stock_faible > 0): ?>
                        <span class="text-[#f59e0b]"><i data-lucide="alert-triangle" class="mr-0.5 inline h-3 w-3"></i> <?= $stock_faible ?> stock faible</span>
                    <?php endif; ?>
                    <?php if ($rupture > 0): ?>
                        <span class="text-[#ef4444]"><i data-lucide="x-circle" class="mr-0.5 inline h-3 w-3"></i> <?= $rupture ?> rupture</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Graphiques + Commandes par statut -->
        <div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- CA mensuel (Chart.js) -->
            <div class="rounded-xl border border-[#e2e8f0] bg-white p-6 shadow-sm lg:col-span-2">
                <h3 class="mb-4 flex items-center gap-2 text-base font-bold text-[#0f172a]"><i data-lucide="trending-up" class="h-4 w-4"></i> Évolution du chiffre d'affaires</h3>
                <canvas id="caChart" height="200"></canvas>
            </div>

            <!-- Commandes par statut -->
            <div class="rounded-xl border border-[#e2e8f0] bg-white p-6 shadow-sm">
                <h3 class="mb-4 flex items-center gap-2 text-base font-bold text-[#0f172a]"><i data-lucide="pie-chart" class="h-4 w-4"></i> Commandes par statut</h3>
                <div class="space-y-3">
                    <?php
                    $statuts_config = [
                        'en_attente' => ['label' => 'En attente', 'color' => 'bg-[#f59e0b]', 'text' => 'text-[#92400e]', 'bg' => 'bg-[#fef3c7]'],
                        'confirmée' => ['label' => 'Confirmée', 'color' => 'bg-[#3b82f6]', 'text' => 'text-[#1e40af]', 'bg' => 'bg-[#dbeafe]'],
                        'en_livraison' => ['label' => 'En livraison', 'color' => 'bg-[#10b981]', 'text' => 'text-[#065f46]', 'bg' => 'bg-[#d1fae5]'],
                        'livrée' => ['label' => 'Livrée', 'color' => 'bg-[#22c55e]', 'text' => 'text-[#14532d]', 'bg' => 'bg-[#bbf7d0]'],
                        'annulée' => ['label' => 'Annulée', 'color' => 'bg-[#ef4444]', 'text' => 'text-[#991b1b]', 'bg' => 'bg-[#fee2e2]'],
                    ];
                    $total = array_sum($statuts_counts) ?: 1;
                    foreach ($statuts_config as $key => $cfg):
                        $count = $statuts_counts[$key] ?? 0;
                        $pct = round($count / $total * 100);
                    ?>
                        <div>
                            <div class="mb-1 flex items-center justify-between text-sm">
                                <span class="<?= $cfg['text'] ?> font-medium"><?= $cfg['label'] ?></span>
                                <span class="font-semibold text-[#0f172a]"><?= $count ?> (<?= $pct ?>%)</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-[#f1f5f9]">
                                <div class="<?= $cfg['color'] ?> h-full rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Top produits + Stock faible -->
        <div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Top produits -->
            <div class="rounded-xl border border-[#e2e8f0] bg-white shadow-sm">
                <div class="border-b border-[#e2e8f0] px-6 py-5">
                    <h3 class="flex items-center gap-2 text-base font-bold text-[#0f172a]"><i data-lucide="trophy" class="h-4 w-4"></i> Top 5 produits les plus vendus</h3>
                </div>
                <div class="p-4">
                    <?php if (empty($top_produits)): ?>
                        <p class="py-6 text-center text-sm text-[#64748b]">Aucune vente pour le moment</p>
                    <?php else: ?>
                        <?php foreach ($top_produits as $i => $p): ?>
                            <div class="flex items-center gap-4 rounded-xl px-3 py-3 transition-colors hover:bg-[#f8fafc]">
                                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg font-extrabold <?= $i === 0 ? 'bg-[#fef3c7] text-[#92400e]' : ($i === 1 ? 'bg-[#f1f5f9] text-[#64748b]' : ($i === 2 ? 'bg-[#fef2f2] text-[#991b1b]' : 'bg-[#f8fafc] text-[#94a3b8]')) ?>">
                                    <?= $i + 1 ?>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold text-[#0f172a]"><?= htmlspecialchars($p['nom']) ?></div>
                                    <div class="text-xs text-[#64748b]"><?= $p['total_vendus'] ?> vendus · <?= number_format($p['total_ca'], 0, ',', ' ') ?> FCFA</div>
                                </div>
                                <div class="text-sm font-bold text-[#10b981]"><?= $p['total_vendus'] ?>×</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions rapides + Stock -->
            <div class="space-y-6">
                <div class="rounded-xl border border-[#e2e8f0] bg-white p-6 shadow-sm">
                    <h3 class="mb-4 flex items-center gap-2 text-base font-bold text-[#0f172a]"><i data-lucide="zap" class="h-4 w-4"></i> Actions rapides</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <a href="produits.php" class="flex items-center gap-2 rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm font-medium text-[#0f172a] transition-all hover:border-[#10b981] hover:bg-[#10b981]/5 hover:text-[#10b981]"><i data-lucide="plus-circle" class="h-4 w-4"></i> Nouveau produit</a>
                        <a href="commandes.php?statut=en_attente" class="flex items-center gap-2 rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm font-medium text-[#0f172a] transition-all hover:border-[#10b981] hover:bg-[#10b981]/5 hover:text-[#10b981]"><i data-lucide="clock" class="h-4 w-4"></i> Commandes en attente</a>
                        <a href="produits.php" class="flex items-center gap-2 rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm font-medium text-[#0f172a] transition-all hover:border-[#10b981] hover:bg-[#10b981]/5 hover:text-[#10b981]"><i data-lucide="alert-triangle" class="h-4 w-4"></i> Stock faible</a>
                        <a href="livraisons.php" class="flex items-center gap-2 rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm font-medium text-[#0f172a] transition-all hover:border-[#10b981] hover:bg-[#10b981]/5 hover:text-[#10b981]"><i data-lucide="truck" class="h-4 w-4"></i> Livraisons</a>
                    </div>
                </div>

                <?php if ($stock_faible > 0 || $rupture > 0): ?>
                <div class="rounded-xl border border-[#e2e8f0] bg-white p-6 shadow-sm">
                    <h3 class="mb-4 flex items-center gap-2 text-base font-bold text-[#0f172a]">
                        <i data-lucide="alert-triangle" class="h-4 w-4 text-[#f59e0b]"></i>
                        Alertes stock
                    </h3>
                    <?php
                    $alertes = $pdo->query("SELECT id, nom, stock FROM produits WHERE stock <= 5 ORDER BY stock ASC LIMIT 5")->fetchAll();
                    ?>
                    <div class="space-y-2">
                        <?php foreach ($alertes as $p): ?>
                            <div class="flex items-center justify-between rounded-lg px-3 py-2 text-sm <?= $p['stock'] <= 0 ? 'bg-[#fee2e2]' : 'bg-[#fef3c7]' ?>">
                                <span class="font-medium text-[#0f172a]"><?= htmlspecialchars($p['nom']) ?></span>
                                <span class="font-bold <?= $p['stock'] <= 0 ? 'text-[#ef4444]' : 'text-[#f59e0b]' ?>"><?= $p['stock'] <= 0 ? 'Rupture' : $p['stock'] . ' restants' ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dernières commandes -->
        <div class="overflow-hidden rounded-xl border border-[#e2e8f0] bg-white pb-1 shadow-sm">
            <div class="flex items-center justify-between border-b border-[#e2e8f0] px-6 py-5">
                <h3 class="flex items-center gap-2 text-base font-bold text-[#0f172a]"><i data-lucide="clock" class="h-4 w-4"></i> Dernières commandes</h3>
                <a href="commandes.php" class="inline-flex items-center gap-1 rounded-lg bg-[#f1f5f9] px-3 py-1.5 text-xs font-semibold text-[#0f172a] transition-all hover:bg-[#10b981] hover:text-white">Toutes les commandes <i data-lucide="arrow-right" class="h-3 w-3"></i></a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-[#e2e8f0] bg-[#f8fafc] text-xs font-semibold uppercase tracking-wider text-[#1e293b]">
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Paiement</th>
                            <th class="px-4 py-3">Statut</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($commandes)): ?>
                            <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-[#64748b]">Aucune commande pour le moment</td></tr>
                        <?php endif; ?>
                        <?php foreach ($commandes as $c): ?>
                        <tr class="border-b border-[#e2e8f0] text-sm transition-colors hover:bg-[#10b981]/[0.02]">
                            <td class="px-4 py-3 font-bold text-[#10b981]">#<?= $c['id'] ?></td>
                            <td class="px-4 py-3 font-medium text-[#0f172a]"><?= htmlspecialchars($c['nom']) ?></td>
                            <td class="px-4 py-3 font-semibold"><?= number_format($c['total'], 0, ',', ' ') ?> FCFA</td>
                            <td class="px-4 py-3 text-xs text-[#64748b]"><?= $c['moyen_paiement'] === 'mobile_money' ? 'Mobile Money' : 'Cash' ?></td>
                            <td class="px-4 py-3"><span class="badge-<?= htmlspecialchars($c['statut']) ?> inline-block rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider"><?= htmlspecialchars(ucfirst($c['statut'])) ?></span></td>
                            <td class="px-4 py-3 text-xs text-[#64748b]"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                            <td class="px-4 py-3 text-right"><a href="commandes.php?id=<?= $c['id'] ?>" class="inline-flex items-center gap-1 rounded-lg bg-[#f1f5f9] px-3 py-1.5 text-xs font-semibold text-[#0f172a] transition-all hover:bg-[#10b981] hover:text-white"><i data-lucide="eye" class="h-3.5 w-3.5"></i> Voir</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
lucide.createIcons()

const ctx = document.getElementById('caChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($mois_labels) ?>,
        datasets: [{
            label: 'Chiffre d\'affaires (FCFA)',
            data: <?= json_encode($mois_data) ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(244, 63, 94, 0.1)',
            fill: true,
            tension: 0.3,
            pointBackgroundColor: '#10b981',
            pointRadius: 4,
            pointHoverRadius: 6,
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(v) { return v.toLocaleString('fr-FR') + ' F' },
                    font: { size: 11 }
                },
                grid: { color: '#f1f5f9' }
            },
            x: {
                ticks: { font: { size: 11 } },
                grid: { display: false }
            }
        }
    }
});
</script>
</body>
</html>

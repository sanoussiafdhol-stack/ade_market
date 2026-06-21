<?php
require_once '../config/database.php';
require_once '../config/session.php';

redirigerSiNonAdmin();

$message = "";
$filtre_statut = $_GET['statut'] ?? '';
$recherche = trim($_GET['recherche'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifierTokenCSRF($_POST['csrf_token'])) {
        $message = "Erreur de validation CSRF.";
    } else {
        $livraison_id = (int)$_POST['livraison_id'];
        $statut = $_POST['statut'];
        $livreur = trim($_POST['livreur']);

        $stmt = $pdo->prepare("UPDATE livraisons SET statut = ?, livreur = ? WHERE id = ?");
        $stmt->execute([$statut, $livreur, $livraison_id]);

        if ($statut === 'livré') {
            $stmt = $pdo->prepare("UPDATE livraisons SET date_livraison = NOW() WHERE id = ?");
            $stmt->execute([$livraison_id]);
            $stmt = $pdo->prepare("UPDATE commandes SET statut = 'livrée' WHERE id = (SELECT commande_id FROM livraisons WHERE id = ?)");
            $stmt->execute([$livraison_id]);
        }

        require_once '../config/email.php';
        $stmt = $pdo->prepare("
            SELECT l.*, c.id as cmd_id, u.nom, u.email
            FROM livraisons l
            JOIN commandes c ON l.commande_id = c.id
            JOIN utilisateurs u ON c.utilisateur_id = u.id
            WHERE l.id = ?
        ");
        $stmt->execute([$livraison_id]);
        $cmd = $stmt->fetch();

        if ($cmd && $cmd['email']) {
            if ($statut === 'en_cours') {
                $sujet = "Commande #{$cmd['cmd_id']} — En cours de livraison";
                $msg = "Bonjour {$cmd['nom']},\n\nVotre commande #{$cmd['cmd_id']} est en cours de livraison.\n";
                $msg .= $livreur ? "Livreur : $livreur\n" : "";
                $msg .= "\nSoyez prêt à la réceptionner.\n\nADE MARKET - Porto-Novo\ncontact@ademarket.bj";
                envoyerEmail($cmd['email'], $sujet, $msg);
            } elseif ($statut === 'livré') {
                $sujet = "Commande #{$cmd['cmd_id']} — Livrée avec succès";
                $msg = "Bonjour {$cmd['nom']},\n\nVotre commande #{$cmd['cmd_id']} a été livrée avec succès.\nMerci de votre confiance !\n\nADE MARKET - Porto-Novo\ncontact@ademarket.bj";
                envoyerEmail($cmd['email'], $sujet, $msg);
            }
        }

        $message = "Livraison mise à jour.";
    }
}

// Stats
$stats = [];
$stmt = $pdo->query("SELECT statut, COUNT(*) as nb FROM livraisons GROUP BY statut");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
    $stats[$s['statut']] = $s['nb'];
}
$total_livraisons = array_sum($stats);

// Filtrage
$where = "1=1";
$params = [];
if ($filtre_statut) {
    $where .= " AND l.statut = ?";
    $params[] = $filtre_statut;
}
if ($recherche) {
    $where .= " AND (l.id LIKE ? OR u.nom LIKE ? OR u.telephone LIKE ? OR l.livreur LIKE ?)";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
}

$livraisons = $pdo->prepare("
    SELECT l.*, c.id as commande_id, c.total, c.adresse_livraison, u.nom as client, u.telephone
    FROM livraisons l
    JOIN commandes c ON l.commande_id = c.id
    JOIN utilisateurs u ON c.utilisateur_id = u.id
    WHERE $where
    ORDER BY l.id DESC
");
$livraisons->execute($params);
$livraisons = $livraisons->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livraisons - ADE MARKET Admin</title>
    <meta name="description" content="Suivez et gérez les livraisons ADE MARKET : assignation livreur et mise à jour des statuts.">
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
            <a href="livraisons.php" class="active flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="truck" class="h-4 w-4"></i> Livraisons</a>
            <a href="clients.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="users" class="h-4 w-4"></i> Clients</a>
            <a href="../client/index.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="globe" class="h-4 w-4"></i> Voir le site</a>
            <a href="../client/deconnexion.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="log-out" class="h-4 w-4"></i> Déconnexion</a>
        </nav>
    </aside>

    <main class="flex-1 overflow-y-auto bg-[#f8fafc] p-10">
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <h1 class="flex items-center gap-3 text-2xl font-bold text-[#0f172a]"><i data-lucide="truck" class="h-6 w-6"></i> Gestion des livraisons</h1>
            <span class="rounded-full bg-[#10b981]/10 px-4 py-1.5 text-sm font-bold text-[#10b981]"><?= $total_livraisons ?> livraisons</span>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 rounded-xl border border-[rgba(16,185,129,0.2)] bg-[#d1fae5] px-5 py-3 text-sm font-medium text-[#065f46]" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show=false, 4000)">
                <i data-lucide="check-circle" class="mr-1.5 inline h-4 w-4"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="mb-8 grid grid-cols-3 gap-4">
            <?php
            $statuts_config = [
                'en_attente' => ['label' => 'En attente', 'bg' => 'bg-[#fef3c7]', 'text' => 'text-[#92400e]', 'icon' => 'clock'],
                'en_cours' => ['label' => 'En cours', 'bg' => 'bg-[#dbeafe]', 'text' => 'text-[#1e40af]', 'icon' => 'truck'],
                'livré' => ['label' => 'Livré', 'bg' => 'bg-[#bbf7d0]', 'text' => 'text-[#14532d]', 'icon' => 'package-check'],
            ];
            foreach ($statuts_config as $key => $cfg):
                $count = $stats[$key] ?? 0;
                $active = $filtre_statut === $key ? 'ring-2 ring-[#10b981]' : '';
            ?>
                <a href="?statut=<?= $key ?>" class="<?= $cfg['bg'] ?> <?= $cfg['text'] ?> <?= $active ?> flex items-center gap-3 rounded-xl px-5 py-4 transition-all hover:shadow-md">
                    <i data-lucide="<?= $cfg['icon'] ?>" class="h-6 w-6 flex-shrink-0"></i>
                    <div>
                        <div class="text-2xl font-extrabold"><?= $count ?></div>
                        <div class="text-xs font-medium opacity-80"><?= $cfg['label'] ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Search + Filters -->
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <form method="GET" class="flex flex-1 items-center gap-2">
                <?php if ($filtre_statut): ?>
                    <input type="hidden" name="statut" value="<?= htmlspecialchars($filtre_statut) ?>">
                <?php endif; ?>
                <div class="relative flex-1">
                    <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#94a3b8]"></i>
                    <input type="text" name="recherche" placeholder="Rechercher par ID, client, téléphone ou livreur..." value="<?= htmlspecialchars($recherche) ?>" class="w-full rounded-xl border border-[#e2e8f0] bg-white py-3 pl-10 pr-4 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                </div>
                <button type="submit" class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[#10b981] px-5 py-3 text-sm font-semibold text-white transition-all hover:opacity-85"><i data-lucide="search" class="h-4 w-4"></i> Chercher</button>
            </form>
            <?php if ($filtre_statut || $recherche): ?>
                <a href="livraisons.php" class="inline-flex items-center gap-2 rounded-xl border border-[#e2e8f0] bg-white px-5 py-3 text-sm font-medium text-[#64748b] transition-all hover:border-[#fca5a5] hover:text-[#ef4444]"><i data-lucide="x" class="h-4 w-4"></i> Réinitialiser</a>
            <?php endif; ?>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-[#e2e8f0] bg-white pb-1 shadow-sm">
            <div class="flex items-center justify-between border-b border-[#e2e8f0] px-6 py-5">
                <h3 class="flex items-center gap-2 text-base font-bold text-[#0f172a]">
                    <i data-lucide="list" class="h-4 w-4"></i>
                    <?php if ($filtre_statut): ?>
                        <?= $statuts_config[$filtre_statut]['label'] ?? '' ?> —
                    <?php endif; ?>
                    <?= count($livraisons) ?> résultat(s)
                </h3>
                <div class="flex gap-1 text-xs">
                    <a href="livraisons.php" class="rounded-lg px-3 py-1.5 font-medium transition-all <?= !$filtre_statut ? 'bg-[#10b981] text-white' : 'bg-[#f1f5f9] text-[#64748b] hover:bg-[#e2e8f0]' ?>">Toutes</a>
                    <?php foreach (['en_attente', 'en_cours', 'livré'] as $s): ?>
                        <a href="?statut=<?= $s ?>" class="rounded-lg px-3 py-1.5 font-medium transition-all <?= $filtre_statut === $s ? 'bg-[#10b981] text-white' : 'bg-[#f1f5f9] text-[#64748b] hover:bg-[#e2e8f0]' ?>"><?= $statuts_config[$s]['label'] ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-[#e2e8f0] bg-[#f8fafc] text-xs font-semibold uppercase tracking-wider text-[#1e293b]">
                            <th class="px-4 py-3">Commande</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Téléphone</th>
                            <th class="px-4 py-3">Adresse</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Livreur</th>
                            <th class="px-4 py-3">Statut</th>
                            <th class="px-4 py-3">Livré le</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($livraisons)): ?>
                            <tr><td colspan="9" class="px-4 py-12 text-center text-sm text-[#64748b]">Aucune livraison trouvée.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($livraisons as $l): ?>
                        <tr class="border-b border-[#e2e8f0] text-sm transition-colors hover:bg-[#10b981]/[0.02]">
                            <td class="px-4 py-3 font-bold text-[#10b981]">#<?= $l['commande_id'] ?></td>
                            <td class="px-4 py-3 font-medium text-[#0f172a]"><?= htmlspecialchars($l['client']) ?></td>
                            <td class="px-4 py-3 text-[#64748b]"><?= htmlspecialchars($l['telephone']) ?></td>
                            <td class="px-4 py-3 text-xs text-[#64748b] max-w-[140px] truncate" title="<?= htmlspecialchars($l['adresse_livraison']) ?>"><?= htmlspecialchars($l['adresse_livraison']) ?></td>
                            <td class="px-4 py-3 font-semibold"><?= number_format($l['total'], 0, ',', ' ') ?> FCFA</td>
                            <td class="px-4 py-3 font-medium"><?= $l['livreur'] ? htmlspecialchars($l['livreur']) : '<span class="text-[#94a3b8]">—</span>' ?></td>
                            <td class="px-4 py-3"><span class="badge-<?= $l['statut'] ?> inline-block rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider"><?= $statuts_config[$l['statut']]['label'] ?? $l['statut'] ?></span></td>
                            <td class="px-4 py-3 text-xs text-[#64748b]"><?= $l['date_livraison'] ? date('d/m/Y H:i', strtotime($l['date_livraison'])) : '-' ?></td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" class="inline-flex items-center gap-1.5">
                                    <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                    <input type="hidden" name="livraison_id" value="<?= $l['id'] ?>">
                                    <input type="text" name="livreur" placeholder="Livreur" value="<?= htmlspecialchars($l['livreur'] ?? '') ?>" class="w-24 rounded-lg border border-[#e2e8f0] px-2.5 py-1.5 text-xs outline-none transition-all focus:border-[#10b981]">
                                    <select name="statut" class="rounded-lg border border-[#e2e8f0] bg-white px-2 py-1.5 text-xs font-medium text-[#1e293b] outline-none transition-all focus:border-[#10b981]">
                                        <option value="en_attente" <?= $l['statut']==='en_attente'?'selected':'' ?>>En attente</option>
                                        <option value="en_cours" <?= $l['statut']==='en_cours'?'selected':'' ?>>En cours</option>
                                        <option value="livré" <?= $l['statut']==='livré'?'selected':'' ?>>Livré</option>
                                    </select>
                                    <button type="submit" class="inline-flex cursor-pointer items-center gap-1 rounded-lg bg-[#10b981] px-2.5 py-1.5 text-xs font-semibold text-white transition-all hover:opacity-85"><i data-lucide="check" class="h-3 w-3"></i></button>
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

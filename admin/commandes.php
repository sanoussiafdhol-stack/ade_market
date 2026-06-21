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
        $commande_id = (int)$_POST['commande_id'];
        $statut = $_POST['statut'];

        $stmt = $pdo->prepare("SELECT c.*, u.nom, u.email FROM commandes c JOIN utilisateurs u ON c.utilisateur_id = u.id WHERE c.id = ?");
        $stmt->execute([$commande_id]);
        $cmd = $stmt->fetch();

        if ($cmd) {
            $stmt = $pdo->prepare("UPDATE commandes SET statut = ? WHERE id = ?");
            $stmt->execute([$statut, $commande_id]);

            if ($statut === 'livrée') {
                $stmt = $pdo->prepare("UPDATE livraisons SET statut = 'livré', date_livraison = NOW() WHERE commande_id = ?");
                $stmt->execute([$commande_id]);
            }

            require_once '../config/email.php';

            $statuts_labels = ['en_attente' => 'En attente', 'confirmée' => 'Confirmée', 'en_livraison' => 'En livraison', 'livrée' => 'Livrée', 'annulée' => 'Annulée'];
            $sujet = "Commande #$commande_id — {$statuts_labels[$statut]}";
            $message_mail = "Bonjour {$cmd['nom']},\n\n";
            $message_mail .= "Le statut de votre commande #$commande_id a été mis à jour :\n";
            $message_mail .= "Nouveau statut : {$statuts_labels[$statut]}\n\n";

            if ($statut === 'confirmée') {
                $message_mail .= "Votre commande est confirmée ! Nous préparons vos articles.\n";
            } elseif ($statut === 'en_livraison') {
                $message_mail .= "Votre commande est en cours de livraison. Soyez prêt à la réceptionner.\n";
            } elseif ($statut === 'livrée') {
                $message_mail .= "Votre commande a été livrée avec succès. Merci de votre confiance !\n";
            } elseif ($statut === 'annulée') {
                $message_mail .= "Votre commande a été annulée. Contactez-nous pour plus d'informations.\n";
            }

            $message_mail .= "\nADE MARKET - Porto-Novo, Bénin\ncontact@ademarket.bj";

            $email_envoye = envoyerEmail($cmd['email'], $sujet, $message_mail);
            if (!$email_envoye) {
                error_log("Échec envoi email pour commande #$commande_id à {$cmd['email']}");
            }
        }

        $message = "Statut mis à jour.";
    }
}

// Stats
$stats = [];
$stmt = $pdo->query("SELECT statut, COUNT(*) as nb FROM commandes GROUP BY statut");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
    $stats[$s['statut']] = $s['nb'];
}
$total_commandes = array_sum($stats);

// Filtrage
$where = "1=1";
$params = [];
if ($filtre_statut) {
    $where .= " AND c.statut = ?";
    $params[] = $filtre_statut;
}
if ($recherche) {
    $where .= " AND (c.id = ? OR u.nom LIKE ? OR u.telephone LIKE ?)";
    $params[] = is_numeric($recherche) ? (int)$recherche : 0;
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
}

$commandes = $pdo->prepare("SELECT c.*, u.nom, u.telephone as client_tel FROM commandes c JOIN utilisateurs u ON c.utilisateur_id = u.id WHERE $where ORDER BY c.created_at DESC");
$commandes->execute($params);
$commandes = $commandes->fetchAll();

// Détail
$detail = null;
$detail_produits = [];
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT c.*, u.nom, u.telephone, u.email FROM commandes c JOIN utilisateurs u ON c.utilisateur_id = u.id WHERE c.id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $detail = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT cp.*, p.nom FROM commande_produits cp JOIN produits p ON cp.produit_id = p.id WHERE cp.commande_id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $detail_produits = $stmt->fetchAll();
}

$statuts = ['en_attente', 'confirmée', 'en_livraison', 'livrée', 'annulée'];
$statuts_labels = ['en_attente' => 'En attente', 'confirmée' => 'Confirmée', 'en_livraison' => 'En livraison', 'livrée' => 'Livrée', 'annulée' => 'Annulée'];
$statuts_icones = ['en_attente' => 'clock', 'confirmée' => 'check-circle', 'en_livraison' => 'truck', 'livrée' => 'package-check', 'annulée' => 'x-circle'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes - ADE MARKET Admin</title>
    <meta name="description" content="Gérez les commandes clients sur ADE MARKET : suivi, statuts et mise à jour.">
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
            <a href="commandes.php" class="active flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="shopping-cart" class="h-4 w-4"></i> Commandes</a>
            <a href="livraisons.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="truck" class="h-4 w-4"></i> Livraisons</a>
            <a href="clients.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="users" class="h-4 w-4"></i> Clients</a>
            <a href="../client/index.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="globe" class="h-4 w-4"></i> Voir le site</a>
            <a href="../client/deconnexion.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="log-out" class="h-4 w-4"></i> Déconnexion</a>
        </nav>
    </aside>

    <main class="flex-1 overflow-y-auto bg-[#f8fafc] p-10">
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <h1 class="flex items-center gap-3 text-2xl font-bold text-[#0f172a]"><i data-lucide="clipboard-list" class="h-6 w-6"></i> Gestion des commandes</h1>
            <span class="rounded-full bg-[#10b981]/10 px-4 py-1.5 text-sm font-bold text-[#10b981]"><?= $total_commandes ?> commandes</span>
        </div>

        <?php if ($message): ?>
            <div class="mb-6 rounded-xl border border-[rgba(16,185,129,0.2)] bg-[#d1fae5] px-5 py-3 text-sm font-medium text-[#065f46]" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show=false, 4000)">
                <i data-lucide="check-circle" class="mr-1.5 inline h-4 w-4"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Stats cards -->
        <div class="mb-8 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
            <?php foreach ($statuts as $s): ?>
                <?php
                $count = $stats[$s] ?? 0;
                $colors = [
                    'en_attente' => ['bg' => 'bg-[#fef3c7]', 'text' => 'text-[#92400e]', 'icon' => 'clock'],
                    'confirmée' => ['bg' => 'bg-[#dbeafe]', 'text' => 'text-[#1e40af]', 'icon' => 'check-circle'],
                    'en_livraison' => ['bg' => 'bg-[#d1fae5]', 'text' => 'text-[#065f46]', 'icon' => 'truck'],
                    'livrée' => ['bg' => 'bg-[#bbf7d0]', 'text' => 'text-[#14532d]', 'icon' => 'package-check'],
                    'annulée' => ['bg' => 'bg-[#fee2e2]', 'text' => 'text-[#991b1b]', 'icon' => 'x-circle'],
                ];
                $c = $colors[$s];
                $active = $filtre_statut === $s ? 'ring-2 ring-[#10b981]' : '';
                ?>
                <a href="?statut=<?= $s ?>" class="<?= $c['bg'] ?> <?= $active ?> flex items-center gap-3 rounded-xl px-4 py-3 transition-all hover:shadow-md <?= $c['text'] ?>">
                    <i data-lucide="<?= $c['icon'] ?>" class="h-5 w-5 flex-shrink-0"></i>
                    <div>
                        <div class="text-xl font-extrabold"><?= $count ?></div>
                        <div class="text-xs font-medium opacity-80"><?= $statuts_labels[$s] ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Search + Reset -->
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <form method="GET" class="flex flex-1 items-center gap-2">
                <?php if ($filtre_statut): ?>
                    <input type="hidden" name="statut" value="<?= htmlspecialchars($filtre_statut) ?>">
                <?php endif; ?>
                <div class="relative flex-1">
                    <i data-lucide="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#94a3b8]"></i>
                    <input type="text" name="recherche" placeholder="Rechercher par ID, client ou téléphone..." value="<?= htmlspecialchars($recherche) ?>" class="w-full rounded-xl border border-[#e2e8f0] bg-white py-3 pl-10 pr-4 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                </div>
                <button type="submit" class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[#10b981] px-5 py-3 text-sm font-semibold text-white transition-all hover:opacity-85"><i data-lucide="search" class="h-4 w-4"></i> Chercher</button>
            </form>
            <?php if ($filtre_statut || $recherche): ?>
                <a href="commandes.php" class="inline-flex items-center gap-2 rounded-xl border border-[#e2e8f0] bg-white px-5 py-3 text-sm font-medium text-[#64748b] transition-all hover:border-[#fca5a5] hover:text-[#ef4444]"><i data-lucide="x" class="h-4 w-4"></i> Réinitialiser</a>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.4fr_1fr]">
            <div class="overflow-hidden rounded-xl border border-[#e2e8f0] bg-white pb-1 shadow-sm">
                <div class="flex items-center justify-between border-b border-[#e2e8f0] px-6 py-5">
                    <h3 class="flex items-center gap-2 text-base font-bold text-[#0f172a]">
                        <i data-lucide="list" class="h-4 w-4"></i>
                        <?php if ($filtre_statut): ?>
                            <?= $statuts_labels[$filtre_statut] ?> —
                        <?php endif; ?>
                        <?= count($commandes) ?> résultat(s)
                    </h3>
                    <!-- Quick filter tabs -->
                    <div class="flex gap-1 text-xs">
                        <a href="commandes.php" class="rounded-lg px-3 py-1.5 font-medium transition-all <?= !$filtre_statut ? 'bg-[#10b981] text-white' : 'bg-[#f1f5f9] text-[#64748b] hover:bg-[#e2e8f0]' ?>">Toutes</a>
                        <?php foreach ($statuts as $s): ?>
                            <a href="?statut=<?= $s ?>" class="rounded-lg px-3 py-1.5 font-medium transition-all <?= $filtre_statut === $s ? 'bg-[#10b981] text-white' : 'bg-[#f1f5f9] text-[#64748b] hover:bg-[#e2e8f0]' ?>"><?= $statuts_labels[$s] ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-[#e2e8f0] bg-[#f8fafc] text-xs font-semibold uppercase tracking-wider text-[#1e293b]">
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3">Client</th>
                                <th class="px-4 py-3">Téléphone</th>
                                <th class="px-4 py-3">Total</th>
                                <th class="px-4 py-3">Statut</th>
                                <th class="px-4 py-3">Paiement</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($commandes)): ?>
                                <tr><td colspan="8" class="px-4 py-12 text-center text-sm text-[#64748b]">Aucune commande trouvée.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($commandes as $c): ?>
                            <tr class="border-b border-[#e2e8f0] text-sm transition-colors hover:bg-[#10b981]/[0.02]">
                                <td class="px-4 py-3 font-bold text-[#10b981]">#<?= $c['id'] ?></td>
                                <td class="px-4 py-3 font-medium text-[#0f172a]"><?= htmlspecialchars($c['nom']) ?></td>
                                <td class="px-4 py-3 text-[#64748b]"><?= htmlspecialchars($c['client_tel'] ?? '-') ?></td>
                                <td class="px-4 py-3 font-semibold"><?= number_format($c['total'], 0, ',', ' ') ?> FCFA</td>
                                <td class="px-4 py-3"><span class="badge-<?= $c['statut'] ?> inline-block rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider"><?= $statuts_labels[$c['statut']] ?></span></td>
                                <td class="px-4 py-3 text-xs text-[#64748b]"><?= $c['moyen_paiement'] === 'mobile_money' ? 'Mobile Money' : 'Cash' ?></td>
                                <td class="px-4 py-3 text-xs text-[#64748b]"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" class="inline-flex items-center gap-1.5">
                                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                        <input type="hidden" name="commande_id" value="<?= $c['id'] ?>">
                                        <select name="statut" class="rounded-lg border border-[#e2e8f0] bg-white px-2 py-1.5 text-xs font-medium text-[#1e293b] outline-none transition-all focus:border-[#10b981]">
                                            <?php foreach ($statuts as $s): ?>
                                                <option value="<?= $s ?>" <?= $c['statut'] === $s ? 'selected' : '' ?>><?= $statuts_labels[$s] ?></option>
                                            <?php endforeach; ?>
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

            <?php if ($detail): ?>
            <div id="detail-commande" class="rounded-xl border border-[#e2e8f0] bg-white shadow-sm">
                <div class="border-b border-[#e2e8f0] px-6 py-5">
                    <div class="flex items-center justify-between">
                        <h3 class="flex items-center gap-2 text-base font-bold text-[#0f172a]"><i data-lucide="info" class="h-4 w-4"></i> Commande #<?= $detail['id'] ?></h3>
                        <a href="commandes.php<?= $filtre_statut ? '?statut='.$filtre_statut : '' ?>" class="inline-flex items-center gap-1 rounded-lg border border-[#e2e8f0] px-3 py-1.5 text-xs font-medium text-[#64748b] transition-all hover:bg-[#f1f5f9]"><i data-lucide="x" class="h-3 w-3"></i> Fermer</a>
                    </div>
                </div>
                <div class="p-6">
                    <!-- Client info -->
                    <div class="mb-5 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="block text-xs text-[#64748b]">Client</span>
                            <span class="font-medium text-[#0f172a]"><?= htmlspecialchars($detail['nom']) ?></span>
                        </div>
                        <div>
                            <span class="block text-xs text-[#64748b]">Téléphone</span>
                            <span class="font-medium text-[#0f172a]"><?= htmlspecialchars($detail['telephone'] ?? '-') ?></span>
                        </div>
                        <div class="col-span-2">
                            <span class="block text-xs text-[#64748b]">Email</span>
                            <span class="font-medium text-[#0f172a]"><?= htmlspecialchars($detail['email']) ?></span>
                        </div>
                        <div>
                            <span class="block text-xs text-[#64748b]">Date</span>
                            <span class="font-medium text-[#0f172a]"><?= date('d/m/Y H:i', strtotime($detail['created_at'])) ?></span>
                        </div>
                        <div>
                            <span class="block text-xs text-[#64748b]">Paiement</span>
                            <span class="inline-flex items-center gap-1 font-medium text-[#0f172a]">
                                <?php if ($detail['moyen_paiement'] === 'mobile_money'): ?>
                                    <i data-lucide="smartphone" class="h-3.5 w-3.5 text-[#10b981]"></i> Mobile Money
                                <?php else: ?>
                                    <i data-lucide="banknote" class="h-3.5 w-3.5 text-[#10b981]"></i> Cash (livraison)
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="col-span-2">
                            <span class="block text-xs text-[#64748b]">Adresse livraison</span>
                            <span class="font-medium text-[#0f172a]"><?= htmlspecialchars($detail['adresse_livraison']) ?></span>
                        </div>
                    </div>

                    <!-- Products -->
                    <div class="mb-4">
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-[#64748b]">Produits</h4>
                        <div class="rounded-lg border border-[#e2e8f0]">
                            <?php
                            $sous_total = 0;
                            foreach ($detail_produits as $i => $p):
                                $sous_total += $p['prix_unitaire'] * $p['quantite'];
                            ?>
                                <div class="flex items-center justify-between px-4 py-2.5 <?= $i < count($detail_produits) - 1 ? 'border-b border-[#e2e8f0]' : '' ?> text-sm">
                                    <span class="font-medium text-[#1e293b]"><?= htmlspecialchars($p['nom']) ?> <span class="text-[#64748b]">x<?= $p['quantite'] ?></span></span>
                                    <span class="font-semibold"><?= number_format($p['prix_unitaire'] * $p['quantite'], 0, ',', ' ') ?> FCFA</span>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($detail['remise'] > 0): ?>
                                <div class="flex items-center justify-between border-t border-[#e2e8f0] bg-[#f0fdf4] px-4 py-2 text-sm font-medium text-[#10b981]">
                                    <span><i data-lucide="tag" class="mr-1 inline h-3.5 w-3.5"></i> Remise</span>
                                    <span>-<?= number_format($detail['remise'], 0, ',', ' ') ?> FCFA</span>
                                </div>
                            <?php endif; ?>
                            <div class="flex items-center justify-between border-t-2 border-[#e2e8f0] bg-[#f8fafc] px-4 py-3 text-sm font-bold text-[#10b981]">
                                <span>Total</span>
                                <span><?= number_format($detail['total'], 0, ',', ' ') ?> FCFA</span>
                            </div>
                        </div>
                    </div>

                    <!-- Facture -->
                    <div class="mb-4">
                        <a href="facture.php?id=<?= $detail['id'] ?>" target="_blank" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-[#e2e8f0] bg-white px-5 py-2.5 text-sm font-medium text-[#0f172a] transition-all hover:border-[#10b981] hover:bg-[#10b981]/5 hover:text-[#10b981]"><i data-lucide="file-text" class="h-4 w-4"></i> Télécharger la facture PDF</a>
                    </div>

                    <!-- Status update -->
                    <div class="border-t border-[#e2e8f0] pt-5">
                        <h4 class="mb-3 text-xs font-semibold uppercase tracking-wider text-[#64748b]">Modifier le statut</h4>
                        <form method="POST" class="flex flex-col gap-3">
                            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                            <input type="hidden" name="commande_id" value="<?= $detail['id'] ?>">
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($statuts as $s): ?>
                                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-medium transition-all <?= $detail['statut'] === $s ? 'border-[#10b981] bg-[#10b981]/10 text-[#10b981]' : 'border-[#e2e8f0] text-[#64748b] hover:border-[#94a3b8]' ?>">
                                        <input type="radio" name="statut" value="<?= $s ?>" <?= $detail['statut'] === $s ? 'checked' : '' ?>>
                                        <i data-lucide="<?= $statuts_icones[$s] ?>" class="h-4 w-4"></i>
                                        <?= $statuts_labels[$s] ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="mt-2 inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl bg-[#10b981] px-5 py-2.5 text-sm font-semibold text-white transition-all hover:opacity-85"><i data-lucide="refresh-cw" class="h-4 w-4"></i> Appliquer le statut</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="flex min-h-[200px] flex-col items-center justify-center rounded-xl border border-[#e2e8f0] bg-white p-6 text-center text-[#64748b] shadow-sm">
                <i data-lucide="info" class="mb-3 h-10 w-10 text-[#cbd5e1]"></i>
                <p class="text-sm">Utilisez le menu déroulant dans le tableau pour changer le statut</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>lucide.createIcons()</script>
<?php if (isset($_GET['id'])): ?>
<script>setTimeout(() => document.getElementById('detail-commande')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100)</script>
<?php endif; ?>
</body>
</html>

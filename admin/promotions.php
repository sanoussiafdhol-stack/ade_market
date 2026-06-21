<?php
require_once '../config/database.php';
require_once '../config/session.php';

redirigerSiNonAdmin();

$message = "";
$erreur = "";

// Ajouter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verifierTokenCSRF($_POST['csrf_token'])) {
        $erreur = "Erreur de validation CSRF.";
    } else {
        if ($_POST['action'] === 'ajouter') {
            $code = strtoupper(trim($_POST['code']));
            $type = $_POST['type'];
            $valeur = (float)$_POST['valeur'];
            $min_total = (float)$_POST['min_total'];
            $max_utilisations = (int)$_POST['max_utilisations'];
            $expire_le = $_POST['expire_le'] ?: null;

            if (empty($code) || $valeur <= 0) {
                $erreur = "Code et valeur requis.";
            } elseif ($type === 'pourcentage' && $valeur > 100) {
                $erreur = "Le pourcentage ne peut pas dépasser 100%.";
            } else {
                $check = $pdo->prepare("SELECT id FROM promotions WHERE code = ?");
                $check->execute([$code]);
                if ($check->fetch()) {
                    $erreur = "Ce code existe déjà.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO promotions (code, type, valeur, min_total, max_utilisations, expire_le) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$code, $type, $valeur, $min_total, $max_utilisations, $expire_le]);
                    $message = "Code promo " . htmlspecialchars($code) . " ajouté.";
                }
            }
        } elseif ($_POST['action'] === 'supprimer') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Code promo supprimé.";
        } elseif ($_POST['action'] === 'basculer') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE promotions SET actif = NOT actif WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Statut mis à jour.";
        }
    }
}

$promotions = $pdo->query("SELECT * FROM promotions ORDER BY cree_le DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotions - ADE MARKET Admin</title>
    <meta name="description" content="Gérez les codes promo et promotions sur ADE MARKET.">
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
            <a href="promotions.php" class="active flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="tag" class="h-4 w-4"></i> Promotions</a>
            <a href="clients.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="users" class="h-4 w-4"></i> Clients</a>
            <a href="../client/index.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="globe" class="h-4 w-4"></i> Voir le site</a>
            <a href="../client/deconnexion.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="log-out" class="h-4 w-4"></i> Déconnexion</a>
        </nav>
    </aside>

    <main class="flex-1 overflow-y-auto bg-[#f8fafc] p-10">
        <h1 class="mb-8 flex items-center gap-3 text-2xl font-bold text-[#0f172a]"><i data-lucide="tag" class="h-6 w-6"></i> Codes promo</h1>

        <?php if ($message): ?>
            <div class="mb-6 rounded-xl border border-[rgba(16,185,129,0.2)] bg-[#d1fae5] px-5 py-3 text-sm font-medium text-[#065f46]"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($erreur): ?>
            <div class="mb-6 rounded-xl border border-[rgba(220,38,38,0.15)] bg-[#fee2e2] px-5 py-3 text-sm font-medium text-[#dc2626]"><?= $erreur ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_1.8fr]">
            <div class="rounded-xl border border-[#e2e8f0] bg-white p-8 shadow-sm">
                <h3 class="mb-6 border-b-2 border-[#f8fafc] pb-3 text-lg font-bold text-[#0f172a]">Nouveau code promo</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                    <input type="hidden" name="action" value="ajouter">
                    <div class="mb-4">
                        <label class="mb-2 block text-sm font-medium text-[#1e293b]">Code</label>
                        <input type="text" name="code" placeholder="Ex: PROMO10" required class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                    </div>
                    <div class="mb-4">
                        <label class="mb-2 block text-sm font-medium text-[#1e293b]">Type</label>
                        <select name="type" class="w-full rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                            <option value="pourcentage">Pourcentage (%)</option>
                            <option value="fixe">Montant fixe (FCFA)</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="mb-2 block text-sm font-medium text-[#1e293b]">Valeur</label>
                        <input type="number" name="valeur" step="1" placeholder="Ex: 10 (pour 10%)" required class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                    </div>
                    <div class="mb-4">
                        <label class="mb-2 block text-sm font-medium text-[#1e293b]">Panier minimum (FCFA)</label>
                        <input type="number" name="min_total" step="1" placeholder="0 = aucun minimum" class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                    </div>
                    <div class="mb-4">
                        <label class="mb-2 block text-sm font-medium text-[#1e293b]">Utilisations max</label>
                        <input type="number" name="max_utilisations" placeholder="0 = illimité" class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                    </div>
                    <div class="mb-4">
                        <label class="mb-2 block text-sm font-medium text-[#1e293b]">Expire le</label>
                        <input type="datetime-local" name="expire_le" class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                    </div>
                    <button type="submit" class="btn-gradient inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl px-5 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-xl"><i data-lucide="plus-circle" class="h-4 w-4"></i> Ajouter</button>
                </form>
            </div>

            <div class="overflow-hidden rounded-xl border border-[#e2e8f0] bg-white pb-1 shadow-sm">
                <h3 class="border-b border-[#e2e8f0] px-6 pb-4 pt-6 text-lg font-bold text-[#0f172a]">Tous les codes (<?= count($promotions) ?>)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-[#e2e8f0] bg-[#f8fafc] text-xs font-semibold uppercase tracking-wider text-[#1e293b]">
                                <th class="px-4 py-3">Code</th>
                                <th class="px-4 py-3">Réduction</th>
                                <th class="px-4 py-3">Min.</th>
                                <th class="px-4 py-3">Utilisations</th>
                                <th class="px-4 py-3">Expire</th>
                                <th class="px-4 py-3">Actif</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($promotions)): ?>
                                <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-[#64748b]">Aucun code promo.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($promotions as $p): ?>
                                <?php
                                $expiree = $p['expire_le'] && strtotime($p['expire_le']) < time();
                                $epuisee = $p['max_utilisations'] > 0 && $p['utilisations'] >= $p['max_utilisations'];
                                $active = $p['actif'] && !$expiree && !$epuisee;
                                ?>
                            <tr class="border-b border-[#e2e8f0] text-sm transition-colors hover:bg-[#10b981]/[0.02] <?= !$active ? 'opacity-50' : '' ?>">
                                <td class="px-4 py-3 font-bold text-[#0f172a]"><?= htmlspecialchars($p['code']) ?></td>
                                <td class="px-4 py-3 font-semibold"><?= $p['type'] === 'pourcentage' ? $p['valeur'] . '%' : number_format($p['valeur'], 0, ',', ' ') . ' FCFA' ?></td>
                                <td class="px-4 py-3 text-[#64748b]"><?= $p['min_total'] > 0 ? number_format($p['min_total'], 0, ',', ' ') . ' FCFA' : '-' ?></td>
                                <td class="px-4 py-3"><?= $p['utilisations'] ?><?= $p['max_utilisations'] > 0 ? '/' . $p['max_utilisations'] : '' ?></td>
                                <td class="px-4 py-3 text-xs text-[#64748b]"><?= $p['expire_le'] ? (strtotime($p['expire_le']) < time() ? '<span class="text-[#ef4444]">Expiré</span>' : date('d/m/Y', strtotime($p['expire_le']))) : '-' ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($active): ?>
                                        <span class="inline-block rounded-full bg-[#d1fae5] px-2.5 py-0.5 text-xs font-bold text-[#065f46]">Oui</span>
                                    <?php else: ?>
                                        <span class="inline-block rounded-full bg-[#fee2e2] px-2.5 py-0.5 text-xs font-bold text-[#991b1b]">Non</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex gap-1">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                            <input type="hidden" name="action" value="basculer">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="inline-flex cursor-pointer items-center gap-1 rounded-lg bg-[#f1f5f9] px-2.5 py-1.5 text-xs font-semibold text-[#64748b] transition-all hover:bg-[#dbeafe] hover:text-[#2563eb]" title="Activer/Désactiver"><i data-lucide="toggle-left" class="h-3.5 w-3.5"></i></button>
                                        </form>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                            <input type="hidden" name="action" value="supprimer">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="inline-flex cursor-pointer items-center gap-1 rounded-lg bg-[#fee2e2] px-2.5 py-1.5 text-xs font-semibold text-[#ef4444] transition-all hover:bg-[#ef4444] hover:text-white" onclick="return confirm('Supprimer ce code promo ?')"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script>lucide.createIcons()</script>
</body>
</html>

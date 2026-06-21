<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/validation.php';
require_once '../config/logger.php';
require_once '../config/paiement.php';

redirigerSiNonConnecte();

$panier = $_SESSION['panier'] ?? [];
if (empty($panier)) {
    header("Location: panier.php");
    exit();
}

$total = 0;
foreach ($panier as $item) {
    $total += $item['prix'] * $item['quantite'];
}

$promo = null;
$remise = 0;
$code_applique = $_SESSION['code_promo'] ?? '';

if ($code_applique) {
    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE code = ? AND actif = 1 AND (expire_le IS NULL OR expire_le > NOW()) AND (max_utilisations = 0 OR utilisations < max_utilisations)");
    $stmt->execute([$code_applique]);
    $promo = $stmt->fetch();
    if ($promo && $total >= $promo['min_total']) {
        $remise = $promo['type'] === 'pourcentage' ? $total * $promo['valeur'] / 100 : min($promo['valeur'], $total);
    } else {
        $promo = null;
        $remise = 0;
        unset($_SESSION['code_promo']);
    }
}

$total_apres_remise = $total - $remise;

$erreur = "";
$stripe_url = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifierTokenCSRF($_POST['csrf_token'])) {
        $erreur = "Session invalide, veuillez réessayer.";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'appliquer_code') {
        $code = strtoupper(trim($_POST['code_promo'] ?? ''));
        $stmt = $pdo->prepare("SELECT * FROM promotions WHERE code = ? AND actif = 1 AND (expire_le IS NULL OR expire_le > NOW()) AND (max_utilisations = 0 OR utilisations < max_utilisations)");
        $stmt->execute([$code]);
        $promo_trouve = $stmt->fetch();
        if ($promo_trouve && $total >= $promo_trouve['min_total']) {
            $_SESSION['code_promo'] = $code;
            $remise = $promo_trouve['type'] === 'pourcentage' ? $total * $promo_trouve['valeur'] / 100 : min($promo_trouve['valeur'], $total);
            $promo = $promo_trouve;
            $total_apres_remise = $total - $remise;
        } elseif ($promo_trouve) {
            $erreur = "Panier minimum de " . number_format($promo_trouve['min_total'], 0, ',', ' ') . " FCFA non atteint.";
        } else {
            $erreur = "Code promo invalide ou expiré.";
        }
    } else {
        // Traitement de la finalisation de commande
        Validateur::reset();
        $adresse = Validateur::chaine($_POST['adresse'] ?? '', $min = 5, $max = 255, 'adresse');
        $telephone = Validateur::chaine($_POST['telephone'] ?? '', $min = 8, $max = 20, 'telephone');

        if (!$adresse) {
            $erreur = "Adresse invalide (5-255 caractères).";
        } elseif (!$telephone) {
            $erreur = "Téléphone invalide (8-20 caractères).";
        } else {
            try {
                $pdo->beginTransaction();

                // Crée la commande d'abord
                $promo_code_id = $promo ? $promo['id'] : null;
                $stmt = $pdo->prepare("
                    INSERT INTO commandes 
                    (utilisateur_id, total, adresse_livraison, moyen_paiement, telephone, promo_code_id, remise, statut) 
                    VALUES (?, ?, ?, 'stripe', ?, ?, ?, 'en_attente_paiement')
                ");
                $stmt->execute([$_SESSION['utilisateur_id'], $total_apres_remise, $adresse, $telephone, $promo_code_id, $remise]);
                $commande_id = $pdo->lastInsertId();

                // Increment promo usage
                if ($promo) {
                    $stmt = $pdo->prepare("UPDATE promotions SET utilisations = utilisations + 1 WHERE id = ?");
                    $stmt->execute([$promo['id']]);
                }

                // Ajoute les produits à la commande
                foreach ($panier as $produit_id => $item) {
                    $stmt = $pdo->prepare("INSERT INTO commande_produits (commande_id, produit_id, quantite, prix_unitaire) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$commande_id, $produit_id, $item['quantite'], $item['prix']]);

                    // Réserve le stock
                    $stmt = $pdo->prepare("UPDATE produits SET stock = stock - ? WHERE id = ? AND stock >= ?");
                    $stmt->execute([$item['quantite'], $produit_id, $item['quantite']]);
                    if ($stmt->rowCount() === 0) {
                        throw new Exception("Stock insuffisant pour le produit");
                    }
                }

                // Crée la livraison
                $stmt = $pdo->prepare("INSERT INTO livraisons (commande_id) VALUES (?)");
                $stmt->execute([$commande_id]);

                $pdo->commit();

                // Initialise Stripe et crée une session de paiement
                try {
                    Paiement::init($pdo);
                    
                    $stmt = $pdo->prepare("SELECT email FROM utilisateurs WHERE id = ?");
                    $stmt->execute([$_SESSION['utilisateur_id']]);
                    $user_email = $stmt->fetchColumn();
                    
                    $session = Paiement::creerSessionPaiement($commande_id, $user_email, $total_apres_remise * 100, $panier);
                    $stripe_url = $session->url;
                    
                    // Redirige vers Stripe
                    header("Location: " . $stripe_url);
                    exit();
                } catch (Exception $e) {
                    Logger::erreur('Erreur création session Stripe', ['message' => $e->getMessage()]);
                    $erreur = "Erreur lors de la création de la session de paiement.";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                Logger::erreur('Erreur création commande', ['message' => $e->getMessage()]);
                $erreur = $e->getMessage() ?: "Erreur lors de la création de la commande.";
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$_SESSION['utilisateur_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finaliser ma commande - ADE MARKET</title>
    <meta name="description" content="Finalisez votre commande ADE MARKET avec livraison rapide à Porto-Novo, Bénin. Paiement sécurisé.">
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
        <a href="accueil.php" class="inline-flex items-center gap-1.5 rounded-full border border-white/20 px-3.5 py-2 text-sm font-medium text-white/80 transition-all hover:bg-white hover:text-[#0f172a]"><i data-lucide="home" class="h-3.5 w-3.5"></i></a>
        <a href="compte.php" class="inline-flex items-center gap-1.5 rounded-full border border-white/20 px-3.5 py-2 text-sm font-medium text-white/80 transition-all hover:bg-white hover:text-[#0f172a]"><i data-lucide="user" class="h-3.5 w-3.5"></i></a>
        <a href="mes_commandes.php" class="inline-flex items-center gap-1.5 rounded-full border border-white/20 px-3.5 py-2 text-sm font-medium text-white/80 transition-all hover:bg-white hover:text-[#0f172a]"><i data-lucide="clipboard-list" class="h-3.5 w-3.5"></i></a>
        <a href="panier.php" class="inline-flex items-center gap-2 rounded-full border border-white/30 px-5 py-2.5 text-sm font-medium text-white transition-all hover:bg-white hover:text-[#0f172a]"><i data-lucide="arrow-left" class="h-4 w-4"></i> Panier</a>
    </div>
</header>

<div class="mx-auto my-12 grid max-w-4xl gap-8 px-6 max-md:grid-cols-1 md:grid-cols-2">
    <h2 class="col-span-full mb-2 text-2xl font-bold text-[#0f172a]">Finaliser ma commande</h2>

    <?php if ($erreur): ?>
        <div class="col-span-full rounded-xl border border-[rgba(220,38,38,0.15)] bg-[#fee2e2] px-5 py-3 text-sm font-medium text-[#dc2626]"><?= $erreur ?></div>
    <?php endif; ?>

    <div class="rounded-xl border border-[#e2e8f0] bg-white p-8 shadow-sm">
        <h3 class="mb-4 flex items-center gap-2 border-b-2 border-[#f8fafc] pb-3 text-lg font-bold text-[#0f172a]"><i data-lucide="package" class="h-5 w-5"></i> Récapitulatif</h3>
        <?php foreach ($panier as $item): ?>
            <div class="flex items-center justify-between border-b border-[#e2e8f0] py-3 text-sm">
                <span class="font-medium text-[#1e293b]"><?= htmlspecialchars($item['nom']) ?> <span class="text-[#64748b]">x<?= $item['quantite'] ?></span></span>
                <span class="font-bold text-[#0f172a]"><?= number_format($item['prix'] * $item['quantite'], 0, ',', ' ') ?> FCFA</span>
            </div>
        <?php endforeach; ?>
        <?php if ($remise > 0): ?>
            <div class="flex items-center justify-between border-b border-[#e2e8f0] py-3 text-sm">
                <span class="font-medium text-[#10b981]"><i data-lucide="tag" class="mr-1 inline h-4 w-4"></i> Code promo <?= htmlspecialchars($code_applique) ?></span>
                <span class="font-bold text-[#10b981]">-<?= number_format($remise, 0, ',', ' ') ?> FCFA</span>
            </div>
        <?php endif; ?>
        <div class="flex items-center justify-between py-4 text-xl font-extrabold text-[#10b981]">
            <span>Total</span>
            <span><?= number_format($total_apres_remise, 0, ',', ' ') ?> FCFA</span>
        </div>
        <div class="mt-4 rounded-lg border-l-4 border-[#f59e0b] bg-[#fffbeb] px-5 py-3 text-sm font-medium leading-relaxed text-[#b45309]">
            <i data-lucide="truck" class="mr-1 inline h-4 w-4"></i> <strong>Livraison à domicile (Porto-Novo)</strong><br>
            Délai estimé : 1 à 3 heures après validation.
        </div>

        <!-- Code promo -->
        <form method="POST" class="mt-5 border-t border-[#e2e8f0] pt-5">
            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
            <input type="hidden" name="action" value="appliquer_code">
            <label class="mb-2 block text-sm font-medium text-[#1e293b]"><i data-lucide="tag" class="mr-1 inline h-4 w-4"></i> Code promo</label>
            <div class="flex gap-2">
                <input type="text" name="code_promo" placeholder="Ex: WELCOME10" value="<?= htmlspecialchars($code_applique) ?>" class="flex-1 rounded-xl border border-[#e2e8f0] px-4 py-2.5 text-sm text-[#1e293b] uppercase outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                <button type="submit" class="inline-flex cursor-pointer items-center gap-1 rounded-xl bg-[#10b981] px-5 py-2.5 text-sm font-semibold text-white transition-all hover:opacity-85"><i data-lucide="check" class="h-4 w-4"></i> Appliquer</button>
            </div>
        </form>
    </div>

    <div class="rounded-xl border border-[#e2e8f0] bg-white p-8 shadow-sm">
        <h3 class="mb-4 flex items-center gap-2 border-b-2 border-[#f8fafc] pb-3 text-lg font-bold text-[#0f172a]"><i data-lucide="truck" class="h-5 w-5"></i> Livraison & Paiement</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
            <div class="mb-5">
                <label class="mb-2 block text-sm font-medium text-[#1e293b]">Adresse complète de livraison</label>
                <textarea name="adresse" placeholder="Ex: Quartier Plateau, Face BOA, Porto-Novo" required class="min-h-[90px] w-full rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15"><?= htmlspecialchars($user['adresse'] ?? '') ?></textarea>
            </div>
            <div class="mb-5">
                <label class="mb-2 block text-sm font-medium text-[#1e293b]">Téléphone de contact</label>
                <input type="text" name="telephone" placeholder="Ex: +229 97000000" value="<?= htmlspecialchars($user['telephone'] ?? '') ?>" required class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
            </div>
            <div class="mb-5">
                <label class="mb-2 block text-sm font-medium text-[#1e293b]">Moyen de paiement</label>
                <select name="paiement" class="w-full rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                    <option value="mobile_money"><i data-lucide="smartphone" class="mr-1 inline h-4 w-4"></i> Mobile Money (MTN Moov)</option>
                    <option value="cash"><i data-lucide="banknote" class="mr-1 inline h-4 w-4"></i> Paiement à la livraison</option>
                </select>
            </div>
            <button type="submit" class="btn-gradient mt-4 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl px-5 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-xl"><i data-lucide="check-circle" class="h-5 w-5"></i> Confirmer la commande</button>
        </form>
    </div>
</div>

<script>lucide.createIcons()</script>
</body>
</html>

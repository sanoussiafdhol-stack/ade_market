<?php
require_once '../config/database.php';
require_once '../config/session.php';

$erreur_panier = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifierTokenCSRF($_POST['csrf_token'])) {
        $erreur_panier = "Erreur de validation CSRF.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'ajouter') {
            $produit_id = (int)$_POST['produit_id'];
            $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ? AND stock > 0");
            $stmt->execute([$produit_id]);
            $produit = $stmt->fetch();
            if ($produit) {
                if (!isset($_SESSION['panier'][$produit_id])) {
                    $_SESSION['panier'][$produit_id] = ['nom' => $produit['nom'], 'prix' => $produit['prix'], 'quantite' => 1];
                } else {
                    $_SESSION['panier'][$produit_id]['quantite']++;
                }
            }
            header("Location: panier.php"); exit();
        }

        if ($action === 'supprimer') {
            unset($_SESSION['panier'][(int)$_POST['produit_id']]);
            header("Location: panier.php"); exit();
        }

        if ($action === 'vider') {
            unset($_SESSION['panier']);
            header("Location: panier.php"); exit();
        }

        if ($action === 'modifier_quantite') {
            $produit_id = (int)$_POST['produit_id'];
            $quantite = max(0, (int)$_POST['quantite']);
            if ($quantite > 0 && isset($_SESSION['panier'][$produit_id])) {
                $stmt = $pdo->prepare("SELECT stock FROM produits WHERE id = ?");
                $stmt->execute([$produit_id]);
                $stock = $stmt->fetchColumn();
                $_SESSION['panier'][$produit_id]['quantite'] = min($quantite, $stock);
            } elseif ($quantite === 0) {
                unset($_SESSION['panier'][$produit_id]);
            }
            header("Location: panier.php"); exit();
        }
    }
}

$panier = $_SESSION['panier'] ?? [];
$total = 0;
foreach ($panier as $item) $total += $item['prix'] * $item['quantite'];
$panier_count = array_sum(array_column($panier, 'quantite'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Panier - ADE MARKET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green: #00c853;
            --green-dark: #00953d;
            --green-light: #e8fff2;
            --dark: #0a0f1e;
            --gray: #6b7280;
            --light: #f9fafb;
            --white: #ffffff;
            --border: #e5e7eb;
            --red: #ef4444;
            --red-light: #fee2e2;
        }

        body { font-family: 'DM Sans', sans-serif; background: var(--light); color: var(--dark); min-height: 100vh; }
        h1,h2,h3,h4 { font-family: 'Syne', sans-serif; }

        /* HEADER */
        header {
            position: sticky; top: 0; z-index: 100;
            background: rgba(10,15,30,0.97);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding: 0 2rem;
        }

        .header-inner {
            max-width: 1200px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            height: 70px;
        }

        .logo-text {
            font-family: 'Syne', sans-serif; font-size: 1.4rem; font-weight: 800;
            background: linear-gradient(135deg, var(--green), #69f0ae);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text; text-decoration: none;
        }

        .logo-sub { font-size: 0.68rem; color: #4b5563; margin-top: -3px; }

        .nav-links { display: flex; align-items: center; gap: 0.6rem; }

        .nav-btn {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.5rem 1rem; border-radius: 100px;
            font-size: 0.83rem; font-weight: 500;
            text-decoration: none; transition: all 0.2s;
            border: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.75);
        }

        .nav-btn:hover { background: rgba(255,255,255,0.1); color: white; }

        .nav-btn.primary {
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            border-color: transparent; color: white; font-weight: 600;
        }

        .burger { display: none; background: none; border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 0.4rem; color: white; cursor: pointer; }

        .mobile-nav { display: none; background: #0d1526; border-top: 1px solid rgba(255,255,255,0.05); padding: 1rem 2rem; flex-direction: column; gap: 0.4rem; }
        .mobile-nav a { color: rgba(255,255,255,0.75); text-decoration: none; padding: 0.7rem; border-radius: 8px; font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem; }
        .mobile-nav.open { display: flex; }

        /* PAGE */
        .page { max-width: 900px; margin: 0 auto; padding: 2.5rem 1.5rem; }

        .page-title {
            font-size: 1.75rem; font-weight: 800; color: var(--dark);
            margin-bottom: 2rem;
            display: flex; align-items: center; gap: 0.75rem;
        }

        .page-title-icon {
            width: 44px; height: 44px;
            background: var(--green-light);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: var(--green);
        }

        /* ERREUR */
        .erreur {
            background: var(--red-light); border: 1px solid rgba(220,38,38,0.15);
            color: var(--red); padding: 0.75rem 1rem; border-radius: 10px;
            font-size: 0.875rem; font-weight: 500; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 0.5rem;
        }

        /* EMPTY */
        .empty {
            background: white; border: 1px solid var(--border);
            border-radius: 20px; padding: 5rem 2rem;
            text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        }

        .empty-icon { color: #e5e7eb; margin: 0 auto 1.5rem; }
        .empty-text { font-size: 1.1rem; color: var(--gray); margin-bottom: 2rem; }

        .empty-btn {
            display: inline-flex; align-items: center; gap: 0.6rem;
            padding: 0.85rem 2rem; border-radius: 100px;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: white; text-decoration: none; font-weight: 600;
            font-size: 0.95rem; transition: all 0.2s;
            box-shadow: 0 6px 20px rgba(0,200,83,0.25);
        }

        .empty-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,200,83,0.3); }

        /* PANIER TABLE */
        .panier-card {
            background: white; border: 1px solid var(--border);
            border-radius: 20px; overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            margin-bottom: 1.25rem;
        }

        .panier-header {
            display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 1rem; padding: 0.875rem 1.5rem;
            background: var(--light); border-bottom: 1px solid var(--border);
            font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.1em; color: var(--gray);
        }

        .panier-row {
            display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 1rem; padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            align-items: center; transition: background 0.15s;
        }

        .panier-row:last-child { border-bottom: none; }
        .panier-row:hover { background: #fafafa; }

        .item-name { font-weight: 600; font-size: 0.95rem; color: var(--dark); }
        .item-price { font-size: 0.9rem; color: var(--gray); }
        .item-subtotal { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 800; color: var(--dark); }

        /* QTE CONTROL */
        .qte-control { display: flex; align-items: center; gap: 0.5rem; }

        .qte-btn {
            width: 30px; height: 30px; border-radius: 8px;
            border: 1.5px solid var(--border); background: white;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.2s; color: var(--dark);
        }

        .qte-btn:hover { border-color: var(--green); color: var(--green); background: var(--green-light); }

        .qte-val { min-width: 28px; text-align: center; font-weight: 700; font-size: 0.95rem; }

        .del-btn {
            width: 34px; height: 34px; border-radius: 8px;
            border: none; background: var(--red-light);
            color: var(--red); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }

        .del-btn:hover { background: var(--red); color: white; }

        /* FOOTER PANIER */
        .panier-footer {
            background: white; border: 1px solid var(--border);
            border-radius: 20px; padding: 1.75rem 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 1.5rem;
        }

        .vider-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.7rem 1.25rem; border-radius: 10px;
            border: 1.5px solid var(--border); background: white;
            color: var(--gray); font-size: 0.875rem; font-weight: 600;
            cursor: pointer; transition: all 0.2s;
            font-family: 'DM Sans', sans-serif;
        }

        .vider-btn:hover { border-color: var(--red); color: var(--red); background: var(--red-light); }

        .total-section { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }

        .total-label { font-size: 1rem; color: var(--gray); font-weight: 500; }
        .total-amount { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; color: var(--green); }

        .commander-btn {
            display: inline-flex; align-items: center; gap: 0.6rem;
            padding: 0.85rem 2rem; border-radius: 100px;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: white; text-decoration: none; font-weight: 700;
            font-size: 0.95rem; transition: all 0.2s;
            box-shadow: 0 6px 20px rgba(0,200,83,0.25);
            font-family: 'DM Sans', sans-serif;
        }

        .commander-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,200,83,0.35); }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .burger { display: flex; align-items: center; }
            .panier-header { display: none; }
            .panier-row {
                grid-template-columns: 1fr auto;
                grid-template-rows: auto auto auto;
                gap: 0.75rem;
            }
            .item-name { grid-column: 1; }
            .item-price { grid-column: 1; font-size: 0.85rem; }
            .qte-control { grid-column: 1; }
            .item-subtotal { grid-column: 1; }
            .del-btn { grid-column: 2; grid-row: 1; align-self: start; }
            .panier-footer { flex-direction: column; align-items: stretch; text-align: center; }
            .total-section { justify-content: center; flex-direction: column; gap: 0.75rem; }
            .commander-btn { justify-content: center; }
            .vider-btn { justify-content: center; }
        }
    </style>
</head>
<body x-data="{ menuOpen: false }">

<!-- HEADER -->
<header>
    <div class="header-inner">
        <div>
            <a href="accueil.php" class="logo-text">ADE MARKET</a>
            <div class="logo-sub">Porto-Novo, Bénin</div>
        </div>
        <nav class="nav-links">
            <a href="accueil.php" class="nav-btn"><i data-lucide="home" style="width:14px;height:14px"></i> Accueil</a>
            <a href="index.php" class="nav-btn"><i data-lucide="grid" style="width:14px;height:14px"></i> Catalogue</a>
            <?php if (estConnecte()): ?>
                <a href="compte.php" class="nav-btn"><i data-lucide="user" style="width:14px;height:14px"></i></a>
                <a href="mes_commandes.php" class="nav-btn"><i data-lucide="clipboard-list" style="width:14px;height:14px"></i></a>
                <a href="deconnexion.php" class="nav-btn"><i data-lucide="log-out" style="width:14px;height:14px"></i></a>
            <?php else: ?>
                <a href="connexion.php" class="nav-btn">Connexion</a>
            <?php endif; ?>
        </nav>
        <button class="burger" @click="menuOpen = !menuOpen">
            <i data-lucide="menu" style="width:20px;height:20px"></i>
        </button>
    </div>
    <div class="mobile-nav" :class="menuOpen ? 'open' : ''">
        <a href="accueil.php"><i data-lucide="home" style="width:16px;height:16px"></i> Accueil</a>
        <a href="index.php"><i data-lucide="grid" style="width:16px;height:16px"></i> Catalogue</a>
        <?php if (estConnecte()): ?>
            <a href="compte.php"><i data-lucide="user" style="width:16px;height:16px"></i> Mon compte</a>
            <a href="mes_commandes.php"><i data-lucide="clipboard-list" style="width:16px;height:16px"></i> Mes commandes</a>
            <a href="deconnexion.php"><i data-lucide="log-out" style="width:16px;height:16px"></i> Déconnexion</a>
        <?php else: ?>
            <a href="connexion.php"><i data-lucide="log-in" style="width:16px;height:16px"></i> Connexion</a>
        <?php endif; ?>
    </div>
</header>

<!-- PAGE -->
<div class="page">
    <h2 class="page-title">
        <div class="page-title-icon"><i data-lucide="shopping-cart" style="width:20px;height:20px"></i></div>
        Mon panier
        <?php if (!empty($panier)): ?>
            <span style="font-size:0.9rem;font-weight:500;color:var(--gray)">(<?= $panier_count ?> article<?= $panier_count > 1 ? 's' : '' ?>)</span>
        <?php endif; ?>
    </h2>

    <?php if ($erreur_panier): ?>
        <div class="erreur"><i data-lucide="alert-circle" style="width:16px;height:16px"></i> <?= $erreur_panier ?></div>
    <?php endif; ?>

    <?php if (empty($panier)): ?>
        <div class="empty">
            <i data-lucide="shopping-cart" class="empty-icon" style="width:72px;height:72px"></i>
            <p class="empty-text">Votre panier est vide.</p>
            <a href="index.php" class="empty-btn"><i data-lucide="shopping-bag" style="width:18px;height:18px"></i> Continuer mes achats</a>
        </div>

    <?php else: ?>
        <div class="panier-card">
            <div class="panier-header">
                <div>Produit</div>
                <div>Prix unitaire</div>
                <div>Quantité</div>
                <div>Sous-total</div>
                <div></div>
            </div>

            <?php foreach ($panier as $id => $item): ?>
            <div class="panier-row">
                <div class="item-name"><?= htmlspecialchars($item['nom']) ?></div>
                <div class="item-price"><?= number_format($item['prix'], 0, ',', ' ') ?> FCFA</div>
                <div class="qte-control">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                        <input type="hidden" name="action" value="modifier_quantite">
                        <input type="hidden" name="produit_id" value="<?= $id ?>">
                        <button type="submit" name="quantite" value="<?= $item['quantite'] - 1 ?>" class="qte-btn" <?= $item['quantite'] <= 1 ? 'onclick="return confirm(\'Supprimer cet article ?\')"' : '' ?>>
                            <i data-lucide="minus" style="width:13px;height:13px"></i>
                        </button>
                    </form>
                    <span class="qte-val"><?= $item['quantite'] ?></span>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                        <input type="hidden" name="action" value="modifier_quantite">
                        <input type="hidden" name="produit_id" value="<?= $id ?>">
                        <button type="submit" name="quantite" value="<?= $item['quantite'] + 1 ?>" class="qte-btn">
                            <i data-lucide="plus" style="width:13px;height:13px"></i>
                        </button>
                    </form>
                </div>
                <div class="item-subtotal"><?= number_format($item['prix'] * $item['quantite'], 0, ',', ' ') ?> FCFA</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="produit_id" value="<?= $id ?>">
                    <button type="submit" class="del-btn" title="Supprimer">
                        <i data-lucide="trash-2" style="width:15px;height:15px"></i>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="panier-footer">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                <input type="hidden" name="action" value="vider">
                <button type="submit" class="vider-btn" onclick="return confirm('Vider tout le panier ?')">
                    <i data-lucide="trash-2" style="width:16px;height:16px"></i> Vider le panier
                </button>
            </form>

            <div class="total-section">
                <div>
                    <div class="total-label">Total à payer</div>
                    <div class="total-amount"><?= number_format($total, 0, ',', ' ') ?> FCFA</div>
                </div>
                <a href="commande.php" class="commander-btn">
                    Commander <i data-lucide="arrow-right" style="width:18px;height:18px"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>lucide.createIcons()</script>
</body>
</html>
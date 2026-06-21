<?php
require_once '../config/database.php';
require_once '../config/session.php';

$produits_par_page = 12;
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$offset = ($page - 1) * $produits_par_page;

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

$categorie_id = isset($_GET['categorie']) ? (int)$_GET['categorie'] : 0;
$recherche = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';
$tri = isset($_GET['tri']) ? $_GET['tri'] : 'nouveaute';
$prix_min = isset($_GET['prix_min']) ? (float)$_GET['prix_min'] : 0;
$prix_max = isset($_GET['prix_max']) ? (float)$_GET['prix_max'] : 0;

$where = "WHERE p.stock > 0";
$params = [];
$count_params = [];

if ($categorie_id > 0) {
    $where .= " AND p.categorie_id = ?";
    $params[] = $categorie_id;
    $count_params[] = $categorie_id;
}
if ($recherche !== '') {
    $where .= " AND p.nom LIKE ?";
    $params[] = "%$recherche%";
    $count_params[] = "%$recherche%";
}
if ($prix_min > 0) {
    $where .= " AND p.prix >= ?";
    $params[] = $prix_min;
    $count_params[] = $prix_min;
}
if ($prix_max > 0) {
    $where .= " AND p.prix <= ?";
    $params[] = $prix_max;
    $count_params[] = $prix_max;
}

$order = match ($tri) {
    'prix_croissant' => 'p.prix ASC',
    'prix_decroissant' => 'p.prix DESC',
    'nom_a_z' => 'p.nom ASC',
    'nom_z_a' => 'p.nom DESC',
    default => 'p.id DESC',
};

$total = $pdo->prepare("SELECT COUNT(*) FROM produits p $where");
$total->execute($count_params);
$total_produits = $total->fetchColumn();
$total_pages = max(1, ceil($total_produits / $produits_par_page));
if ($page > $total_pages) $page = $total_pages;

$sql = "SELECT p.*, c.nom as categorie_nom FROM produits p LEFT JOIN categories c ON p.categorie_id = c.id $where ORDER BY $order LIMIT $produits_par_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produits = $stmt->fetchAll();

$panier_count = 0;
if (isset($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $item) {
        $panier_count += $item['quantite'];
    }
}

function buildQuery($overrides) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === '' || $v === null) unset($params[$k]);
        else $params[$k] = $v;
    }
    unset($params['page']);
    return http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue - ADE MARKET</title>
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
        }

        body { font-family: 'DM Sans', sans-serif; background: var(--light); color: var(--dark); }
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
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
            text-decoration: none;
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

        .nav-btn.primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,200,83,0.3); }

        .burger { display: none; background: none; border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 0.4rem; color: white; cursor: pointer; }

        .mobile-nav { display: none; background: #0d1526; border-top: 1px solid rgba(255,255,255,0.05); padding: 1rem 2rem; flex-direction: column; gap: 0.4rem; }
        .mobile-nav a { color: rgba(255,255,255,0.75); text-decoration: none; padding: 0.7rem; border-radius: 8px; font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem; }
        .mobile-nav a:hover { background: rgba(255,255,255,0.05); }
        .mobile-nav.open { display: flex; }

        /* SEARCH BAR */
        .search-bar {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
        }

        .search-inner {
            max-width: 1200px; margin: 0 auto;
            display: flex; gap: 0.75rem;
        }

        .search-input {
            flex: 1; padding: 0.75rem 1.25rem;
            border: 1.5px solid var(--border);
            border-radius: 100px; font-size: 0.9rem;
            outline: none; transition: all 0.2s;
            font-family: 'DM Sans', sans-serif;
        }

        .search-input:focus { border-color: var(--green); box-shadow: 0 0 0 3px rgba(0,200,83,0.1); }

        .search-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: white; border: none; border-radius: 100px;
            font-size: 0.9rem; font-weight: 600; cursor: pointer;
            font-family: 'DM Sans', sans-serif; transition: all 0.2s;
        }

        .search-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,200,83,0.3); }

        /* PAGE LAYOUT */
        .page-inner {
            max-width: 1200px; margin: 0 auto;
            padding: 2rem;
            display: flex; gap: 2rem;
        }

        /* SIDEBAR */
        .sidebar { width: 220px; flex-shrink: 0; }

        .sidebar-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            position: sticky;
            top: 90px;
        }

        .sidebar-title {
            font-size: 0.8rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.1em;
            color: var(--gray); margin-bottom: 1rem;
        }

        .cat-link {
            display: block; padding: 0.6rem 0.9rem;
            border-radius: 10px; text-decoration: none;
            font-size: 0.875rem; font-weight: 500;
            color: var(--gray); transition: all 0.2s;
            margin-bottom: 0.2rem;
        }

        .cat-link:hover { background: var(--green-light); color: var(--green-dark); }
        .cat-link.active { background: var(--green); color: white; font-weight: 600; }

        .divider { border: none; border-top: 1px solid var(--border); margin: 1.25rem 0; }

        .price-inputs { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; }

        .price-input {
            width: 100%; padding: 0.5rem 0.75rem;
            border: 1.5px solid var(--border); border-radius: 8px;
            font-size: 0.8rem; outline: none;
            font-family: 'DM Sans', sans-serif; transition: all 0.2s;
        }

        .price-input:focus { border-color: var(--green); }
        .price-sep { font-size: 0.75rem; color: var(--gray); flex-shrink: 0; }

        .apply-btn {
            width: 100%; padding: 0.6rem;
            background: var(--light); border: 1px solid var(--border);
            border-radius: 8px; font-size: 0.8rem; font-weight: 600;
            color: var(--gray); cursor: pointer; transition: all 0.2s;
            font-family: 'DM Sans', sans-serif;
        }

        .apply-btn:hover { background: var(--green-light); color: var(--green-dark); border-color: var(--green); }

        .reset-link {
            display: flex; align-items: center; justify-content: center; gap: 0.4rem;
            padding: 0.6rem; border-radius: 8px;
            border: 1px solid var(--border); color: var(--gray);
            font-size: 0.8rem; font-weight: 600; text-decoration: none;
            transition: all 0.2s; margin-top: 0.75rem;
        }

        .reset-link:hover { border-color: #fca5a5; color: #ef4444; }

        /* MAIN CONTENT */
        .main-content { flex: 1; min-width: 0; }

        .content-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;
        }

        .content-title { font-size: 1.5rem; font-weight: 800; color: var(--dark); }
        .content-count { font-size: 0.9rem; font-weight: 400; color: var(--gray); }

        .sort-form { display: flex; align-items: center; gap: 0.5rem; }
        .sort-label { font-size: 0.8rem; color: var(--gray); font-weight: 500; }

        .sort-select {
            padding: 0.5rem 0.75rem;
            border: 1.5px solid var(--border); border-radius: 8px;
            font-size: 0.83rem; font-weight: 500; color: var(--dark);
            outline: none; cursor: pointer; background: white;
            font-family: 'DM Sans', sans-serif; transition: all 0.2s;
        }

        .sort-select:focus { border-color: var(--green); }

        /* PRODUCT GRID */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .product-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px; overflow: hidden;
            display: flex; flex-direction: column;
            transition: all 0.25s;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.08);
            border-color: #d1fae5;
        }

        .product-img { width: 100%; height: 170px; object-fit: cover; }

        .product-img-placeholder {
            height: 170px; background: var(--light);
            display: flex; align-items: center; justify-content: center; color: #d1d5db;
        }

        .product-body { padding: 1rem; flex: 1; display: flex; flex-direction: column; }

        .product-cat {
            font-size: 0.68rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.1em;
            color: var(--green); margin-bottom: 0.35rem;
        }

        .product-name {
            font-size: 0.9rem; font-weight: 600; color: var(--dark);
            margin-bottom: 0.5rem; line-height: 1.4;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }

        .product-price {
            font-family: 'Syne', sans-serif;
            font-size: 1.1rem; font-weight: 800; color: var(--dark);
            margin-bottom: 0.4rem;
        }

        .product-stock {
            display: flex; align-items: center; gap: 0.3rem;
            font-size: 0.72rem; color: #059669; font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .add-btn {
            width: 100%; padding: 0.65rem;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: white; border: none; border-radius: 10px;
            font-size: 0.83rem; font-weight: 600; cursor: pointer;
            transition: all 0.2s; margin-top: auto;
            font-family: 'DM Sans', sans-serif;
        }

        .add-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,200,83,0.3); }

        /* EMPTY */
        .empty {
            grid-column: 1/-1; background: white; border: 1px solid var(--border);
            border-radius: 16px; padding: 4rem 2rem; text-align: center; color: var(--gray);
        }

        .empty-icon { width: 60px; height: 60px; color: #e5e7eb; margin: 0 auto 1rem; }
        .empty-title { font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; }
        .empty-link {
            display: inline-flex; align-items: center; gap: 0.5rem;
            margin-top: 1.25rem; padding: 0.7rem 1.5rem;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: white; border-radius: 100px; text-decoration: none;
            font-size: 0.85rem; font-weight: 600; transition: all 0.2s;
        }

        /* PAGINATION */
        .pagination {
            display: flex; align-items: center; justify-content: center;
            gap: 0.5rem; margin-top: 2.5rem;
        }

        .page-btn {
            display: flex; align-items: center; justify-content: center;
            width: 38px; height: 38px; border-radius: 10px;
            border: 1.5px solid var(--border); background: white;
            font-size: 0.875rem; font-weight: 600; color: var(--dark);
            text-decoration: none; transition: all 0.2s;
        }

        .page-btn:hover { border-color: var(--green); color: var(--green); }
        .page-btn.active { background: linear-gradient(135deg, var(--green), var(--green-dark)); border-color: transparent; color: white; }

        /* TOAST */
        .toast {
            position: fixed; top: 1.5rem; right: 1.5rem; z-index: 999;
            background: var(--green); color: white;
            padding: 0.75rem 1.5rem; border-radius: 12px;
            font-size: 0.875rem; font-weight: 600;
            box-shadow: 0 8px 30px rgba(0,200,83,0.3);
        }

        /* MOBILE FILTER TOGGLE */
        .filter-toggle {
            display: none; width: 100%;
            align-items: center; justify-content: space-between;
            padding: 0.85rem 1rem; background: white;
            border: 1.5px solid var(--border); border-radius: 12px;
            font-size: 0.875rem; font-weight: 600; cursor: pointer;
            margin-bottom: 1rem; font-family: 'DM Sans', sans-serif;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .burger { display: flex; align-items: center; }
            .page-inner { flex-direction: column; padding: 1rem; gap: 0; }
            .sidebar { width: 100%; }
            .sidebar-card { position: static; margin-bottom: 1rem; }
            .filter-toggle { display: flex; }
            .sidebar-content { display: none; }
            .sidebar-content.open { display: block; }
            .product-grid { grid-template-columns: repeat(2, 1fr); }
            .product-img, .product-img-placeholder { height: 130px; }
            .search-bar { padding: 0.75rem 1rem; }
        }

        @media (max-width: 380px) {
            .product-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body x-data="{ toastMsg: '', menuOpen: false, sidebarOpen: false }">

<div x-show="toastMsg" x-text="toastMsg" x-transition class="toast"></div>

<!-- HEADER -->
<header>
    <div class="header-inner">
        <div>
            <a href="accueil.php" class="logo-text">ADE MARKET</a>
            <div class="logo-sub">Porto-Novo, Bénin</div>
        </div>

        <nav class="nav-links">
            <a href="accueil.php" class="nav-btn"><i data-lucide="home" style="width:14px;height:14px"></i> Accueil</a>
            <?php if (estConnecte()): ?>
                <span style="color:rgba(255,255,255,0.4);font-size:0.83rem">Bonjour, <strong style="color:white"><?= htmlspecialchars($_SESSION['nom']) ?></strong></span>
                <a href="compte.php" class="nav-btn"><i data-lucide="user" style="width:14px;height:14px"></i></a>
                <a href="mes_commandes.php" class="nav-btn"><i data-lucide="clipboard-list" style="width:14px;height:14px"></i></a>
                <a href="panier.php" class="nav-btn primary"><i data-lucide="shopping-cart" style="width:14px;height:14px"></i> Panier (<?= $panier_count ?>)</a>
                <a href="deconnexion.php" class="nav-btn"><i data-lucide="log-out" style="width:14px;height:14px"></i></a>
            <?php else: ?>
                <a href="panier.php" class="nav-btn primary"><i data-lucide="shopping-cart" style="width:14px;height:14px"></i> Panier (<?= $panier_count ?>)</a>
                <a href="connexion.php" class="nav-btn">Connexion</a>
                <a href="inscription.php" class="nav-btn primary">S'inscrire</a>
            <?php endif; ?>
        </nav>

        <button class="burger" @click="menuOpen = !menuOpen">
            <i data-lucide="menu" style="width:20px;height:20px"></i>
        </button>
    </div>

    <div class="mobile-nav" :class="menuOpen ? 'open' : ''">
        <a href="accueil.php"><i data-lucide="home" style="width:16px;height:16px"></i> Accueil</a>
        <?php if (estConnecte()): ?>
            <a href="compte.php"><i data-lucide="user" style="width:16px;height:16px"></i> Mon compte</a>
            <a href="mes_commandes.php"><i data-lucide="clipboard-list" style="width:16px;height:16px"></i> Mes commandes</a>
            <a href="panier.php"><i data-lucide="shopping-cart" style="width:16px;height:16px"></i> Panier (<?= $panier_count ?>)</a>
            <a href="deconnexion.php"><i data-lucide="log-out" style="width:16px;height:16px"></i> Déconnexion</a>
        <?php else: ?>
            <a href="connexion.php"><i data-lucide="log-in" style="width:16px;height:16px"></i> Connexion</a>
            <a href="inscription.php"><i data-lucide="user-plus" style="width:16px;height:16px"></i> S'inscrire</a>
        <?php endif; ?>
    </div>
</header>

<!-- SEARCH -->
<div class="search-bar">
    <form method="GET" class="search-inner">
        <?php if ($categorie_id): ?><input type="hidden" name="categorie" value="<?= $categorie_id ?>"><?php endif; ?>
        <?php if ($tri !== 'nouveaute'): ?><input type="hidden" name="tri" value="<?= htmlspecialchars($tri) ?>"><?php endif; ?>
        <input type="text" name="recherche" placeholder="Rechercher un produit..." value="<?= htmlspecialchars($recherche) ?>" class="search-input">
        <button type="submit" class="search-btn"><i data-lucide="search" style="width:16px;height:16px"></i> Rechercher</button>
    </form>
</div>

<!-- MAIN -->
<div class="page-inner">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <button class="filter-toggle" @click="sidebarOpen = !sidebarOpen">
            <span style="display:flex;align-items:center;gap:0.5rem"><i data-lucide="filter" style="width:16px;height:16px"></i> Filtres & Catégories</span>
            <i data-lucide="chevron-down" style="width:16px;height:16px" :style="sidebarOpen ? 'transform:rotate(180deg)' : ''"></i>
        </button>

        <div class="sidebar-card">
            <div class="sidebar-content" :class="sidebarOpen || window.innerWidth >= 768 ? 'open' : ''" style="display:block">

                <div class="sidebar-title">Catégories</div>
                <a href="?<?= buildQuery(['categorie' => null]) ?>" class="cat-link <?= $categorie_id === 0 ? 'active' : '' ?>">Tous les produits</a>
                <?php foreach ($categories as $cat): ?>
                    <a href="?<?= buildQuery(['categorie' => $cat['id']]) ?>" class="cat-link <?= $categorie_id === $cat['id'] ? 'active' : '' ?>"><?= htmlspecialchars($cat['nom']) ?></a>
                <?php endforeach; ?>

                <hr class="divider">

                <div class="sidebar-title">Prix (FCFA)</div>
                <form method="GET">
                    <?php foreach (['recherche', 'categorie', 'tri'] as $f): ?>
                        <?php if (isset($_GET[$f]) && $_GET[$f] !== ''): ?>
                            <input type="hidden" name="<?= $f ?>" value="<?= htmlspecialchars($_GET[$f]) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <div class="price-inputs">
                        <input type="number" name="prix_min" placeholder="Min" value="<?= $prix_min ?: '' ?>" class="price-input">
                        <span class="price-sep">—</span>
                        <input type="number" name="prix_max" placeholder="Max" value="<?= $prix_max ?: '' ?>" class="price-input">
                    </div>
                    <button type="submit" class="apply-btn">✓ Appliquer</button>
                </form>

                <?php if ($categorie_id || $recherche || $prix_min || $prix_max || $tri !== 'nouveaute'): ?>
                    <a href="index.php" class="reset-link"><i data-lucide="x" style="width:14px;height:14px"></i> Réinitialiser</a>
                <?php endif; ?>
            </div>
        </div>
    </aside>

    <!-- CONTENT -->
    <div class="main-content">
        <div class="content-header">
            <h1 class="content-title">
                <?= $recherche ? "\"$recherche\"" : "Nos articles" ?>
                <span class="content-count">(<?= $total_produits ?>)</span>
            </h1>
            <form method="GET" class="sort-form">
                <?php foreach ($_GET as $k => $v): ?>
                    <?php if ($k !== 'tri' && $k !== 'page'): ?>
                        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <label class="sort-label">Trier :</label>
                <select name="tri" onchange="this.form.submit()" class="sort-select">
                    <option value="nouveaute" <?= $tri === 'nouveaute' ? 'selected' : '' ?>>Nouveautés</option>
                    <option value="prix_croissant" <?= $tri === 'prix_croissant' ? 'selected' : '' ?>>Prix ↑</option>
                    <option value="prix_decroissant" <?= $tri === 'prix_decroissant' ? 'selected' : '' ?>>Prix ↓</option>
                    <option value="nom_a_z" <?= $tri === 'nom_a_z' ? 'selected' : '' ?>>A → Z</option>
                    <option value="nom_z_a" <?= $tri === 'nom_z_a' ? 'selected' : '' ?>>Z → A</option>
                </select>
            </form>
        </div>

        <div class="product-grid">
            <?php if (empty($produits)): ?>
                <div class="empty">
                    <i data-lucide="shopping-bag" class="empty-icon"></i>
                    <div class="empty-title">Aucun produit trouvé</div>
                    <p style="font-size:0.85rem">Essayez d'autres critères de recherche</p>
                    <a href="index.php" class="empty-link"><i data-lucide="x" style="width:14px;height:14px"></i> Réinitialiser</a>
                </div>
            <?php else: ?>
                <?php foreach ($produits as $p): ?>
                <div class="product-card">
                    <?php if ($p['image']): ?>
                        <img src="/ade_market/assets/images/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['nom']) ?>" class="product-img">
                    <?php else: ?>
                        <div class="product-img-placeholder"><i data-lucide="image" style="width:40px;height:40px"></i></div>
                    <?php endif; ?>
                    <div class="product-body">
                        <div class="product-cat"><?= htmlspecialchars($p['categorie_nom'] ?? 'Général') ?></div>
                        <div class="product-name"><?= htmlspecialchars($p['nom']) ?></div>
                        <div class="product-price"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</div>
                        <div class="product-stock"><i data-lucide="check-circle" style="width:12px;height:12px"></i> En stock (<?= $p['stock'] ?>)</div>
                        <form method="POST" action="panier.php" @submit.prevent="fetch('panier.php', { method: 'POST', body: new FormData($el) }).then(() => toastMsg = '✅ Ajouté au panier !')">
                            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                            <input type="hidden" name="action" value="ajouter">
                            <input type="hidden" name="produit_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="add-btn">Ajouter au panier</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= buildQuery(['page' => $page - 1]) ?>" class="page-btn"><i data-lucide="chevron-left" style="width:16px;height:16px"></i></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?<?= buildQuery(['page' => $i]) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?<?= buildQuery(['page' => $page + 1]) ?>" class="page-btn"><i data-lucide="chevron-right" style="width:16px;height:16px"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>lucide.createIcons()</script>
</body>
</html>
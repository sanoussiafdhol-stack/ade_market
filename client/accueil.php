<?php
require_once '../config/database.php';
require_once '../config/session.php';

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$nouveautes = $pdo->query("SELECT * FROM produits WHERE stock > 0 ORDER BY id DESC LIMIT 4")->fetchAll();
$best_sellers = $pdo->query("
    SELECT p.*, SUM(cp.quantite) as vendus
    FROM produits p
    JOIN commande_produits cp ON cp.produit_id = p.id
    JOIN commandes c ON cp.commande_id = c.id AND c.statut != 'annulée'
    GROUP BY p.id
    ORDER BY vendus DESC
    LIMIT 4
")->fetchAll();

$panier_count = 0;
if (isset($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $item) {
        $panier_count += $item['quantite'];
    }
}

$cat_icons = ['🥗', '🥤', '🧴', '🥬', '⚡'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ADE MARKET - Votre marché à Porto-Novo</title>
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
            --dark-2: #111827;
            --gray: #6b7280;
            --light: #f9fafb;
            --white: #ffffff;
            --border: #e5e7eb;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--white);
            color: var(--dark);
            overflow-x: hidden;
        }

        h1, h2, h3, h4 { font-family: 'Syne', sans-serif; }

        /* HEADER */
        header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(10, 15, 30, 0.97);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding: 0 2rem;
        }

        .header-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 70px;
        }

        .logo-text {
            font-family: 'Syne', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--green), #69f0ae);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-sub { font-size: 0.7rem; color: #4b5563; margin-top: -4px; }

        .nav-links { display: flex; align-items: center; gap: 0.75rem; }

        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1rem;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.75);
        }

        .nav-btn:hover { background: rgba(255,255,255,0.1); color: white; }

        .nav-btn.primary {
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            border-color: transparent;
            color: white;
            font-weight: 600;
        }

        .nav-btn.primary:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(0,200,83,0.3); }

        /* BURGER */
        .burger { display: none; background: none; border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 0.4rem; color: white; cursor: pointer; }

        .mobile-nav { display: none; background: var(--dark-2); border-top: 1px solid rgba(255,255,255,0.05); padding: 1rem 2rem; flex-direction: column; gap: 0.5rem; }
        .mobile-nav a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 0.75rem; border-radius: 8px; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; }
        .mobile-nav a:hover { background: rgba(255,255,255,0.05); }
        .mobile-nav.open { display: flex; }

        /* HERO */
        .hero {
            background: var(--dark);
            min-height: 85vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding: 4rem 2rem;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 60% 40%, rgba(0,200,83,0.12), transparent),
                        radial-gradient(ellipse 50% 50% at 20% 80%, rgba(0,149,61,0.08), transparent);
        }

        .hero-grid {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        .hero-inner {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(0,200,83,0.1);
            border: 1px solid rgba(0,200,83,0.2);
            color: var(--green);
            padding: 0.4rem 1rem;
            border-radius: 100px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            width: fit-content;
        }

        .hero h2 {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 800;
            color: white;
            line-height: 1.1;
            margin-bottom: 1.5rem;
        }

        .hero h2 span {
            background: linear-gradient(135deg, var(--green), #69f0ae);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            color: rgba(255,255,255,0.55);
            font-size: 1.05rem;
            line-height: 1.7;
            margin-bottom: 2.5rem;
            max-width: 440px;
        }

        .hero-btns { display: flex; gap: 1rem; flex-wrap: wrap; }

        .hero-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.9rem 2rem;
            border-radius: 100px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.25s;
        }

        .hero-btn.main {
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: white;
            box-shadow: 0 8px 30px rgba(0,200,83,0.25);
        }

        .hero-btn.main:hover { transform: translateY(-2px); box-shadow: 0 14px 40px rgba(0,200,83,0.35); }

        .hero-btn.outline {
            border: 1px solid rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.8);
        }

        .hero-btn.outline:hover { background: rgba(255,255,255,0.07); color: white; }

        .hero-stats {
            display: flex;
            gap: 2rem;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .hero-stat-val { font-family: 'Syne', sans-serif; font-size: 1.8rem; font-weight: 800; color: var(--green); }
        .hero-stat-label { font-size: 0.78rem; color: rgba(255,255,255,0.4); margin-top: 2px; }

        /* HERO RIGHT */
        .hero-visual {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .hero-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.3s;
        }

        .hero-card:hover { transform: translateY(-4px); }
        .hero-card:first-child { grid-column: span 2; }

        .hero-card img {
            width: 100%;
            height: 160px;
            object-fit: cover;
        }

        .hero-card:first-child img { height: 200px; }

        .hero-card-body { padding: 0.75rem 1rem; }
        .hero-card-name { font-size: 0.85rem; font-weight: 600; color: rgba(255,255,255,0.85); }
        .hero-card-price { font-size: 0.9rem; font-weight: 700; color: var(--green); margin-top: 2px; }
        .hero-card-placeholder { height: 160px; background: rgba(255,255,255,0.03); display: flex; align-items: center; justify-content: center; }

        /* SECTION */
        section { padding: 5rem 2rem; }
        .section-inner { max-width: 1200px; margin: 0 auto; }

        .section-label {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--green);
            margin-bottom: 0.75rem;
        }

        .section-title {
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 3rem;
        }

        /* CATEGORIES */
        .categories-section { background: var(--light); }

        .cat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1rem;
        }

        .cat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.75rem 1rem;
            text-align: center;
            text-decoration: none;
            transition: all 0.25s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .cat-card:hover {
            border-color: var(--green);
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0,200,83,0.1);
        }

        .cat-icon {
            width: 56px;
            height: 56px;
            background: var(--green-light);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: transform 0.2s;
        }

        .cat-card:hover .cat-icon { transform: scale(1.1); }
        .cat-name { font-size: 0.9rem; font-weight: 600; color: var(--dark); }

        /* PRODUCTS */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.25rem;
        }

        .product-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.25s;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.08);
            border-color: #d1fae5;
        }

        .product-img { width: 100%; height: 180px; object-fit: cover; }
        .product-img-placeholder {
            height: 180px;
            background: var(--light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #d1d5db;
        }

        .product-body { padding: 1rem; flex: 1; display: flex; flex-direction: column; }
        .product-cat { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--green); margin-bottom: 0.4rem; }
        .product-name { font-size: 0.95rem; font-weight: 600; color: var(--dark); margin-bottom: 0.5rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .product-price { font-size: 1.15rem; font-weight: 800; color: var(--dark); margin-bottom: 0.75rem; font-family: 'Syne', sans-serif; }
        .product-badge { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.72rem; color: #059669; font-weight: 600; margin-bottom: 0.75rem; }

        .add-btn {
            width: 100%;
            padding: 0.7rem;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: auto;
            font-family: 'DM Sans', sans-serif;
        }

        .add-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,200,83,0.3); }

        /* SECTION HEADER */
        .section-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .see-all {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--green);
            text-decoration: none;
            transition: gap 0.2s;
        }

        .see-all:hover { gap: 0.7rem; }

        /* ABOUT */
        .about-section { background: var(--dark); }

        .about-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5rem;
            align-items: center;
        }

        .about-label { color: var(--green); }
        .about-title { color: white; margin-bottom: 1.5rem; }
        .about-text { color: rgba(255,255,255,0.5); line-height: 1.8; margin-bottom: 2rem; }

        .about-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .about-stat {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
        }

        .about-stat-val { font-family: 'Syne', sans-serif; font-size: 1.75rem; font-weight: 800; color: var(--green); }
        .about-stat-label { font-size: 0.75rem; color: rgba(255,255,255,0.35); margin-top: 0.25rem; }

        .about-visual {
            background: rgba(0,200,83,0.06);
            border: 1px solid rgba(0,200,83,0.1);
            border-radius: 24px;
            padding: 3rem;
            text-align: center;
        }

        .about-visual-icon { font-size: 4rem; margin-bottom: 1.5rem; }
        .about-visual p { color: rgba(255,255,255,0.6); font-size: 0.9rem; line-height: 1.6; }

        /* CTA */
        .cta-section {
            background: linear-gradient(135deg, var(--green) 0%, #00953d 50%, #007a32 100%);
            text-align: center;
            padding: 6rem 2rem;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 60% 60% at 50% 50%, rgba(255,255,255,0.08), transparent);
        }

        .cta-inner { position: relative; z-index: 1; max-width: 600px; margin: 0 auto; }
        .cta-title { font-size: clamp(2rem, 4vw, 3rem); font-weight: 800; color: white; margin-bottom: 1rem; }
        .cta-text { color: rgba(255,255,255,0.75); font-size: 1.05rem; margin-bottom: 2.5rem; line-height: 1.6; }

        .cta-btns { display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap; }

        .cta-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.9rem 2rem;
            border-radius: 100px;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.25s;
            font-family: 'DM Sans', sans-serif;
        }

        .cta-btn.white { background: white; color: var(--green-dark); }
        .cta-btn.white:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(0,0,0,0.15); }
        .cta-btn.border { border: 2px solid rgba(255,255,255,0.4); color: white; }
        .cta-btn.border:hover { background: rgba(255,255,255,0.1); }

        /* FOOTER */
        footer {
            background: var(--dark);
            padding: 4rem 2rem 2rem;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .footer-inner { max-width: 1200px; margin: 0 auto; }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .footer-brand p { color: rgba(255,255,255,0.35); font-size: 0.85rem; line-height: 1.7; margin-top: 0.75rem; max-width: 260px; }

        .footer-col h5 { color: white; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 1rem; }
        .footer-col a { display: block; color: rgba(255,255,255,0.35); font-size: 0.85rem; text-decoration: none; margin-bottom: 0.6rem; transition: color 0.2s; }
        .footer-col a:hover { color: var(--green); }

        .footer-contact { display: flex; align-items: center; gap: 0.5rem; color: rgba(255,255,255,0.35); font-size: 0.85rem; margin-bottom: 0.6rem; }
        .footer-contact svg { color: var(--green); flex-shrink: 0; }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.05);
            padding-top: 1.5rem;
            text-align: center;
            color: rgba(255,255,255,0.2);
            font-size: 0.8rem;
        }

        /* TOAST */
        .toast {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 999;
            background: var(--green);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            box-shadow: 0 8px 30px rgba(0,200,83,0.3);
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .burger { display: flex; align-items: center; }
            .hero-inner { grid-template-columns: 1fr; gap: 2rem; }
            .hero-visual { display: none; }
            .hero { min-height: auto; padding: 3rem 1.5rem; }
            .about-grid { grid-template-columns: 1fr; gap: 2rem; }
            .footer-grid { grid-template-columns: 1fr; gap: 2rem; }
            .hero-stats { gap: 1.5rem; }
            section { padding: 3rem 1.25rem; }
            .cat-grid { grid-template-columns: repeat(2, 1fr); }
            .product-grid { grid-template-columns: repeat(2, 1fr); gap: 0.875rem; }
            .product-img, .product-img-placeholder { height: 140px; }
        }

        @media (max-width: 400px) {
            .product-grid { grid-template-columns: 1fr; }
        }

        /* ANIMATIONS */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-up { animation: fadeUp 0.6s ease forwards; }
        .fade-up-2 { animation: fadeUp 0.6s 0.1s ease forwards; opacity: 0; }
        .fade-up-3 { animation: fadeUp 0.6s 0.2s ease forwards; opacity: 0; }
        .fade-up-4 { animation: fadeUp 0.6s 0.3s ease forwards; opacity: 0; }
    </style>
</head>
<body x-data="{ toastMsg: '', menuOpen: false }">

<!-- TOAST -->
<div x-show="toastMsg" x-text="toastMsg" x-transition class="toast"></div>

<!-- HEADER -->
<header>
    <div class="header-inner">
        <div>
            <div class="logo-text">ADE MARKET</div>
            <div class="logo-sub">Porto-Novo, Bénin</div>
        </div>

        <nav class="nav-links">
            <a href="index.php" class="nav-btn">Catalogue</a>
            <?php if (estConnecte()): ?>
                <span style="color:rgba(255,255,255,0.4); font-size:0.85rem;">Bonjour, <strong style="color:white"><?= htmlspecialchars($_SESSION['nom']) ?></strong></span>
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
        <a href="index.php"><i data-lucide="grid" style="width:16px;height:16px"></i> Catalogue</a>
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

<!-- HERO -->
<section class="hero">
    <div class="hero-grid"></div>
    <div class="hero-inner">
        <div>
            <div class="hero-badge fade-up">
                <span style="width:6px;height:6px;background:var(--green);border-radius:50%;display:inline-block;animation:pulse 2s infinite"></span>
                Livraison 1-3h à Porto-Novo
            </div>
            <h2 class="fade-up-2">Votre marché<br><span>en ligne</span><br>à Porto-Novo</h2>
            <p class="fade-up-3">Produits frais, alimentation, hygiène et électroménager livrés directement chez vous. Commandez maintenant !</p>
            <div class="hero-btns fade-up-4">
                <a href="index.php" class="hero-btn main"><i data-lucide="shopping-bag" style="width:18px;height:18px"></i> Voir le catalogue</a>
                <a href="inscription.php" class="hero-btn outline"><i data-lucide="user-plus" style="width:18px;height:18px"></i> Créer un compte</a>
            </div>
            <div class="hero-stats fade-up-4">
                <div>
                    <div class="hero-stat-val">1-3h</div>
                    <div class="hero-stat-label">Délai de livraison</div>
                </div>
                <div>
                    <div class="hero-stat-val">100%</div>
                    <div class="hero-stat-label">Satisfait ou remboursé</div>
                </div>
                <div>
                    <div class="hero-stat-val">MTN</div>
                    <div class="hero-stat-label">Mobile Money accepté</div>
                </div>
            </div>
        </div>

        <!-- Produits dans le hero -->
        <div class="hero-visual">
            <?php foreach (array_slice($nouveautes, 0, 3) as $i => $p): ?>
            <div class="hero-card <?= $i === 0 ? '' : '' ?>">
                <?php if ($p['image']): ?>
                    <img src="/ade_market/assets/images/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['nom']) ?>">
                <?php else: ?>
                    <div class="hero-card-placeholder"><i data-lucide="image" style="width:32px;height:32px;color:#374151"></i></div>
                <?php endif; ?>
                <div class="hero-card-body">
                    <div class="hero-card-name"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="hero-card-price"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CATEGORIES -->
<section class="categories-section">
    <div class="section-inner">
        <span class="section-label">Catégories</span>
        <h3 class="section-title">Ce que nous proposons</h3>
        <div class="cat-grid">
            <?php foreach ($categories as $i => $cat): ?>
            <a href="index.php?categorie=<?= $cat['id'] ?>" class="cat-card">
                <div class="cat-icon"><?= $cat_icons[$i] ?? '🛍️' ?></div>
                <div class="cat-name"><?= htmlspecialchars($cat['nom']) ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- NOUVEAUTES -->
<section style="background:white">
    <div class="section-inner">
        <div class="section-header">
            <div>
                <span class="section-label">Nouveautés</span>
                <h3 class="section-title" style="margin-bottom:0">Nos derniers articles</h3>
            </div>
            <a href="index.php" class="see-all">Tout voir <i data-lucide="arrow-right" style="width:16px;height:16px"></i></a>
        </div>
        <div class="product-grid">
            <?php foreach ($nouveautes as $p): ?>
            <div class="product-card">
                <?php if ($p['image']): ?>
                    <img src="/ade_market/assets/images/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['nom']) ?>" class="product-img">
                <?php else: ?>
                    <div class="product-img-placeholder"><i data-lucide="image" style="width:40px;height:40px"></i></div>
                <?php endif; ?>
                <div class="product-body">
                    <div class="product-price"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</div>
                    <div class="product-name"><?= htmlspecialchars($p['nom']) ?></div>
                    <div class="product-badge"><i data-lucide="check-circle" style="width:12px;height:12px"></i> En stock (<?= $p['stock'] ?>)</div>
                    <form method="POST" action="panier.php" @submit.prevent="fetch('panier.php', { method: 'POST', body: new FormData($el) }).then(() => toastMsg = '✅ Ajouté au panier !')">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                        <input type="hidden" name="action" value="ajouter">
                        <input type="hidden" name="produit_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="add-btn">Ajouter au panier</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- BEST SELLERS -->
<?php if (!empty($best_sellers)): ?>
<section style="background:var(--light)">
    <div class="section-inner">
        <div class="section-header">
            <div>
                <span class="section-label">Meilleures ventes</span>
                <h3 class="section-title" style="margin-bottom:0">Les plus populaires</h3>
            </div>
            <a href="index.php" class="see-all">Tout voir <i data-lucide="arrow-right" style="width:16px;height:16px"></i></a>
        </div>
        <div class="product-grid">
            <?php foreach ($best_sellers as $p): ?>
            <div class="product-card">
                <?php if ($p['image']): ?>
                    <img src="/ade_market/assets/images/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['nom']) ?>" class="product-img">
                <?php else: ?>
                    <div class="product-img-placeholder"><i data-lucide="image" style="width:40px;height:40px"></i></div>
                <?php endif; ?>
                <div class="product-body">
                    <div class="product-badge" style="color:#f59e0b"><i data-lucide="trending-up" style="width:12px;height:12px"></i> <?= $p['vendus'] ?> vendus</div>
                    <div class="product-price"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</div>
                    <div class="product-name"><?= htmlspecialchars($p['nom']) ?></div>
                    <form method="POST" action="panier.php" @submit.prevent="fetch('panier.php', { method: 'POST', body: new FormData($el) }).then(() => toastMsg = '✅ Ajouté au panier !')">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                        <input type="hidden" name="action" value="ajouter">
                        <input type="hidden" name="produit_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="add-btn">Ajouter au panier</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ABOUT -->
<section class="about-section">
    <div class="section-inner">
        <div class="about-grid">
            <div>
                <span class="section-label about-label">À propos</span>
                <h3 class="section-title about-title">ADE MARKET<br>Porto-Novo</h3>
                <p class="about-text">Votre marché en ligne à Porto-Novo. Produits alimentaires, articles ménagers et bien plus, livrés directement chez vous. Commandez avant 17h et recevez sous 1 à 3 heures. Paiement Mobile Money ou à la livraison.</p>
                <div class="about-stats">
                    <div class="about-stat">
                        <div class="about-stat-val">1-3h</div>
                        <div class="about-stat-label">Livraison</div>
                    </div>
                    <div class="about-stat">
                        <div class="about-stat-val">100%</div>
                        <div class="about-stat-label">Satisfait</div>
                    </div>
                    <div class="about-stat">
                        <div class="about-stat-val">24/7</div>
                        <div class="about-stat-label">Disponible</div>
                    </div>
                </div>
            </div>
            <div class="about-visual">
                <div class="about-visual-icon">🛍️</div>
                <p style="color:rgba(255,255,255,0.7);font-size:1.1rem;font-weight:600;margin-bottom:0.5rem">Des produits frais et de qualité</p>
                <p>Livrés avec soin directement à Porto-Novo et ses environs</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="cta-inner">
        <h3 class="cta-title">Prêt à commander ?</h3>
        <p class="cta-text">Rejoignez nos clients satisfaits à Porto-Novo. Livraison rapide, paiement sécurisé Mobile Money.</p>
        <div class="cta-btns">
            <a href="index.php" class="cta-btn white"><i data-lucide="shopping-bag" style="width:18px;height:18px"></i> Voir le catalogue</a>
            <a href="inscription.php" class="cta-btn border"><i data-lucide="user-plus" style="width:18px;height:18px"></i> Créer un compte</a>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-inner">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="logo-text">ADE MARKET</div>
                <p>Votre marché en ligne à Porto-Novo. Produits de qualité, livraison rapide et paiement sécurisé.</p>
            </div>
            <div class="footer-col">
                <h5>Contact</h5>
                <div class="footer-contact"><i data-lucide="map-pin" style="width:14px;height:14px"></i> Porto-Novo, Bénin</div>
                <div class="footer-contact"><i data-lucide="phone" style="width:14px;height:14px"></i> +229 97000000</div>
                <div class="footer-contact"><i data-lucide="mail" style="width:14px;height:14px"></i> contact@ademarket.bj</div>
            </div>
            <div class="footer-col">
                <h5>Liens rapides</h5>
                <a href="index.php">Catalogue</a>
                <a href="panier.php">Panier</a>
                <a href="connexion.php">Connexion</a>
                <a href="inscription.php">Inscription</a>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> ADE MARKET — Porto-Novo, Bénin. Tous droits réservés.
        </div>
    </div>
</footer>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}
</style>

<script>lucide.createIcons()</script>
</body>
</html>
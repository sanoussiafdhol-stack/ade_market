<?php
require_once '../config/database.php';
require_once '../config/session.php';

redirigerSiNonAdmin();

$message = "";
$produit = null;

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
    $stmt->execute([$id]);
    $produit = $stmt->fetch();
}

if (!$produit) {
    header("Location: produits.php");
    exit();
}

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifierTokenCSRF($_POST['csrf_token'])) {
        $message = "Erreur de validation CSRF.";
    } else {
        $nom = trim($_POST['nom']);
        $description = trim($_POST['description']);
        $prix = (float)$_POST['prix'];
        $stock = (int)$_POST['stock'];
        $categorie_id = (int)$_POST['categorie_id'];
        $image = $produit['image'];

        $upload_error = "";
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $mime = mime_content_type($_FILES['image']['tmp_name']);
            if (!in_array($ext, $allowed_exts) || !in_array($mime, $allowed_mimes)) {
                $upload_error = "Type de fichier non autorisé (JPG, PNG, GIF, WEBP uniquement).";
            } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                $upload_error = "L'image ne doit pas dépasser 2 Mo.";
            } elseif (!getimagesize($_FILES['image']['tmp_name'])) {
                $upload_error = "Le fichier n'est pas une image valide.";
            } else {
                $image = uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], "../assets/images/" . $image);
            }
        }

        if ($upload_error) {
            $message = $upload_error;
        } else {
            $stmt = $pdo->prepare("UPDATE produits SET nom=?, description=?, prix=?, stock=?, categorie_id=?, image=? WHERE id=?");
            $stmt->execute([$nom, $description, $prix, $stock, $categorie_id, $image, $id]);
            $message = "Produit modifié avec succès !";

            $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
            $stmt->execute([$id]);
            $produit = $stmt->fetch();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un produit - ADE MARKET Admin</title>
    <meta name="description" content="Modifiez les informations d'un produit dans le catalogue ADE MARKET.">
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
            <a href="produits.php" class="active flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="package" class="h-4 w-4"></i> Produits</a>
            <a href="commandes.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="shopping-cart" class="h-4 w-4"></i> Commandes</a>
            <a href="livraisons.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="truck" class="h-4 w-4"></i> Livraisons</a>
            <a href="clients.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="users" class="h-4 w-4"></i> Clients</a>
            <a href="../client/index.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="globe" class="h-4 w-4"></i> Voir le site</a>
            <a href="../client/deconnexion.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="log-out" class="h-4 w-4"></i> Déconnexion</a>
        </nav>
    </aside>

    <main class="flex-1 overflow-y-auto bg-[#f8fafc] p-10">
        <a href="produits.php" class="btn-retour mb-6 inline-flex items-center gap-2 rounded-xl border border-[#e2e8f0] px-4 py-2 text-sm font-medium text-[#64748b] transition-all hover:bg-[#e2e8f0] hover:text-[#0f172a]"><i data-lucide="arrow-left" class="h-4 w-4"></i> Retour aux produits</a>
        <h1 class="mb-8 text-2xl font-bold text-[#0f172a]">Modifier le produit</h1>

        <?php if ($message): ?>
            <div class="mb-6 rounded-xl border border-[rgba(16,185,129,0.2)] bg-[#d1fae5] px-5 py-3 text-sm font-medium text-[#065f46]"><?= $message ?></div>
        <?php endif; ?>

        <div class="max-w-xl rounded-xl border border-[#e2e8f0] bg-white p-8 shadow-sm">
            <h3 class="mb-6 border-b-2 border-[#f8fafc] pb-3 text-lg font-bold text-[#0f172a]"><?= htmlspecialchars($produit['nom']) ?></h3>

            <?php if ($produit['image']): ?>
            <div class="mb-6 flex items-center gap-4">
                <img src="../assets/images/<?= htmlspecialchars($produit['image']) ?>" class="image-actuelle h-28 w-28 rounded-xl border-2 border-[#e2e8f0] object-cover">
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                <div class="mb-5">
                    <label class="mb-2 block text-sm font-medium text-[#1e293b]">Nom</label>
                    <input type="text" name="nom" value="<?= htmlspecialchars($produit['nom']) ?>" required class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                </div>
                <div class="mb-5">
                    <label class="mb-2 block text-sm font-medium text-[#1e293b]">Description</label>
                    <textarea name="description" class="min-h-[90px] w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15"><?= htmlspecialchars($produit['description'] ?? '') ?></textarea>
                </div>
                <div class="mb-5">
                    <label class="mb-2 block text-sm font-medium text-[#1e293b]">Prix (FCFA)</label>
                    <input type="number" name="prix" value="<?= $produit['prix'] ?>" required class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                </div>
                <div class="mb-5">
                    <label class="mb-2 block text-sm font-medium text-[#1e293b]">Stock</label>
                    <input type="number" name="stock" value="<?= $produit['stock'] ?>" required class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                </div>
                <div class="mb-5">
                    <label class="mb-2 block text-sm font-medium text-[#1e293b]">Catégorie</label>
                    <select name="categorie_id" class="w-full rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $produit['categorie_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-5">
                    <label class="mb-2 block text-sm font-medium text-[#1e293b]">Nouvelle image (optionnel)</label>
                    <input type="file" name="image" accept="image/*" class="w-full text-sm text-[#64748b] file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-[#10b981]/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#10b981] hover:file:bg-[#10b981]/20">
                </div>
                <button type="submit" class="btn-gradient inline-flex cursor-pointer items-center gap-2 rounded-xl px-8 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-xl"><i data-lucide="save" class="h-4 w-4"></i> Enregistrer</button>
            </form>
        </div>
    </main>
</div>

<script>lucide.createIcons()</script>
</body>
</html>

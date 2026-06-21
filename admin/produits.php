<?php
require_once '../config/database.php';
require_once '../config/session.php';

redirigerSiNonAdmin();

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifierTokenCSRF($_POST['csrf_token'])) {
        $message = "Erreur de validation CSRF.";
    } elseif ($_POST['action'] === 'ajouter') {
        $nom = trim($_POST['nom']);
        $description = trim($_POST['description']);
        $prix = (float)$_POST['prix'];
        $stock = (int)$_POST['stock'];
        $categorie_id = (int)$_POST['categorie_id'];
        $image = "";

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
            $stmt = $pdo->prepare("INSERT INTO produits (nom, description, prix, stock, categorie_id, image) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $description, $prix, $stock, $categorie_id, $image]);
            $message = "Produit ajouté avec succès !";
        }
    } elseif ($_POST['action'] === 'supprimer') {
        $stmt = $pdo->prepare("DELETE FROM produits WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $message = "Produit supprimé.";
    }
}

$produits = $pdo->query("SELECT p.*, c.nom as categorie_nom FROM produits p LEFT JOIN categories c ON p.categorie_id = c.id ORDER BY p.id DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des produits - ADE MARKET Admin</title>
    <meta name="description" content="Gérez le catalogue de produits ADE MARKET : ajout, modification et suivi des stocks.">
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
        <h1 class="mb-8 text-2xl font-bold text-[#0f172a]">Gestion des produits</h1>

        <?php if ($message): ?>
            <div class="mb-6 rounded-xl border border-[rgba(16,185,129,0.2)] bg-[#d1fae5] px-5 py-3 text-sm font-medium text-[#065f46]"><?= $message ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_1.8fr]">
            <div class="rounded-xl border border-[#e2e8f0] bg-white p-8 shadow-sm">
                <h3 class="mb-6 border-b-2 border-[#f8fafc] pb-3 text-lg font-bold text-[#0f172a]">Ajouter un produit</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                    <input type="hidden" name="action" value="ajouter">
                    <div class="mb-5">
                        <label class="mb-2 block text-sm font-medium text-[#1e293b]">Nom</label>
                        <input type="text" name="nom" placeholder="Ex: Sac de riz 25kg" required class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                    </div>
                    <div class="mb-5">
                        <label class="mb-2 block text-sm font-medium text-[#1e293b]">Description</label>
                        <textarea name="description" placeholder="Description détaillée..." class="min-h-[90px] w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15"></textarea>
                    </div>
                    <div class="mb-5">
                        <label class="mb-2 block text-sm font-medium text-[#1e293b]">Prix (FCFA)</label>
                        <input type="number" name="prix" step="1" placeholder="Ex: 15000" required class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                    </div>
                    <div class="mb-5">
                        <label class="mb-2 block text-sm font-medium text-[#1e293b]">Stock</label>
                        <input type="number" name="stock" placeholder="Ex: 50" required class="w-full rounded-xl border border-[#e2e8f0] px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                    </div>
                    <div class="mb-5">
                        <label class="mb-2 block text-sm font-medium text-[#1e293b]">Catégorie</label>
                        <select name="categorie_id" class="w-full rounded-xl border border-[#e2e8f0] bg-white px-4 py-3 text-sm text-[#1e293b] outline-none transition-all focus:border-[#10b981] focus:ring-4 focus:ring-[#10b981]/15">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-5">
                        <label class="mb-2 block text-sm font-medium text-[#1e293b]">Image</label>
                        <input type="file" name="image" accept="image/*" class="w-full text-sm text-[#64748b] file:mr-4 file:cursor-pointer file:rounded-lg file:border-0 file:bg-[#10b981]/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#10b981] hover:file:bg-[#10b981]/20">
                    </div>
                    <button type="submit" class="btn-gradient mt-4 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl px-5 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-xl"><i data-lucide="plus-circle" class="h-4 w-4"></i> Ajouter le produit</button>
                </form>
            </div>

            <div class="overflow-hidden rounded-xl border border-[#e2e8f0] bg-white pb-1 shadow-sm">
                <h3 class="border-b border-[#e2e8f0] px-6 pb-4 pt-6 text-lg font-bold text-[#0f172a]">Tous les produits (<?= count($produits) ?>)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-[#e2e8f0] bg-[#f8fafc] text-xs font-semibold uppercase tracking-wider text-[#1e293b]">
                                <th class="px-4 py-3">Image</th>
                                <th class="px-4 py-3">Nom</th>
                                <th class="px-4 py-3">Catégorie</th>
                                <th class="px-4 py-3">Prix</th>
                                <th class="px-4 py-3">Stock</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produits as $p): ?>
                            <tr class="border-b border-[#e2e8f0] text-sm transition-colors hover:bg-[#10b981]/[0.02]">
                                <td class="px-4 py-3">
                                    <?php if ($p['image']): ?>
                                        <img src="../assets/images/<?= htmlspecialchars($p['image']) ?>" class="h-11 w-11 rounded-lg border border-[#e2e8f0] object-cover">
                                    <?php else: ?>
                                        <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-[#f8fafc]"><i data-lucide="image" class="h-5 w-5 text-[#94a3b8]"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 font-semibold text-[#0f172a]"><?= htmlspecialchars($p['nom']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($p['categorie_nom'] ?? '-') ?></td>
                                <td class="px-4 py-3 font-bold"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</td>
                                <td class="px-4 py-3">
                                    <?php if ($p['stock'] <= 5): ?>
                                        <span class="stock-low inline-block rounded-lg px-2 py-1 text-xs font-bold"><?= $p['stock'] ?></span>
                                    <?php else: ?>
                                        <span class="font-medium"><?= $p['stock'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex gap-2">
                                        <a href="modifier_produit.php?id=<?= $p['id'] ?>" class="inline-flex items-center gap-1 rounded-lg bg-[#dbeafe] px-3 py-1.5 text-xs font-semibold text-[#2563eb] transition-all hover:bg-[#2563eb] hover:text-white"><i data-lucide="pencil" class="h-3.5 w-3.5"></i> Modifier</a>
                                        <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce produit ?')">
                                            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                            <input type="hidden" name="action" value="supprimer">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="inline-flex cursor-pointer items-center gap-1 rounded-lg bg-[#fee2e2] px-3 py-1.5 text-xs font-semibold text-[#ef4444] transition-all hover:bg-[#ef4444] hover:text-white"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Supprimer</button>
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

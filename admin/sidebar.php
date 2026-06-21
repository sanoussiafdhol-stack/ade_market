<aside class="sidebar flex w-64 flex-shrink-0 flex-col bg-[#0f172a] py-8 text-[#94a3b8] shadow-xl">
    <div class="mb-6 border-b border-[#1e293b] px-8 pb-8">
        <h2 class="gradient-text text-2xl font-extrabold">ADE MARKET</h2>
        <p class="text-xs text-[#64748b]">Administration</p>
    </div>
    <nav class="flex flex-col">
        <a href="dashboard.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? ' active' : '' ?>"><i data-lucide="bar-chart-3" class="h-4 w-4"></i> Dashboard</a>
        <a href="produits.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white<?= basename($_SERVER['PHP_SELF']) === 'produits.php' || basename($_SERVER['PHP_SELF']) === 'modifier_produit.php' ? ' active' : '' ?>"><i data-lucide="package" class="h-4 w-4"></i> Produits</a>
        <a href="commandes.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white<?= basename($_SERVER['PHP_SELF']) === 'commandes.php' ? ' active' : '' ?>"><i data-lucide="shopping-cart" class="h-4 w-4"></i> Commandes</a>
        <a href="livraisons.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white<?= basename($_SERVER['PHP_SELF']) === 'livraisons.php' ? ' active' : '' ?>"><i data-lucide="truck" class="h-4 w-4"></i> Livraisons</a>
        <a href="promotions.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white<?= basename($_SERVER['PHP_SELF']) === 'promotions.php' ? ' active' : '' ?>"><i data-lucide="tag" class="h-4 w-4"></i> Promotions</a>
        <a href="clients.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white<?= basename($_SERVER['PHP_SELF']) === 'clients.php' ? ' active' : '' ?>"><i data-lucide="users" class="h-4 w-4"></i> Clients</a>
        <a href="../client/index.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="globe" class="h-4 w-4"></i> Voir le site</a>
        <a href="../client/deconnexion.php" class="flex items-center gap-3 border-l-4 border-transparent px-8 py-3 font-medium text-[#94a3b8] transition-all hover:bg-white/[0.03] hover:text-white"><i data-lucide="log-out" class="h-4 w-4"></i> Déconnexion</a>
    </nav>
</aside>
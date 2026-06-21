<?php
require_once '../config/database.php';
require_once '../config/session.php';

redirigerSiNonAdmin();

$commande_id = (int)($_GET['id'] ?? 0);
if (!$commande_id) die("Commande invalide.");

$stmt = $pdo->prepare("
    SELECT c.*, u.nom, u.email, u.telephone
    FROM commandes c
    JOIN utilisateurs u ON c.utilisateur_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$commande_id]);
$commande = $stmt->fetch();
if (!$commande) die("Commande introuvable.");

$stmt = $pdo->prepare("
    SELECT cp.*, p.nom
    FROM commande_produits cp
    JOIN produits p ON cp.produit_id = p.id
    WHERE cp.commande_id = ?
");
$stmt->execute([$commande_id]);
$produits = $stmt->fetchAll();

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;

$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page { margin: 30px; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #1e293b; }
.header { text-align: center; border-bottom: 3px solid #10b981; padding-bottom: 15px; margin-bottom: 20px; }
.header h1 { color: #10b981; font-size: 26px; margin: 0; }
.header p { color: #64748b; font-size: 11px; margin: 3px 0 0; }
.infos { width: 100%; margin-bottom: 20px; }
.infos td { vertical-align: top; padding: 4px 10px; font-size: 11px; }
.infos td:first-child { font-weight: bold; color: #64748b; white-space: nowrap; }
table.produits { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
table.produits th { background: #10b981; color: white; padding: 8px 10px; text-align: left; font-size: 11px; text-transform: uppercase; }
table.produits td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
table.produits tr:last-child td { border-bottom: none; }
.total { text-align: right; font-size: 14px; font-weight: bold; color: #10b981; padding: 10px 0; border-top: 2px solid #e2e8f0; margin-top: 10px; }
.footer { text-align: center; color: #94a3b8; font-size: 10px; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 12px; }
.badge { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; }
.badge-en_attente { background: #fef3c7; color: #92400e; }
.badge-confirmée { background: #dbeafe; color: #1e40af; }
.badge-activée { background: #d1fae5; color: #065f46; }
.badge-en_livraison { background: #d1fae5; color: #065f46; }
.badge-livrée { background: #bbf7d0; color: #14532d; }
.badge-annulée { background: #fee2e2; color: #991b1b; }
</style>
</head>
<body>

<div class="header">
    <h1>ADE MARKET</h1>
    <p>Porto-Novo, Bénin — contact@ademarket.bj</p>
</div>

<h2 style="margin:0 0 15px; font-size:16px;">FACTURE #' . $commande_id . '</h2>

<table class="infos">
    <tr><td>Client</td><td>' . htmlspecialchars($commande['nom']) . '</td></tr>
    <tr><td>Email</td><td>' . htmlspecialchars($commande['email']) . '</td></tr>
    <tr><td>Téléphone</td><td>' . htmlspecialchars($commande['telephone'] ?? '-') . '</td></tr>
    <tr><td>Adresse</td><td>' . htmlspecialchars($commande['adresse_livraison']) . '</td></tr>
    <tr><td>Date</td><td>' . date('d/m/Y H:i', strtotime($commande['created_at'])) . '</td></tr>
    <tr><td>Paiement</td><td>' . ($commande['moyen_paiement'] === 'mobile_money' ? 'Mobile Money' : 'Paiement à la livraison') . '</td></tr>
    <tr><td>Statut</td><td><span class="badge badge-' . $commande['statut'] . '">' . ucfirst(str_replace('_', ' ', $commande['statut'])) . '</span></td></tr>
</table>

<table class="produits">
    <thead>
        <tr><th>Produit</th><th style="text-align:center;">Qté</th><th style="text-align:right;">Prix unit.</th><th style="text-align:right;">Total</th></tr>
    </thead>
    <tbody>';

$total = 0;
foreach ($produits as $p) {
    $sous_total = $p['prix_unitaire'] * $p['quantite'];
    $total += $sous_total;
    $html .= '
        <tr>
            <td>' . htmlspecialchars($p['nom']) . '</td>
            <td style="text-align:center;">' . $p['quantite'] . '</td>
            <td style="text-align:right;">' . number_format($p['prix_unitaire'], 0, ',', ' ') . ' FCFA</td>
            <td style="text-align:right;">' . number_format($sous_total, 0, ',', ' ') . ' FCFA</td>
        </tr>';
}

$html .= '
    </tbody>
</table>

<div class="total">
    Total : ' . number_format($commande['total'], 0, ',', ' ') . ' FCFA
</div>

<div class="footer">
    ADE MARKET — Porto-Novo, Bénin | Livraison sous 1 à 3 heures<br>
    Merci de votre confiance !
</div>

</body>
</html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("facture_$commande_id.pdf", ["Attachment" => true]);

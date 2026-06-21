# 💳 Système de Paiement Stripe - Documentation

## 📦 Ce qui a été implémenté

✅ **Intégration Stripe complète**
- Checkout sécurisé avec Stripe
- Gestion des paiements via API
- Webhooks pour les mises à jour

✅ **Pages créées**
- `confirmation_paiement.php` - Confirmation après paiement
- `webhook_stripe.php` - Traitement des webhooks

✅ **Classe `Paiement`**
- Gestion des sessions de paiement
- Traitement des webhooks
- Remboursement possible
- Logging complet

✅ **Base de données**
- Table `paiements` pour tracer les transactions
- Table `stripe_webhooks` pour l'audit
- Colonnes `stripe_payment_id` dans `commandes`

---

## 🚀 Installation (20 minutes)

### 1️⃣ Installer Stripe via Composer

```bash
cd C:\xampp\htdocs\ade_market
composer require stripe/stripe-php
```

### 2️⃣ Configurer les clés Stripe

**Créez/mettez à jour votre `.env` :**

```env
# Clés Stripe (https://dashboard.stripe.com/apikeys)
STRIPE_PUBLIC_KEY=pk_test_xxxxxxxxxxxxx
STRIPE_SECRET_KEY=sk_test_xxxxxxxxxxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxx
```

**Pour obtenir les clés :**
1. Allez sur https://dashboard.stripe.com
2. Créez un compte (gratuit)
3. Allez dans Settings → API Keys
4. Copiez les clés **Test**

### 3️⃣ Exécuter la migration BD

```bash
# Via phpMyAdmin ou CLI
mysql -u root -p ade_market < migrations/002_add_payments.sql
```

### 4️⃣ Configurer le webhook

**Dans Stripe Dashboard :**
1. Allez dans Developers → Webhooks
2. Créez un endpoint
3. URL: `https://votre-domaine.com/webhook_stripe.php`
4. Événements:
   - `checkout.session.completed`
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
5. Copiez le **Signing Secret** dans `.env` (STRIPE_WEBHOOK_SECRET)

---

## 💰 Flux de paiement

```
1. Client finalise commande
           ↓
2. Saisit adresse + téléphone
           ↓
3. Clique "Payer par Stripe"
           ↓
4. Commande créée (statut: en_attente_paiement)
           ↓
5. Redirecté vers Stripe Checkout
           ↓
6. Rentre infos carte + valide
           ↓
7. Stripe traite le paiement
           ↓
8. Webhook reçu (checkout.session.completed)
           ↓
9. Commande mise à jour (statut: payée)
           ↓
10. Redirected vers confirmation_paiement.php
           ↓
11. ✅ Paiement réussi affichée
           ↓
12. Email de confirmation envoyé
```

---

## 📝 Utilisation de la classe Paiement

### Créer une session de paiement

```php
require_once 'config/paiement.php';

// Initialise
Paiement::init($pdo);

// Crée une session
$session = Paiement::creerSessionPaiement(
    $commande_id,      // ID de la commande
    $email,             // Email client
    $montant * 100,     // Montant en centimes
    $panier             // Panier
);

// Redirige vers Stripe
header("Location: " . $session->url);
```

### Récupérer une session

```php
$session_data = Paiement::obtenirSession($session_id);
// Retourne:
// [
//     'statut' => 'payé' ou 'en_attente',
//     'commande_id' => 123,
//     'session' => $session_obj
// ]
```

### Traiter un webhook

```php
// Le fichier webhook_stripe.php gère cela automatiquement
// Mais vous pouvez aussi le faire manuellement:

$payload = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
Paiement::traiterWebhook();
```

### Rembourser une commande

```php
try {
    Paiement::rembourser($commande_id);
    echo "Remboursement effectué!";
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}
```

---

## 🗄️ Tables BD

### Table `paiements`
```sql
id (INT) - Clé primaire
commande_id (INT) - Foreign key
stripe_session_id (VARCHAR) - Session Stripe
stripe_payment_id (VARCHAR) - Payment Intent ID
stripe_refund_id (VARCHAR) - Refund ID (si remboursé)
montant (DECIMAL) - Montant en FCFA
devise (VARCHAR) - Devise (xof)
statut (ENUM) - en_attente, confirmé, échoué, remboursé
created_at, updated_at (TIMESTAMP)
```

### Table `stripe_webhooks`
```sql
id (INT)
event_id (VARCHAR) - Unique event ID
event_type (VARCHAR) - Type d'événement
data (JSON) - Données du webhook
processed (BOOLEAN)
created_at (TIMESTAMP)
```

### Colonnes ajoutées à `commandes`
- `stripe_payment_id` (VARCHAR)
- `updated_at` (TIMESTAMP)
- Mise à jour de la colonne `statut` pour supporter:
  - `en_attente_paiement`
  - `payée`
  - `paiement_échoué`
  - `livraison_en_cours`
  - `livrée`

---

## 🔐 Sécurité

✅ **Validation stricte des données**
- Adresse et téléphone validés
- Montants vérifiés

✅ **Protection CSRF**
- Tokens vérifié sur formulaires

✅ **Webhooks sécurisés**
- Signature Stripe vérifiée
- Événements loggés

✅ **Logging complet**
- Chaque transaction tracée
- Erreurs enregistrées

✅ **Pas d'info sensible exposée**
- Clés Stripe dans `.env`
- Messages d'erreur génériques aux clients

---

## 🧪 Test en mode développement

### 1. Cartes de test Stripe

| Cas | Numéro | Exp | CVC |
|-----|--------|-----|-----|
| Succès | 4242 4242 4242 4242 | Futur | N'importe |
| Décliné | 4000 0000 0000 0002 | Futur | N'importe |
| 3D Secure | 4000 0025 0000 3155 | Futur | N'importe |
| Expiré | 4000 0000 0000 0069 | 04/25 | N'importe |

### 2. Tester un paiement

1. Allez sur http://localhost/ade_market/client/panier.php
2. Créez une commande
3. Finalisez avec une adresse
4. Utilisez une carte de test Stripe
5. Validez
6. Confirmez la redirection

### 3. Vérifier les webhooks

Dans Stripe Dashboard → Developers → Webhooks:
- Cliquez sur votre endpoint
- Onglet "Events"
- Voyez les appels webhook

---

## 📊 Montoring

### Vérifier les paiements

```sql
-- Tous les paiements
SELECT * FROM paiements;

-- Paiements échoués
SELECT * FROM paiements WHERE statut = 'échoué';

-- Commandes non payées
SELECT * FROM commandes WHERE statut = 'en_attente_paiement';
```

### Consulter les logs

```bash
# Erreurs paiements
tail logs/error_*.log | grep -i stripe

# Actions paiements
tail logs/action_*.log | grep -i paiement
```

### Webhooks en audit

```sql
SELECT * FROM stripe_webhooks WHERE processed = 0;
```

---

## 🐛 Troubleshooting

### ❌ "Clés Stripe manquantes"
```
Solution: Vérifiez .env
STRIPE_SECRET_KEY = sk_test_...
STRIPE_PUBLIC_KEY = pk_test_...
```

### ❌ "Webhook signature invalide"
```
Solution: Vérifiez STRIPE_WEBHOOK_SECRET dans .env
Doit correspondre exactement au secret du webhook
```

### ❌ "Session introuvable"
```
Solution: La session Stripe a expiré
Les sessions durent 24h
Client doit réessayer
```

### ❌ "Erreur lors de la création de session"
```
Solution: Vérifiez:
1. Clés API valides
2. Internet accessible
3. Logs pour plus d'infos
```

### ❌ "Paiement décliné"
```
Stripe le communiquera à l'utilisateur
Logs contiendront la raison
Client peut réessayer
```

---

## 🔗 États des commandes

```
en_attente_paiement
    ↓ (paiement réussi)
payée
    ↓ (préparation)
traitée
    ↓ (expédition)
livraison_en_cours
    ↓ (arrivée)
livrée
    ↓ (si remboursement)
    remboursement en cours
    
OU si paiement échoue:
en_attente_paiement
    ↓ (paiement échoué)
paiement_échoué
    ↓ (client réessaie)
payée
```

---

## 📨 Emails

### Après paiement réussi
- Sujet: "Paiement reçu - Commande #XXX"
- Contient: Récapitulatif, numéro commande, date livraison

### Après paiement échoué
- Sujet: "Paiement refusé - Veuillez réessayer"
- Contient: Raison, lien pour réessayer

### Avant livraison
- Sujet: "Votre commande est en chemin!"
- Contient: Numéro suivi, adresse de livraison

---

## 🎯 Points importants

⚠️ **PRODUCTION**
- Utilisez des clés **Live** (pk_live_, sk_live_)
- Activez HTTPS
- Testez complètement

⚠️ **REMBOURSEMENT**
- Peut être fait depuis le dashboard Stripe
- OU programmatiquement: `Paiement::rembourser($commande_id)`
- Stripe gardera 2.9% + 0.30€ de commission

⚠️ **DEVISES**
- Tous les montants en FCFA (XOF)
- Stripe accepte XOF
- Conversions automatiques si nécessaire

⚠️ **LIMITES**
- Montant min: 50 XOF (~0.08€)
- Montant max: 999,999,99 XOF (~1,500€)
- Rate limit Stripe: 100 req/s

---

## 📈 Métriques

### Dashboard Stripe
- Montant traité
- Nombre de transactions
- Taux de réussite/échec
- Remboursements

### Dashboard ADE MARKET
```sql
-- Revenue total
SELECT SUM(total) FROM commandes WHERE statut = 'payée';

-- Commandes par jour
SELECT DATE(created_at), COUNT(*) FROM commandes GROUP BY DATE(created_at);

-- Montant moyen
SELECT AVG(total) FROM commandes WHERE statut = 'payée';
```

---

## 🔄 Prochaines étapes

1. **Invoices** - Générer factures PDF automatiquement
2. **Refunds** - Interface admin pour remboursements
3. **Analytics** - Dashboard paiements
4. **3D Secure** - Authentification renforcée
5. **Paiement local** - Mobile Money, virement bancaire

---

## 📞 Support Stripe

- Documentation: https://stripe.com/docs
- Dashboard: https://dashboard.stripe.com
- Support: https://support.stripe.com


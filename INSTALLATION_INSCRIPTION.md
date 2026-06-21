# 📝 Guide d'Installation - Système d'Inscription

## 📋 Vue d'ensemble

Le système d'inscription amélioré inclut :
- ✅ Validation stricte de tous les champs
- ✅ Vérification d'email par token
- ✅ Logging de toutes les actions
- ✅ Gestion sécurisée des mots de passe
- ✅ Prévention du XSS et CSRF
- ✅ Renvoi d'email de confirmation

---

## 🚀 Installation étape par étape

### 1️⃣ Exécutez la migration BD

Le script ajoute les colonnes nécessaires à la table `utilisateurs`.

**Option A: Via phpMyAdmin**
1. Ouvrez phpMyAdmin
2. Sélectionnez la BD `ade_market`
3. Allez dans "SQL"
4. Copiez le contenu de `migrations/001_add_email_verification.sql`
5. Exécutez

**Option B: Via CLI**
```bash
cd C:\xampp\htdocs\ade_market
mysql -u root -p ade_market < migrations/001_add_email_verification.sql
# Laissez le mot de passe vide (appuyez sur Entrée)
```

**Option C: Via PHP**
```php
<?php
require 'config/database.php';
$sql = file_get_contents('migrations/001_add_email_verification.sql');
$pdo->exec($sql);
echo "Migration exécutée!";
?>
```

### 2️⃣ Configurez les variables d'environnement

Créez ou modifiez le fichier `.env` à la racine :

```env
# Base de données
DB_HOST=localhost
DB_NAME=ade_market
DB_USER=root
DB_PASS=

# Email (SMTP)
SMTP_HOST=smtp.gmail.com
SMTP_USER=votre-email@gmail.com
SMTP_PASS=votre-mot-de-passe-app
SMTP_ENCRYPTION=tls
SMTP_PORT=587
SMTP_FROM=noreply@ademarket.bj
SMTP_FROM_NAME=ADE MARKET

# Admin
ADMIN_EMAIL=admin@ademarket.bj
```

**Pour Gmail :**
1. Allez sur myaccount.google.com/apppasswords
2. Générez un mot de passe d'application
3. Utilisez ce mot de passe dans `SMTP_PASS`

### 3️⃣ Vérifiez les permissions

```bash
# Dossier logs doit être writable
chmod 755 logs/

# Dossier migrations
chmod 755 migrations/
```

### 4️⃣ Testez l'installation

```bash
# Exécutez le script de test
php test_inscription.php
```

Vous devriez voir ✅ pour tous les tests.

---

## 🔄 Flux d'inscription

### 1. Utilisateur visite `/client/inscription.php`
```
┌─────────────────────────────────────┐
│ Formulaire d'inscription             │
│ - Nom                               │
│ - Email                             │
│ - Téléphone                         │
│ - Adresse                           │
│ - Mot de passe (8+ chars)           │
│ - Confirmer mot de passe            │
│ - Accepter conditions               │
└─────────────────────────────────────┘
```

### 2. Validation stricte
```
Validateur::nom()           ✅
Validateur::email()         ✅
Validateur::telephone()     ✅
Validateur::adresse()       ✅
Validateur::motDePasse()    ✅
```

### 3. Si valide
```
✅ Génère token_verification
✅ Hash le mot de passe
✅ Insère l'utilisateur (email_verifi = 0)
✅ Envoie email de confirmation
✅ Affiche message succès
```

### 4. Utilisateur reçoit email
```
Sujet: Confirmez votre email - ADE MARKET

Lien: https://site.com/client/verifier_email.php?token=xxx...

Expire dans: 24 heures
```

### 5. Utilisateur clique sur le lien
```
vérifier_email.php reçoit le token
    ↓
Cherche utilisateur avec ce token
    ↓
Si trouvé: email_verifi = 1, token = NULL
    ↓
Affiche succès + redirection connexion
```

### 6. Utilisateur peut maintenant se connecter
```
/client/connexion.php
    ↓
Vérifie email_verifi = 1
    ↓
✅ Connexion réussie
```

---

## 📧 Renvoi d'email

Si l'utilisateur ne reçoit pas l'email :

1. Allez sur `/client/renvoyer_email_confirmation.php`
2. Entrez l'email
3. Un nouveau token est généré et envoyé

---

## 🐛 Troubleshooting

### ❌ "Colonnes manquantes"
```
Solution: Exécutez la migration BD
php migrations/001_add_email_verification.sql
```

### ❌ "Email non envoyé"
```
Solution: Vérifiez les variables SMTP dans .env
- SMTP_HOST correct?
- SMTP_USER/PASS correct?
- Les logs/ contiennent l'erreur
```

### ❌ "Token invalide"
```
Solution: Le token a expiré (24h)
- Utilisateur: allez sur renvoyer_email_confirmation.php
- Ou: régénérez manuellement en BD
  UPDATE utilisateurs SET token_verification = NULL WHERE email = 'user@email.com';
```

### ❌ "Erreur de validation"
```
Solution: Les champs ne répondent pas aux critères:
- Nom: 2-100 caractères
- Email: format valide
- Téléphone: 8-20 caractères
- Adresse: 5-255 caractères
- Mot de passe: 8+ caractères
```

---

## 📊 Monitoring

### Vérifiez les logs
```bash
# Les fichiers de log sont créés automatiquement dans /logs/
- error_YYYY-MM-DD.log    (erreurs)
- action_YYYY-MM-DD.log   (inscriptions, tentatives)
- warning_YYYY-MM-DD.log  (avertissements)
```

### Vérifiez les utilisateurs non confirmés
```sql
SELECT * FROM utilisateurs WHERE email_verifi = 0;
```

### Envoyez manuellement l'email
```php
<?php
require 'config/email.php';
$message = "Lien: https://...";
envoyerEmail('user@email.com', 'Sujet', $message);
?>
```

---

## 🔐 Sécurité

Mesures implémentées :

✅ **Validation stricte** - Tous les inputs validés  
✅ **Hash MD5** - Mots de passe hashés avec password_hash()  
✅ **Protection CSRF** - Tokens vérifié sur POST  
✅ **Sanitization** - Prévention XSS avec echapper()  
✅ **Email unique** - Pas de doublons  
✅ **Logging** - Toutes les actions tracées  
✅ **Email vérification** - Confirmation requise  
✅ **Gestion d'erreurs** - Pas d'infos sensibles  

---

## 📱 Pages créées

| Page | URL | Description |
|------|-----|-------------|
| Inscription | `/client/inscription.php` | Formulaire d'inscription |
| Vérification | `/client/verifier_email.php` | Confirmation email |
| Renvoi email | `/client/renvoyer_email_confirmation.php` | Renvoi token |
| Connexion | `/client/connexion.php` | Connexion (amélioriée) |

---

## 💾 Fichiers modifiés/créés

```
config/
  ├── validation.php          (NEW - Validateur classe)
  ├── logger.php              (NEW - Logging système)
  ├── error_handler.php       (NEW - Gestion erreurs)
  └── email.php               (EXISTING - amélioration)

client/
  ├── inscription.php         (UPDATE - validation stricte)
  ├── connexion.php           (UPDATE - lien email)
  ├── verifier_email.php      (NEW)
  └── renvoyer_email_confirmation.php  (NEW)

migrations/
  └── 001_add_email_verification.sql   (NEW)

logs/                         (NEW - auto-créé)
  ├── error_YYYY-MM-DD.log
  ├── action_YYYY-MM-DD.log
  └── warning_YYYY-MM-DD.log

tests/
  └── test_inscription.php    (NEW)
```

---

## ✅ Checklist de déploiement

- [ ] Migration BD exécutée
- [ ] Variables .env configurées
- [ ] SMTP testé et fonctionnel
- [ ] Dossier /logs/ writable
- [ ] Script test_inscription.php en vert ✅
- [ ] Inscription testée
- [ ] Email de confirmation reçu
- [ ] Vérification d'email fonctionne
- [ ] Connexion possible après vérification
- [ ] Logs créés correctement

---

## 📞 Support

Erreur non couverte? Vérifiez:
1. Les logs dans `/logs/`
2. La console de l'erreur (F12)
3. Les erreurs MySQL dans phpMyAdmin


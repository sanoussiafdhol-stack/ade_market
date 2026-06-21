# 🔐 Système de Sécurité - Documentation

## Fichiers ajoutés

### 1. `config/validation.php` - Validation & Sanitization
**Classe centralisée pour valider tous les inputs utilisateurs**

**Utilisation :**
```php
require_once '../config/validation.php';

// Validation email
$email = Validateur::email($_POST['email']);  // Retourne email validé ou false

// Validation mot de passe
if (!Validateur::motDePasse($_POST['mdp'], $min = 8)) {
    // Récupère les erreurs
    $erreurs = Validateur::getErreurs();
}

// Autres validateurs disponibles :
Validateur::nom($nom, $min, $max);           // Valide un nom
Validateur::chaine($texte, $min, $max);      // Texte générique
Validateur::entier($nombre, $min, $max);     // Nombre entier
Validateur::decimal($nombre, $min, $max);    // Nombre décimal
Validateur::url($url);                        // URL valide
Validateur::date($date);                      // Format YYYY-MM-DD
Validateur::fichier($fichier, $extensions);  // Upload fichier

// Nettoyage des données
$texte_propre = Validateur::echapper($texte);     // Prévient XSS
$texte_clean = Validateur::nettoyer($texte);     // Supprime slashes
$slug = Validateur::slug($titre);                 // URL-friendly
```

### 2. `config/logger.php` - Système de Logging
**Enregistre les erreurs et actions importantes**

**Utilisation :**
```php
require_once '../config/logger.php';

Logger::action('Connexion réussie', ['email' => $email, 'role' => $role]);
Logger::avertissement('Tentative suspecte', ['ip' => $_SERVER['REMOTE_ADDR']]);
Logger::erreur('Erreur BD', ['query' => $sql]);
Logger::info('Info générale');

// Récupère les logs récents
$erreurs = Logger::getLogs('ERROR', $jours = 7);
```

**Les fichiers de log sont stockés dans :** `/logs/` (créé automatiquement)
- Format : `error_2026-06-15.log`, `action_2026-06-15.log`, etc.
- Rotation automatique : nouveau fichier si > 10MB

### 3. `config/error_handler.php` - Gestion centralisée des erreurs
**Attrape TOUS les erreurs et exceptions**

**Utilisation :**
```php
require_once '../config/error_handler.php';
// Active automatiquement au chargement
```

**Bénéfices :**
- Pages d'erreur personnalisées (404, 500, etc.)
- Logs automatiques des erreurs
- Infos sensibles cachées en production
- Trace complète pour debug

## 4. Page `inscription.php` - Améliorations

**Changements :**
✅ Validation stricte nom, email, téléphone, adresse  
✅ Confirmation mot de passe obligatoire  
✅ Mot de passe minimum 8 caractères  
✅ Acceptation conditions obligatoire  
✅ Vérification email avant de pouvoir se connecter  
✅ Token de vérification généré et envoyé par email  
✅ Messages d'erreur clairs par champ  
✅ Logging de toutes les nouvelles inscriptions  
✅ Gestion des exceptions et erreurs DB  

## 5. Page `verifier_email.php` - Vérification d'email
**Nouvelle page** pour confirmer l'email via token

- Marque l'email comme vérifié dans la BD
- Affiche succès/erreur
- Redirige vers connexion ou accueil

## 6. Page `renvoyer_email_confirmation.php` - Renvoi d'email
**Nouvelle page** pour renvoyer l'email de confirmation

- Validation email
- Génère un nouveau token
- Renvoie l'email
- Messages de confirmation

---

## 🗄️ Migration BD

**Exécutez le script migration :**
```bash
# À partir de phpMyAdmin ou CLI:
# Fichier: migrations/001_add_email_verification.sql
```

**Colonnes ajoutées :**
- `email_verifi` (BOOLEAN) - Email confirmé
- `token_verification` (VARCHAR 64) - Token de confirmation
- `created_at` (TIMESTAMP) - Date création compte
- `token_reset_mdp` (VARCHAR 64) - Token réinitialisation
- `reset_mdp_expires` (TIMESTAMP) - Expiration réinitialisation

---

## ✅ Checklist de configuration

1. **[ ] Colonnes BD ajoutées**
   - Exécutez : `migrations/001_add_email_verification.sql`
   - Commande : `mysql -u root -p ade_market < migrations/001_add_email_verification.sql`

2. **[ ] Variables d'environnement (.env)**
   ```
   SMTP_HOST=smtp.gmail.com
   SMTP_USER=votre-email@gmail.com
   SMTP_PASS=votre-mot-de-passe-app
   SMTP_ENCRYPTION=tls
   SMTP_PORT=587
   SMTP_FROM=noreply@ademarket.bj
   SMTP_FROM_NAME=ADE MARKET
   ADMIN_EMAIL=admin@ademarket.bj
   ```

3. **[ ] Fichier .env créé avec valeurs correctes**
   - Voir `config/load_env.php` pour charger ces variables

4. **[ ] Dossier /logs/ writable**
   - Permissions : 755
   - Commande : `chmod 755 logs/`

5. **[ ] Test d'email**
   - Allez sur `/client/inscription.php`
   - Créez un compte
   - Vérifiez que l'email est envoyé

6. **[ ] Test de logging**
   - Allez dans `/logs/`
   - Vérifiez les fichiers `*.log`

Pour compléter la sécurité :

1. **Ajouter ces validateurs aux autres pages** (inscription, panier, etc.)
   ```php
   require_once '../config/validation.php';
   ```

2. **Mettre à jour le `.env` avec les constantes manquantes :**
   ```
   ADMIN_EMAIL=admin@ademarket.bj
   ```

3. **S'assurer que la BD a la colonne `email_verifi`** 
   - Si absent : `ALTER TABLE utilisateurs ADD COLUMN email_verifi BOOLEAN DEFAULT 0;`

4. **Tester le logging :** 
   - Allez sur `/logs/` et vérifiez que les fichiers se créent

5. **Ajouter validation à l'inscription :**
   ```php
   $email = Validateur::email($_POST['email']);
   $nom = Validateur::nom($_POST['nom']);
   $mdp = Validateur::motDePasse($_POST['mdp']);
   ```

---

## 📝 Intégration rapide dans d'autres fichiers

**En tête de tout fichier nécessitant validation :**
```php
require_once '../config/validation.php';
require_once '../config/logger.php';
require_once '../config/error_handler.php';
```

**Puis utiliser :**
```php
// Validation
$email = valider('email', $_POST['email']);

// Logging
Logger::action('Action utilisateur', ['user_id' => $_SESSION['utilisateur_id']]);

// Nettoyage pour affichage
echo echapper($_GET['search']);
```

---

## 🔍 Bonnes pratiques

✅ **TOUJOURS valider les inputs utilisateurs**  
✅ **TOUJOURS loger les actions sensibles**  
✅ **TOUJOURS échapper les outputs** (sauf dans formulaires value="")  
✅ **TOUJOURS utiliser prepared statements** (déjà fait avec PDO)  
✅ **TOUJOURS régénérer session après connexion**

❌ **NE PAS** afficher d'infos sensibles (chemins, BD details) aux utilisateurs  
❌ **NE PAS** faire confiance aux données du client  
❌ **NE PAS** mettre les logs en web root (protéger `/logs/`)

---

## 🛡️ Prochaines améliorations critiques

1. **Rate limiting** - Limiter les requêtes par IP
2. **2FA** - Double authentification (SMS, authenticator app)
3. **Encryption** - Chiffrer les données sensibles
4. **CORS** - Si API REST
5. **Tests unitaires** - PHPUnit


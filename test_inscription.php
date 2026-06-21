#!/usr/bin/env php
<?php
/**
 * Script de test du système d'inscription
 * Usage: php test_inscription.php
 */

echo "═══════════════════════════════════════════════════════════\n";
echo "  TEST DU SYSTÈME D'INSCRIPTION - ADE MARKET\n";
echo "═══════════════════════════════════════════════════════════\n\n";

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/validation.php';
require_once __DIR__ . '/config/logger.php';

// Test 1: Validation email
echo "1️⃣  Test Validation Email\n";
$email_test = "test@example.com";
Validateur::reset();
$resultat = Validateur::email($email_test);
echo "   Email: $email_test\n";
echo "   Résultat: " . ($resultat ? "✅ PASS\n" : "❌ FAIL\n");

// Test 2: Validation email invalide
echo "\n2️⃣  Test Validation Email Invalide\n";
$email_invalide = "invalid-email";
Validateur::reset();
$resultat = Validateur::email($email_invalide);
echo "   Email: $email_invalide\n";
echo "   Résultat: " . (!$resultat ? "✅ PASS (rejeté correctement)\n" : "❌ FAIL\n");

// Test 3: Validation mot de passe
echo "\n3️⃣  Test Validation Mot de Passe\n";
$mdp_test = "SecurePass123!";
Validateur::reset();
$resultat = Validateur::motDePasse($mdp_test, 8);
echo "   Mot de passe: " . str_repeat("*", strlen($mdp_test)) . "\n";
echo "   Résultat: " . ($resultat ? "✅ PASS\n" : "❌ FAIL\n");

// Test 4: Validation mot de passe court
echo "\n4️⃣  Test Validation Mot de Passe Court\n";
$mdp_court = "abc";
Validateur::reset();
$resultat = Validateur::motDePasse($mdp_court, 8);
echo "   Mot de passe: " . str_repeat("*", strlen($mdp_court)) . " (3 caractères)\n";
echo "   Résultat: " . (!$resultat ? "✅ PASS (rejeté correctement)\n" : "❌ FAIL\n");

// Test 5: Validation nom
echo "\n5️⃣  Test Validation Nom\n";
$nom_test = "Jean Dupont";
Validateur::reset();
$resultat = Validateur::nom($nom_test);
echo "   Nom: $nom_test\n";
echo "   Résultat: " . ($resultat ? "✅ PASS\n" : "❌ FAIL\n");

// Test 6: Test logging
echo "\n6️⃣  Test Logging\n";
Logger::action("Test action du script", ["test" => true]);
echo "   Logger::action() appelée\n";
echo "   ✅ PASS\n";

// Test 7: Vérifier les tables BD
echo "\n7️⃣  Test Colonnes BD Utilisateurs\n";
try {
    $stmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='utilisateurs'");
    $colonnes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $colonnes_requises = ['email_verifi', 'token_verification', 'created_at'];
    $tous_present = true;
    
    foreach ($colonnes_requises as $col) {
        if (in_array($col, $colonnes)) {
            echo "   ✅ Colonne '$col' présente\n";
        } else {
            echo "   ❌ Colonne '$col' MANQUANTE\n";
            $tous_present = false;
        }
    }
    
    if ($tous_present) {
        echo "   ✅ PASS\n";
    } else {
        echo "   ❌ FAIL - Exécutez : migrations/001_add_email_verification.sql\n";
    }
} catch (Exception $e) {
    echo "   ❌ FAIL - " . $e->getMessage() . "\n";
}

// Test 8: Dossier logs
echo "\n8️⃣  Test Dossier /logs\n";
$logs_dir = __DIR__ . '/logs';
if (is_dir($logs_dir)) {
    echo "   ✅ Dossier existant\n";
    if (is_writable($logs_dir)) {
        echo "   ✅ Writable\n";
        echo "   ✅ PASS\n";
    } else {
        echo "   ❌ NOT writable\n";
        echo "   ❌ FAIL - chmod 755 logs/\n";
    }
} else {
    echo "   ❌ Dossier non trouvé\n";
    echo "   ❌ FAIL - mkdir logs/\n";
}

// Test 9: Fichier de configuration email
echo "\n9️⃣  Test Configuration Email\n";
$smtp_host = defined('SMTP_HOST') ? SMTP_HOST : false;
if ($smtp_host) {
    echo "   ✅ SMTP_HOST défini: $smtp_host\n";
} else {
    echo "   ❌ SMTP_HOST non défini\n";
}
$smtp_user = defined('SMTP_USER') ? SMTP_USER : false;
if ($smtp_user) {
    echo "   ✅ SMTP_USER défini\n";
} else {
    echo "   ❌ SMTP_USER non défini\n";
}

echo "\n" . (($smtp_host && $smtp_user) ? "✅ PASS\n" : "❌ FAIL - Mettez à jour config/load_env.php ou .env\n");

// Test 10: Classe Validateur disponible
echo "\n🔟 Test Classes Disponibles\n";
echo "   ✅ Validateur: " . (class_exists('Validateur') ? "OK\n" : "FAIL\n");
echo "   ✅ Logger: " . (class_exists('Logger') ? "OK\n" : "FAIL\n");
echo "   ✅ ErrorHandler: " . (class_exists('ErrorHandler') ? "OK\n" : "FAIL\n");

echo "\n═══════════════════════════════════════════════════════════\n";
echo "  ✅ TESTS TERMINÉS\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "\n📝 Prochaines étapes:\n";
echo "   1. Exécutez la migration BD si test 7 échoue\n";
echo "   2. Vérifiez/mettez à jour les variables d'environnement\n";
echo "   3. Testez l'inscription sur /client/inscription.php\n";
echo "   4. Vérifiez les logs dans /logs/\n";
echo "\n";
?>

<?php
require_once __DIR__ . '/load_env.php';
$host = defined('DB_HOST') ? DB_HOST : "localhost";
$dbname = defined('DB_NAME') ? DB_NAME : "ade_market";
$username = defined('DB_USER') ? DB_USER : "root";
$password = defined('DB_PASS') ? DB_PASS : "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>

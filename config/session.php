<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function estConnecte() {
    return isset($_SESSION['utilisateur_id']);
}

function estAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function redirigerSiNonConnecte() {
    if (!estConnecte()) {
        header("Location: connexion.php");
        exit();
    }
}

function redirigerSiNonAdmin() {
    if (!estAdmin()) {
        header("Location: ../client/index.php");
        exit();
    }
}

function regenererSession() {
    session_regenerate_id(true);
}

function genererTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifierTokenCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

<?php
/**
 * Classe de validation et sanitization des données
 */
class Validateur {
    
    private static $erreurs = [];

    /**
     * Réinitialise les erreurs
     */
    public static function reset() {
        self::$erreurs = [];
    }

    /**
     * Récupère toutes les erreurs
     */
    public static function getErreurs() {
        return self::$erreurs;
    }

    /**
     * Ajoute une erreur
     */
    public static function addErreur($champ, $message) {
        self::$erreurs[$champ] = $message;
    }

    /**
     * Valide un email
     */
    public static function email($email) {
        $email = trim($email);
        if (empty($email)) {
            self::addErreur('email', 'L\'email est requis');
            return false;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::addErreur('email', 'L\'email n\'est pas valide');
            return false;
        }
        if (strlen($email) > 255) {
            self::addErreur('email', 'L\'email est trop long');
            return false;
        }
        return $email;
    }

    /**
     * Valide un mot de passe
     */
    public static function motDePasse($mdp, $min = 8, $champ = 'mot_de_passe') {
        if (empty($mdp)) {
            self::addErreur($champ, 'Le mot de passe est requis');
            return false;
        }
        if (strlen($mdp) < $min) {
            self::addErreur($champ, "Le mot de passe doit contenir au moins $min caractères");
            return false;
        }
        if (strlen($mdp) > 255) {
            self::addErreur($champ, 'Le mot de passe est trop long');
            return false;
        }
        return true;
    }

    /**
     * Valide un nom
     */
    public static function nom($nom, $min = 2, $max = 100) {
        $nom = trim($nom);
        if (empty($nom)) {
            self::addErreur('nom', 'Le nom est requis');
            return false;
        }
        if (strlen($nom) < $min) {
            self::addErreur('nom', "Le nom doit contenir au moins $min caractères");
            return false;
        }
        if (strlen($nom) > $max) {
            self::addErreur('nom', "Le nom ne doit pas dépasser $max caractères");
            return false;
        }
        // Évite les caractères suspects
        if (!preg_match('/^[\p{L}\p{N}\s\-\.\']+$/u', $nom)) {
            self::addErreur('nom', 'Le nom contient des caractères non autorisés');
            return false;
        }
        return $nom;
    }

    /**
     * Valide une chaîne générique
     */
    public static function chaine($valeur, $min = 1, $max = 255, $champ = 'champ') {
        $valeur = trim($valeur);
        if (empty($valeur) && $min > 0) {
            self::addErreur($champ, "Le champ $champ est requis");
            return false;
        }
        if (strlen($valeur) < $min) {
            self::addErreur($champ, "Le champ doit contenir au moins $min caractères");
            return false;
        }
        if (strlen($valeur) > $max) {
            self::addErreur($champ, "Le champ ne doit pas dépasser $max caractères");
            return false;
        }
        return $valeur;
    }

    /**
     * Valide un nombre entier
     */
    public static function entier($valeur, $min = null, $max = null, $champ = 'nombre') {
        if (empty($valeur) && $valeur !== 0 && $valeur !== '0') {
            self::addErreur($champ, "Le champ $champ est requis");
            return false;
        }
        if (!is_numeric($valeur) || (int)$valeur != $valeur) {
            self::addErreur($champ, "Le champ $champ doit être un nombre entier");
            return false;
        }
        $entier = (int)$valeur;
        if ($min !== null && $entier < $min) {
            self::addErreur($champ, "Le champ doit être au minimum $min");
            return false;
        }
        if ($max !== null && $entier > $max) {
            self::addErreur($champ, "Le champ ne doit pas dépasser $max");
            return false;
        }
        return $entier;
    }

    /**
     * Valide un nombre décimal
     */
    public static function decimal($valeur, $min = null, $max = null, $champ = 'montant') {
        if (empty($valeur) && $valeur !== 0 && $valeur !== '0') {
            self::addErreur($champ, "Le champ $champ est requis");
            return false;
        }
        if (!is_numeric($valeur)) {
            self::addErreur($champ, "Le champ $champ doit être un nombre");
            return false;
        }
        $decimal = (float)$valeur;
        if ($min !== null && $decimal < $min) {
            self::addErreur($champ, "Le champ doit être au minimum $min");
            return false;
        }
        if ($max !== null && $decimal > $max) {
            self::addErreur($champ, "Le champ ne doit pas dépasser $max");
            return false;
        }
        return $decimal;
    }

    /**
     * Valide un URL
     */
    public static function url($url) {
        $url = trim($url);
        if (empty($url)) {
            self::addErreur('url', 'L\'URL est requise');
            return false;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            self::addErreur('url', 'L\'URL n\'est pas valide');
            return false;
        }
        return $url;
    }

    /**
     * Valide une date au format YYYY-MM-DD
     */
    public static function date($date, $champ = 'date') {
        $date = trim($date);
        if (empty($date)) {
            self::addErreur($champ, "La date est requise");
            return false;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            self::addErreur($champ, "La date doit être au format YYYY-MM-DD");
            return false;
        }
        // Vérifie si c'est une date valide
        if (!checkdate(substr($date, 5, 2), substr($date, 8, 2), substr($date, 0, 4))) {
            self::addErreur($champ, "La date n'est pas valide");
            return false;
        }
        return $date;
    }

    /**
     * Valide un fichier upload
     */
    public static function fichier($fichier, $extensions_autorisees = ['jpg', 'jpeg', 'png', 'gif'], $taille_max = 5242880) {
        if (!isset($fichier['name']) || empty($fichier['name'])) {
            self::addErreur('fichier', 'Le fichier est requis');
            return false;
        }
        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            $messages_erreur = [
                UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale',
                UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale',
                UPLOAD_ERR_PARTIAL => 'Le fichier n\'a pas été complètement uploadé',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé',
                UPLOAD_ERR_NO_TMP_DIR => 'Erreur serveur',
                UPLOAD_ERR_CANT_WRITE => 'Erreur serveur',
            ];
            self::addErreur('fichier', $messages_erreur[$fichier['error']] ?? 'Erreur lors de l\'upload');
            return false;
        }
        if ($fichier['size'] > $taille_max) {
            self::addErreur('fichier', 'Le fichier dépasse ' . ($taille_max / 1024 / 1024) . ' MB');
            return false;
        }
        $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $extensions_autorisees)) {
            self::addErreur('fichier', 'L\'extension ' . $extension . ' n\'est pas autorisée');
            return false;
        }
        return [
            'name' => $fichier['name'],
            'extension' => $extension,
            'tmp_name' => $fichier['tmp_name'],
            'size' => $fichier['size']
        ];
    }

    /**
     * Nettoie une chaîne pour l'affichage (prévient XSS)
     */
    public static function echapper($texte) {
        return htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Nettoie une chaîne de texte brut
     */
    public static function nettoyer($texte) {
        $texte = trim($texte);
        $texte = stripslashes($texte);
        $texte = htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');
        return $texte;
    }

    /**
     * Génère un slug (URL-friendly) à partir d'une chaîne
     */
    public static function slug($texte) {
        $texte = strtolower(trim($texte));
        $texte = preg_replace('/[^a-z0-9-]/', '-', $texte);
        $texte = preg_replace('/-+/', '-', $texte);
        $texte = trim($texte, '-');
        return $texte;
    }
}

// Alias court pour plus de facilité
function valider($type, ...$args) {
    return Validateur::$type(...$args);
}

function echapper($texte) {
    return Validateur::echapper($texte);
}

function nettoyer($texte) {
    return Validateur::nettoyer($texte);
}
?>

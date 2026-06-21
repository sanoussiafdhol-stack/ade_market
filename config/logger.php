<?php
/**
 * Classe de logging pour tracer les erreurs et actions
 */
class Logger {
    
    private static $log_dir = __DIR__ . '/../logs';
    private static $max_file_size = 10485760; // 10MB

    public static function init() {
        if (!is_dir(self::$log_dir)) {
            mkdir(self::$log_dir, 0755, true);
        }
    }

    /**
     * Log une erreur
     */
    public static function erreur($message, $context = []) {
        self::log('ERROR', $message, $context);
    }

    /**
     * Log un avertissement
     */
    public static function avertissement($message, $context = []) {
        self::log('WARNING', $message, $context);
    }

    /**
     * Log une action
     */
    public static function action($message, $context = []) {
        self::log('ACTION', $message, $context);
    }

    /**
     * Log une information
     */
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }

    /**
     * Fonction centrale de logging
     */
    private static function log($niveau, $message, $context = []) {
        self::init();
        
        $date = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $user_id = $_SESSION['utilisateur_id'] ?? 'anonymous';
        
        $contexte_str = !empty($context) ? ' | ' . json_encode($context) : '';
        $ligne = "[$date] [$niveau] [IP: $ip] [User: $user_id] $message$contexte_str\n";
        
        $fichier = self::$log_dir . '/' . strtolower($niveau) . '_' . date('Y-m-d') . '.log';
        
        // Vérifie la taille du fichier
        if (file_exists($fichier) && filesize($fichier) > self::$max_file_size) {
            rename($fichier, $fichier . '.' . time());
        }
        
        file_put_contents($fichier, $ligne, FILE_APPEND | LOCK_EX);
    }

    /**
     * Récupère les logs récents
     */
    public static function getLogs($type = 'ERROR', $jours = 7) {
        self::init();
        
        $logs = [];
        $depuis = time() - ($jours * 86400);
        
        foreach (glob(self::$log_dir . '/' . strtolower($type) . '_*.log') as $fichier) {
            if (filemtime($fichier) > $depuis) {
                $contenu = file_get_contents($fichier);
                $logs = array_merge($logs, explode("\n", $contenu));
            }
        }
        
        return array_filter($logs); // Enlève les lignes vides
    }
}

// Initialise le logger au chargement
Logger::init();
?>

<?php
/**
 * Gestionnaire centralisé des erreurs
 */
class ErrorHandler {
    
    public static function init() {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Gère les erreurs PHP
     */
    public static function handleError($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $type_erreur = self::getTypeErreur($errno);
        Logger::erreur("$type_erreur: $errstr", [
            'fichier' => $errfile,
            'ligne' => $errline
        ]);

        if (ini_get('display_errors')) {
            echo "<pre>$type_erreur: $errstr in $errfile on line $errline</pre>";
        }

        return false; // Permet à PHP de gérer l'erreur aussi
    }

    /**
     * Gère les exceptions
     */
    public static function handleException(Throwable $e) {
        Logger::erreur($e->getMessage(), [
            'fichier' => $e->getFile(),
            'ligne' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        http_response_code(500);
        self::afficherErreur(500, 'Une erreur est survenue');
        exit;
    }

    /**
     * Gère les erreurs fatales
     */
    public static function handleShutdown() {
        $erreur = error_get_last();
        if ($erreur !== null && ($erreur['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
            Logger::erreur('FATAL ERROR: ' . $erreur['message'], [
                'fichier' => $erreur['file'],
                'ligne' => $erreur['line']
            ]);
            http_response_code(500);
            self::afficherErreur(500, 'Erreur système');
            exit;
        }
    }

    /**
     * Affiche une page d'erreur personnalisée
     */
    public static function afficherErreur($code, $message = '') {
        $messages = [
            400 => 'Mauvaise requête',
            403 => 'Accès refusé',
            404 => 'Page non trouvée',
            500 => 'Erreur serveur',
        ];

        $titre = $messages[$code] ?? 'Erreur';
        $message = $message ?: $titre;
        $description = [
            400 => 'La requête envoyée n\'est pas valide.',
            403 => 'Vous n\'avez pas accès à cette ressource.',
            404 => 'La page que vous recherchez n\'existe pas.',
            500 => 'Une erreur interne s\'est produite. Veuillez réessayer plus tard.',
        ];
        $desc = $description[$code] ?? 'Une erreur est survenue.';

        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo $code; ?> - <?php echo echapper($titre); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
                    background: linear-gradient(135deg, #0a0f1e 0%, #1a1f2e 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 2rem;
                }

                .container {
                    text-align: center;
                    max-width: 500px;
                    background: rgba(255, 255, 255, 0.05);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 20px;
                    padding: 3rem 2rem;
                    animation: slideIn 0.5s ease-out;
                }

                @keyframes slideIn {
                    from {
                        opacity: 0;
                        transform: translateY(-20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .code {
                    font-size: 5rem;
                    font-weight: 800;
                    background: linear-gradient(135deg, #00c853, #00953d);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                    margin-bottom: 1rem;
                    font-family: 'Syne', sans-serif;
                }

                .titre {
                    font-size: 1.75rem;
                    color: #ffffff;
                    margin-bottom: 0.5rem;
                    font-weight: 600;
                }

                .description {
                    color: #b8bcc8;
                    margin-bottom: 2rem;
                    line-height: 1.6;
                }

                .boutons {
                    display: flex;
                    gap: 1rem;
                    justify-content: center;
                    flex-wrap: wrap;
                }

                .btn {
                    padding: 0.75rem 1.5rem;
                    border-radius: 10px;
                    text-decoration: none;
                    font-weight: 500;
                    transition: all 0.3s ease;
                    cursor: pointer;
                    border: none;
                    font-size: 1rem;
                }

                .btn-primary {
                    background: linear-gradient(135deg, #00c853, #00953d);
                    color: white;
                }

                .btn-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 20px rgba(0, 200, 83, 0.3);
                }

                .btn-secondary {
                    background: transparent;
                    color: #00c853;
                    border: 2px solid #00c853;
                }

                .btn-secondary:hover {
                    background: rgba(0, 200, 83, 0.1);
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="code"><?php echo $code; ?></div>
                <div class="titre"><?php echo echapper($titre); ?></div>
                <div class="description"><?php echo echapper($desc); ?></div>
                <div class="boutons">
                    <a href="/" class="btn btn-primary">Accueil</a>
                    <button onclick="history.back()" class="btn btn-secondary">Retour</button>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Retourne le type d'erreur en texte
     */
    private static function getTypeErreur($errno) {
        $types = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED',
        ];

        return $types[$errno] ?? 'UNKNOWN';
    }
}

// Initialise au chargement
ErrorHandler::init();
?>

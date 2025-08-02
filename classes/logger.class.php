<?php
/**
 * Système de logs pour la galerie photos
 * 
 * Permet d'enregistrer les événements, erreurs et actions administratives
 * @version 1.0
 */

if (!defined('GALLERY_ACCESS')) {
    die('Accès direct interdit');
}

class Logger {
    
    const LEVEL_ERROR = 1;
    const LEVEL_WARNING = 2;
    const LEVEL_INFO = 3;
    const LEVEL_DEBUG = 4;
    
    private static $instance = null;
    private $logFile;
    private $logLevel;
    
    /**
     * Singleton
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Logger();
        }
        return self::$instance;
    }
    
    /**
     * Constructeur privé
     */
    private function __construct() {
        // Créer le dossier de logs s'il n'existe pas
        $logsDir = defined('LOGS_DIR') ? LOGS_DIR : 'logs/';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        
        $this->logFile = $logsDir . 'gallery_' . date('Y-m') . '.log';
        $this->logLevel = $this->getLevelFromString(defined('LOG_LEVEL') ? LOG_LEVEL : 'INFO');
        
        // Rotation des logs si nécessaire
        $this->rotateLogIfNeeded();
    }
    
    /**
     * Convertir le niveau de log en entier
     */
    private function getLevelFromString($level) {
        switch (strtoupper($level)) {
            case 'ERROR': return self::LEVEL_ERROR;
            case 'WARNING': return self::LEVEL_WARNING;
            case 'INFO': return self::LEVEL_INFO;
            case 'DEBUG': return self::LEVEL_DEBUG;
            default: return self::LEVEL_INFO;
        }
    }
    
    /**
     * Convertir le niveau entier en string
     */
    private function getLevelString($level) {
        switch ($level) {
            case self::LEVEL_ERROR: return 'ERROR';
            case self::LEVEL_WARNING: return 'WARNING';
            case self::LEVEL_INFO: return 'INFO';
            case self::LEVEL_DEBUG: return 'DEBUG';
            default: return 'INFO';
        }
    }
    
    /**
     * Rotation des logs si trop volumineux
     */
    private function rotateLogIfNeeded() {
        if (!file_exists($this->logFile)) {
            return;
        }
        
        $maxSize = (defined('MAX_LOG_SIZE') ? MAX_LOG_SIZE : 10) * 1024 * 1024; // Convertir Mo en octets
        
        if (filesize($this->logFile) > $maxSize) {
            $backupFile = $this->logFile . '.old';
            
            // Supprimer l'ancien backup s'il existe
            if (file_exists($backupFile)) {
                unlink($backupFile);
            }
            
            // Renommer le fichier actuel
            rename($this->logFile, $backupFile);
        }
    }
    
    /**
     * Écrire un message de log
     */
    private function writeLog($level, $message, $context = []) {
        if (!(defined('LOGS_ENABLED') ? LOGS_ENABLED : true) || $level > $this->logLevel) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $levelStr = $this->getLevelString($level);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 100);
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        // Formatage du message
        $logEntry = sprintf(
            "[%s] %s - IP: %s - URI: %s - %s",
            $timestamp,
            $levelStr,
            $ip,
            $requestUri,
            $message
        );
        
        // Ajouter le contexte si présent
        if (!empty($context)) {
            $logEntry .= ' - Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        $logEntry .= PHP_EOL;
        
        // Écrire dans le fichier
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // En mode debug, aussi afficher dans la console PHP
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log($logEntry);
        }
    }
    
    /**
     * Log d'erreur
     */
    public function error($message, $context = []) {
        $this->writeLog(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Log d'avertissement
     */
    public function warning($message, $context = []) {
        $this->writeLog(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log d'information
     */
    public function info($message, $context = []) {
        $this->writeLog(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log de debug
     */
    public function debug($message, $context = []) {
        $this->writeLog(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Log d'action administrative
     */
    public function adminAction($action, $details = []) {
        $session_id = session_id() ?: 'no-session';
        $context = array_merge([
            'session_id' => $session_id,
            'action_type' => 'admin'
        ], $details);
        
        $this->info("Action admin: " . $action, $context);
    }
    
    /**
     * Log de tentative de connexion
     */
    public function loginAttempt($success, $username = 'admin') {
        $status = $success ? 'SUCCESS' : 'FAILED';
        $message = "Tentative de connexion {$status} pour l'utilisateur: {$username}";
        
        $context = [
            'username' => $username,
            'success' => $success,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        if ($success) {
            $this->info($message, $context);
        } else {
            $this->warning($message, $context);
        }
    }
    
    /**
     * Log d'accès aux fichiers
     */
    public function fileAccess($file, $action = 'read') {
        $this->debug("Accès fichier: {$action} - {$file}");
    }
    
    /**
     * Log d'erreur système
     */
    public function systemError($error, $file = null, $line = null) {
        $context = [];
        if ($file) $context['file'] = $file;
        if ($line) $context['line'] = $line;
        
        $this->error("Erreur système: " . $error, $context);
    }
    
    /**
     * Obtenir les logs récents
     */
    public function getRecentLogs($limit = 100) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES);
        
        if ($lines === false) {
            return [];
        }
        
        // Retourner les dernières lignes
        return array_slice(array_reverse($lines), 0, $limit);
    }
    
    /**
     * Obtenir les statistiques des logs
     */
    public function getLogStats() {
        if (!file_exists($this->logFile)) {
            return [
                'total_entries' => 0,
                'file_size' => 0,
                'last_entry' => null,
                'levels' => []
            ];
        }
        
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES);
        $total = count($lines);
        $fileSize = filesize($this->logFile);
        
        // Compter par niveau
        $levels = [
            'ERROR' => 0,
            'WARNING' => 0,
            'INFO' => 0,
            'DEBUG' => 0
        ];
        
        $lastEntry = null;
        
        foreach ($lines as $line) {
            if (preg_match('/\] (ERROR|WARNING|INFO|DEBUG) -/', $line, $matches)) {
                $level = $matches[1];
                if (isset($levels[$level])) {
                    $levels[$level]++;
                }
            }
            
            // Extraire la dernière entrée
            if (preg_match('/^\[([^\]]+)\]/', $line, $matches)) {
                $lastEntry = $matches[1];
            }
        }
        
        return [
            'total_entries' => $total,
            'file_size' => $fileSize,
            'file_size_formatted' => $this->formatBytes($fileSize),
            'last_entry' => $lastEntry,
            'levels' => $levels
        ];
    }
    
    /**
     * Formater la taille en octets
     */
    private function formatBytes($size) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }
    
    /**
     * Nettoyer les anciens logs
     */
    public function cleanOldLogs($olderThanDays = 30) {
        $logsDir = defined('LOGS_DIR') ? LOGS_DIR : 'logs/';
        $cutoffTime = time() - ($olderThanDays * 24 * 60 * 60);
        $deleted = 0;
        
        if (!is_dir($logsDir)) {
            return $deleted;
        }
        
        $files = glob($logsDir . '*.log*');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deleted++;
                    $this->info("Ancien fichier de log supprimé: " . basename($file));
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Vider le log actuel
     */
    public function clearCurrentLog() {
        if (file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
            $this->info("Fichier de log vidé par l'administrateur");
            return true;
        }
        return false;
    }
}

// ==========================================
// FONCTIONS GLOBALES PRATIQUES
// ==========================================

/**
 * Log rapide d'erreur
 */
function logError($message, $context = []) {
    Logger::getInstance()->error($message, $context);
}

/**
 * Log rapide d'information
 */
function logInfo($message, $context = []) {
    Logger::getInstance()->info($message, $context);
}

/**
 * Log rapide de debug
 */
function logDebug($message, $context = []) {
    Logger::getInstance()->debug($message, $context);
}

/**
 * Log d'action administrative
 */
function logAdminAction($action, $details = []) {
    Logger::getInstance()->adminAction($action, $details);
}

// ==========================================
// GESTIONNAIRE D'ERREURS GLOBAL
// ==========================================

/**
 * Gestionnaire d'erreurs personnalisé
 */
function customErrorHandler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    $logger = Logger::getInstance();
    
    switch ($severity) {
        case E_ERROR:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_USER_ERROR:
            $logger->systemError($message, $file, $line);
            break;
            
        case E_WARNING:
        case E_CORE_WARNING:
        case E_COMPILE_WARNING:
        case E_USER_WARNING:
            $logger->warning("PHP Warning: " . $message, ['file' => $file, 'line' => $line]);
            break;
            
        default:
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $logger->debug("PHP Notice: " . $message, ['file' => $file, 'line' => $line]);
            }
            break;
    }
    
    return false; // Laisser PHP gérer l'erreur normalement
}

/**
 * Gestionnaire d'exceptions non capturées
 */
function customExceptionHandler($exception) {
    $logger = Logger::getInstance();
    $logger->systemError(
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    );
    
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<pre>Exception non gérée: " . $exception->getMessage() . "\n";
        echo "Fichier: " . $exception->getFile() . " ligne " . $exception->getLine() . "</pre>";
    } else {
        echo "Une erreur inattendue s'est produite. Veuillez contacter l'administrateur.";
    }
}

// Activer les gestionnaires d'erreurs
if (defined('LOGS_ENABLED') ? LOGS_ENABLED : true) {
    set_error_handler('customErrorHandler');
    set_exception_handler('customExceptionHandler');
}

?>
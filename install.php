<?php
/**
 * Script d'installation automatique pour la galerie photos
 * 
 * Ce script vérifie les prérequis, configure l'environnement
 * et guide l'utilisateur dans la configuration initiale
 */

// Désactiver l'affichage des erreurs pendant l'installation
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration temporaire pour l'installation
define('INSTALL_MODE', true);
define('SITE_VERSION', '1.0.0');

class GalleryInstaller {
    
    private $errors = [];
    private $warnings = [];
    private $success = [];
    private $requirements = [];
    
    public function __construct() {
        $this->checkRequirements();
    }
    
    /**
     * Vérifier les prérequis système
     */
    private function checkRequirements() {
        // Version PHP
        $phpVersion = PHP_VERSION;
        $minPhpVersion = '7.4.0';
        
        if (version_compare($phpVersion, $minPhpVersion, '>=')) {
            $this->requirements['php_version'] = [
                'status' => 'ok',
                'message' => "PHP {$phpVersion} (requis: {$minPhpVersion}+)"
            ];
        } else {
            $this->requirements['php_version'] = [
                'status' => 'error',
                'message' => "PHP {$phpVersion} - Version {$minPhpVersion}+ requise"
            ];
            $this->errors[] = "Version PHP insuffisante";
        }
        
        // Extensions PHP requises
        $requiredExtensions = [
            'json' => 'Manipulation des données JSON',
            'session' => 'Gestion des sessions utilisateur',
            'zip' => 'Création d\'archives pour les sauvegardes',
            'gd' => 'Traitement des images (optionnel mais recommandé)'
        ];
        
        foreach ($requiredExtensions as $ext => $description) {
            if (extension_loaded($ext)) {
                $this->requirements["ext_{$ext}"] = [
                    'status' => 'ok',
                    'message' => "{$ext} - {$description}"
                ];
            } else {
                $status = ($ext === 'gd') ? 'warning' : 'error';
                $this->requirements["ext_{$ext}"] = [
                    'status' => $status,
                    'message' => "{$ext} - {$description} (MANQUANT)"
                ];
                
                if ($status === 'error') {
                    $this->errors[] = "Extension PHP manquante: {$ext}";
                } else {
                    $this->warnings[] = "Extension PHP recommandée manquante: {$ext}";
                }
            }
        }
        
        // Permissions des dossiers
        $this->checkDirectoryPermissions();
        
        // Configuration du serveur web
        $this->checkServerConfig();
    }
    
    /**
     * Vérifier les permissions des dossiers
     */
    private function checkDirectoryPermissions() {
        $directories = [
            '.' => ['read' => true, 'write' => true, 'description' => 'Dossier racine du projet'],
            'photos' => ['read' => true, 'write' => false, 'description' => 'Dossier des activités photos'],
            'data' => ['read' => true, 'write' => true, 'description' => 'Données de configuration'],
            'logs' => ['read' => true, 'write' => true, 'description' => 'Fichiers de logs']
        ];
        
        foreach ($directories as $dir => $config) {
            $exists = is_dir($dir);
            $readable = $exists && is_readable($dir);
            $writable = $exists && is_writable($dir);
            
            $status = 'ok';
            $message = $config['description'];
            
            if (!$exists) {
                // Tenter de créer le dossier
                if (@mkdir($dir, 0755, true)) {
                    $message .= " (créé automatiquement)";
                    $this->success[] = "Dossier {$dir} créé avec succès";
                } else {
                    $status = 'error';
                    $message .= " (DOSSIER MANQUANT - impossible de le créer)";
                    $this->errors[] = "Impossible de créer le dossier: {$dir}";
                }
            } else {
                if (!$readable) {
                    $status = 'error';
                    $message .= " (NON LISIBLE)";
                    $this->errors[] = "Dossier non lisible: {$dir}";
                }
                
                if ($config['write'] && !$writable) {
                    $status = 'error';
                    $message .= " (NON MODIFIABLE)";
                    $this->errors[] = "Dossier non modifiable: {$dir}";
                }
            }
            
            $this->requirements["dir_{$dir}"] = [
                'status' => $status,
                'message' => $message
            ];
        }
    }
    
    /**
     * Vérifier la configuration du serveur
     */
    private function checkServerConfig() {
        // Limite de mémoire
        $memoryLimit = ini_get('memory_limit');
        $memoryBytes = $this->convertToBytes($memoryLimit);
        $recommendedMemory = 128 * 1024 * 1024; // 128MB
        
        if ($memoryBytes >= $recommendedMemory) {
            $this->requirements['memory_limit'] = [
                'status' => 'ok',
                'message' => "Limite mémoire: {$memoryLimit}"
            ];
        } else {
            $this->requirements['memory_limit'] = [
                'status' => 'warning',
                'message' => "Limite mémoire: {$memoryLimit} (128M recommandés)"
            ];
            $this->warnings[] = "Limite de mémoire PHP faible";
        }
        
        // Taille maximum d'upload
        $uploadMax = ini_get('upload_max_filesize');
        $this->requirements['upload_max'] = [
            'status' => 'ok',
            'message' => "Taille max upload: {$uploadMax}"
        ];
        
        // Temps d'exécution maximum
        $maxTime = ini_get('max_execution_time');
        if ($maxTime == 0 || $maxTime >= 300) {
            $this->requirements['max_execution_time'] = [
                'status' => 'ok',
                'message' => "Temps d'exécution max: " . ($maxTime == 0 ? 'illimité' : $maxTime . 's')
            ];
        } else {
            $this->requirements['max_execution_time'] = [
                'status' => 'warning',
                'message' => "Temps d'exécution max: {$maxTime}s (300s recommandés)"
            ];
            $this->warnings[] = "Temps d'exécution PHP limité";
        }
    }
    
    /**
     * Convertir une valeur de mémoire en octets
     */
    private function convertToBytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value)-1]);
        $value = (int) $value;
        
        switch($last) {
            case 'g': $value *= 1024;
            case 'm': $value *= 1024;
            case 'k': $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Créer les fichiers de configuration initiaux
     */
    public function createInitialConfig($data) {
        try {
            // Créer le fichier de configuration principal
            $configContent = $this->generateConfigFile($data);
            if (!file_put_contents('config.php', $configContent)) {
                throw new Exception("Impossible de créer le fichier config.php");
            }
            
            // Créer le fichier .htaccess de sécurité
            $htaccessContent = $this->generateHtaccessFile();
            file_put_contents('.htaccess', $htaccessContent);
            
            // Créer le fichier d'activités vide
            $activitiesFile = 'data/activities.json';
            if (!file_exists($activitiesFile)) {
                file_put_contents($activitiesFile, '{}');
            }
            
            // Créer un fichier d'exemple dans le dossier photos
            $this->createExampleStructure();
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors de la création des fichiers: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Générer le contenu du fichier config.php
     */
    private function generateConfigFile($data) {
        $adminPassword = $data['admin_password'] ?? 'admin123';
        $siteName = $data['site_name'] ?? 'Galerie Photos - Activités';
        $securityKey = bin2hex(random_bytes(32));
        
        return "<?php
/**
 * Configuration générée automatiquement
 * Date: " . date('Y-m-d H:i:s') . "
 */

if (!defined('GALLERY_ACCESS')) {
    die('Accès direct non autorisé');
}

// INFORMATIONS GÉNÉRALES
define('SITE_NAME', '" . addslashes($siteName) . "');
define('SITE_DESCRIPTION', 'Galerie photos organisée par activités');
define('SITE_VERSION', '" . SITE_VERSION . "');

// CHEMINS
define('PHOTOS_DIR', 'photos/');
define('DATA_DIR', 'data/');
define('CSS_DIR', 'css/');
define('JS_DIR', 'js/');
define('LOGS_DIR', 'logs/');

// SÉCURITÉ
define('ADMIN_PASSWORD', '" . addslashes($adminPassword) . "');
define('ADMIN_SESSION_DURATION', 7200);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_BLOCK_DURATION', 900);
define('SECURITY_KEY', '" . $securityKey . "');

// IMAGES
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('MAX_IMAGE_WIDTH', 2048);
define('MAX_IMAGE_HEIGHT', 2048);
define('JPEG_QUALITY', 85);
define('AUTO_GENERATE_THUMBNAILS', true);
define('THUMBNAIL_WIDTH', 300);
define('THUMBNAIL_HEIGHT', 200);

// INTERFACE
define('ACTIVITIES_PER_PAGE', 12);
define('PHOTOS_PER_PAGE', 24);
define('THEME_COLORS', [
    'primary' => '#0BABDF',
    'secondary' => '#0A76B7',
    'accent' => '#D30420',
    'background' => '#FFFFFF',
    'text' => '#333333',
    'text_light' => '#666666'
]);

// PERFORMANCE
define('DATA_CACHE_DURATION', 3600);
define('IMAGE_CACHE_ENABLED', true);
define('JSON_COMPRESSION', true);
define('LAZY_LOADING_ENABLED', true);

// LOGS
define('LOGS_ENABLED', true);
define('LOG_LEVEL', 'INFO');
define('MAX_LOG_SIZE', 10);
define('DEBUG_MODE', false);

// CONSTANTES CALCULÉES
define('PROJECT_ROOT', dirname(__FILE__));
if (!defined('BASE_URL')) {
    \$protocol = (isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    \$host = \$_SERVER['HTTP_HOST'] ?? 'localhost';
    \$path = dirname(\$_SERVER['PHP_SELF'] ?? '');
    define('BASE_URL', \$protocol . '://' . \$host . \$path);
}
define('ASSET_VERSION', SITE_VERSION . '.' . filemtime(__FILE__));

// CONFIGURATION PHP
if (function_exists('ini_set')) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    
    if (DEBUG_MODE) {
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', 0);
        error_reporting(E_ERROR | E_WARNING | E_PARSE);
    }
}

if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Europe/Paris');
}

?>";
    }
    
    /**
     * Générer le contenu du fichier .htaccess
     */
    private function generateHtaccessFile() {
        return "# Configuration générée automatiquement
# Galerie Photos - Sécurité et optimisations

# Bloquer l'accès aux fichiers sensibles
<Files \"*.json\">
    Order Allow,Deny
    Deny from all
</Files>

<Files \"config.php\">
    Order Allow,Deny
    Deny from all
</Files>

<Files \"*.log\">
    Order Allow,Deny
    Deny from all
</Files>

<Files \"install.php\">
    Order Allow,Deny
    Deny from all
</Files>

# Bloquer l'affichage du contenu des dossiers
Options -Indexes

# Optimisations images
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg \"access plus 1 month\"
    ExpiresByType image/jpeg \"access plus 1 month\"
    ExpiresByType image/gif \"access plus 1 month\"
    ExpiresByType image/png \"access plus 1 month\"
    ExpiresByType image/webp \"access plus 1 month\"
    ExpiresByType text/css \"access plus 1 week\"
    ExpiresByType application/javascript \"access plus 1 week\"
</IfModule>

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/json
</IfModule>

# Headers de sécurité
<IfModule mod_headers.c>
    Header always set X-XSS-Protection \"1; mode=block\"
    Header always set X-Content-Type-Options \"nosniff\"
    Header always set X-Frame-Options \"SAMEORIGIN\"
</IfModule>
";
    }
    
    /**
     * Créer une structure d'exemple
     */
    private function createExampleStructure() {
        // Créer un dossier d'exemple
        $exampleDir = 'photos/exemple-activite';
        if (!is_dir($exampleDir)) {
            mkdir($exampleDir, 0755, true);
            
            // Créer un fichier README dans le dossier exemple
            $readmeContent = "# Dossier d'exemple

Placez vos photos d'activité dans ce dossier.

Formats supportés : JPG, PNG, GIF, WEBP
Nommage : Utilisez des noms descriptifs sans caractères spéciaux

Exemples :
- randonnee-montagne.jpg
- groupe-sommet.png
- panorama-lac.jpg

Une fois vos photos ajoutées, allez dans l'interface d'administration
et lancez un 'Scan des dossiers' pour les détecter automatiquement.
";
            file_put_contents($exampleDir . '/README.txt', $readmeContent);
        }
    }
    
    /**
     * Obtenir les prérequis sous forme de tableau
     */
    public function getRequirements() {
        return $this->requirements;
    }
    
    /**
     * Vérifier si l'installation peut continuer
     */
    public function canInstall() {
        return empty($this->errors);
    }
    
    /**
     * Obtenir les erreurs
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Obtenir les avertissements
     */
    public function getWarnings() {
        return $this->warnings;
    }
    
    /**
     * Obtenir les succès
     */
    public function getSuccess() {
        return $this->success;
    }
}

// ==========================================
// TRAITEMENT DE L'INSTALLATION
// ==========================================

$installer = new GalleryInstaller();
$step = $_GET['step'] ?? 'requirements';
$installComplete = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'install') {
    if ($installer->canInstall()) {
        $configData = [
            'admin_password' => $_POST['admin_password'] ?? 'admin123',
            'site_name' => $_POST['site_name'] ?? 'Galerie Photos - Activités'
        ];
        
        if ($installer->createInitialConfig($configData)) {
            $installComplete = true;
            $step = 'complete';
        } else {
            $step = 'error';
        }
    }
}

// Vérifier si l'installation a déjà été effectuée
$alreadyInstalled = file_exists('config.php') && !INSTALL_MODE;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Galerie Photos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #0BABDF, #0A76B7);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .install-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 2rem;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .install-header {
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 2px solid #0BABDF;
            padding-bottom: 1rem;
        }
        
        .install-header h1 {
            color: #0A76B7;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }
        
        .requirement-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            border-left: 4px solid;
        }
        
        .requirement-ok {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .requirement-warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        .requirement-error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .status-icon {
            margin-right: 1rem;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #0BABDF;
            box-shadow: 0 0 0 3px rgba(11, 171, 223, 0.1);
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 2rem;
            background: #0BABDF;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background: #0A76B7;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .progress-steps {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .step {
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .step-active {
            background: #0BABDF;
            color: white;
        }
        
        .step-completed {
            background: #28a745;
            color: white;
        }
        
        .step-pending {
            background: #f8f9fa;
            color: #6c757d;
        }
        
        .install-complete {
            text-align: center;
            padding: 2rem;
        }
        
        .install-complete h2 {
            color: #28a745;
            margin-bottom: 1rem;
        }
        
        .install-complete .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="install-container">
        
        <?php if ($alreadyInstalled && !INSTALL_MODE): ?>
            <div class="install-header">
                <h1>Installation déjà effectuée</h1>
                <p>La galerie photos est déjà configurée.</p>
            </div>
            
            <div class="alert alert-success">
                L'installation a déjà été effectuée. Vous pouvez accéder à votre galerie.
            </div>
            
            <div style="text-align: center;">
                <a href="index.php" class="btn btn-success">Accéder à la galerie</a>
                <a href="admin.php" class="btn">Administration</a>
            </div>
            
        <?php elseif ($step === 'requirements'): ?>
            <div class="install-header">
                <h1>Installation de la Galerie Photos</h1>
                <p>Vérification des prérequis système</p>
            </div>
            
            <div class="progress-steps">
                <span class="step step-active">1. Prérequis</span>
                <span class="step step-pending">2. Configuration</span>
                <span class="step step-pending">3. Installation</span>
            </div>
            
            <h3 style="margin-bottom: 1rem;">Prérequis système</h3>
            
            <?php foreach ($installer->getRequirements() as $req): ?>
                <div class="requirement-item requirement-<?php echo $req['status']; ?>">
                    <span class="status-icon">
                        <?php 
                        echo $req['status'] === 'ok' ? '✓' : 
                             ($req['status'] === 'warning' ? '⚠' : '✗'); 
                        ?>
                    </span>
                    <span><?php echo htmlspecialchars($req['message']); ?></span>
                </div>
            <?php endforeach; ?>
            
            <?php if (!empty($installer->getErrors())): ?>
                <div class="alert alert-danger">
                    <strong>Erreurs détectées :</strong><br>
                    <?php foreach ($installer->getErrors() as $error): ?>
                        • <?php echo htmlspecialchars($error); ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($installer->getWarnings())): ?>
                <div class="alert" style="background: #fff3cd; border-color: #ffc107; color: #856404;">
                    <strong>Avertissements :</strong><br>
                    <?php foreach ($installer->getWarnings() as $warning): ?>
                        • <?php echo htmlspecialchars($warning); ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 2rem;">
                <?php if ($installer->canInstall()): ?>
                    <a href="?step=config" class="btn">Continuer l'installation</a>
                <?php else: ?>
                    <p style="color: #dc3545; font-weight: 500;">
                        Veuillez corriger les erreurs avant de continuer.
                    </p>
                    <a href="?" class="btn" style="margin-top: 1rem;">Réessayer</a>
                <?php endif; ?>
            </div>
            
        <?php elseif ($step === 'config'): ?>
            <div class="install-header">
                <h1>Configuration</h1>
                <p>Paramètres de base de votre galerie</p>
            </div>
            
            <div class="progress-steps">
                <span class="step step-completed">1. Prérequis</span>
                <span class="step step-active">2. Configuration</span>
                <span class="step step-pending">3. Installation</span>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="install">
                
                <div class="form-group">
                    <label for="site_name">Nom de votre galerie</label>
                    <input type="text" id="site_name" name="site_name" 
                           value="Galerie Photos - Activités" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_password">Mot de passe administrateur</label>
                    <input type="password" id="admin_password" name="admin_password" 
                           placeholder="Choisissez un mot de passe sécurisé" required>
                    <small style="color: #666; font-size: 0.9rem;">
                        Ce mot de passe vous permettra d'accéder à l'interface d'administration.
                    </small>
                </div>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="?step=requirements" class="btn" style="background: #6c757d; margin-right: 1rem;">Retour</a>
                    <button type="submit" class="btn">Installer</button>
                </div>
            </form>
            
        <?php elseif ($step === 'complete'): ?>
            <div class="install-complete">
                <div class="success-icon">✓</div>
                <h2>Installation terminée !</h2>
                <p>Votre galerie photos a été configurée avec succès.</p>
                
                <div class="alert alert-success" style="text-align: left; margin: 2rem 0;">
                    <strong>Prochaines étapes :</strong><br>
                    1. Ajoutez vos photos dans le dossier <code>photos/</code><br>
                    2. Organisez-les par activité (un dossier par activité)<br>
                    3. Connectez-vous à l'administration pour scanner vos dossiers<br>
                    4. Ajoutez des tags et descriptions à vos activités
                </div>
                
                <div style="margin-top: 2rem;">
                    <a href="index.php" class="btn btn-success" style="margin-right: 1rem;">Voir la galerie</a>
                    <a href="admin.php" class="btn">Administration</a>
                </div>
                
                <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 5px; font-size: 0.9rem;">
                    <strong>Sécurité :</strong> Supprimez le fichier <code>install.php</code> 
                    après l'installation pour des raisons de sécurité.
                </div>
            </div>
            
        <?php elseif ($step === 'error'): ?>
            <div class="install-header">
                <h1>Erreur d'installation</h1>
            </div>
            
            <div class="alert alert-danger">
                <strong>L'installation a échoué.</strong><br>
                Vérifiez les permissions de fichiers et réessayez.
            </div>
            
            <div style="text-align: center;">
                <a href="?step=config" class="btn">Réessayer</a>
            </div>
        <?php endif; ?>
        
    </div>
</body>
</html>
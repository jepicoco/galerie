<?php
/**
 * Script de mise à jour automatique pour la galerie photos
 * 
 * Gère les mises à jour de version, les migrations de données
 * et les optimisations système
 */

session_start();
define('GALLERY_ACCESS', true);

// Vérifier l'authentification admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    die('Accès non autorisé. Connectez-vous en tant qu\'administrateur.');
}

require_once 'config.php';
require_once 'classes/autoload.php';

class GalleryUpdater {
    
    private $currentVersion;
    private $targetVersion = '1.1.0';
    private $logger;
    private $updates = [];
    
    public function __construct() {
        $this->logger = Logger::getInstance();
        $this->currentVersion = defined('SITE_VERSION') ? SITE_VERSION : '1.0.0';
        $this->defineUpdates();
    }
    
    /**
     * Définir les mises à jour disponibles
     */
    private function defineUpdates() {
        $this->updates = [
            '1.0.1' => [
                'description' => 'Corrections mineures et optimisations',
                'migrations' => ['addImageMetadata', 'optimizeJsonFiles'],
                'optional' => false
            ],
            '1.0.2' => [
                'description' => 'Amélioration de la sécurité et nouvelles fonctionnalités',
                'migrations' => ['updateSecurityConfig', 'addUserPreferences'],
                'optional' => false
            ],
            '1.1.0' => [
                'description' => 'Interface améliorée et fonctionnalités avancées',
                'migrations' => ['migrateToNewFormat', 'addAdvancedFeatures'],
                'optional' => true
            ]
        ];
    }
    
    /**
     * Vérifier les mises à jour disponibles
     */
    public function checkForUpdates() {
        $availableUpdates = [];
        
        foreach ($this->updates as $version => $updateInfo) {
            if (version_compare($this->currentVersion, $version, '<')) {
                $availableUpdates[$version] = $updateInfo;
            }
        }
        
        return $availableUpdates;
    }
    
    /**
     * Appliquer une mise à jour
     */
    public function applyUpdate($targetVersion) {
        try {
            if (!isset($this->updates[$targetVersion])) {
                throw new Exception("Version de mise à jour inconnue: {$targetVersion}");
            }
            
            $updateInfo = $this->updates[$targetVersion];
            
            // Créer une sauvegarde avant la mise à jour
            $this->createPreUpdateBackup($targetVersion);
            
            // Exécuter les migrations
            $this->logger->info("Début de la mise à jour vers la version {$targetVersion}");
            
            foreach ($updateInfo['migrations'] as $migration) {
                $this->logger->info("Exécution de la migration: {$migration}");
                $this->runMigration($migration);
            }
            
            // Mettre à jour la version dans le fichier de configuration
            $this->updateVersionInConfig($targetVersion);
            
            $this->logger->adminAction('Mise à jour appliquée', [
                'from_version' => $this->currentVersion,
                'to_version' => $targetVersion,
                'migrations' => $updateInfo['migrations']
            ]);
            
            return [
                'success' => true,
                'message' => "Mise à jour vers la version {$targetVersion} terminée avec succès",
                'new_version' => $targetVersion
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Erreur durant la mise à jour: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Créer une sauvegarde avant mise à jour
     */
    private function createPreUpdateBackup($version) {
        $backupDir = DATA_DIR . 'backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupName = 'pre_update_' . $version . '_' . date('Y-m-d_H-i-s');
        $backupPath = $backupDir . $backupName . '.zip';
        
        $zip = new ZipArchive();
        if ($zip->open($backupPath, ZipArchive::CREATE) === TRUE) {
            
            // Sauvegarder les données
            $this->addDirectoryToZip($zip, DATA_DIR, 'data/');
            
            // Sauvegarder la configuration
            if (file_exists('config.php')) {
                $zip->addFile('config.php', 'config/config.php');
            }
            
            $zip->close();
            
            $this->logger->info("Sauvegarde pré-mise à jour créée: {$backupName}");
        }
    }
    
    /**
     * Ajouter un dossier à l'archive ZIP
     */
    private function addDirectoryToZip($zip, $sourcePath, $archivePath) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = $archivePath . substr($filePath, strlen(realpath($sourcePath)) + 1);
                $relativePath = str_replace('\\', '/', $relativePath);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    
    /**
     * Exécuter une migration spécifique
     */
    private function runMigration($migrationName) {
        $methodName = 'migration_' . $migrationName;
        
        if (method_exists($this, $methodName)) {
            $this->$methodName();
        } else {
            throw new Exception("Migration introuvable: {$migrationName}");
        }
    }
    
    /**
     * Migration: Ajouter les métadonnées d'images
     */
    private function migration_addImageMetadata() {
        $activitiesFile = DATA_DIR . 'activities.json';
        
        if (file_exists($activitiesFile)) {
            $activities = json_decode(file_get_contents($activitiesFile), true);
            
            foreach ($activities as $key => &$activity) {
                if (isset($activity['photos'])) {
                    foreach ($activity['photos'] as $index => $photo) {
                        $photoPath = PHOTOS_DIR . $key . '/' . $photo;
                        
                        if (file_exists($photoPath)) {
                            // Ajouter les métadonnées si elles n'existent pas
                            if (!isset($activity['photos_metadata'])) {
                                $activity['photos_metadata'] = [];
                            }
                            
                            if (!isset($activity['photos_metadata'][$photo])) {
                                $imageInfo = getimagesize($photoPath);
                                $activity['photos_metadata'][$photo] = [
                                    'size' => filesize($photoPath),
                                    'width' => $imageInfo[0] ?? 0,
                                    'height' => $imageInfo[1] ?? 0,
                                    'mime_type' => $imageInfo['mime'] ?? 'unknown',
                                    'added_date' => date('Y-m-d H:i:s', filemtime($photoPath))
                                ];
                            }
                        }
                    }
                }
            }
            
            file_put_contents($activitiesFile, json_encode($activities, JSON_PRETTY_PRINT));
            $this->logger->info("Métadonnées d'images ajoutées");
        }
    }
    
    /**
     * Migration: Optimiser les fichiers JSON
     */
    private function migration_optimizeJsonFiles() {
        $jsonFiles = [
            DATA_DIR . 'activities.json',
            DATA_DIR . 'photos_list.json'
        ];
        
        foreach ($jsonFiles as $file) {
            if (file_exists($file)) {
                $data = json_decode(file_get_contents($file), true);
                if ($data !== null) {
                    // Réecrire avec compression optimale
                    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
                    $this->logger->info("Fichier JSON optimisé: " . basename($file));
                }
            }
        }
    }
    
    /**
     * Migration: Mettre à jour la configuration de sécurité
     */
    private function migration_updateSecurityConfig() {
        $htaccessFile = '.htaccess';
        
        // Ajouter de nouvelles règles de sécurité
        $newSecurityRules = "
# Règles de sécurité ajoutées automatiquement - v1.0.2
<IfModule mod_headers.c>
    Header always set Referrer-Policy \"strict-origin-when-cross-origin\"
    Header always set Permissions-Policy \"geolocation=(), microphone=(), camera=()\"
</IfModule>

# Bloquer les scripts potentiellement dangereux
<FilesMatch \"\.(php|phtml|php3|php4|php5|php7|phps|pht|phar)$\">
    <RequireAll>
        Require all denied
        Require local
    </RequireAll>
</FilesMatch>
";
        
        if (file_exists($htaccessFile)) {
            $currentContent = file_get_contents($htaccessFile);
            if (strpos($currentContent, 'v1.0.2') === false) {
                file_put_contents($htaccessFile, $currentContent . $newSecurityRules);
                $this->logger->info("Configuration de sécurité mise à jour");
            }
        }
    }
    
    /**
     * Migration: Ajouter les préférences utilisateur
     */
    private function migration_addUserPreferences() {
        $prefsFile = DATA_DIR . 'user_preferences.json';
        
        if (!file_exists($prefsFile)) {
            $defaultPrefs = [
                'interface' => [
                    'theme' => 'default',
                    'items_per_page' => 12,
                    'sort_order' => 'name_asc',
                    'show_metadata' => false
                ],
                'gallery' => [
                    'auto_play_slideshow' => false,
                    'slideshow_interval' => 5000,
                    'zoom_mode' => 'click',
                    'show_file_names' => true
                ],
                'admin' => [
                    'auto_backup' => true,
                    'backup_frequency' => 'weekly',
                    'log_level' => 'INFO'
                ]
            ];
            
            file_put_contents($prefsFile, json_encode($defaultPrefs, JSON_PRETTY_PRINT));
            $this->logger->info("Fichier de préférences utilisateur créé");
        }
    }
    
    /**
     * Migration: Migrer vers le nouveau format
     */
    private function migration_migrateToNewFormat() {
        $activitiesFile = DATA_DIR . 'activities.json';
        
        if (file_exists($activitiesFile)) {
            $activities = json_decode(file_get_contents($activitiesFile), true);
            
            foreach ($activities as $key => &$activity) {
                // Ajouter de nouveaux champs pour v1.1.0
                if (!isset($activity['created_date'])) {
                    $activity['created_date'] = date('Y-m-d H:i:s');
                }
                
                if (!isset($activity['updated_date'])) {
                    $activity['updated_date'] = date('Y-m-d H:i:s');
                }
                
                if (!isset($activity['visibility'])) {
                    $activity['visibility'] = 'public';
                }
                
                if (!isset($activity['featured'])) {
                    $activity['featured'] = false;
                }
                
                // Restructurer les tags pour supporter les catégories
                if (isset($activity['tags']) && is_array($activity['tags'])) {
                    $newTags = [];
                    foreach ($activity['tags'] as $tag) {
                        $newTags[] = [
                            'name' => $tag,
                            'category' => 'general',
                            'color' => '#0BABDF'
                        ];
                    }
                    $activity['tags_v2'] = $newTags;
                }
            }
            
            file_put_contents($activitiesFile, json_encode($activities, JSON_PRETTY_PRINT));
            $this->logger->info("Migration vers le nouveau format terminée");
        }
    }
    
    /**
     * Migration: Ajouter les fonctionnalités avancées
     */
    private function migration_addAdvancedFeatures() {
        // Créer le fichier de configuration des fonctionnalités avancées
        $featuresFile = DATA_DIR . 'advanced_features.json';
        
        $features = [
            'search' => [
                'enabled' => true,
                'fuzzy_search' => true,
                'search_in_descriptions' => true,
                'search_history' => true
            ],
            'gallery' => [
                'slideshow_mode' => true,
                'fullscreen_mode' => true,
                'keyboard_shortcuts' => true,
                'touch_gestures' => true,
                'image_comparison' => false
            ],
            'admin' => [
                'bulk_operations' => true,
                'advanced_statistics' => true,
                'export_options' => true,
                'scheduled_tasks' => false
            ],
            'performance' => [
                'image_lazy_loading' => true,
                'data_caching' => true,
                'cdn_support' => false,
                'image_optimization' => false
            ]
        ];
        
        file_put_contents($featuresFile, json_encode($features, JSON_PRETTY_PRINT));
        $this->logger->info("Configuration des fonctionnalités avancées créée");
    }
    
    /**
     * Mettre à jour la version dans le fichier de configuration
     */
    private function updateVersionInConfig($newVersion) {
        $configFile = 'config.php';
        
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            
            // Remplacer la version
            $content = preg_replace(
                "/define\('SITE_VERSION',\s*'[^']*'\);/",
                "define('SITE_VERSION', '{$newVersion}');",
                $content
            );
            
            file_put_contents($configFile, $content);
            $this->logger->info("Version mise à jour dans la configuration: {$newVersion}");
        }
    }
    
    /**
     * Vérifier l'intégrité après mise à jour
     */
    public function verifyIntegrity() {
        $checks = [];
        
        // Vérifier les fichiers essentiels
        $essentialFiles = [
            'config.php' => 'Configuration principale',
            'index.php' => 'Page d\'accueil',
            'admin.php' => 'Interface d\'administration',
            DATA_DIR . 'activities.json' => 'Données des activités'
        ];
        
        foreach ($essentialFiles as $file => $description) {
            $checks[] = [
                'name' => $description,
                'status' => file_exists($file) ? 'ok' : 'error',
                'message' => file_exists($file) ? 'Présent' : 'Manquant'
            ];
        }
        
        // Vérifier la cohérence des données
        $activitiesFile = DATA_DIR . 'activities.json';
        if (file_exists($activitiesFile)) {
            $activities = json_decode(file_get_contents($activitiesFile), true);
            $checks[] = [
                'name' => 'Intégrité des données',
                'status' => ($activities !== null) ? 'ok' : 'error',
                'message' => ($activities !== null) ? 'Données valides' : 'Données corrompues'
            ];
        }
        
        return $checks;
    }
    
    /**
     * Nettoyer les fichiers temporaires de mise à jour
     */
    public function cleanup() {
        $tempFiles = glob(DATA_DIR . 'temp_update_*');
        $cleaned = 0;
        
        foreach ($tempFiles as $file) {
            if (unlink($file)) {
                $cleaned++;
            }
        }
        
        $this->logger->info("Nettoyage terminé: {$cleaned} fichier(s) temporaire(s) supprimé(s)");
        return $cleaned;
    }
    
    /**
     * Obtenir des informations sur la version actuelle
     */
    public function getVersionInfo() {
        return [
            'current_version' => $this->currentVersion,
            'target_version' => $this->targetVersion,
            'updates_available' => count($this->checkForUpdates()),
            'is_latest' => version_compare($this->currentVersion, $this->targetVersion, '>=')
        ];
    }
}

// Traitement des actions
$updater = new GalleryUpdater();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'check_updates':
            $updates = $updater->checkForUpdates();
            echo json_encode(['success' => true, 'updates' => $updates]);
            break;
            
        case 'apply_update':
            $version = $_POST['version'] ?? '';
            $result = $updater->applyUpdate($version);
            echo json_encode($result);
            break;
            
        case 'verify_integrity':
            $checks = $updater->verifyIntegrity();
            echo json_encode(['success' => true, 'checks' => $checks]);
            break;
            
        case 'cleanup':
            $cleaned = $updater->cleanup();
            echo json_encode(['success' => true, 'cleaned_files' => $cleaned]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
    }
    exit;
}

// Interface HTML
$versionInfo = $updater->getVersionInfo();
$availableUpdates = $updater->checkForUpdates();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mise à jour - Galerie Photos</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .version-info {
            background: linear-gradient(135deg, #0BABDF, #0A76B7);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .current-version {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .version-status {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .update-item {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
            border-left: 4px solid #0BABDF;
        }
        
        .update-header {
            background: #f8f9fa;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .update-version {
            font-size: 1.3rem;
            font-weight: bold;
            color: #0A76B7;
        }
        
        .update-description {
            padding: 1.5rem;
            color: #666;
            line-height: 1.6;
        }
        
        .update-actions {
            padding: 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .update-optional {
            background: #fff3cd;
            color: #856404;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .no-updates {
            text-align: center;
            padding: 3rem;
            background: #f8f9fa;
            border-radius: 8px;
            color: #666;
        }
        
        .integrity-check {
            margin-top: 2rem;
        }
        
        .check-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .check-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-ok {
            background: #d4edda;
            color: #155724;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Gestionnaire de Mise à Jour</h1>
            <nav>
                <a href="admin.php" class="btn btn-secondary">Retour Admin</a>
                <a href="index.php" class="btn btn-outline">Accueil</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            
            <!-- Informations de version -->
            <div class="version-info">
                <div class="current-version">v<?php echo htmlspecialchars($versionInfo['current_version']); ?></div>
                <div class="version-status">
                    <?php if ($versionInfo['is_latest']): ?>
                        ✅ Vous utilisez la dernière version
                    <?php else: ?>
                        🔄 <?php echo $versionInfo['updates_available']; ?> mise(s) à jour disponible(s)
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions principales -->
            <section class="admin-actions">
                <h2>Actions</h2>
                <div class="actions-grid">
                    
                    <div class="action-form">
                        <h3>Vérifier les mises à jour</h3>
                        <p>Recherche de nouvelles versions disponibles</p>
                        <button onclick="checkUpdates()" class="btn btn-primary">Vérifier</button>
                    </div>

                    <div class="action-form">
                        <h3>Vérifier l'intégrité</h3>
                        <p>Contrôle de l'état du système après mise à jour</p>
                        <button onclick="verifyIntegrity()" class="btn btn-secondary">Vérifier</button>
                    </div>

                    <div class="action-form">
                        <h3>Nettoyer</h3>
                        <p>Supprime les fichiers temporaires de mise à jour</p>
                        <button onclick="cleanup()" class="btn btn-danger">Nettoyer</button>
                    </div>
                    
                </div>
            </section>

            <!-- Mises à jour disponibles -->
            <section>
                <h2>Mises à Jour Disponibles</h2>
                
                <div id="updates-list">
                    <?php if (empty($availableUpdates)): ?>
                        <div class="no-updates">
                            <h3>🎉 Aucune mise à jour disponible</h3>
                            <p>Votre galerie photos est à jour !</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($availableUpdates as $version => $updateInfo): ?>
                            <div class="update-item">
                                <div class="update-header">
                                    <div>
                                        <div class="update-version">Version <?php echo htmlspecialchars($version); ?></div>
                                        <?php if ($updateInfo['optional']): ?>
                                            <span class="update-optional">Optionnelle</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="update-description">
                                    <?php echo htmlspecialchars($updateInfo['description']); ?>
                                    
                                    <?php if (!empty($updateInfo['migrations'])): ?>
                                        <h4 style="margin-top: 1rem; margin-bottom: 0.5rem;">Modifications incluses:</h4>
                                        <ul style="margin-left: 1.5rem;">
                                            <?php foreach ($updateInfo['migrations'] as $migration): ?>
                                                <li><?php echo htmlspecialchars($migration); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="update-actions">
                                    <button onclick="applyUpdate('<?php echo $version; ?>')" 
                                            class="btn btn-primary">
                                        Installer cette mise à jour
                                    </button>
                                    
                                    <?php if ($updateInfo['optional']): ?>
                                        <span style="color: #856404; font-size: 0.9rem;">
                                            Cette mise à jour est optionnelle et peut être ignorée.
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Vérification d'intégrité -->
            <section class="integrity-check" id="integrity-section" style="display: none;">
                <h2>Vérification d'Intégrité</h2>
                <div class="diagnostic-section">
                    <div class="checks-list" id="integrity-results">
                        <!-- Résultats de vérification -->
                    </div>
                </div>
            </section>
            
        </div>
    </main>

    <script>
        // Vérifier les mises à jour
        async function checkUpdates() {
            try {
                showLoading(true);
                const response = await fetch('update.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=check_updates'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Recharger la page pour afficher les nouvelles mises à jour
                    location.reload();
                } else {
                    alert('Erreur lors de la vérification: ' + result.error);
                }
            } catch (error) {
                alert('Erreur de communication: ' + error.message);
            } finally {
                showLoading(false);
            }
        }

        // Appliquer une mise à jour
        async function applyUpdate(version) {
            if (!confirm(`Êtes-vous sûr de vouloir installer la mise à jour vers la version ${version} ?\n\nUne sauvegarde automatique sera créée avant l'installation.`)) {
                return;
            }
            
            try {
                showLoading(true);
                const response = await fetch('update.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=apply_update&version=${encodeURIComponent(version)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Mise à jour terminée avec succès!\n\nNouvelle version: ${result.new_version}`);
                    location.reload();
                } else {
                    alert('Erreur lors de la mise à jour: ' + result.error);
                }
            } catch (error) {
                alert('Erreur de communication: ' + error.message);
            } finally {
                showLoading(false);
            }
        }

        // Vérifier l'intégrité
        async function verifyIntegrity() {
            try {
                showLoading(true);
                const response = await fetch('update.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=verify_integrity'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    displayIntegrityResults(result.checks);
                } else {
                    alert('Erreur lors de la vérification: ' + result.error);
                }
            } catch (error) {
                alert('Erreur de communication: ' + error.message);
            } finally {
                showLoading(false);
            }
        }

        // Nettoyer les fichiers temporaires
        async function cleanup() {
            if (!confirm('Supprimer les fichiers temporaires de mise à jour ?')) {
                return;
            }
            
            try {
                showLoading(true);
                const response = await fetch('update.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=cleanup'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Nettoyage terminé: ${result.cleaned_files} fichier(s) supprimé(s)`);
                } else {
                    alert('Erreur lors du nettoyage: ' + result.error);
                }
            } catch (error) {
                alert('Erreur de communication: ' + error.message);
            } finally {
                showLoading(false);
            }
        }

        // Afficher les résultats de vérification d'intégrité
        function displayIntegrityResults(checks) {
            const section = document.getElementById('integrity-section');
            const results = document.getElementById('integrity-results');
            
            results.innerHTML = '';
            
            checks.forEach(check => {
                const item = document.createElement('div');
                item.className = 'check-item';
                item.innerHTML = `
                    <span>${check.name}</span>
                    <div>
                        <span style="margin-right: 1rem;">${check.message}</span>
                        <span class="check-status status-${check.status}">${check.status.toUpperCase()}</span>
                    </div>
                `;
                results.appendChild(item);
            });
            
            section.style.display = 'block';
        }

        // Gestion du loading
        function showLoading(show) {
            document.body.classList.toggle('loading', show);
        }

        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const updateItems = document.querySelectorAll('.update-item');
            updateItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
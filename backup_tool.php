<?php
/**
 * Outil de sauvegarde et restauration pour la galerie photos
 * 
 * Permet de sauvegarder et restaurer les configurations et données
 */

// Sécurité
session_start();
define('GALLERY_ACCESS', true);

// Vérifier l'authentification admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    die('Accès non autorisé');
}

require_once 'config.php';
require_once 'classes/autoload.php';

class BackupManager {
    
    private $backupDir;
    private $logger;
    
    public function __construct() {
        $this->backupDir = DATA_DIR . 'backups/';
        $this->logger = Logger::getInstance();
        
        // Créer le dossier de sauvegarde
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Créer une sauvegarde complète
     */
    public function createFullBackup($description = '') {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backupName = 'backup_' . $timestamp;
            $backupPath = $this->backupDir . $backupName . '.zip';
            
            // Créer l'archive ZIP
            $zip = new ZipArchive();
            
            if ($zip->open($backupPath, ZipArchive::CREATE) !== TRUE) {
                throw new Exception("Impossible de créer l'archive ZIP");
            }
            
            // Ajouter les fichiers de données
            $this->addDirectoryToZip($zip, DATA_DIR, 'data/');
            
            // Ajouter les fichiers de configuration
            $configFiles = ['config.php', 'logger.php', '.htaccess'];
            foreach ($configFiles as $file) {
                if (file_exists($file)) {
                    $zip->addFile($file, 'config/' . $file);
                }
            }
            
            // Ajouter les informations de sauvegarde
            $backupInfo = [
                'timestamp' => $timestamp,
                'description' => $description,
                'version' => SITE_VERSION,
                'php_version' => PHP_VERSION,
                'activities_count' => $this->countActivities(),
                'photos_count' => $this->countPhotos(),
                'created_by' => 'admin',
                'size' => 0 // Sera calculé après
            ];
            
            $zip->addFromString('backup_info.json', json_encode($backupInfo, JSON_PRETTY_PRINT));
            
            $zip->close();
            
            // Mettre à jour la taille
            $backupInfo['size'] = filesize($backupPath);
            file_put_contents($this->backupDir . $backupName . '_info.json', json_encode($backupInfo, JSON_PRETTY_PRINT));
            
            $this->logger->adminAction('Sauvegarde créée', [
                'backup_name' => $backupName,
                'size' => $backupInfo['size'],
                'description' => $description
            ]);
            
            return [
                'success' => true,
                'backup_name' => $backupName,
                'size' => $backupInfo['size'],
                'path' => $backupPath
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la sauvegarde: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
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
                
                // Remplacer les antislash par des slash pour la compatibilité
                $relativePath = str_replace('\\', '/', $relativePath);
                
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    
    /**
     * Compter le nombre d'activités
     */
    private function countActivities() {
        $activitiesFile = DATA_DIR . 'activities.json';
        if (file_exists($activitiesFile)) {
            $activities = json_decode(file_get_contents($activitiesFile), true);
            return count($activities ?: []);
        }
        return 0;
    }
    
    /**
     * Compter le nombre de photos
     */
    private function countPhotos() {
        $photosFile = DATA_DIR . 'photos_list.json';
        if (file_exists($photosFile)) {
            $photos = json_decode(file_get_contents($photosFile), true);
            return count($photos ?: []);
        }
        return 0;
    }
    
    /**
     * Lister les sauvegardes existantes
     */
    public function listBackups() {
        $backups = [];
        $files = glob($this->backupDir . 'backup_*_info.json');
        
        foreach ($files as $infoFile) {
            $info = json_decode(file_get_contents($infoFile), true);
            if ($info) {
                $backupName = str_replace(['_info.json'], '', basename($infoFile));
                $zipFile = $this->backupDir . $backupName . '.zip';
                
                $backups[] = [
                    'name' => $backupName,
                    'info' => $info,
                    'zip_exists' => file_exists($zipFile),
                    'zip_path' => $zipFile,
                    'size_formatted' => $this->formatBytes($info['size'] ?? 0)
                ];
            }
        }
        
        // Trier par date décroissante
        usort($backups, function($a, $b) {
            return strcmp($b['info']['timestamp'], $a['info']['timestamp']);
        });
        
        return $backups;
    }
    
    /**
     * Supprimer une sauvegarde
     */
    public function deleteBackup($backupName) {
        try {
            $zipFile = $this->backupDir . $backupName . '.zip';
            $infoFile = $this->backupDir . $backupName . '_info.json';
            
            $deleted = 0;
            
            if (file_exists($zipFile)) {
                unlink($zipFile);
                $deleted++;
            }
            
            if (file_exists($infoFile)) {
                unlink($infoFile);
                $deleted++;
            }
            
            if ($deleted > 0) {
                $this->logger->adminAction('Sauvegarde supprimée', ['backup_name' => $backupName]);
                return ['success' => true, 'deleted_files' => $deleted];
            } else {
                return ['success' => false, 'error' => 'Sauvegarde introuvable'];
            }
            
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la suppression de sauvegarde: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Restaurer une sauvegarde
     */
    public function restoreBackup($backupName) {
        try {
            $zipFile = $this->backupDir . $backupName . '.zip';
            
            if (!file_exists($zipFile)) {
                throw new Exception("Fichier de sauvegarde introuvable");
            }
            
            // Créer une sauvegarde de sécurité avant la restauration
            $securityBackup = $this->createFullBackup('Sauvegarde automatique avant restauration');
            
            $zip = new ZipArchive();
            
            if ($zip->open($zipFile) !== TRUE) {
                throw new Exception("Impossible d'ouvrir le fichier de sauvegarde");
            }
            
            // Extraire les données
            if (!$zip->extractTo(PROJECT_ROOT, ['data/'])) {
                throw new Exception("Erreur lors de l'extraction des données");
            }
            
            $zip->close();
            
            $this->logger->adminAction('Sauvegarde restaurée', [
                'backup_name' => $backupName,
                'security_backup' => $securityBackup['backup_name'] ?? 'N/A'
            ]);
            
            return [
                'success' => true,
                'security_backup' => $securityBackup
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de la restauration: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Nettoyer les anciennes sauvegardes
     */
    public function cleanOldBackups($maxAge = 30, $maxCount = 10) {
        $backups = $this->listBackups();
        $deleted = 0;
        $cutoffTime = time() - ($maxAge * 24 * 60 * 60);
        
        // Supprimer par âge et nombre
        foreach ($backups as $index => $backup) {
            $shouldDelete = false;
            
            // Supprimer si trop ancien
            $backupTime = strtotime($backup['info']['timestamp']);
            if ($backupTime < $cutoffTime) {
                $shouldDelete = true;
            }
            
            // Supprimer si on dépasse le nombre maximum (garder les plus récents)
            if ($index >= $maxCount) {
                $shouldDelete = true;
            }
            
            if ($shouldDelete) {
                $result = $this->deleteBackup($backup['name']);
                if ($result['success']) {
                    $deleted++;
                }
            }
        }
        
        if ($deleted > 0) {
            $this->logger->adminAction('Nettoyage des anciennes sauvegardes', ['deleted_count' => $deleted]);
        }
        
        return $deleted;
    }
    
    /**
     * Exporter uniquement la configuration
     */
    public function exportConfiguration() {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $exportName = 'config_export_' . $timestamp . '.json';
            $exportPath = $this->backupDir . $exportName;
            
            // Collecter les données de configuration
            $config = [
                'export_info' => [
                    'timestamp' => $timestamp,
                    'version' => SITE_VERSION,
                    'type' => 'configuration_only'
                ],
                'activities' => [],
                'system_info' => getSystemInfo()
            ];
            
            // Charger les activités
            $activitiesFile = DATA_DIR . 'activities.json';
            if (file_exists($activitiesFile)) {
                $config['activities'] = json_decode(file_get_contents($activitiesFile), true);
            }
            
            file_put_contents($exportPath, json_encode($config, JSON_PRETTY_PRINT));
            
            $this->logger->adminAction('Configuration exportée', ['export_name' => $exportName]);
            
            return [
                'success' => true,
                'export_name' => $exportName,
                'path' => $exportPath,
                'size' => filesize($exportPath)
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de l\'export de configuration: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
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
     * Obtenir les statistiques de sauvegarde
     */
    public function getBackupStats() {
        $backups = $this->listBackups();
        $totalSize = 0;
        
        foreach ($backups as $backup) {
            $totalSize += $backup['info']['size'] ?? 0;
        }
        
        return [
            'total_backups' => count($backups),
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'oldest_backup' => end($backups)['info']['timestamp'] ?? null,
            'newest_backup' => reset($backups)['info']['timestamp'] ?? null,
            'backup_directory' => $this->backupDir
        ];
    }
}

// ==========================================
// TRAITEMENT DES ACTIONS
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $backup = new BackupManager();
    $action = $_POST['action'] ?? '';
    
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'create_backup':
            $description = $_POST['description'] ?? '';
            $result = $backup->createFullBackup($description);
            echo json_encode($result);
            break;
            
        case 'delete_backup':
            $backupName = $_POST['backup_name'] ?? '';
            $result = $backup->deleteBackup($backupName);
            echo json_encode($result);
            break;
            
        case 'restore_backup':
            $backupName = $_POST['backup_name'] ?? '';
            $result = $backup->restoreBackup($backupName);
            echo json_encode($result);
            break;
            
        case 'clean_old_backups':
            $maxAge = intval($_POST['max_age'] ?? 30);
            $maxCount = intval($_POST['max_count'] ?? 10);
            $deleted = $backup->cleanOldBackups($maxAge, $maxCount);
            echo json_encode(['success' => true, 'deleted_count' => $deleted]);
            break;
            
        case 'export_config':
            $result = $backup->exportConfiguration();
            echo json_encode($result);
            break;
            
        case 'download_backup':
            $backupName = $_POST['backup_name'] ?? '';
            $zipFile = DATA_DIR . 'backups/' . $backupName . '.zip';
            
            if (file_exists($zipFile)) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $backupName . '.zip"');
                header('Content-Length: ' . filesize($zipFile));
                readfile($zipFile);
                exit;
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Fichier introuvable']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
    }
    
    exit;
}

// ==========================================
// INTERFACE HTML
// ==========================================

$backup = new BackupManager();
$backups = $backup->listBackups();
$stats = $backup->getBackupStats();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sauvegarde et Restauration - Galerie Photos</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .backup-item {
            background: white;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #0BABDF;
        }
        
        .backup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .backup-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .backup-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            font-size: 0.9rem;
            color: #666;
        }
        
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .status-ok { background-color: #28a745; }
        .status-error { background-color: #dc3545; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Sauvegarde et Restauration</h1>
            <nav>
                <a href="admin.php" class="btn btn-secondary">Retour Admin</a>
                <a href="index.php" class="btn btn-outline">Accueil</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            
            <!-- Statistiques -->
            <section class="stats">
                <h2>Statistiques</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php echo $stats['total_backups']; ?></h3>
                        <p>Sauvegardes</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $stats['total_size_formatted']; ?></h3>
                        <p>Espace utilisé</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $stats['newest_backup'] ? date('d/m/Y', strtotime($stats['newest_backup'])) : 'Aucune'; ?></h3>
                        <p>Dernière sauvegarde</p>
                    </div>
                </div>
            </section>

            <!-- Actions -->
            <section class="admin-actions">
                <h2>Actions de sauvegarde</h2>
                <div class="actions-grid">
                    
                    <div class="action-form">
                        <h3>Créer une sauvegarde</h3>
                        <p>Sauvegarde complète des données et configuration</p>
                        <form id="backup-form">
                            <input type="hidden" name="action" value="create_backup">
                            <div class="form-group">
                                <label>Description (optionnelle)</label>
                                <input type="text" name="description" placeholder="Ex: Avant mise à jour...">
                            </div>
                            <button type="submit" class="btn btn-primary">Créer la sauvegarde</button>
                        </form>
                    </div>

                    <div class="action-form">
                        <h3>Exporter la configuration</h3>
                        <p>Exporte uniquement les paramètres et activités</p>
                        <form id="export-config-form">
                            <input type="hidden" name="action" value="export_config">
                            <button type="submit" class="btn btn-secondary">Exporter</button>
                        </form>
                    </div>

                    <div class="action-form">
                        <h3>Nettoyer les anciennes</h3>
                        <p>Supprime automatiquement les sauvegardes anciennes</p>
                        <form id="clean-form">
                            <input type="hidden" name="action" value="clean_old_backups">
                            <div class="form-group">
                                <label>Âge maximum (jours)</label>
                                <input type="number" name="max_age" value="30" min="1">
                            </div>
                            <div class="form-group">
                                <label>Nombre maximum</label>
                                <input type="number" name="max_count" value="10" min="1">
                            </div>
                            <button type="submit" class="btn btn-danger">Nettoyer</button>
                        </form>
                    </div>
                    
                </div>
            </section>

            <!-- Liste des sauvegardes -->
            <section>
                <h2>Sauvegardes existantes</h2>
                
                <?php if (empty($backups)): ?>
                    <p class="no-activities">Aucune sauvegarde trouvée. Créez votre première sauvegarde ci-dessus.</p>
                <?php else: ?>
                    <div id="backups-list">
                        <?php foreach ($backups as $backup): ?>
                            <div class="backup-item">
                                <div class="backup-header">
                                    <h3>
                                        <span class="status-indicator <?php echo $backup['zip_exists'] ? 'status-ok' : 'status-error'; ?>"></span>
                                        <?php echo htmlspecialchars($backup['name']); ?>
                                    </h3>
                                    <div class="backup-actions">
                                        <?php if ($backup['zip_exists']): ?>
                                            <button class="btn btn-primary btn-download" data-backup="<?php echo htmlspecialchars($backup['name']); ?>">Télécharger</button>
                                            <button class="btn btn-secondary btn-restore" data-backup="<?php echo htmlspecialchars($backup['name']); ?>">Restaurer</button>
                                        <?php endif; ?>
                                        <button class="btn btn-danger btn-delete" data-backup="<?php echo htmlspecialchars($backup['name']); ?>">Supprimer</button>
                                    </div>
                                </div>
                                
                                <div class="backup-info">
                                    <div><strong>Date:</strong> <?php echo date('d/m/Y H:i:s', strtotime($backup['info']['timestamp'])); ?></div>
                                    <div><strong>Taille:</strong> <?php echo $backup['size_formatted']; ?></div>
                                    <div><strong>Activités:</strong> <?php echo $backup['info']['activities_count'] ?? 'N/A'; ?></div>
                                    <div><strong>Photos:</strong> <?php echo $backup['info']['photos_count'] ?? 'N/A'; ?></div>
                                    <?php if (!empty($backup['info']['description'])): ?>
                                        <div><strong>Description:</strong> <?php echo htmlspecialchars($backup['info']['description']); ?></div>
                                    <?php endif; ?>
                                    <div><strong>Version:</strong> <?php echo htmlspecialchars($backup['info']['version'] ?? 'Inconnue'); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            
        </div>
    </main>

    <script>
        // Gestion des formulaires AJAX
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const button = this.querySelector('button[type="submit"]');
                const originalText = button.textContent;
                button.textContent = 'Traitement...';
                button.disabled = true;
                
                try {
                    const formData = new FormData(this);
                    const response = await fetch('backup.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('Opération réussie!');
                        location.reload();
                    } else {
                        alert('Erreur: ' + result.error);
                    }
                } catch (error) {
                    alert('Erreur de communication: ' + error.message);
                } finally {
                    button.textContent = originalText;
                    button.disabled = false;
                }
            });
        });

        // Gestion des boutons d'action
        document.addEventListener('click', async function(e) {
            if (e.target.classList.contains('btn-delete')) {
                if (!confirm('Êtes-vous sûr de vouloir supprimer cette sauvegarde ?')) return;
                
                const backupName = e.target.dataset.backup;
                await performAction('delete_backup', { backup_name: backupName });
                
            } else if (e.target.classList.contains('btn-restore')) {
                if (!confirm('ATTENTION: La restauration remplacera toutes les données actuelles. Continuer ?')) return;
                
                const backupName = e.target.dataset.backup;
                await performAction('restore_backup', { backup_name: backupName });
                
            } else if (e.target.classList.contains('btn-download')) {
                const backupName = e.target.dataset.backup;
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'backup.php';
                form.innerHTML = `
                    <input type="hidden" name="action" value="download_backup">
                    <input type="hidden" name="backup_name" value="${backupName}">
                `;
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            }
        });

        async function performAction(action, data) {
            try {
                const formData = new FormData();
                formData.append('action', action);
                
                for (const [key, value] of Object.entries(data)) {
                    formData.append(key, value);
                }
                
                const response = await fetch('backup.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Opération réussie!');
                    location.reload();
                } else {
                    alert('Erreur: ' + result.error);
                }
            } catch (error) {
                alert('Erreur de communication: ' + error.message);
            }
        }
    </script>
</body>
</html>
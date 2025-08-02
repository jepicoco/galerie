<?php
define('GALLERY_ACCESS', true);

ini_set('error_reporting', E_ALL);

require_once 'config.php';

session_start();

require_once 'functions.php';

// Vérifier l'authentification admin
$is_admin = is_admin();

if (!$is_admin) {
    header('Location: index.php');
    exit;
}

$logger = Logger::getInstance();

// Gestion des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_watermark':
                $watermarkEnabled = isset($_POST['watermark_enabled']) ? 'true' : 'false';
                $watermarkText = addslashes($_POST['watermark_text'] ?? 'Gala de danse');
                $watermarkOpacity = floatval($_POST['watermark_opacity'] ?? 0.3);
                $watermarkSize = intval($_POST['watermark_size'] ?? 24) . 'px';
                $watermarkColor = $_POST['watermark_color'] ?? '#FFFFFF';
                
                // Lire le fichier config actuel
                $configFile = 'config.php';
                if (file_exists($configFile)) {
                    $configContent = file_get_contents($configFile);
                    
                    // Remplacer ou ajouter les constantes watermark
                    $watermarkConfig = "
// WATERMARK
define('WATERMARK_ENABLED', $watermarkEnabled);
define('WATERMARK_TEXT', '$watermarkText');
define('WATERMARK_OPACITY', $watermarkOpacity);
define('WATERMARK_SIZE', '$watermarkSize');
define('WATERMARK_COLOR', '$watermarkColor');
define('WATERMARK_ANGLE', -45);";

                    // Supprimer l'ancienne section watermark si elle existe
                    $configContent = preg_replace('/\/\/ WATERMARK.*?define\(\'WATERMARK_ANGLE\'[^;]*;/s', '', $configContent);
                    
                    // Ajouter la nouvelle configuration avant la fermeture PHP
                    $configContent = str_replace('?>', $watermarkConfig . "\n\n?>", $configContent);
                    
                    // Sauvegarder
                    if (file_put_contents($configFile, $configContent)) {
                        header('Location: parametres.php?watermark_updated=1');
                        exit;
                    } else {
                        $success_message = "Erreur lors de la sauvegarde de la configuration.";
                    }
                }
                break;

            // Ajouter ces cas dans votre switch d'actions
            case 'generate_cache':
                echo "<div class='admin-content'>";
                echo "<h1>Génération du cache d'images</h1>";
                
                $options = [
                    'generate_thumbnails' => isset($_POST['generate_thumbnails']),
                    'generate_resized' => isset($_POST['generate_resized']),
                    'force_regenerate' => isset($_POST['force_regenerate'])
                ];
                
                if (!$options['generate_thumbnails'] && !$options['generate_resized']) {
                    echo "<div class='error'>❌ Aucun type de cache sélectionné</div>";
                } else {
                    $report = generateAllImageCache($options);
                    displayCacheGenerationReport($report);
                }
                
                echo "<div class='back-nav'>";
                echo "<a href='admin_parameters.php' class='btn btn-secondary'>← Retour aux paramètres</a>";
                echo "</div>";
                echo "</div>";
                break;

            case 'clear_cache':
                echo "<div class='admin-content'>";
                echo "<h1>Nettoyage du cache</h1>";
                
                $cacheType = $_POST['cache_type'] ?? 'all';
                $report = clearImageCache($cacheType);
                
                echo "<div class='cleanup-report'>";
                
                if ($report['thumbnails_deleted'] > 0) {
                    echo "<p class='success'>✅ {$report['thumbnails_deleted']} miniatures supprimées</p>";
                }
                
                if ($report['resized_deleted'] > 0) {
                    echo "<p class='success'>✅ {$report['resized_deleted']} images redimensionnées supprimées</p>";
                }
                
                if ($report['space_freed'] > 0) {
                    echo "<p class='info'>💾 Espace libéré: " . formatBytes($report['space_freed']) . "</p>";
                }
                
                if (!empty($report['errors'])) {
                    echo "<div class='errors'>";
                    echo "<h4>Erreurs:</h4>";
                    foreach ($report['errors'] as $error) {
                        echo "<p class='error'>" . htmlspecialchars($error) . "</p>";
                    }
                    echo "</div>";
                }
                
                if ($report['thumbnails_deleted'] === 0 && $report['resized_deleted'] === 0) {
                    echo "<p class='info'>ℹ️ Aucun fichier à supprimer</p>";
                }
                
                echo "</div>";
                
                echo "<div class='back-nav'>";
                echo "<a href='admin_parameters.php' class='btn btn-secondary'>← Retour aux paramètres</a>";
                echo "</div>";
                echo "</div>";
                break;
        }
    }
}

/**
 * Charger les données des activités depuis le fichier JSON
 */
function loadActivitiesData() {
    $dataFile = DATA_DIR . 'activities.json';
    
    if (file_exists($dataFile)) {
        $content = file_get_contents($dataFile);
        return json_decode($content, true) ?: [];
    }
    
    return [];
}

/**
 * Vérifier si les dossiers requis existent
 */
function ensureRequiredDirectories() {
    $directories = [
        DATA_DIR,
        PHOTOS_DIR,
        CACHE_DIR,
        THUMBNAILS_CACHE_DIR,
        RESIZED_CACHE_DIR
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new Exception("Impossible de créer le dossier : " . $dir);
            }
        }
    }
}

// Message de succès après redirection
if (isset($_GET['watermark_updated'])) {
    $success_message = "Configuration du watermark mise à jour avec succès.";
}

$watermarkConfig = getWatermarkConfig();

/**
 * Générer tous les thumbnails et images redimensionnées
 */
function generateAllImageCache($options = []) {
    try {
        // Vérifier les prérequis
        ensureRequiredDirectories();
        
        // Options par défaut
        $defaultOptions = [
            'generate_thumbnails' => true,
            'generate_resized' => true,
            'force_regenerate' => false,
            'resized_width' => MAX_IMAGE_WIDTH,
            'resized_height' => MAX_IMAGE_HEIGHT
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        // Inclure les fonctions de cache d'images
        if (!function_exists('generateThumbnail')) {
            require_once 'image_core.php';
        }
        
        $report = [
            'total_photos' => 0,
            'thumbnails_created' => 0,
            'thumbnails_existed' => 0,
            'thumbnails_errors' => [],
            'resized_created' => 0,
            'resized_existed' => 0,
            'resized_errors' => [],
            'processing_time' => 0,
            'activities_processed' => 0,
            'total_cache_size' => 0
        ];
        
        $startTime = microtime(true);
        
        // Charger les activités
        $activities = loadActivitiesData();
        
        if (empty($activities)) {
            $report['thumbnails_errors'][] = "Aucune activité trouvée dans activities.json";
            return $report;
        }
        
        // Vérifier que l'extension GD est disponible
        if (!extension_loaded('gd')) {
            throw new Exception("L'extension PHP GD est requise pour le traitement des images");
        }
        
        // Parcourir toutes les activités
        foreach ($activities as $activityKey => $activity) {
            if (!isset($activity['photos']) || !is_array($activity['photos'])) {
                continue;
            }
            
            $report['activities_processed']++;
            
            foreach ($activity['photos'] as $photo) {
                $report['total_photos']++;
                
                // Chemin de l'image originale
                $originalPath = PHOTOS_DIR . $activityKey . '/' . $photo;
                
                // Vérifier que l'image originale existe
                if (!file_exists($originalPath)) {
                    $report['thumbnails_errors'][] = "Image manquante: {$activityKey}/{$photo}";
                    continue;
                }
                
                // Vérifier que c'est bien un fichier image
                $imageInfo = @getimagesize($originalPath);
                if (!$imageInfo) {
                    $report['thumbnails_errors'][] = "Fichier non valide: {$activityKey}/{$photo}";
                    continue;
                }
                
                // Générer les thumbnails
                if ($options['generate_thumbnails']) {
                    $thumbnailPath = THUMBNAILS_CACHE_DIR . str_replace('/', '_', $activityKey . '/' . $photo);
                    
                    if ($options['force_regenerate'] || !isCacheValid($thumbnailPath, $originalPath)) {
                        $success = generateThumbnail($originalPath, $thumbnailPath);
                        
                        if ($success) {
                            $report['thumbnails_created']++;
                            if (file_exists($thumbnailPath)) {
                                $report['total_cache_size'] += filesize($thumbnailPath);
                            }
                        } else {
                            $report['thumbnails_errors'][] = "Échec génération thumbnail: {$activityKey}/{$photo}";
                        }
                    } else {
                        $report['thumbnails_existed']++;
                        if (file_exists($thumbnailPath)) {
                            $report['total_cache_size'] += filesize($thumbnailPath);
                        }
                    }
                }
                
                // Générer les images redimensionnées
                if ($options['generate_resized']) {
                    $resizedPath = RESIZED_CACHE_DIR . $options['resized_width'] . 'x' . $options['resized_height'] . '_' . str_replace('/', '_', $activityKey . '/' . $photo);
                    
                    if ($options['force_regenerate'] || !isCacheValid($resizedPath, $originalPath)) {
                        $success = resizeImage($originalPath, $resizedPath, $options['resized_width'], $options['resized_height']);
                        
                        if ($success) {
                            $report['resized_created']++;
                            if (file_exists($resizedPath)) {
                                $report['total_cache_size'] += filesize($resizedPath);
                            }
                        } else {
                            $report['resized_errors'][] = "Échec génération resized: {$activityKey}/{$photo}";
                        }
                    } else {
                        $report['resized_existed']++;
                        if (file_exists($resizedPath)) {
                            $report['total_cache_size'] += filesize($resizedPath);
                        }
                    }
                }
            }
        }
        
        $report['processing_time'] = round(microtime(true) - $startTime, 2);
        
        return $report;
        
    } catch (Exception $e) {
        return [
            'total_photos' => 0,
            'thumbnails_created' => 0,
            'thumbnails_existed' => 0,
            'thumbnails_errors' => ["Erreur fatale: " . $e->getMessage()],
            'resized_created' => 0,
            'resized_existed' => 0,
            'resized_errors' => [],
            'processing_time' => 0,
            'activities_processed' => 0,
            'total_cache_size' => 0
        ];
    }
}

/**
 * Nettoyer le cache des images
 */
function clearImageCache($type = 'all') {
    $report = [
        'thumbnails_deleted' => 0,
        'resized_deleted' => 0,
        'errors' => [],
        'space_freed' => 0
    ];
    
    $cacheDirs = [];
    
    switch ($type) {
        case 'thumbnails':
            $cacheDirs['thumbnails'] = THUMBNAILS_CACHE_DIR;
            break;
        case 'resized':
            $cacheDirs['resized'] = RESIZED_CACHE_DIR;
            break;
        case 'all':
        default:
            $cacheDirs['thumbnails'] = THUMBNAILS_CACHE_DIR;
            $cacheDirs['resized'] = RESIZED_CACHE_DIR;
            break;
    }
    
    foreach ($cacheDirs as $cacheType => $cacheDir) {
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $fileSize = filesize($file);
                    if (unlink($file)) {
                        $report[$cacheType . '_deleted']++;
                        $report['space_freed'] += $fileSize;
                    } else {
                        $report['errors'][] = "Impossible de supprimer: " . basename($file);
                    }
                }
            }
        }
    }
    
    return $report;
}

/**
 * Traiter le cache par chunks avec progression
 */
function processImageCacheChunk($options = [], $offset = 0, $chunkSize = 5) {
    try {
        // Inclure les fonctions nécessaires
        if (!function_exists('generateThumbnail')) {
            require_once 'image_core.php';
        }
        
        // Charger toutes les activités
        $activities = loadActivitiesData();
        
        // Collecter toutes les photos
        $allPhotos = [];
        foreach ($activities as $activityKey => $activity) {
            if (isset($activity['photos']) && is_array($activity['photos'])) {
                foreach ($activity['photos'] as $photo) {
                    $allPhotos[] = [
                        'activityKey' => $activityKey,
                        'photo' => $photo,
                        'originalPath' => PHOTOS_DIR . $activityKey . '/' . $photo
                    ];
                }
            }
        }
        
        $totalPhotos = count($allPhotos);
        $photosToProcess = array_slice($allPhotos, $offset, $chunkSize);
        
        $report = [
            'total_photos' => $totalPhotos,
            'processed_photos' => 0,
            'thumbnails_created' => 0,
            'resized_created' => 0,
            'errors' => [],
            'offset' => $offset,
            'completed' => false,
            'progress_percent' => 0
        ];
        
        // Traiter le chunk actuel
        foreach ($photosToProcess as $photoData) {
            $activityKey = $photoData['activityKey'];
            $photo = $photoData['photo'];
            $originalPath = $photoData['originalPath'];
            
            if (!file_exists($originalPath)) {
                $report['errors'][] = "Image manquante: {$activityKey}/{$photo}";
                continue;
            }
            
            // Traiter thumbnail
            if ($options['generate_thumbnails'] ?? true) {
                $thumbnailPath = THUMBNAILS_CACHE_DIR . str_replace('/', '_', $activityKey . '/' . $photo);
                
                if (($options['force_regenerate'] ?? false) || !isCacheValid($thumbnailPath, $originalPath)) {
                    if (generateThumbnail($originalPath, $thumbnailPath)) {
                        $report['thumbnails_created']++;
                    }
                }
            }
            
            // Traiter resized
            if ($options['generate_resized'] ?? true) {
                $width = $options['resized_width'] ?? MAX_IMAGE_WIDTH;
                $height = $options['resized_height'] ?? MAX_IMAGE_HEIGHT;
                $resizedPath = RESIZED_CACHE_DIR . $width . 'x' . $height . '_' . str_replace('/', '_', $activityKey . '/' . $photo);
                
                if (($options['force_regenerate'] ?? false) || !isCacheValid($resizedPath, $originalPath)) {
                    if (resizeImage($originalPath, $resizedPath, $width, $height)) {
                        $report['resized_created']++;
                    }
                }
            }
            
            $report['processed_photos']++;
        }
        
        // Calculer la progression
        $newOffset = $offset + $chunkSize;
        $report['completed'] = $newOffset >= $totalPhotos;
        $report['progress_percent'] = min(100, round(($newOffset / $totalPhotos) * 100));
        $report['next_offset'] = $newOffset;
        
        return $report;
        
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage(),
            'completed' => true,
            'progress_percent' => 0
        ];
    }
}

/**
 * Obtenir les statistiques du cache
 */
function getCacheStatistics() {
    $stats = [
        'thumbnails' => [
            'count' => 0,
            'size' => 0,
            'path' => THUMBNAILS_CACHE_DIR
        ],
        'resized' => [
            'count' => 0,
            'size' => 0,
            'path' => RESIZED_CACHE_DIR
        ],
        'total_size' => 0,
        'total_count' => 0
    ];
    
    // Statistiques des miniatures
    if (is_dir(THUMBNAILS_CACHE_DIR)) {
        $thumbnails = glob(THUMBNAILS_CACHE_DIR . '*');
        foreach ($thumbnails as $file) {
            if (is_file($file)) {
                $stats['thumbnails']['count']++;
                $fileSize = filesize($file);
                $stats['thumbnails']['size'] += $fileSize;
                $stats['total_size'] += $fileSize;
            }
        }
    }
    
    // Statistiques des images redimensionnées
    if (is_dir(RESIZED_CACHE_DIR)) {
        $resized = glob(RESIZED_CACHE_DIR . '*');
        foreach ($resized as $file) {
            if (is_file($file)) {
                $stats['resized']['count']++;
                $fileSize = filesize($file);
                $stats['resized']['size'] += $fileSize;
                $stats['total_size'] += $fileSize;
            }
        }
    }
    
    $stats['total_count'] = $stats['thumbnails']['count'] + $stats['resized']['count'];
    
    return $stats;
}

/**
 * Formater la taille en octets de manière lisible
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Afficher le rapport de génération complet
 */
function displayCacheGenerationReport($report) {
    echo "<div class='cache-report'>";
    echo "<h3>📊 Rapport de génération du cache d'images</h3>";
    
    // Statistiques générales
    echo "<div class='stats-overview'>";
    echo "<div class='stat-card primary'>";
    echo "<h4>📸 Photos traitées</h4>";
    echo "<span class='stat-number'>{$report['total_photos']}</span>";
    echo "</div>";
    
    echo "<div class='stat-card secondary'>";
    echo "<h4>📁 Activités</h4>";
    echo "<span class='stat-number'>{$report['activities_processed']}</span>";
    echo "</div>";
    
    echo "<div class='stat-card info'>";
    echo "<h4>⏱️ Temps</h4>";
    echo "<span class='stat-number'>{$report['processing_time']}s</span>";
    echo "</div>";
    
    echo "<div class='stat-card success'>";
    echo "<h4>💾 Taille cache</h4>";
    echo "<span class='stat-number'>" . formatBytes($report['total_cache_size']) . "</span>";
    echo "</div>";
    echo "</div>";
    
    // Détails par type
    echo "<div class='cache-details'>";
    
    // Miniatures
    echo "<div class='cache-type-section'>";
    echo "<h4>🖼️ Miniatures</h4>";
    echo "<div class='cache-stats'>";
    echo "<span class='stat success'>✅ Créées: {$report['thumbnails_created']}</span>";
    echo "<span class='stat info'>📁 Existantes: {$report['thumbnails_existed']}</span>";
    if (!empty($report['thumbnails_errors'])) {
        echo "<span class='stat error'>❌ Erreurs: " . count($report['thumbnails_errors']) . "</span>";
    }
    echo "</div>";
    echo "</div>";
    
    // Images redimensionnées
    echo "<div class='cache-type-section'>";
    echo "<h4>🔍 Images redimensionnées</h4>";
    echo "<div class='cache-stats'>";
    echo "<span class='stat success'>✅ Créées: {$report['resized_created']}</span>";
    echo "<span class='stat info'>📁 Existantes: {$report['resized_existed']}</span>";
    if (!empty($report['resized_errors'])) {
        echo "<span class='stat error'>❌ Erreurs: " . count($report['resized_errors']) . "</span>";
    }
    echo "</div>";
    echo "</div>";
    
    echo "</div>";
    
    // Erreurs détaillées
    if (!empty($report['thumbnails_errors']) || !empty($report['resized_errors'])) {
        echo "<div class='errors-section'>";
        echo "<h4>⚠️ Erreurs détaillées</h4>";
        
        if (!empty($report['thumbnails_errors'])) {
            echo "<h5>Miniatures :</h5>";
            echo "<ul class='error-list'>";
            foreach ($report['thumbnails_errors'] as $error) {
                echo "<li>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul>";
        }
        
        if (!empty($report['resized_errors'])) {
            echo "<h5>Images redimensionnées :</h5>";
            echo "<ul class='error-list'>";
            foreach ($report['resized_errors'] as $error) {
                echo "<li>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul>";
        }
        
        echo "</div>";
    } else {
        echo "<p class='no-errors'>✅ Aucune erreur détectée</p>";
    }
    
    echo "</div>";
}

// Gérer les requêtes AJAX
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['ajax_action']) {
        case 'process_cache_chunk':
            $options = [
                'generate_thumbnails' => isset($_POST['generate_thumbnails']),
                'generate_resized' => isset($_POST['generate_resized']),
                'force_regenerate' => isset($_POST['force_regenerate']),
                'resized_width' => MAX_IMAGE_WIDTH,
                'resized_height' => MAX_IMAGE_HEIGHT
            ];
            
            $offset = intval($_POST['offset'] ?? 0);
            $result = processImageCacheChunk($options, $offset, 5);
            
            echo json_encode($result);
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - <?php echo(SITE_NAME); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/admin.parameters.css">
    <link rel="icon" href="favicon.png" />
</head>
<body>
    
    <?php include('include.header.php'); ?>

    <main >
        <div class="container">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

        
            <!-- Configuration Watermark -->
            <section class="config-section">
                <div class="accordion-section">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <h3>🎨 Configuration Watermark</h3>
                        <span class="accordion-toggle">▼</span>
                    </div>
                    
                    <div class="accordion-content">
                        <div class="accordion-inner">
                            <form method="POST" class="config-form">
                                <input type="hidden" name="action" value="update_watermark">
                                
                                <div class="checkbox-group">
                                    <input type="checkbox" 
                                           id="watermark_enabled"
                                           name="watermark_enabled" 
                                           <?php echo $watermarkConfig['WATERMARK_ENABLED'] ? 'checked' : ''; ?>>
                                    <label for="watermark_enabled">Activer le watermark</label>
                                </div>
                                
                                <div class="form-group">
                                    <label for="watermark_text">Texte du watermark</label>
                                    <input type="text" 
                                           id="watermark_text" 
                                           name="watermark_text" 
                                           value="<?php echo htmlspecialchars($watermarkConfig['WATERMARK_TEXT']); ?>"
                                           placeholder="Votre texte de watermark">
                                </div>
                                
                                <div class="form-group">
                                    <label for="watermark_opacity">Opacité (0.1 à 1.0)</label>
                                    <input type="number" 
                                           id="watermark_opacity" 
                                           name="watermark_opacity" 
                                           value="<?php echo $watermarkConfig['WATERMARK_OPACITY']; ?>"
                                           min="0.1" max="1.0" step="0.1">
                                </div>
                                
                                <div class="form-group">
                                    <label for="watermark_size">Taille de police (px)</label>
                                    <input type="number" 
                                           id="watermark_size" 
                                           name="watermark_size" 
                                           value="<?php echo str_replace('px', '', $watermarkConfig['WATERMARK_SIZE']); ?>"
                                           min="12" max="72">
                                </div>
                                
                                <div class="form-group">
                                    <label for="watermark_color">Couleur</label>
                                    <input type="color" 
                                           id="watermark_color" 
                                           name="watermark_color" 
                                           value="<?php echo $watermarkConfig['WATERMARK_COLOR']; ?>">
                                </div>
                                
                                <button type="submit" class="btn btn-primary">💾 Sauvegarder Watermark</button>
                            </form>
                            
                            <!-- Aperçu -->
                            <div class="watermark-preview" style="margin-top: 2rem;">
                                <h4>Aperçu :</h4>
                                <div class="watermark-container" data-watermark="<?php echo htmlspecialchars($watermarkConfig['WATERMARK_TEXT']); ?>" 
                                     style="width: 200px; height: 150px; background: #f0f0f0; display: inline-block; border-radius: 8px;">
                                    <div class="watermark-pattern">
                                        <?php for ($i = 0; $i < 4; $i++): ?>
                                            <div class="watermark-text" style="left: <?php echo $i * 100; ?>px; top: 50px;">
                                                <?php echo htmlspecialchars($watermarkConfig['WATERMARK_TEXT']); ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- Configuration Email -->
            <section class="config-section">
                <div class="accordion-section">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <h3>📧 Configuration Email</h3>
                        <span class="accordion-toggle">▼</span>
                    </div>
                    
                    <div class="accordion-content">
                        <div class="accordion-inner">
                            <div class="email-config-info">
                                <h4>Paramètres actuels</h4>
                                <div class="config-grid">
                                    <div class="config-item">
                                        <strong>Envoi activé :</strong> 
                                        <span class="<?php echo (defined('MAIL_ENABLED') && MAIL_ENABLED) ? 'status-ok' : 'status-warning'; ?>">
                                            <?php echo (defined('MAIL_ENABLED') && MAIL_ENABLED) ? '✅ Oui' : '⚠️ Non'; ?>
                                        </span>
                                    </div>
                                    <div class="config-item">
                                        <strong>Méthode :</strong> 
                                        <span class="<?php echo (defined('SMTP_ENABLED') && SMTP_ENABLED) ? 'status-ok' : 'status-info'; ?>">
                                            <?php echo (defined('SMTP_ENABLED') && SMTP_ENABLED) ? '📡 SMTP' : '📬 mail()'; ?>
                                        </span>
                                    </div>
                                    <?php if (defined('SMTP_ENABLED') && SMTP_ENABLED): ?>
                                    <div class="config-item">
                                        <strong>Serveur SMTP :</strong> 
                                        <code><?php echo defined('SMTP_HOST') ? SMTP_HOST . ':' . (defined('SMTP_PORT') ? SMTP_PORT : '25') : 'Non configuré'; ?></code>
                                    </div>
                                    <div class="config-item">
                                        <strong>Sécurité :</strong> 
                                        <code><?php echo defined('SMTP_SECURE') ? strtoupper(SMTP_SECURE) : 'Aucune'; ?></code>
                                    </div>
                                    <?php endif; ?>
                                    <div class="config-item">
                                        <strong>Email expéditeur :</strong> 
                                        <code><?php echo defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'Non configuré'; ?></code>
                                    </div>
                                    <div class="config-item">
                                        <strong>Destinataires admin :</strong> 
                                        <code><?php echo defined('MAIL_ADMIN_RECIPIENTS') ? MAIL_ADMIN_RECIPIENTS : 'Non configuré'; ?></code>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="email-actions">
                                <button id="test-email-config" class="btn btn-secondary">🧪 Tester la configuration</button>
                                <button id="send-test-email" class="btn btn-primary">📧 Envoyer un email de test</button>
                                <div id="email-test-result" class="test-result" style="display: none;"></div>
                            </div>
                            
                            <div class="email-help">
                                <h4>💡 Configuration pour Free Pro</h4>
                                <p>Pour utiliser SMTP Free Pro, configurez dans <code>config.php</code> :</p>
                                <pre><code>// Configuration SMTP Free Pro
define('SMTP_ENABLED', true);
define('SMTP_HOST', 'smtp.free.fr');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');</code></pre>
                                
                                <div class="email-notes">
                                    <h5>📋 Notes importantes :</h5>
                                    <ul>
                                        <li>Port 587 avec TLS est recommandé</li>
                                        <li>Pas d'authentification nécessaire depuis la Freebox Pro</li>
                                        <li>Utilisez votre adresse @free.fr comme expéditeur</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Informations système -->
            <section class="config-section">
                <div class="accordion-section">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <h3>🔧 Informations système</h3>
                        <span class="accordion-toggle">▼</span>
                    </div>
                    
                    <div class="accordion-content">
                        <div class="accordion-inner">
                            <div class="system-info">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <strong>Version PHP :</strong>
                                        <span><?php echo phpversion(); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Serveur web :</strong>
                                        <span><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu'; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Dossier photos :</strong>
                                        <span><?php echo PHOTOS_DIR; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Mode debug :</strong>
                                        <span class="<?php echo (defined('DEBUG_MODE') && DEBUG_MODE) ? 'status-warning' : 'status-ok'; ?>">
                                            <?php echo (defined('DEBUG_MODE') && DEBUG_MODE) ? '⚠️ Activé' : '✅ Désactivé'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="system-actions">
                                <button id="run-diagnostic" class="btn btn-secondary">🔍 Diagnostic complet</button>
                                <div id="diagnostic-result" class="test-result" style="display: none;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

             <!-- Nouvelle section Cache des Images -->
            <div class="admin-section">
                <h2>🖼️ Gestion du Cache des Images</h2>
                
                <!-- Statistiques actuelles -->
                <?php 
                $cacheStats = getCacheStatistics();
                ?>
                <div class="cache-stats-section">
                    <h3>📊 Statistiques actuelles</h3>
                    <div class='stats-grid'>
                        <div class='stat-card info'>
                            <h4>🖼️ Miniatures</h4>
                            <span class='stat-number'><?php echo $cacheStats['thumbnails']['count']; ?></span>
                            <small><?php echo formatBytes($cacheStats['thumbnails']['size']); ?></small>
                        </div>
                        
                        <div class='stat-card primary'>
                            <h4>🔍 Redimensionnées</h4>
                            <span class='stat-number'><?php echo $cacheStats['resized']['count']; ?></span>
                            <small><?php echo formatBytes($cacheStats['resized']['size']); ?></small>
                        </div>
                        
                        <div class='stat-card success'>
                            <h4>💾 Total</h4>
                            <span class='stat-number'><?php echo $cacheStats['total_count']; ?></span>
                            <small><?php echo formatBytes($cacheStats['total_size']); ?></small>
                        </div>
                    </div>
                </div>
                
                <!-- Actions de génération -->
                <div class="cache-actions">
                    <h3>🔄 Génération du cache</h3>
                    
                    <div class="cache-form">
                        <div class="form-options">
                            <label>
                                <input type="checkbox" id="generate_thumbnails" checked>
                                Générer les miniatures (<?php echo THUMBNAIL_WIDTH; ?>x<?php echo THUMBNAIL_HEIGHT; ?>px)
                            </label>
                            
                            <label>
                                <input type="checkbox" id="generate_resized" checked>
                                Générer les images redimensionnées (<?php echo MAX_IMAGE_WIDTH; ?>x<?php echo MAX_IMAGE_HEIGHT; ?>px)
                            </label>
                            
                            <label>
                                <input type="checkbox" id="force_regenerate">
                                Forcer la régénération (ignorer le cache existant)
                            </label>
                        </div>
                        
                        <button type="button" id="generate-cache-btn" class="btn btn-primary progress-btn">
                            <span class="btn-icon">🔄</span>
                            <span class="btn-text">Générer le cache</span>
                            <div class="progress-fill"></div>
                        </button>
                        
                        <div id="progress-info" class="progress-info" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-bar-fill"></div>
                                <span class="progress-text">0%</span>
                            </div>
                            <div class="progress-details">
                                <span id="progress-status">Initialisation...</span>
                            </div>
                        </div>
                        
                        <div id="generation-results" class="generation-results" style="display: none;"></div>
                    </div>
                </div>
                
                <!-- Actions de nettoyage -->
                <div class="cache-cleanup">
                    <h3>🗑️ Nettoyage du cache</h3>
                    
                    <div class="cleanup-buttons">
                        <form method="post" style="display: inline-block;">
                            <input type="hidden" name="action" value="clear_cache">
                            <input type="hidden" name="cache_type" value="thumbnails">
                            <button type="submit" class="btn btn-warning" onclick="return confirm('Supprimer toutes les miniatures ?');">
                                🖼️ Vider les miniatures
                            </button>
                        </form>
                        
                        <form method="post" style="display: inline-block;">
                            <input type="hidden" name="action" value="clear_cache">
                            <input type="hidden" name="cache_type" value="resized">
                            <button type="submit" class="btn btn-warning" onclick="return confirm('Supprimer toutes les images redimensionnées ?');">
                                🔍 Vider les redimensionnées
                            </button>
                        </form>
                        
                        <form method="post" style="display: inline-block;">
                            <input type="hidden" name="action" value="clear_cache">
                            <input type="hidden" name="cache_type" value="all">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Supprimer TOUT le cache d\'images ?');">
                                🗑️ Tout vider
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="js/admin.parameters.js"></script>
    <script src="js/admin.js"></script>
</body>
</html>
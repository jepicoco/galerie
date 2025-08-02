<?php
require_once 'classes/autoload.php';
require_once 'email_handler.php';
// Récupération de la configuration Watermark
$watermarkConfig = getWatermarkConfig();

// Vérifier si c'est une connexion admin
$is_admin = is_admin();

// Fonction pour lire les valeurs du watermark depuis config_watermark.php
function getWatermarkConfig() {
    $configFile = 'config_watermark.php';
    $defaults = [
        'WATERMARK_ENABLED' => true,
        'WATERMARK_TEXT' => 'Gala de danse',
        'WATERMARK_OPACITY' => 0.3,
        'WATERMARK_SIZE' => '24px',
        'WATERMARK_COLOR' => '#FFFFFF',
        'WATERMARK_ANGLE' => '-45'
    ];

    if (file_exists($configFile)) {
        $content = file_get_contents($configFile);
        
        // Extraire les valeurs avec regex
        foreach ($defaults as $key => $default) {
            if (preg_match("/define\('$key',\s*(.+?)\);/", $content, $matches)) {
                $value = trim($matches[1], " '\"");
                if ($key === 'WATERMARK_ENABLED') {
                    $defaults[$key] = ($value === 'true');
                } elseif ($key === 'WATERMARK_OPACITY') {
                    $defaults[$key] = floatval($value);
                } else {
                    $defaults[$key] = $value;
                }
            }
        }
    }
    
    return $defaults;
}

// Vérifier si c'est une connexion admin
function is_admin(){
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}


// Fonctions utilitaires (extraites de admin.php)
function scanPhotosDirectories() {
    $photosDir = PHOTOS_DIR;
    $activities = [];
    
    if (!is_dir($photosDir)) {
        return $activities;
    }
    
    $directories = array_filter(glob($photosDir . '*'), 'is_dir');
    
    foreach ($directories as $dir) {
        $activityKey = basename($dir);
        
        // Ignorer les dossiers système
        if (in_array($activityKey, ['.', '..', 'thumbs', 'cache'])) {
            continue;
        }
        
        $photos = [];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        foreach ($imageExtensions as $ext) {
            $files = glob($dir . '/*.' . $ext);
            $files = array_merge($files, glob($dir . '/*.' . strtoupper($ext)));
            
            foreach ($files as $file) {
                $photos[] = basename($file);
            }
        }
        
        if (!empty($photos)) {
            sort($photos);
            
            $activities[$activityKey] = [
                'name' => ucfirst(str_replace(['_', '-'], ' ', $activityKey)),
                'photos' => $photos,
                'description' => '',
                'tags' => [],
                'featured' => false,
                'visibility' => 'public',
                'pricing_type' => $pricing_type ?? DEFAULT_ACTIVITY_TYPE,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    // Fusionner avec les données existantes
    $existingActivities = loadActivitiesData();
    foreach ($activities as $key => $activity) {
        if (isset($existingActivities[$key])) {
            // Garder les métadonnées existantes, mettre à jour les photos
            $existingActivities[$key]['photos'] = $activity['photos'];
            $existingActivities[$key]['updated_at'] = date('Y-m-d H:i:s');
        } else {
            $existingActivities[$key] = $activity;
        }
    }
    
    saveActivitiesData($existingActivities);
    return $activities;
}

/**
 * Sauver les données des activités depuis le fichier JSON
 */
function saveActivitiesData($activities) {
    $dataFile = DATA_DIR . 'activities.json';
    
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    
    return file_put_contents($dataFile, json_encode($activities, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Debug des fichiers de commandes
 */
function debugOrderFiles($reference, $ordersDir) {
    $files = [
        'temp' => $ordersDir . 'temp/' . $reference . '.json',
        'validated' => glob($ordersDir . $reference . '_*.json')
    ];
    
    $info = [
        'reference' => $reference,
        'temp_exists' => file_exists($files['temp']),
        'validated_files' => $files['validated'],
        'validated_count' => count($files['validated'])
    ];
    
    error_log("Debug commande $reference: " . json_encode($info));
    return $info;
}

function calculateOrderTotal($order) {
    $total = 0;
    foreach ($order['items'] as $item) {
        $total += $item['total_price'];
    }
    return $total;
}

/**
 * Charger les données des activités avec leur configuration de tarification
 */
function loadActivitiesConfiguration() {
    $activitiesFile = DATA_DIR . 'activities.json';
    
    if (!file_exists($activitiesFile)) {
        return [];
    }
    
    $content = file_get_contents($activitiesFile);
    return json_decode($content, true) ?: [];
}

/**
 * Récupérer le prix d'une activité selon son type de tarification
 */
function getActivityPrice($activityKey) {
    global $ACTIVITY_PRICING;
    static $activitiesData = null;
    
    // Charger les données une seule fois (cache statique)
    if ($activitiesData === null) {
        $activitiesData = loadActivitiesConfiguration();
    }
    
    // Récupérer le type de tarification de l'activité
    $pricingType = DEFAULT_ACTIVITY_TYPE; // Valeur par défaut
    
    if (isset($activitiesData[$activityKey]['pricing_type'])) {
        $pricingType = $activitiesData[$activityKey]['pricing_type'];
    }
    
    // Retourner le prix selon le type
    if (isset($ACTIVITY_PRICING[$pricingType])) {
        return $ACTIVITY_PRICING[$pricingType]['price'];
    }
    
    // Fallback sur le prix par défaut
    return isset($ACTIVITY_PRICING[DEFAULT_ACTIVITY_TYPE]) 
        ? $ACTIVITY_PRICING[DEFAULT_ACTIVITY_TYPE]['price'] 
        : 2; // Prix de secours
}

/**
 * Récupérer les informations complètes du type de tarification
 */
function getActivityTypeInfo($activityKey) {
    global $ACTIVITY_PRICING;
    static $activitiesData = null;
    
    if ($activitiesData === null) {
        $activitiesData = loadActivitiesConfiguration();
    }

    $pricingType = $activitiesData[$activityKey]['pricing_type'] ?? DEFAULT_ACTIVITY_TYPE;
    
    return $ACTIVITY_PRICING[$pricingType] ?? $ACTIVITY_PRICING[DEFAULT_ACTIVITY_TYPE];
}

// Charger les données des activités
$activities_file = DATA_DIR . 'activities.json';
$activities = [];
if (file_exists($activities_file)) {
    $activities = json_decode(file_get_contents($activities_file), true) ?: [];
}

// Enrichir les données des activités avec les URLs des thumbnails
$enrichedActivities = [];

foreach ($activities as $activityKey => $activity) {
    // Copier les données de base de l'activité
    $enrichedActivity = $activity;
    
    // Enrichir chaque photo avec les URLs
    if (isset($activity['photos']) && is_array($activity['photos'])) {
        $enrichedPhotos = [];
        
        foreach ($activity['photos'] as $photoName) {
            // Construire le chemin relatif de la photo
            $photoPath = $activityKey . '/' . $photoName;
            
            // Créer l'objet photo enrichi
            $enrichedPhoto = [
                'name' => $photoName,
                'path' => $photoPath,
                'originalUrl' => GetImageUrl($photoPath, IMG_ORIGINAL),
                'thumbPath' => GetImageUrl($photoPath, IMG_THUMBNAIL),
                'resizedUrl' => GetImageUrl($photoPath, IMG_RESIZED)
            ];
            
            // Ajouter les métadonnées si elles existent
            if (isset($activity['photos_metadata'][$photoName])) {
                $enrichedPhoto['metadata'] = $activity['photos_metadata'][$photoName];
            }
            
            $enrichedPhotos[] = $enrichedPhoto;
        }
        
        // Remplacer le tableau simple par le tableau enrichi
        $enrichedActivity['photos'] = $enrichedPhotos;
    }
    
    $enrichedActivities[$activityKey] = $enrichedActivity;
}

/**
 * Compte le nombre de commandes réglées en attente de retrait
 * @return int Nombre de commandes
 * @version 1.0
 */
function countPendingRetrievals() {
    $csvFile = 'data/commandes.csv';
    $count = 0;
    
    if (!file_exists($csvFile)) {
        return 0;
    }
    
    $handle = fopen($csvFile, 'r');
    $headers = fgetcsv($handle, 0, ';');
    
    while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
        $row = array_combine($headers, $data);
        if ($row['Statut paiement'] === 'Réglé' && $row['Statut retrait'] !== 'Récupéré') {
            $count++;
        }
    }
    
    fclose($handle);
    return $count;
}

/**
 * Compte le nombre de commandes en attente de paiement
 * @return int Nombre de commandes
 * @version 1.0
 */
function countPendingPayments() {
    $csvFile = 'data/commandes.csv';
    $count = 0;
    
    if (!file_exists($csvFile)) {
        return 0;
    }
    
    $handle = fopen($csvFile, 'r');
    $headers = fgetcsv($handle, 0, ';');
    
    while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
        $row = array_combine($headers, $data);
        if ($row['Statut paiement'] !== 'Réglé') {
            $count++;
        }
    }
    
    fclose($handle);
    return $count;
}

/**
 * Nettoie les commandes temporaires anciennes
 * @param string $ordersDir Répertoire des commandes
 * @return int Nombre de fichiers supprimés
 */
function cleanOldTempOrders($ordersDir) {
    $tempDir = $ordersDir . 'temp/';
    
    if (!is_dir($tempDir)) {
        return 0;
    }
    
    $tempFiles = glob($tempDir . '*.json');
    $deletedCount = 0;
    $maxAge = 20 * 3600; // 20 heures en secondes
    
    foreach ($tempFiles as $file) {
        $fileAge = time() - filemtime($file);
        
        if ($fileAge > $maxAge) {
            if (unlink($file)) {
                $deletedCount++;
                error_log("Commande temporaire supprimée (age: " . round($fileAge/3600, 1) . "h): " . basename($file));
            }
        }
    }
    
    return $deletedCount;
}
?>